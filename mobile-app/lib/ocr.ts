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
 * where those same letters are legitimate.
 *
 * b/B -> 6 (not 8): confirmed against a real physical-device photo where
 * Tesseract read the digit "6" as "b" ("3b72051802840001" vs the real
 * "3672051802840001"). There's no real-world evidence for a b/8
 * confusion, so that guess was replaced rather than kept alongside it. */
function normalizeOcrDigits(s: string): string {
  return s
    .replace(/[oOQD]/g, "0")
    .replace(/[iIlL|!]/g, "1")
    .replace(/[zZ]/g, "2")
    .replace(/[sS]/g, "5")
    .replace(/[bBgG]/g, "6")
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
  let nikLineIdx = lines.findIndex((l) => /\bNIK\b/i.test(l));
  // OCR frequently mangles the "NIK" label itself (seen in practice as
  // "ik" - the leading N lost to glare/noise). Fall back to the same
  // fuzzy label matching used for Nama/Alamat so the right line is still
  // located; the extracted candidate still has to pass isPlausibleNik.
  if (nikLineIdx === -1) {
    nikLineIdx = lines.findIndex((l) => findFuzzyLabel(l, "NIK", 1) !== null);
  }
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

/** Compact (separator-free) versions of the boundary labels above, for
 * fuzzy matching when OCR mangles a label itself (same idea as the
 * Nama/Alamat fuzzy labels). The compact forms matter because e.g. "RT/RW"
 * or "Kel/Desa" split across separators won't match a single fuzzy word;
 * comparing against the de-separated form catches both. The two that sit
 * in the middle of the Alamat block (RT/RW and Kel/Desa) are the critical
 * ones - a misread there makes Alamat swallow every line below it, seen in
 * practice as "RIAW 020/006" (should be RT/RW) and "KeiDesa" (should be
 * Kel/Desa) running straight into the address. */
function isBoundaryLine(line: string): boolean {
  if (OTHER_LABEL.test(line) || /\bNIK\b/i.test(line)) return true;
  for (const [target, maxDist] of [
    ["RTRW", 2],
    ["KELDESA", 2],
    ["KECAMATAN", 2],
    ["TEMPATLAHIR", 2],
    ["JENISKELAMIN", 2],
    ["STATUSPERKAWINAN", 2],
    ["KEWARGANEGARAAN", 2],
    ["PEKERJAAN", 2],
    ["AGAMA", 2],
    ["BERLAKU", 2],
  ] as Array<[string, number]>) {
    if (findFuzzyLabel(line, target, maxDist)) return true;
  }
  return false;
}

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

/** Counts real alphanumeric characters in a string, ignoring whitespace
 * and symbol noise. Used to tell "this line is actual card text" apart
 * from "this line is hologram/guilloche texture noise that Tesseract
 * hallucinated into a stray letter or two" - confirmed necessary against
 * a real device photo where Nama's value came back as just "i" (a single
 * noise character) and got accepted as if it were a real name. A field
 * that fails this check is left BLANK rather than filled with garbage,
 * so the user is prompted to fill it in manually instead of unknowingly
 * submitting noise as real data. */
function alnumCount(s: string): number {
  return (s.match(/[A-Za-z0-9]/g) ?? []).length;
}
const MIN_REAL_CONTENT_CHARS = 2;

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
    if (isBoundaryLine(line)) {
      collectingAlamat = false;
      awaitingNama = false;
      continue;
    }

    // Label-less line: either the value for a "Nama" label that had
    // nothing after it (":" dropped by OCR, value pushed to its own
    // line), or a continuation line for Alamat. Noise-only lines (stray
    // symbols/single letters from the hologram texture) are skipped
    // rather than appended, so they can't glue garbage onto a
    // continuation field - see alnumCount above.
    if (awaitingNama) {
      if (alnumCount(line) >= MIN_REAL_CONTENT_CHARS) nama = line;
      awaitingNama = false;
    } else if (collectingAlamat && alnumCount(line) >= MIN_REAL_CONTENT_CHARS) {
      alamat = alamat ? `${alamat} ${line}` : line;
    }
  }
  const cleanedNama = cleanExtractedValue(nama);
  const cleanedAlamat = cleanExtractedValue(alamat);
  return {
    nama: alnumCount(cleanedNama) >= MIN_REAL_CONTENT_CHARS ? cleanedNama : "",
    alamat: cleanedAlamat,
  };
}

/** CLAHE (Contrast Limited Adaptive Histogram Equalization) applied
 * in-place to a grayscale pixel buffer, tile-by-tile with bilinear
 * interpolation across tile boundaries (standard algorithm, see
 * Zuiderveld 1994 / the same technique OpenCV's cv2.CLAHE implements). */
function applyClahe(
  gray: Uint8ClampedArray,
  width: number,
  height: number,
  tilesX = 8,
  tilesY = 8,
  clipLimit = 3.0
): void {
  const tileW = Math.ceil(width / tilesX);
  const tileH = Math.ceil(height / tilesY);

  // One 0-255 -> 0-255 mapping (histogram-equalized, with the histogram
  // clipped at `clipLimit` to avoid over-amplifying noise in near-flat
  // regions) per tile.
  const maps: Uint8ClampedArray[][] = [];
  for (let ty = 0; ty < tilesY; ty++) {
    maps[ty] = [];
    for (let tx = 0; tx < tilesX; tx++) {
      const x0 = tx * tileW;
      const y0 = ty * tileH;
      const x1 = Math.min(width, x0 + tileW);
      const y1 = Math.min(height, y0 + tileH);
      const hist = new Uint32Array(256);
      for (let y = y0; y < y1; y++) {
        for (let x = x0; x < x1; x++) {
          hist[gray[y * width + x]]++;
        }
      }
      const pixelCount = (x1 - x0) * (y1 - y0);
      const clip = Math.max(1, Math.round((clipLimit * pixelCount) / 256));
      let excess = 0;
      for (let i = 0; i < 256; i++) {
        if (hist[i] > clip) {
          excess += hist[i] - clip;
          hist[i] = clip;
        }
      }
      const redistribute = excess / 256;
      const map = new Uint8ClampedArray(256);
      let cdf = 0;
      const scale = 255 / pixelCount;
      for (let i = 0; i < 256; i++) {
        cdf += hist[i] + redistribute;
        map[i] = Math.round(cdf * scale);
      }
      maps[ty][tx] = map;
    }
  }

  // Apply each pixel's mapping as a bilinear blend of its 4 nearest tile
  // centers, so there's no visible seam at tile edges.
  const out = new Uint8ClampedArray(gray.length);
  for (let y = 0; y < height; y++) {
    const tyF = (y - tileH / 2) / tileH;
    const ty0Raw = Math.floor(tyF);
    const wy = tyF - ty0Raw;
    const ty0 = Math.min(Math.max(ty0Raw, 0), tilesY - 1);
    const ty1 = Math.min(Math.max(ty0Raw + 1, 0), tilesY - 1);
    for (let x = 0; x < width; x++) {
      const txF = (x - tileW / 2) / tileW;
      const tx0Raw = Math.floor(txF);
      const wx = txF - tx0Raw;
      const tx0 = Math.min(Math.max(tx0Raw, 0), tilesX - 1);
      const tx1 = Math.min(Math.max(tx0Raw + 1, 0), tilesX - 1);

      const v = gray[y * width + x];
      const v00 = maps[ty0][tx0][v];
      const v01 = maps[ty0][tx1][v];
      const v10 = maps[ty1][tx0][v];
      const v11 = maps[ty1][tx1][v];
      const top = v00 * (1 - wx) + v01 * wx;
      const bottom = v10 * (1 - wx) + v11 * wx;
      out[y * width + x] = Math.round(top * (1 - wy) + bottom * wy);
    }
  }
  out.forEach((v, i) => (gray[i] = v));
}

/** Grayscale + CLAHE + mild upscale, done in-browser via canvas before
 * handing the image to Tesseract. CLAHE (not a global contrast stretch)
 * is used because the printed hologram/guilloche background on a KTP
 * varies in brightness across the card, so a single global contrast
 * curve leaves some regions too dark and others blown out. That was
 * confirmed (2026-08, side-by-side offline test against a real user KTP
 * photo) to be the root cause of OCR failures: the global stretch made
 * Tesseract misread the "ALAMAT" label itself as "Aiamat" (silently
 * dropping the whole address) and drop the Nama value entirely. CLAHE
 * equalizes contrast *locally* per tile instead, and the same test read
 * Nama ("MAMAN"), Alamat, and NIK correctly. NOTE: a later session
 * reverted this to an Otsu-based global stretch (commit a796a93) without
 * physical-test evidence; the first real device test under that revert
 * produced an empty Nama again ("Nama i —— ="), the exact symptom CLAHE
 * fixed, so this restores CLAHE. Resolution cap 2200px: modern phone
 * cameras commonly produce crops well above 1600px on the long edge, and
 * downscaling further than necessary throws away real text detail (small
 * fields like RT/RW are only a handful of pixels tall to begin with). */
async function preprocessForOcr(source: File | Blob): Promise<Blob> {
  const bitmap = await createImageBitmap(source);
  const scale = Math.min(2, 2200 / Math.max(bitmap.width, bitmap.height)) || 1;
  const canvas = document.createElement("canvas");
  canvas.width = Math.round(bitmap.width * scale);
  canvas.height = Math.round(bitmap.height * scale);
  const ctx = canvas.getContext("2d");
  if (!ctx) return source;
  ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

  const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const d = imgData.data;
  const w = canvas.width;
  const h = canvas.height;
  const gray = new Uint8ClampedArray(w * h);
  for (let i = 0, p = 0; i < d.length; i += 4, p++) {
    gray[p] = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
  }

  applyClahe(gray, w, h);

  for (let i = 0, p = 0; i < d.length; i += 4, p++) {
    d[i] = d[i + 1] = d[i + 2] = gray[p];
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
