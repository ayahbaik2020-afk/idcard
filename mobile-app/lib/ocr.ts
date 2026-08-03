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

/** CLAHE (Contrast Limited Adaptive Histogram Equalization) applied
 * in-place to a grayscale pixel buffer, tile-by-tile with bilinear
 * interpolation across tile boundaries (standard algorithm, see
 * Zuiderveld 1994 / the same technique OpenCV's cv2.CLAHE implements).
 *
 * This replaces a plain global contrast stretch, which was empirically
 * found (2026-08 - tested offline against a real user KTP photo with
 * both approaches side by side) to be the actual root cause of a
 * production failure: the printed hologram/guilloche background on a
 * KTP varies in brightness across the card, so a single global contrast
 * curve leaves some regions too dark and others blown out. That
 * inconsistency was enough to make Tesseract misread the "ALAMAT" label
 * itself as "Aiamat" (silently dropping the whole address - the label
 * regex requires an exact match), and to glue a stray extra digit onto
 * the NIK. CLAHE equalizes contrast *locally* per tile instead of
 * globally, which fixed both in the same side-by-side test (see
 * WORK_LOG.md for the full before/after). */
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
 * handing the image to Tesseract. See `applyClahe` above for why CLAHE
 * specifically (replacing a plain global contrast stretch). Resolution
 * cap raised from the previous 1600px to 2200px: modern phone cameras
 * commonly produce crops well above 1600px on the long edge, and
 * downscaling further than necessary throws away real text detail
 * (small fields like RT/RW are only a handful of pixels tall to begin
 * with) - this only matters for photos that are already larger than
 * that, small photos still get upscaled the same as before. */
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
