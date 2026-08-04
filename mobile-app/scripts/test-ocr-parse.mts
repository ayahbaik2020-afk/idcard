/**
 * Regression tests for the KTP OCR raw-text parser (lib/ocr.ts). Runs
 * fully offline with `node scripts/test-ocr-parse.ts` - no OCR engine, no
 * browser, no network. Every fixture below is either a real physical-device
 * raw text (see WORK_LOG 2026-08-05 "lanjutan 2/4") or a synthetic case
 * built from a real bug that was fixed, so a future change that silently
 * breaks one of those fixes fails CI-style here.
 *
 * Exit code is non-zero if any case fails.
 */
import { parseKtpRawText, isPlausibleNik } from "../lib/ocr.ts";

type Expected = {
  nik?: string;
  nama?: string;
  alamat?: string;
};

type Case = { name: string; raw: string; expected: Expected };

const CASES: Case[] = [
  {
    name: "kartu bersih standar",
    raw: `NIK: 3672051802840001
NAMA: MAMAN
Tempat/Tgl Lahir: CILEGON, 18-02-1984
Jenis Kelamin: LAKI-LAKI
Alamat: PERUM GRAND SUTERA CILEGON
RT/RW: 020/006
Kel/Desa: CIWADUK
Kecamatan: CILEGON
Agama: ISLAM
Status Perkawinan: KAWIN
Pekerjaan: WIRASWASTA
Kewarganegaraan: WNI
Berlaku Hingga: SEUMUR HIDUP`,
    expected: {
      nik: "3672051802840001",
      nama: "MAMAN",
      alamat: "PERUM GRAND SUTERA CILEGON",
    },
  },

  {
    name: "NIK b->6 (tes fisik pertama)",
    raw: `PROVINSI JAWA BARAT
KOTA CILEGON
NIK: 3b72051802840001
NAMA: MAMAN`,
    expected: { nik: "3672051802840001", nama: "MAMAN" },
  },

  {
    name: "NIK b/B->6, bukan ->8",
    raw: `NIK: 3B72051802840001`,
    expected: { nik: "3672051802840001" },
  },

  {
    name: "NIK angka 8 asli tidak diganggu",
    raw: `NIK: 3672051802840001
NAMA: SUPRATMAN`,
    expected: { nik: "3672051802840001", nama: "SUPRATMAN" },
  },

  {
    name: "NIK digit menempel ekstra di depan (glued digit)",
    raw: `NIK: 13672051802840001`,
    expected: { nik: "3672051802840001" },
  },

  {
    name: "label NIK hilang jadi 'ik' (fuzzy)",
    raw: `ik 3672051802840001
NAMA: MAMAN`,
    expected: { nik: "3672051802840001", nama: "MAMAN" },
  },

  {
    name: "label NIK hilang total, fallback seluruh kartu",
    raw: `PROVINSI JAWA BARAT
3672051802840001
KOTA CILEGON`,
    expected: { nik: "3672051802840001" },
  },

  {
    name: "nama dengan noise hologram (— = ——=)",
    raw: `NIK: 3672051802840001
NAMA: — MAMAN = = —=——=`,
    expected: { nama: "MAMAN" },
  },

  {
    name: "nama cuma noise ('i') dibiarkan kosong",
    raw: `NIK: 3672051802840001
NAMA: i`,
    expected: { nama: "" },
  },

  {
    name: "nama di baris berikutnya (tanpa ':')",
    raw: `NIK: 3672051802840001
NAMA:
MAMAN`,
    expected: { nama: "MAMAN" },
  },

  {
    name: "label ALAMAT salah baca 'Alama' (fuzzy)",
    raw: `NAMA: BUDI SANTOSO
Alama: PERUM GRAND SUTERA CILEGON
RT/RW: 020/006
Kel/Desa: CIWADUK`,
    expected: {
      nama: "BUDI SANTOSO",
      alamat: "PERUM GRAND SUTERA CILEGON",
    },
  },

  {
    name: "alamat berhenti di RT/RW yang salah baca 'RIAW'",
    raw: `NAMA: MAMAN
Alamat: JL MERPATI BLOK C3 NO 23
RIAW 020/006
KeiDesa CIWADUK
Kecamatan CILEGON
Agama: ISLAM`,
    expected: {
      alamat: "JL MERPATI BLOK C3 NO 23",
    },
  },

  {
    name: "alamat berhenti di Kel/Desa salah baca 'KeiDesa'",
    raw: `NAMA: MAMAN
Alamat: PERUM GRAND SUTERA BLOK C
KeiDesa CIWADUK`,
    expected: {
      alamat: "PERUM GRAND SUTERA BLOK C",
    },
  },

  {
    name: "alamat multi-baris normal",
    raw: `NIK: 3672051802840001
NAMA: MAMAN
Alamat: JL MERPATI NO 1
PERUM GRAND SUTERA
CILEGON
RT/RW: 020/006
Kel/Desa: CIWADUK`,
    expected: {
      nama: "MAMAN",
      alamat: "JL MERPATI NO 1 PERUM GRAND SUTERA CILEGON",
    },
  },

  {
    name: "NIK dengan spasi tersebar antar digit",
    raw: `NIK : 3 6 7 2 0 5 1 8 0 2 8 4 0 0 0 1
NAMA : MAMAN`,
    expected: { nik: "3672051802840001", nama: "MAMAN" },
  },
];

const NIK_PLAUSIBLE: Array<[string, boolean]> = [
  ["3672051802840001", true],
  ["3201091405930002", true],
  ["123456", false],
  ["36720518028400018", false],
];

let failed = 0;

for (const c of CASES) {
  const got = parseKtpRawText(c.raw);
  const problems: string[] = [];
  for (const key of ["nik", "nama", "alamat"] as const) {
    const exp = c.expected[key];
    if (exp !== undefined && got[key] !== exp) {
      problems.push(`  ${key}: expected "${exp}" but got "${got[key]}"`);
    }
  }
  if (problems.length > 0) {
    failed++;
    console.error(`FAIL ${c.name}`);
    for (const p of problems) console.error(p);
  } else {
    console.log(`ok   ${c.name}`);
  }
}

for (const [nik, expected] of NIK_PLAUSIBLE) {
  const got = isPlausibleNik(nik);
  if (got !== expected) {
    failed++;
    console.error(`FAIL isPlausibleNik("${nik}"): expected ${expected} but got ${got}`);
  } else {
    console.log(`ok   isPlausibleNik("${nik}") === ${expected}`);
  }
}

if (failed > 0) {
  console.error(`\n${failed} case(s) failed.`);
  process.exit(1);
}
console.log("\nSemua regression test OCR lolos.");
