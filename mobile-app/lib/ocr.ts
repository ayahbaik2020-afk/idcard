import type {
  OcrResult,
  OcrResultItem,
  OcrRuntimeParamsInput
} from "@paddleocr/paddleocr-js";

export type KtpOcrResult = {
  nik: string;
  nama: string;
  alamat: string;
  rawText: string;
};

/** Minimal structural view of the PaddleOCR engine that scanKtp uses. The
 * full PaddleOCR class is only loaded via dynamic import() at call time (see
 * getEngine) so that this module never pulls the heavy onnxruntime/opencv
 * wasm pipeline into the server-side bundle - it only ever runs in the
 * browser, inside a "use client" page. */
type OcrEngine = {
  predict(input: unknown, params?: OcrRuntimeParamsInput): Promise<OcrResult[]>;
  dispose(): Promise<void>;
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
 * from "this line is hologram/guilloche texture noise that the OCR engine
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

// --- PaddleOCR engine lifecycle ---

/** PP-OCRv6_tiny (det ~1.7MB + rec ~4.3MB) is the smallest officially
 * published PP-OCRv6 pair and the one that supports the Latin script set
 * that Indonesian (lang "id") belongs to. Selected by explicit model name
 * because the SDK's `lang`+`ocrVersion: "PP-OCRv6"` shortcut always maps
 * to the larger PP-OCRv6_small pair; tiny must be requested by name (the
 * SDK README documents exactly this). Swap these two names for the
 * `_small_` variants if physical-device accuracy ever turns out to need
 * the bigger models. */
const TEXT_DETECTION_MODEL = "PP-OCRv6_tiny_det";
const TEXT_RECOGNITION_MODEL = "PP-OCRv6_tiny_rec";

/** onnxruntime-web must be told where its .wasm files live (it does not
 * bundle them). The jsdelivr CDN path is pinned to the exact installed
 * onnxruntime-web version so the wasm binary and the JS that loads it
 * can never drift apart. */
const ORT_WASM_PATHS =
  "https://cdn.jsdelivr.net/npm/onnxruntime-web@1.27.0/dist/";

/** Threaded wasm needs crossOriginIsolated (SharedArrayBuffer). Without it
 * the runtime silently falls back to a single thread, so only request more
 * threads when the page is actually isolated - mirrors what the official
 * paddleocr-js demo does. */
function getOrtThreadCount(): number {
  if (typeof self === "undefined" || !self.crossOriginIsolated) return 1;
  return Math.min(4, Math.max(1, (navigator.hardwareConcurrency || 2) - 1));
}

/** Models (~6MB total) are downloaded and compiled into ONNX sessions on
 * the first call and then cached for the lifetime of the page, so a
 * "Ambil ulang foto" / second registration reuses them instead of
 * re-downloading. On failure the cache is cleared so the next call can
 * retry. */
let enginePromise: Promise<OcrEngine> | null = null;

async function createEngine(): Promise<OcrEngine> {
  const { PaddleOCR } = await import("@paddleocr/paddleocr-js");
  return PaddleOCR.create({
    worker: false,
    textDetectionModelName: TEXT_DETECTION_MODEL,
    textRecognitionModelName: TEXT_RECOGNITION_MODEL,
    ortOptions: {
      backend: "auto",
      wasmPaths: ORT_WASM_PATHS,
      numThreads: getOrtThreadCount(),
      simd: true
    }
  });
}

async function getEngine(): Promise<OcrEngine> {
  if (!enginePromise) {
    enginePromise = createEngine().catch((err) => {
      enginePromise = null;
      throw err;
    });
  }
  return enginePromise;
}

// --- PaddleOCR result -> ordered text lines ---

/** PaddleOCR's detection boxes are NOT returned in reading order. Each
 * box becomes one line of text, so before extraction we sort boxes by
 * their vertical position and merge boxes that sit on the same visual row
 * (e.g. the "NIK :" label and the digits next to it are often two
 * separate boxes) into a single line in left-to-right order. This mirrors
 * the reading-order guarantee Tesseract's page segmentation gave the
 * extraction logic. */
function itemsToLines(items: OcrResultItem[]): string[] {
  if (!items.length) return [];

  const boxes = items.map((item) => {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    for (const [x, y] of item.poly) {
      if (x < minX) minX = x;
      if (y < minY) minY = y;
      if (x > maxX) maxX = x;
      if (y > maxY) maxY = y;
    }
    return {
      item,
      centerX: (minX + maxX) / 2,
      centerY: (minY + maxY) / 2,
      height: maxY - minY
    };
  });

  // Row-merge tolerance: half the median box height. Rows on a KTP are
  // evenly spaced and roughly the same height, so boxes whose vertical
  // centers are within this band belong to the same text row.
  const heights = boxes.map((b) => b.height).sort((a, b) => a - b);
  const medianHeight = heights[Math.floor(heights.length / 2)] || 1;
  const tolerance = Math.max(2, medianHeight * 0.5);

  boxes.sort((a, b) => a.centerY - b.centerY || a.centerX - b.centerX);

  const rows: Array<{ boxes: typeof boxes; meanY: number; count: number }> = [];
  for (const box of boxes) {
    const last = rows[rows.length - 1];
    if (last && Math.abs(box.centerY - last.meanY) <= tolerance) {
      last.boxes.push(box);
      last.meanY =
        (last.meanY * last.count + box.centerY) / (last.count + 1);
      last.count += 1;
    } else {
      rows.push({ boxes: [box], meanY: box.centerY, count: 1 });
    }
  }

  return rows
    .map((row) =>
      row.boxes
        .sort((a, b) => a.centerX - b.centerX)
        .map((b) => b.item.text.trim())
        .filter(Boolean)
        .join(" ")
    )
    .filter(Boolean);
}

const DEFAULT_PREDICT_PARAMS: OcrRuntimeParamsInput = {
  // Default pipeline score_thresh is 0.0 (keep everything); a small floor
  // here drops the lowest-confidence hallucinated lines, same trade-off the
  // official demo makes.
  textRecScoreThresh: 0.1
};

/** Second pass only runs when a field is still missing: a lower box
 * threshold recovers text boxes SINGLE-detection merged into noise or
 * dropped entirely (PaddleOCR is far more reliable than Tesseract here,
 * but a second cheap pass is worth it against handing the user a form
 * with fields silently blank - only runs once per registration). */
const RECOVERY_PREDICT_PARAMS: OcrRuntimeParamsInput = {
  textDetBoxThresh: 0.3,
  textRecScoreThresh: 0.05
};

async function recognizeOnce(
  engine: OcrEngine,
  image: Blob,
  params: OcrRuntimeParamsInput,
  onProgress?: (pct: number) => void,
  from?: number,
  to?: number
): Promise<KtpOcrResult> {
  // PaddleOCR's predict() has no progress callback; while inference runs we
  // drive a smooth estimate between the two bounds so the UI bar doesn't
  // appear frozen, then snap to 100 when done.
  let current = from ?? 0;
  const step = Math.max(1, Math.round(((to ?? 100) - current) / 20));
  const timer = setInterval(() => {
    current = Math.min(to ?? 100, current + step);
    onProgress?.(current);
  }, 150);

  try {
    const [result] = await engine.predict(image, params);
    const lines = itemsToLines(result.items);
    const text = lines.join("\n");
    const { nama, alamat } = extractNamaAlamat(lines);
    return { nik: extractNik(text, lines), nama, alamat, rawText: text };
  } finally {
    clearInterval(timer);
  }
}

function fieldsFound(r: KtpOcrResult): number {
  return [r.nik, r.nama, r.alamat].filter(Boolean).length;
}

export async function scanKtp(
  imageSource: File | Blob | string,
  onProgress?: (pct: number) => void
): Promise<KtpOcrResult> {
  onProgress?.(5);
  const engine = await getEngine();
  onProgress?.(30);

  // PaddleOCR's predict() accepts Blob/File/ImageBitmap/canvas/img - but
  // not a URL string, so the string form (used by tests) becomes a Blob.
  const image: Blob =
    typeof imageSource === "string"
      ? await (await fetch(imageSource)).blob()
      : imageSource;

  const first = await recognizeOnce(
    engine,
    image,
    DEFAULT_PREDICT_PARAMS,
    onProgress,
    30,
    60
  );
  if (fieldsFound(first) === 3) {
    onProgress?.(100);
    return first;
  }

  const second = await recognizeOnce(
    engine,
    image,
    RECOVERY_PREDICT_PARAMS,
    onProgress,
    60,
    95
  );
  onProgress?.(100);

  if (fieldsFound(second) <= fieldsFound(first)) return first;

  // Field-level merge: take whichever pass actually read each field, so a
  // pass that's better overall can't blank out a field the other pass got
  // right.
  return {
    nik: second.nik || first.nik,
    nama: second.nama || first.nama,
    alamat: second.alamat || first.alamat,
    rawText:
      second.rawText.length > first.rawText.length
        ? second.rawText
        : first.rawText
  };
}
