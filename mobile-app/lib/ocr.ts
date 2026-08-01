import { createWorker } from "tesseract.js";

export type KtpOcrResult = {
  nik: string;
  nama: string;
  tempatTglLahir: string;
  alamat: string;
  rawText: string;
};

function digitsOnly(s: string): string {
  return s.replace(/\D/g, "");
}

/**
 * KTP fields in the order they physically appear on an Indonesian KTP.
 * Used to segment raw OCR text into fields: a line that matches one of
 * these labels starts a new field, and any following unlabeled lines are
 * treated as a continuation of that field (this is what makes multi-line
 * "Alamat" values work, since street + RT/RW/Kel/Kec are printed on
 * separate lines with no repeated label).
 */
const FIELD_LABELS: { key: string; re: RegExp; multiline?: boolean }[] = [
  { key: "nik", re: /\bNIK\b/i },
  { key: "nama", re: /\bNAMA\b/i },
  { key: "ttl", re: /TEMPAT\s*\/?\s*TG?L\.?\s*LAHIR/i },
  { key: "jk", re: /JENIS\s*KELAMIN/i },
  { key: "alamat", re: /\bALAMAT\b/i, multiline: true },
  { key: "rtrw", re: /\bRT\s*\/?\s*RW\b/i },
  { key: "keldesa", re: /KEL\s*\/?\s*DESA/i },
  { key: "kecamatan", re: /KECAMATAN/i },
  { key: "agama", re: /\bAGAMA\b/i },
  { key: "kawin", re: /STATUS\s*PERKAWINAN/i },
  { key: "kerja", re: /PEKERJAAN/i },
  { key: "wn", re: /KEWARGANEGARAAN/i },
  { key: "berlaku", re: /BERLAKU/i },
];

function parseKtpFields(lines: string[]): Record<string, string> {
  const values: Record<string, string> = {};
  let currentKey: string | null = null;
  let currentMultiline = false;

  for (const raw of lines) {
    const line = raw.trim();
    if (!line) continue;

    const label = FIELD_LABELS.find((l) => l.re.test(line));
    if (label) {
      currentKey = label.key;
      currentMultiline = Boolean(label.multiline);
      // The value is often on the SAME line as the label with no ":"
      // separator at all (OCR frequently drops it, e.g. "Nama MAMAN"
      // instead of "Nama : MAMAN") - strip the matched label text itself
      // plus any leading punctuation, and whatever remains is the value.
      const withoutLabel = line
        .replace(label.re, "")
        .replace(/^[\s:.\-|]+/, "")
        .trim();
      values[currentKey] = withoutLabel;
      continue;
    }

    // No label matched on this line - it's a continuation of whatever
    // field is currently open: fills in the value when the label line
    // itself had nothing after it, or appends for multi-line fields
    // like Alamat (street line, then RT/RW.../Kec... would normally have
    // their own labels, but OCR sometimes drops those labels too).
    if (currentKey && !values[currentKey]) {
      values[currentKey] = line;
      continue;
    }
    if (currentKey && currentMultiline) {
      values[currentKey] = `${values[currentKey]} ${line}`;
    }
  }
  return values;
}

/** Tolerant NIK extraction: OCR frequently inserts stray spaces between
 * digits, so a strict /\d{16}/ match often fails even when every digit
 * was read correctly. */
function extractNik(text: string, lines: string[]): string {
  const nikLineIdx = lines.findIndex((l) => /\bNIK\b/i.test(l));
  if (nikLineIdx !== -1) {
    for (const candidate of [lines[nikLineIdx], lines[nikLineIdx + 1] ?? ""]) {
      const d = digitsOnly(candidate);
      if (d.length === 16) return d;
    }
  }
  const spacedRuns = text.match(/\d[\d ]{14,22}\d/g) ?? [];
  for (const run of spacedRuns) {
    const d = digitsOnly(run);
    if (d.length === 16) return d;
  }
  return text.match(/\d{16}/)?.[0] ?? "";
}

/** Grayscale + contrast stretch + mild upscale, done in-browser via canvas
 * before handing the image to Tesseract. KTP backgrounds have a printed
 * hologram/guilloche pattern that OCR engines struggle with on a raw
 * color photo; flattening to high-contrast grayscale measurably helps
 * text recognition in practice. */
async function preprocessForOcr(source: File | Blob): Promise<Blob> {
  const bitmap = await createImageBitmap(source);
  const scale = Math.min(2, 1600 / Math.max(bitmap.width, bitmap.height)) || 1;
  const canvas = document.createElement("canvas");
  canvas.width = Math.round(bitmap.width * scale);
  canvas.height = Math.round(bitmap.height * scale);
  const ctx = canvas.getContext("2d");
  if (!ctx) return source;
  ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

  const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const d = imgData.data;
  for (let i = 0; i < d.length; i += 4) {
    const gray = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
    const contrasted = Math.min(255, Math.max(0, (gray - 128) * 1.45 + 128));
    d[i] = d[i + 1] = d[i + 2] = contrasted;
  }
  ctx.putImageData(imgData, 0, 0);

  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob ?? source), "image/jpeg", 0.92);
  });
}

export async function scanKtp(
  imageSource: File | Blob | string,
  onProgress?: (pct: number) => void
): Promise<KtpOcrResult> {
  const worker = await createWorker("ind", 1, {
    logger: (m) => {
      if (m.status === "recognizing text" && onProgress) {
        onProgress(Math.round(m.progress * 100));
      }
    },
  });

  try {
    const preprocessed =
      typeof imageSource === "string"
        ? imageSource
        : await preprocessForOcr(imageSource);

    const {
      data: { text },
    } = await worker.recognize(preprocessed);

    const lines = text
      .split("\n")
      .map((l) => l.trim())
      .filter(Boolean);

    const fields = parseKtpFields(lines);

    return {
      nik: extractNik(text, lines),
      nama: fields.nama ?? "",
      tempatTglLahir: fields.ttl ?? "",
      alamat: fields.alamat ?? "",
      rawText: text,
    };
  } finally {
    await worker.terminate();
  }
}
