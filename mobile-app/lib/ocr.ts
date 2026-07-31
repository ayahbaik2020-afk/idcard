import { createWorker } from "tesseract.js";

export type KtpOcrResult = {
  nik: string;
  nama: string;
  tempatTglLahir: string;
  alamat: string;
  rawText: string;
};

// Ambil baris teks setelah label tertentu (mis. "NIK", "Nama") dari hasil
// OCR KTP Indonesia. OCR KTP TIDAK PERNAH 100% akurat (hologram, cahaya,
// posisi kamera) — hasil ini HARUS direview & bisa diedit user, jangan
// pernah submit NIK hasil OCR tanpa konfirmasi user.
function extractField(lines: string[], label: string): string {
  const idx = lines.findIndex((l) => l.toUpperCase().includes(label));
  if (idx === -1) return "";
  const sameLine = lines[idx].split(":").slice(1).join(":").trim();
  if (sameLine) return sameLine;
  return lines[idx + 1]?.trim() ?? "";
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
    const {
      data: { text },
    } = await worker.recognize(imageSource);

    const lines = text
      .split("\n")
      .map((l) => l.trim())
      .filter(Boolean);

    // NIK: cari 16 digit angka berurutan sebagai fallback jika label "NIK"
    // tidak terbaca dengan bersih oleh OCR.
    const nikMatch = text.match(/\b\d{16}\b/);

    return {
      nik: nikMatch?.[0] ?? extractField(lines, "NIK").replace(/\D/g, ""),
      nama: extractField(lines, "NAMA"),
      tempatTglLahir: extractField(lines, "LAHIR"),
      alamat: extractField(lines, "ALAMAT"),
      rawText: text,
    };
  } finally {
    await worker.terminate();
  }
}
