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

const DIGIT_LIKE_RUN = /[\dOoQDiIlL|!zZsSbBgG?]{14,26}/g;

/** Tolerant NIK extraction: OCR frequently inserts stray spaces/characters
 * around the digits and misreads some digits as similar-looking letters,
 * so a strict /\d{16}/ match often fails even when the number was read
 * essentially correctly. Letter->digit normalization is deliberately
 * scoped to text near the "NIK" label first (safest case: we already
 * know we're looking at the right line), then widened card-wide only as
 * a last resort - see below. */
function extractNik(text: string, lines: string[]): string {
  const nikLineIdx = lines.findIndex((l) => /\bNIK\b/i.test(l));
  if (nikLineIdx !== -1) {
    const nearby = `${lines[nikLineIdx]} ${lines[nikLineIdx + 1] ?? ""}`;
    const runs = nearby.match(DIGIT_LIKE_RUN) ?? [];
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

  // Last resort #1: pure digit-with-spaces run anywhere in the document
  // (no letter normalization - the safest possible fallback).
  const spacedRuns = text.match(/\d[\d ]{14,22}\d/g) ?? [];
  for (const run of spacedRuns) {
    const d = digitsOnly(run);
    if (d.length === 16) return d;
  }
  const plain16 = text.match(/\d{16}/)?.[0];
  if (plain16) return plain16;

  // Last resort #2: same letter-normalization used for the NIK-labeled
  // line above, but applied card-wide. Only reached when nothing above
  // worked - typically means OCR failed to output the word "NIK" at all
  // (glare/noise wiped out that specific line), not just the digits next
  // to it. Widening the search this far is safe (despite touching
  // Nama/Alamat text too) because every candidate window still has to
  // pass isPlausibleNik - a real province/regency/district + DOB + serial
  // shape - before being accepted; a random stretch of name/address text
  // coincidentally matching that exact structure is exceedingly unlikely.
  const cardWideRuns = text.match(DIGIT_LIKE_RUN) ?? [];
  for (const run of cardWideRuns) {
    const normalized = digitsOnly(normalizeOcrDigits(run));
    if (normalized.length < 16) continue;
    const windowed = bestNikWindow(normalized);
    if (isPlausibleNik(windowed)) return windowed;
  }
  return "";
}

/** Plain Levenshtein edit distance (insert/delete/substitute), O(n*m)
 * with a single-row rolling array. Small strings only (label words),
 * so no need for anything fancier. */
function levenshtein(a: string, b: string): number {
  const n = b.length;
  const dp = new Array(n + 1);
  for (let j = 0; j <= n; j++) dp[j] = j;
  for (let i = 1; i <= a.length; i++) {
    let prev = dp[0];
    dp[0] = i;
    for (let j = 1; j <= n; j++) {
      const temp = dp[j];
      dp[j] =
        a[i - 1] === b[j - 1]
          ? prev
          : 1 + Math.min(prev, dp[j], dp[j - 1]);
      prev = temp;
    }
  }
  return dp[n];
}

/** Finds a label word in `line` within `maxDistance` OCR-typo edits of
 * `target` (case-insensitive) - e.g. "ALAMAT" also matches "Alama" (one
 * character dropped) or "Alarnat" (one substituted). A strict exact-word
 * regex silently drops the ENTIRE field when the label itself is
 * misread - confirmed in practice against a real device scan: "ALAMAT"
 * came back as "Alama", and because nothing in the raw text matched
 * /\bALAMAT\b/, the address that was sitting right there in the same
 * line ("PERUM GRAND SUTERA CILEGON") was silently discarded along with
 * every continuation line after it. Distance budget is intentionally
 * tight (scales with word length via the caller) so it only forgives
 * genuine near-misses, not unrelated short words. */
function findFuzzyLabel(
  line: string,
  target: string,
  maxDistance: number
): RegExpMatchArray | null {
  const upperTarget = target.toUpperCase();
  for (const m of line.matchAll(/[A-Za-z]+/g)) {
    const word = m[0].toUpperCase();
    if (Math.abs(word.length - upperTarget.length) > maxDistance) continue;
    if (levenshtein(word, upperTarget) <= maxDistance) return m;
  }
  return null;
}

function stripFuzzyLabel(line: string, match: RegExpMatchArray): string {
  const start = match.index ?? 0;
  const end = start + match[0].length;
  return (line.slice(0, start) + line.slice(end))
    .replace(/^[\s:.\-|]+/, "")
    .trim();
}

/** Everything else printed on a KTP (Tempat/Tgl Lahir, Jenis Kelamin,
 * RT/RW, Kel/Desa, Kecamatan, Agama, Status Perkawinan, Pekerjaan,
 * Kewarganegaraan, Berlaku Hingga) - the app only needs NIK/Nama/Alamat,
 * so none of these are extracted or stored. They're matched here for ONE
 * reason only: to tell extractNamaAlamat where those two fields END,
 * since Alamat especially is printed across several unlabeled
 * continuation lines (street, then RT/RW, Kel/Desa, Kecamatan) and would
 * otherwise keep swallowing every line below it to the bottom of the
 * card. */
const OTHER_LABEL =
  /TEMPAT\s*\/?\s*TG?L\.?\s*LAHIR|JENIS\s*KELAMIN|\bRT\s*\/?\s*RW\b|KEL\s*\/?\s*DESA|KECAMATAN|\bAGAMA\b|STATUS\s*PERKAWINAN|PEKERJAAN|KEWARGANEGARAAN|BERLAKU/i;

/** OCR of the card's hologram/guilloche background texture frequently
 * shows up as stray symbol noise glued onto real text (seen in practice:
 * "MAMAN" read back as "— MAMAN = = —=——="). None of "=", "~", "^", "_",
 * a lone "-"/"—" surrounded by spaces, or "|" can legitimately appear in
 * an Indonesian name or address, so they're safe to strip outright.
 * Deliberately NOT touching "-", "/", "." when they sit directly between
 * letters/digits (address values like "BLOK C3.NO.23" or "RT/RW"
 * fragments that occasionally leak into a wrapped Alamat line use those
 * for real). */
function cleanExtractedValue(s: string): string {
  return s
    .replace(/[=~^_|]+/g, " ")
    .replace(/(?:—|--+)/g, " ")
    .replace(/(?<![A-Za-z0-9])-(?![A-Za-z0-9])/g, " ")
    .replace(/\s{2,}/g, " ")
    .trim();
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

    const namaMatch = findFuzzyLabel(line, "NAMA", 1);
    if (namaMatch) {
      collectingAlamat = false;
      nama = stripFuzzyLabel(line, namaMatch);
      awaitingNama = nama === "";
      continue;
    }
    const alamatMatch = findFuzzyLabel(line, "ALAMAT", 1);
    if (alamatMatch) {
      alamat = stripFuzzyLabel(line, alamatMatch);
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
  return { nama: cleanExtractedValue(nama), alamat: cleanExtractedValue(alamat) };
}

/** Grayscale + adaptive contrast stretch + mild upscale, done in-browser
 * via canvas before handing the image to Tesseract. KTP backgrounds have
 * a printed hologram/guilloche pattern that OCR engines struggle with on
 * a raw color photo; flattening to high-contrast grayscale measurably
 * helps text recognition in practice.
 *
 * The contrast stretch is centered on an Otsu threshold computed from the
 * image's own brightness histogram, instead of a fixed midpoint (128).
 * A physical photo (as opposed to a flat gallery scan) commonly has an
 * uneven overall brightness - glare, shadow, or a warm/cool cast from
 * indoor lighting - which pushes the true text/background boundary well
 * off 128; stretching around a fixed midpoint in that case can wash out
 * or flatten exactly the text pixels that most need contrast. */
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
  const pixelCount = d.length / 4;
  const gray = new Float32Array(pixelCount);
  const hist = new Array(256).fill(0);
  for (let i = 0, p = 0; i < d.length; i += 4, p++) {
    const g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
    gray[p] = g;
    hist[Math.min(255, Math.max(0, Math.round(g)))]++;
  }

  // Otsu's method: find the threshold that maximizes between-class
  // variance (foreground text vs. background) from the histogram alone -
  // no external dependency needed, cheap enough for a one-shot form.
  let sumAll = 0;
  for (let t = 0; t < 256; t++) sumAll += t * hist[t];
  let sumB = 0;
  let wB = 0;
  let maxVariance = -1;
  let threshold = 128;
  for (let t = 0; t < 256; t++) {
    wB += hist[t];
    if (wB === 0) continue;
    const wF = pixelCount - wB;
    if (wF === 0) break;
    sumB += t * hist[t];
    const mB = sumB / wB;
    const mF = (sumAll - sumB) / wF;
    const between = wB * wF * (mB - mF) * (mB - mF);
    if (between > maxVariance) {
      maxVariance = between;
      threshold = t;
    }
  }

  const gain = 1.6;
  for (let i = 0, p = 0; i < d.length; i += 4, p++) {
    const contrasted = Math.min(
      255,
      Math.max(0, (gray[p] - threshold) * gain + 128)
    );
    d[i] = d[i + 1] = d[i + 2] = contrasted;
  }
  ctx.putImageData(imgData, 0, 0);

  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob ?? source), "image/jpeg", 0.92);
  });
}

async function recognizeOnce(
  worker: Awaited<ReturnType<typeof createWorker>>,
  image: Blob | string,
  psm: PSM
): Promise<KtpOcrResult> {
  await worker.setParameters({ tessedit_pageseg_mode: psm });
  const {
    data: { text },
  } = await worker.recognize(image);

  const lines = text
    .split("\n")
    .map((l) => l.trim())
    .filter(Boolean);

  const { nama, alamat } = extractNamaAlamat(lines);
  return { nik: extractNik(text, lines), nama, alamat, rawText: text };
}

function fieldsFound(r: KtpOcrResult): number {
  return [r.nik, r.nama, r.alamat].filter(Boolean).length;
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
        onProgress(Math.round(m.progress * 55));
      }
    },
  });

  try {
    const preprocessed =
      typeof imageSource === "string"
        ? imageSource
        : await preprocessForOcr(imageSource);

    // Pass 1: SINGLE_BLOCK - treats the card as one uniform block of text
    // rather than auto-detecting a page layout. Best result in testing
    // for this dense, multi-field KTP layout when the photo is clean.
    const first = await recognizeOnce(worker, preprocessed, PSM.SINGLE_BLOCK);
    if (fieldsFound(first) === 3) {
      onProgress?.(100);
      return first;
    }

    // Pass 2: only when pass 1 missed at least one of NIK/Nama/Alamat.
    // SPARSE_TEXT treats the image as scattered blocks of text instead of
    // one uniform block - a different segmentation strategy that can
    // recover a line SINGLE_BLOCK merged into noise or dropped entirely
    // (seen in practice: NIK/Alamat coming back completely blank from
    // pass 1 despite a well-cropped photo). Costs a second OCR run, but
    // this only runs once per registration, so the extra ~1-2s is worth
    // it against handing the user a form with fields silently blank.
    if (onProgress) onProgress(55);
    const second = await recognizeOnce(worker, preprocessed, PSM.SPARSE_TEXT);
    onProgress?.(100);

    if (fieldsFound(second) <= fieldsFound(first)) return first;

    // Field-level merge: take whichever pass actually read each field,
    // so a pass that's better overall can't blank out a field the other
    // pass got right.
    return {
      nik: second.nik || first.nik,
      nama: second.nama || first.nama,
      alamat: second.alamat || first.alamat,
      rawText:
        second.rawText.length > first.rawText.length
          ? second.rawText
          : first.rawText,
    };
  } finally {
    await worker.terminate();
  }
}
