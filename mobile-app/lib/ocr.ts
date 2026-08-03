import { createWorker, PSM } from "tesseract.js";

export type KtpOcrResult = {
  nik: string;
  nama: string;
  alamat: string;
  rawText: string;
};

function digitsOnly(s: string): string {
  return s.replace(/\D/g, "");
}

/** Common OCR letter/digit confusions on the KTP's numeric fonts (seen in
 * practice and in other Indonesian-KTP OCR projects, e.g.
 * github.com/YukaLangbuana/KTP-OCR which normalizes "?" -> "7"). Applied
 * ONLY to candidate strings already identified as "this is probably the
 * NIK", not to the whole document, so it can't corrupt Nama/Alamat text
 * where those same letters are legitimate. */
function normalizeOcrDigits(s: string): string {
  return s
    .replace(/[oOQD]/g, "0")
    .replace(/[iIlL|!]/g, "1")
    .replace(/[zZ]/g, "2")
    .replace(/[sS]/g, "5")
    .replace(/[bB]/g, "8")
    .replace(/[gG]/g, "6")
    .replace(/\?/g, "7");
}

/** Structural check against the official NIK format (PP No. 37/2007 pasal
 * 37): province+regency+district code, then DOB-derived digits (day is
 * offset +40 for women), then a 4-digit serial. This can't confirm a NIK
 * is real, but a NIK that fails this shape is almost certainly an OCR
 * misread worth flagging for extra scrutiny before the user submits. */
const NIK_SHAPE =
  /^(1[1-9]|21|[37][1-6]|5[1-3]|6[1-5]|[89][12])\d{2}\d{2}([04][1-9]|[1256]\d|[37][01])(0[1-9]|1[0-2])\d{2}\d{4}$/;

export function isPlausibleNik(nik: string): boolean {
  return NIK_SHAPE.test(nik);
}

/** When a digit run near "NIK" is longer than 16 (a stray extra character
 * got glued onto the front/back - e.g. a misread ":" or a fragment of the
 * "NIK" label itself), try every 16-digit window inside it and prefer one
 * that actually matches the NIK shape, instead of blindly taking the
 * first 16 digits (which silently produces a shifted, wrong number: seen
 * in practice as e.g. "1367205180284000" - a 1 glued on the front, real
 * last digit falls off the end). Falls back to the first 16 if no window
 * is structurally plausible. */
function bestNikWindow(digits: string): string {
  if (digits.length <= 16) return digits;
  for (let i = 0; i + 16 <= digits.length; i++) {
    const window = digits.slice(i, i + 16);
    if (isPlausibleNik(window)) return window;
  }
  return digits.slice(0, 16);
}

/** Tolerant NIK extraction: OCR frequently inserts stray spaces/characters
 * around the digits and misreads some digits as similar-looking letters,
 * so a strict /\d{16}/ match often fails even when the number was read
 * essentially correctly. Letter->digit normalization is deliberately
 * scoped to text near the "NIK" label only (never the whole card), so it
 * can't accidentally turn part of the Nama/Alamat text into a fake NIK. */
function extractNik(text: string, lines: string[]): string {
  const nikLineIdx = lines.findIndex((l) => /\bNIK\b/i.test(l));
  if (nikLineIdx !== -1) {
    const nearby = `${lines[nikLineIdx]} ${lines[nikLineIdx + 1] ?? ""}`;
    const runs =
      nearby.match(/[\dOoQDiIlL|!zZsSbBgG?]{14,26}/g) ?? [];
    let firstValidLength = "";
    for (const run of runs) {
      const normalized = digitsOnly(normalizeOcrDigits(run));
      if (normalized.length < 16) continue;
      const windowed = bestNikWindow(normalized);
      if (isPlausibleNik(windowed)) return windowed;
      if (!firstValidLength && normalized.length >= 16) {
        firstValidLength = windowed;
      }
    }
    if (firstValidLength) return firstValidLength;
  }
  // Last resort: pure digit-with-spaces run anywhere in the document (no
  // letter normalization here - too risky to apply card-wide).
  const spacedRuns = text.match(/\d[\d ]{14,22}\d/g) ?? [];
  for (const run of spacedRuns) {
    const d = digitsOnly(run);
    if (d.length === 16) return d;
  }
  return text.match(/\d{16}/)?.[0] ?? "";
}

const NAMA_LABEL = /\bNAMA\b/i;
const ALAMAT_LABEL = /\bALAMAT\b/i;

/** Everything else printed on a KTP (Tempat/Tgl Lahir, Jenis Kelamin,
 * RT/RW, Kel/Desa, Kecamatan, Agama, Status Perkawinan, Pekerjaan,
 * Kewarganegaraan, Berlaku Hingga) - the app only needs NIK/Nama/Alamat,
 * so none of these are extracted or stored. They're matched here for ONE
 * reason only: to tell extractNama/extractAlamat where those two fields
 * END, since Alamat especially is printed across several unlabeled
 * continuation lines (street, then RT/RW, Kel/Desa, Kecamatan) and would
 * otherwise keep swallowing every line below it to the bottom of the
 * card. */
const OTHER_LABEL =
  /TEMPAT\s*\/?\s*TG?L\.?\s*LAHIR|JENIS\s*KELAMIN|\bRT\s*\/?\s*RW\b|KEL\s*\/?\s*DESA|KECAMATAN|\bAGAMA\b|STATUS\s*PERKAWINAN|PEKERJAAN|KEWARGANEGARAAN|BERLAKU/i;

function stripLabel(line: string, label: RegExp): string {
  return line.replace(label, "").replace(/^[\s:.\-|]+/, "").trim();
}

/** Nama and Alamat only. Nama is a single line (label + value, or value on
 * the next line if OCR dropped the ":"). Alamat is the only multi-line
 * field kept: it keeps appending lines until the next recognized label of
 * ANY kind (including the "other" ones above, which are otherwise
 * ignored) so it doesn't run past the address block. */
function extractNamaAlamat(lines: string[]): { nama: string; alamat: string } {
  let nama = "";
  let alamat = "";
  let collectingAlamat = false;
  let awaitingNama = false;

  for (const raw of lines) {
    const line = raw.trim();
    if (!line) continue;

    if (NAMA_LABEL.test(line)) {
      collectingAlamat = false;
      nama = stripLabel(line, NAMA_LABEL);
      awaitingNama = nama === "";
      continue;
    }
    if (ALAMAT_LABEL.test(line)) {
      alamat = stripLabel(line, ALAMAT_LABEL);
      collectingAlamat = true;
      awaitingNama = false;
      continue;
    }
    if (OTHER_LABEL.test(line) || /\bNIK\b/i.test(line)) {
      collectingAlamat = false;
      awaitingNama = false;
      continue;
    }

    // Label-less line: either the value for a "Nama" label that had
    // nothing after it (":" dropped by OCR, value pushed to its own
    // line), or a continuation line for Alamat.
    if (awaitingNama) {
      nama = line;
      awaitingNama = false;
    } else if (collectingAlamat) {
      alamat = alamat ? `${alamat} ${line}` : line;
    }
  }
  return { nama, alamat };
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
  // Language model: "eng" is used instead of "ind" here even though the
  // card is Indonesian. Tested directly against real KTP photos: the
  // "ind" traineddata reads the field VALUES (name/address/etc, printed
  // over the hologram/guilloche background) as near-random noise, while
  // "eng" reads the exact same image close to perfectly (confirmed via
  // side-by-side testing - "ind" mangled "MAMAN" into "Sa ES" and a
  // clean address into garbage, "eng" read both correctly). The card
  // text is plain Latin characters/digits with no Indonesian-specific
  // diacritics, so "eng"'s more mature/accurate LSTM model reads it
  // better than the lower-quality "ind" model despite the language
  // mismatch.
  const worker = await createWorker("eng", 1, {
    logger: (m) => {
      if (m.status === "recognizing text" && onProgress) {
        onProgress(Math.round(m.progress * 100));
      }
    },
  });
  // SINGLE_BLOCK: treat the card as one uniform block of text rather than
  // trying to auto-detect a page layout (the default AUTO mode) - this
  // measurably improved accuracy in testing on the dense, multi-field KTP
  // layout.
  await worker.setParameters({ tessedit_pageseg_mode: PSM.SINGLE_BLOCK });

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

    const { nama, alamat } = extractNamaAlamat(lines);

    return {
      nik: extractNik(text, lines),
      nama,
      alamat,
      rawText: text,
    };
  } finally {
    await worker.terminate();
  }
}
