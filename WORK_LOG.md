# WORK LOG — ID Card System (Sulfindo Adiusaha)

> **Cara pakai file ini:**
> - **Sebelum mulai kerja**: baca dulu section "Belum / Perlu Ditindaklanjuti" di
>   bawah, supaya tahu apa yang masih menggantung dari sesi sebelumnya
>   (baik dari Claude di chat ini, Claude Code, atau developer lain).
> - **Setelah selesai kerja**: pindahkan item terkait dari "Belum" ke
>   "Selesai" (dengan tanggal), dan tambahkan entri baru kalau ada temuan
>   baru yang belum sempat dikerjakan.
> - File ini genderless terhadap siapa yang mengerjakan — siapapun/agent
>   apapun yang menyentuh project ini sebaiknya update file ini juga.

---

## ⏳ Belum / Perlu Ditindaklanjuti

> Diurutkan berdasarkan prioritas (1 = paling mendesak). Urutan ini
> asumsi awal dari diskusi 2026-08-01 — sesuaikan kalau ada
> pertimbangan lain dari lapangan.

1. **[Akurasi OCR] Perlu 1 putaran tes fisik lagi untuk konfirmasi
   akhir** (lihat entri Selesai 2026-08-05 "lanjutan 2" untuk detail
   fix-nya). NIK & Alamat sudah dikonfirmasi cocok 100% terhadap raw
   OCR text fisik yang dikirim user (bukan lagi simulasi/asumsi).
   Yang masih perlu diverifikasi user: (a) apakah Nama sekarang
   terbaca dengan foto/pencahayaan yang lebih baik (kali ini sengaja
   dibiarkan kosong karena datanya sendiri tidak terbaca sama sekali
   di foto itu, bukan bug), (b) coba beberapa KTP/kondisi cahaya lain
   untuk lihat apakah fix `b→6` di NIK tidak menimbulkan masalah baru
   di kasus lain.
2. **Deploy Vercel (`idcard-brown-delta`) sempat 2 commit tertinggal**
   — **dicek 2026-08-04**: akun Vercel yang terhubung ke sesi kerja ini
   (`ayahbaik's projects`) cuma punya akses ke project `sikara`, TIDAK
   ada akses ke `idcard-brown-delta` sama sekali (`list_projects` &
   `list_teams` tidak menampilkannya). Ini mendukung dugaan awal:
   project itu kemungkinan di bawah akun/tim Vercel lain (mungkin akun
   `IT-Merak` sendiri, terpisah dari akun yang terhubung ke sesi kerja
   ini). **Perlu dicek manual oleh pemilik akun**: Vercel dashboard →
   project `idcard-brown-delta` → Settings → Git (siapa yang terhubung)
   dan tab Deployments (cari status "Skipped").
3. **Redesign plant-display**: **masih blocked** — perlu dikonfirmasi
   langsung di layar TV/tablet fisik plant (kontras kartu vs video,
   ukuran font, posisi scanner). Tidak bisa diverifikasi dari sesi kerja
   remote manapun tanpa akses fisik ke perangkat di lapangan.

## ✅ Selesai

### 2026-08-05 (lanjutan 2) — Fix 2 bug nyata dari tes fisik pertama (NIK b→6, filter noise Nama/Alamat)
- Lanjutan langsung dari entri "Belum" #1 di atas (bukti tes fisik
  pertama). Kedua bug direproduksi dulu offline lewat Node.js
  langsung terhadap kode ter-commit sebelum diubah (bukan tebakan):
  `extractNik` menghasilkan `3872051802840001` (real device text),
  `extractNamaAlamat` menghasilkan `nama:"i"`, `alamat:"...CILEGON a"`
  — persis sesuai dugaan di entri "Belum".
- **Fix 1 (NIK)**: `normalizeOcrDigits()` — mapping `b/B` diubah dari
  `→8` jadi `→6`, karena bukti nyata (bukan cuma dua-duanya
  dipertahankan sebagai kemungkinan) menunjukkan Tesseract di kartu
  fisik ini baca digit "6" sebagai "b" pada posisi kode kabupaten NIK.
- **Fix 2 (Nama/Alamat)**: helper baru `alnumCount()` + konstanta
  `MIN_REAL_CONTENT_CHARS=2` — nilai Nama final HARUS punya >=2
  karakter alfanumerik asli (setelah `cleanExtractedValue`) untuk
  diterima, kalau tidak (mis. cuma "i" dari noise) field dibiarkan
  KOSONG, bukan diisi sampah. Baris lanjutan Alamat yang murni noise
  (mis. "a —— — —") sekarang di-skip sebelum sempat disambung ke
  alamat, bukan cuma dibersihkan setelahnya (yang sebelumnya
  meninggalkan sisa huruf nyasar seperti "...CILEGON a").
- Diverifikasi 3 lapis: (1) `npm run build` + `npx eslint` bersih;
  (2) fungsi diekstrak & dijalankan ulang lewat Node.js langsung
  terhadap raw text fisik yang sama persis dari user — hasil sekarang
  `nik:"3672051802840001"` (BENAR, cocok KTP asli), `nama:""` (kosong,
  bukan lagi "i" yang menyesatkan), `alamat:"PERUM GRAND SUTERA
  CILEGON"` (bersih, tanpa noise nyangkut); (3) regression-check
  terhadap sampel foto galeri yang sudah bagus sebelumnya (dari sesi
  2026-08-01) — hasil tidak berubah (`nama:"MAMAN"`, alamat lengkap
  4 baris tersambung benar), jadi filter noise ini tidak menghapus
  konten asli yang pendek/valid.
- **Catatan jujur**: NIK & Alamat sekarang match 100% dengan bukti
  fisik yang dikirim user — ini levelnya lebih kuat dari sesi-sesi
  OCR sebelumnya (bukan cuma "lolos build", tapi cocok terhadap raw
  OCR text asli dari HP). Nama sengaja dibiarkan kosong (bukan "MAMAN"
  yang benar) karena foto fisik user kali ini memang gagal total baca
  nilai Nama-nya (bukan regresi kode - datanya sendiri tidak
  terbaca) - user tetap perlu isi manual untuk kasus foto seperti ini.
  **Masih perlu 1 putaran tes fisik lagi** untuk konfirmasi akhir
  (foto baru, cek apakah Nama kali ini terbaca dengan pencahayaan/
  sudut berbeda) sebelum item ini ditutup total.

### 2026-08-05 (lanjutan) — Fuzzy label matching untuk Nama/Alamat + perbaiki sesi terputus
- Diminta user: "baca WORK_LOG dan lanjutkan pekerjaan ini" (disertai
  screenshot sesi Claude Code lain yang sedang mengedit `lib/ocr.ts`,
  tengah proses "update extractNamaAlamat untuk pakai fuzzy match" lalu
  membersihkan variabel yang tidak lagi dipakai).
- WORK_LOG dibaca dulu sesuai instruksi filenya sendiri. Ditemukan
  working tree punya 2 file modified yang belum sempat di-commit sesi
  sebelumnya (`WORK_LOG.md`, `mobile-app/lib/ocr.ts`) di atas HEAD
  `a796a93`.
- `npm run build` dijalankan dulu untuk cek state sebenarnya (jangan
  asumsi otomatis "sudah selesai" dari screenshot) — **ketemu syntax
  error nyata**: `function stripFuzzyLabel(...): string {` dobel,
  deklarasi pertama terpotong di tengah (langsung disambung komentar
  JSDoc `levenshtein` tanpa `}` penutup) — sisa dari proses edit yang
  terhenti. Diperbaiki: baris deklarasi pertama yang rusak dihapus,
  deklarasi lengkap yang benar (di bawahnya) dipertahankan.
- Fungsi `stripLabel` (lama, sudah digantikan `stripFuzzyLabel`)
  dicek dulu pemakaiannya (`grep`) — dikonfirmasi tidak dipakai di
  mana pun, dihapus.
- Perubahan inti yang sekarang utuh & lolos build: `findFuzzyLabel()` +
  `levenshtein()` — label NAMA/ALAMAT dicocokkan dengan toleransi jarak-
  edit 1 (bukan regex exact-match `\bALAMAT\b` seperti sebelumnya),
  memperbaiki kasus nyata dari sesi 2026-08-04 di mana "ALAMAT" terbaca
  "Alama" oleh Tesseract dan bikin seluruh field hilang.
- Diverifikasi 2 lapis sebelum commit: (1) `npm run build` (Turbopack +
  TypeScript) bersih penuh, semua route ter-generate; (2)
  `npx eslint lib/ocr.ts app/register/page.tsx` tanpa warning; (3)
  logika `extractNamaAlamat`+`findFuzzyLabel` diekstrak & dijalankan
  lewat Node.js langsung terhadap sampel teks OCR nyata (dari foto KTP
  user di sesi sebelumnya, mengandung "Alama" tanpa huruf "t") — hasil
  `nama: "MAMAN"`, `alamat` tertangkap benar, mengonfirmasi fuzzy match
  bekerja seperti yang dimaksud.
- **Catatan jujur**: sama seperti seluruh riwayat OCR di atas — ini
  lolos build & tervalidasi lewat simulasi offline, TAPI **belum ada
  bukti tes fisik di HP asli** untuk kombinasi fuzzy-match ini. Lihat
  item "Belum" #1.

### 2026-08-05 — Verifikasi status git + commit & push perubahan mobile-app
- Diminta user: "commit & push untuk pekerjaan dan perbaikan di
  mobile-app". Sebelum eksekusi, dicek dulu WORK_LOG.md (atas
  instruksi user) — ternyata log sudah berisi banyak entri sampai
  2026-08-04 dari sesi lain yang mengklaim sudah "di-push", jadi
  dicek ulang `git fetch` + `git status` dulu (jangan percaya klaim
  di log begitu saja) — dikonfirmasi: semua yang tercatat di log
  memang sudah `origin/main`, cuma ada 1 file belum ter-commit:
  `mobile-app/lib/ocr.ts`.
- Isi perubahan (WIP dari sesi lain, koheren/utuh bukan setengah
  jadi): revert CLAHE (commit `0895966`) kembali ke contrast-stretch,
  tapi kali ini berbasis **Otsu threshold** (dihitung dari histogram
  foto itu sendiri, bukan titik tengah 128 tetap); tambah **OCR
  dua-pass** (`recognizeOnce` dipanggil dengan PSM SINGLE_BLOCK dulu,
  kalau ada field NIK/Nama/Alamat yang masih kosong dicoba lagi
  dengan PSM SPARSE_TEXT, hasil digabung per-field lewat
  `fieldsFound`); tambah `cleanExtractedValue()` untuk buang noise
  simbol dari tekstur hologram KTP yang suka nempel di teks
  ("— MAMAN = = —=———" → "MAMAN"); perbaikan lanjutan di
  `extractNik()` (fallback bertingkat: dekat label NIK → digit run
  berspasi di seluruh dokumen → normalisasi huruf↔angka card-wide
  dengan validasi struktur).
- Diverifikasi dulu sebelum commit: `npx tsc --noEmit` bersih, `npm
  run build` sukses penuh (semua route ke-generate). Commit `a796a93`,
  push ke `origin/main` berhasil (`289d6de..a796a93`).
- **Catatan jujur**: ini kode yang sehat secara build, TAPI belum ada
  bukti tes fisik untuk kombinasi Otsu+two-pass ini — lihat item
  "Belum" #1.

### 2026-08-04 (lanjutan 4) — Root cause OCR gagal total di device fisik: CLAHE
- User kirim screenshot tes fisik nyata (foto KTP asli, bukan foto
  galeri test sebelumnya): NIK "(kosong) - Tidak terbaca", Nama salah
  total ("ARAN —" vs asli "MAMAN"), Alamat "(kosong) - Tidak terbaca".
- Didiagnosis **offline dengan bukti nyata**, bukan tebakan: crop foto
  KTP diambil dari screenshot yang sama (region kartu di preview),
  diproses ulang lewat pipeline app yang persis sama (`tesseract` CLI,
  `eng`, PSM SINGLE_BLOCK) memakai preprocessing lama (linear contrast
  stretch) vs beberapa alternatif, dibandingkan sisi-demi-sisi.
- **Root cause dikonfirmasi**: preprocessing lama (global/linear
  contrast stretch, satu kurva untuk seluruh gambar) membuat Tesseract
  salah baca label "ALAMAT" jadi "Aiamat" — persis skenario yang sudah
  diwanti-wanti di komentar kode sejak sesi sebelumnya ("kalau
  Tesseract salah baca label itu sendiri, field itu gagal terbaca sama
  sekali"). Karena regex label butuh match persis (`\bALAMAT\b`), satu
  huruf salah baca itu saja bikin seluruh field Alamat hilang. Pola
  cahaya hologram/guilloche KTP yang tidak rata di seluruh kartu bikin
  contrast stretch GLOBAL (satu kurva untuk semua piksel) tidak cukup —
  sebagian area jadi terlalu gelap/terang.
- Diuji beberapa alternatif preprocessing side-by-side pada foto yang
  sama: CLAHE saja, CLAHE+denoise (bilateral), CLAHE+Otsu threshold,
  CLAHE+adaptive threshold, unsharp+CLAHE — **CLAHE saja (clipLimit
  3.0, tile 8x8) menang telak**: label Alamat kebaca benar (multi-baris
  tertangkap sempurna), Nama "MAMAN" benar, NIK 15/16 digit benar
  (vs sebelumnya 0 field terbaca sama sekali). Threshold/denoise
  tambahan malah memperburuk (istilah teknis: over-smoothing
  menghilangkan detail teks yang sudah tipis).
- Juga dites PSM 3/4/6/11/12 dengan preprocessing baru — PSM 6
  (SINGLE_BLOCK, yang sudah dipakai) tetap terbaik, tidak diubah.
- Implementasi: CLAHE ditulis dari nol di `lib/ocr.ts` (histogram
  per-tile + clipping + CDF mapping + interpolasi bilinear antar tile
  supaya tidak ada garis batas terlihat) karena tidak ada OpenCV di
  browser. Diverifikasi 2 lapis sebelum dipakai: (1) port Python-nya
  dicocokkan piksel-demi-piksel ke hasil `cv2.CLAHE` asli (beda
  rata-rata cuma 0.58/255), (2) fungsi `applyClahe` yang di-commit
  (bukan port Python-nya) diekstrak & dijalankan lewat Node.js langsung
  terhadap data piksel yang sama, hasilnya dicek ulang lewat
  `tesseract` — bukan cuma percaya translasi manual dari Python ke TS.
- Resolusi cap dinaikkan dari 1600px ke 2200px (sisi terpanjang) — foto
  HP modern seringkali di atas 1600px meski sudah di-crop pas ke
  kartu, downscale berlebihan membuang detail teks kecil (RT/RW dll).
- Build sukses (`npm run build`, TypeScript bersih). Commit `0895966`,
  di-push. **Catatan jujur**: validasi di atas pakai foto hasil
  rekonstruksi dari screenshot (resolusi diturunkan dari foto asli),
  BUKAN pipeline live-device yang sesungguhnya — jadi ini kemungkinan
  besar perbaikan nyata (root cause & mekanisme kegagalannya
  terkonfirmasi persis, bukan tebakan), tapi **tetap perlu dites ulang
  di HP fisik** sebelum dianggap tuntas (lihat item "Belum" #1).

### 2026-08-04 (lanjutan 3) — Sederhanakan parsing OCR: fokus NIK/Nama/Alamat
- Atas permintaan user: fix crop (di atas) memang bikin foto ter-crop
  benar, tapi akurasi baca masih meleset (NIK/Nama/Alamat semua salah
  di foto fisik user). Diminta sederhanakan logika — fokus baca 3
  field itu saja, buang penanganan field lain.
- `lib/ocr.ts` ditulis ulang: `parseKtpFields()` (13-label generic
  parser, dengan `tempatTglLahir`/`jk`/`rtrw`/`keldesa`/`kecamatan`/
  `agama`/`kawin`/`kerja`/`wn`/`berlaku` yang tidak pernah dipakai UI)
  diganti `extractNamaAlamat()` yang cuma tahu 3 hal: label NAMA,
  label ALAMAT, dan "semua label lain" (dipakai murni sebagai penanda
  BATAS supaya Alamat berhenti nyerap baris, bukan diekstrak/disimpan
  nilainya). `KtpOcrResult.tempatTglLahir` dihapus dari tipe (dicek:
  tidak dipakai di `register/page.tsx`).
- Sekalian ditemukan & diperbaiki bug nyata di `extractNik()`: kalau
  ada 1 karakter nyasar nempel di digit run (misal ":" salah baca),
  kode lama langsung ambil 16 digit pertama secara membabi buta ->
  hasil NIK geser semua + digit terakhir hilang (contoh nyata dari
  user: OCR baca "1367205180284000", asli "3672051802840001" - persis
  pola pergeseran ini). Fix: `bestNikWindow()` - kalau digit run lebih
  dari 16, coba semua jendela 16-digit di dalamnya dan pilih yang
  cocok struktur NIK asli (`isPlausibleNik`), bukan asal ambil 16
  pertama.
- **Catatan jujur/belum selesai**: pendekatan berbasis label (cari
  teks "NIK"/"NAMA"/"ALAMAT" persis) tetap rentan kalau Tesseract
  salah baca label itu SENDIRI (mis. "Alamat" terbaca "Aiamat") -
  field itu akan gagal terbaca sama sekali. Ini bukan regresi baru,
  sudah jadi limitasi dari sejak awal (kode lama pun pakai regex label
  yang sama persis) - tapi kalau masih sering terjadi, kemungkinan
  perlu pendekatan lain (posisi baris relatif, bukan cuma cocok
  teks label) atau OCR engine/API lain yang lebih akurat dari
  Tesseract in-browser.
- Build sukses, commit `6c137c2`, di-push. **Belum dites di device
  fisik.**

### 2026-08-04 (lanjutan 2) — Root cause SEBENARNYA dari upload gagal total
- Fix EXIF sebelumnya (`369bb2d`) **tidak berpengaruh** — user tes ulang,
  hasil identik persis (semua field kosong, foto kepotong rata di kiri).
- Diminta foto KTP asli dari galeri user untuk debug offline. Dicek:
  **tidak ada EXIF orientation sama sekali** di foto itu (1085x692, no
  EXIF) — jadi dugaan EXIF di percobaan sebelumnya salah sasaran.
- Root cause sebenarnya: rasio foto galeri (1085/692 = 1.568) hampir
  identik dengan rasio container (3:2 = 1.5) — kartu KTP sudah memenuhi
  hampir seluruh frame, nyaris tanpa margin. `cropFallbackFile` (jalur
  upload) memakai `GUIDE_INSET` 6% yang sama dengan jalur kamera live —
  padahal inset itu dirancang khusus untuk kompensasi margin di sekitar
  kotak panduan kamera live (yang TIDAK ada saat user pilih foto dari
  galeri). Diterapkan ke foto galeri yang sudah rapat, inset 6% itu
  malah memotong ke dalam konten asli kartu di semua sisi — cocok
  persis dengan pola "NIK"→"IK", "Nama"→"ma", dst.
- Fix: `computeCoverCrop()` sekarang terima parameter `inset` eksplisit
  — jalur live-camera (`capture()`) tetap pakai `GUIDE_INSET` (6%),
  jalur upload (`cropFallbackFile`) pakai `inset=0` (cuma potong sesuai
  rasio 3:2, tanpa mengecilkan lagi ke dalam).
- Divalidasi offline pakai foto KTP asli user + pipeline OCR yang
  sama persis (grayscale/contrast/upscale dari `lib/ocr.ts` + eng +
  PSM SINGLE_BLOCK): semua label field & NIK sekarang terbaca (dari
  sebelumnya 0%), meski akurasi digit NIK & Alamat masih belum
  sempurna untuk foto asli ini (foto fisik dengan pantulan hologram
  lebih berat dari contoh sebelumnya — level ini sama dengan limitasi
  akurasi yang sudah dicatat, bukan regresi baru).
- Build sukses, commit `04ed0a5`, di-push. **Belum dites di device
  fisik oleh user.**

### 2026-08-04 (lanjutan) — Bug EXIF orientation di jalur upload-file (DUGAAN SALAH — lihat entri di atas)
- User tes ulang jalur upload-file (setelah fix `cropFallbackFile` di
  atas) — hasilnya malah **semua field kosong total** (bukan cuma
  Alamat), dengan foto preview yang kepotong rata di sisi kiri di
  setiap baris teks.
- Root cause: `createImageBitmap()` (dipakai baik di
  `cropFallbackFile` maupun `preprocessForOcr`) **tidak otomatis
  membaca tag orientasi EXIF** dari foto — beda dengan `<img>` yang
  otomatis rotate. Foto HP yang secara sensor tersimpan "miring" (tag
  EXIF bilang harus dirotate saat ditampilkan) jadi di-crop pakai
  lebar/tinggi mentah (belum dirotate) → area crop salah, cocok
  persis dengan gejala "kepotong rata di kiri" meski preview `<img>`
  terlihat normal (karena `<img>` respect EXIF, `createImageBitmap`
  tidak).
- Fix: `createImageBitmap(file, { imageOrientation: "from-image" })`
  di `cropFallbackFile`. (Panggilan `createImageBitmap` kedua di
  `preprocessForOcr`/`lib/ocr.ts` aman tanpa perlu diubah — inputnya
  di titik itu sudah berupa hasil gambar dari canvas, yang tidak
  pernah membawa metadata EXIF.)
- Build sukses, commit `369bb2d`, di-push. **Belum dites di device
  fisik.**

### 2026-08-04 — Investigasi & perbaikan lanjutan (mobile app + data)

- **`activity_logs.description`**: kolom `description TEXT` ditambahkan
  (migration `2026_08_04_add_activity_logs_description.sql`), ketiga
  implementasi `logActivity()` yang terduplikasi (`ContractorRepository`,
  `SettingController`, `SanctionController`) diperbaiki supaya benar-
  benar menyimpan parameter `$description` yang selama ini dibuang
  diam-diam. Diverifikasi langsung: insert test row, dikonfirmasi kolom
  terisi benar, `schema.sql` disinkronkan.
- **PVC PLANT tidak ada attendance**: dikonfirmasi **bukan bug**. Ada 2
  kontraktor terdaftar di `PVC PLANT`; logika matching plant di
  `AttendanceController::scan()` sudah benar (tidak ada mismatch nama
  seperti kasus EDC/VCM). Dites langsung lewat endpoint scan sungguhan
  (`id_card=26.0003`) → check-in berhasil. Kesimpulan: kiosk PVC memang
  belum pernah dipakai di lapangan. Data test dibersihkan setelahnya.
- **Data historis `work_hours`**: dicek seluruh 4 record attendance
  yang sudah check-out — **tidak ada satupun yang kena bug >24 jam**
  (tidak ada sesi sepanjang itu dalam data yang ada). Ditemukan 2
  record beda pembulatan ~0.01 jam (rounding artifact, bukan bug) —
  sudah dikoreksi jadi nilai yang benar dihitung ulang dari
  `check_in_time`/`check_out_time` mentah.
- **OCR jalur upload-file berbeda dari live camera**: root cause
  ditemukan di `CameraCapture.tsx` — jalur live-camera (`capture()`)
  memotong foto tepat sesuai kotak panduan sebelum diproses OCR, tapi
  jalur fallback `<input type="file" capture>` (aktif kalau
  `getUserMedia` gagal/ditolak) mengirim foto **mentah tanpa crop** ke
  OCR — cocok dengan gejala "cuma NIK yang kebaca" (NIK punya fallback
  pencarian di seluruh dokumen, field lain tidak). Diperbaiki: logika
  crop (`computeCoverCrop`) diekstrak jadi shared function, dipakai
  juga di jalur fallback (`cropFallbackFile`). Build sukses, **belum
  dites di device fisik**.
- **Task Scheduler otomatis untuk sync** (root cause "sinkronisasi
  masih gagal" dari laporan sebelumnya): didaftarkan ulang
  (`schtasks /create ... /sc minute /mo 10`), berhasil kali ini
  (percobaan sebelumnya gagal tanpa error tercatat — kemungkinan
  masalah state sesaat, bukan masalah permanen). Dites trigger manual
  → sukses (`Last Result: 0`), terverifikasi lewat `scripts/sync.log`.
  Sync sekarang otomatis jalan tiap 10 menit selama user `IT-Merak`
  login di komputer ini (mode "Interactive only").
- **Bug scanner QR freeze/error setelah scan (`p2k3/page.tsx`)**:
  `scanner.clear()` (sinkron, bisa throw) dibungkus try/catch;
  `scanner.stop()` tidak lagi dipanggil dobel (di callback sukses DAN
  di cleanup effect) — sekarang cuma cleanup effect yang jadi satu-
  satunya titik teardown, dengan guard anti-double-handling.
- **Tombol sync di halaman awal mobile app**: `lib/useSyncStatus.ts`
  (hook reusable, diekstrak dari logic yang tadinya inline & terduplikasi
  di `register/page.tsx`) + `components/SyncStatusBar.tsx`, dipasang di
  `app/page.tsx` (halaman utama). Auto-refresh saat halaman dibuka +
  tombol manual. Catatan: ini me-refresh tampilan dari data Supabase
  (hasil push terakhir server lokal), BUKAN memicu langsung script PHP
  di server lokal (LAN pabrik sengaja tidak reachable dari Vercel).
- Semua perubahan lolos `npm run build` (TypeScript bersih) dan `php -l`
  di seluruh file PHP yang disentuh.

### 2026-08-03 — ID Card baru otomatis saat registrasi diperpanjang
- `ContractorService::updateContractor()`: kalau kontraktor tadinya
  expired lalu `expiry_date` diupdate jadi hari-ini-atau-lebih (lewat
  tombol Perpanjang di menu Man Power Expired), sistem otomatis
  generate nomor **ID Card baru** + **QR code baru** (file QR lama
  dihapus). Edit biasa yang tidak menyentuh record expired tidak
  terpengaruh, ID Card lama tetap.
- `ContractorRepository::updateContractor()`: kolom `id_card`
  sebelumnya tidak pernah ikut ter-UPDATE sama sekali — sekarang
  pakai `COALESCE` supaya renewal bisa set nilai baru tanpa
  mengganggu edit biasa.
- Pesan konfirmasi "Registrasi berhasil diperpanjang. ID Card baru:
  X" muncul di dashboard setelah submit.
- Ditest langsung ke data asli: kontraktor `25.0034` (expired) →
  diperpanjang → jadi `26.0002`, dikonfirmasi lewat query DB langsung
  (id_card, qr_code, expiry_date semua berubah benar), file QR baru
  ada di disk, file QR lama tidak ada, 2 entry `activity_logs` di
  timestamp yang tepat.
- Commit `b61ea04`, sudah di-push.

### 2026-08-03 — Proteksi NIK/KTP duplikat di registrasi mobile
- Endpoint baru `app/api/register/check-ktp` (Next.js, service_role
  key) — cek NIK terhadap `synced_contractors` (sudah masuk MySQL
  lokal) DAN `staging_contractors` berstatus `pending` (submission
  lain yang belum ke-sync). Server-side karena kedua tabel itu
  di-RLS-lock dari SELECT oleh anon key.
- `register/page.tsx`: cek ini dijalankan sebelum cek blacklist —
  kalau duplikat, tampilkan layar khusus (nama yang cocok, sudah
  tersinkron atau masih pending) dan blokir lanjut ke foto/submit.
- Ditest langsung: NIK asli yang sudah di `synced_contractors` →
  terdeteksi `source:"synced"`; NIK dummy yang sengaja diinsert
  sebagai `staging_contractors` status `pending` → terdeteksi
  `source:"pending"`. Data test sudah dibersihkan.
- Commit `b61ea04`, sudah di-push.

### 2026-08-01 — Man power expired masih bisa aktif masuk plant (was #1)
- Root cause: `AttendanceController::scan()` cuma cek `status == 'Banned'`,
  tidak pernah cek `expiry_date` sama sekali — kontraktor expired tapi
  status masih `Active` bisa scan masuk/keluar bebas tanpa hambatan.
- Fix: tambah pengecekan `expiry_date < CURDATE()` di kedua titik
  CHECK-IN (first-ever & re-entry setelah check-out) — **CHECK-OUT
  tetap selalu diizinkan** supaya siapa pun yang sudah terlanjur di
  dalam saat kartunya expired tetap bisa check-out normal.
- Ditest langsung ke data asli (kontraktor id_card `25.0034`, expired
  2025-11-14): scan ditolak dengan pesan jelas, dikonfirmasi tidak ada
  row `attendances` baru yang tercipta.
- `plant_display.php`: kotak pesan kuning "ID CARD EXPIRED" terpisah
  dari kotak error generik.
- Dashboard: card baru "MAN POWER EXPIRED" (jumlah + link ke daftar
  yang sudah difilter).
- Daftar kontraktor: filter status "⚠ Expired" (virtual, dihitung dari
  `expiry_date`, bukan value asli di kolom `status`) + badge kuning
  inline di kolom tanggal expired.
- Alur perpanjangan: **tidak perlu UI baru** — form Edit kontraktor
  yang sudah ada memang sudah bisa mengubah `expiry_date` (dan KTP no.
  kalau perlu), jadi cukup dipakai langsung begitu admin lihat badge
  Expired-nya.
- Commit `c6547dc`, sudah di-push ke `origin/main`.
- **Update 2026-08-03**: menu terpisah `expired_contractors` dibuat
  (bukan cuma filter di list biasa) — lihat entri terkait di bawah.

### 2026-08-01 — Menu terpisah "Man Power Expired"
- `ContractorController::expired()` + route `page=expired_contractors`,
  reuse `ContractorService::getList()` dengan filter virtual `Expired`.
- Template baru `contractors/expired.php`: kolom "Sudah Lewat X hari",
  tombol "Perpanjang" menonjol, filter search/company/plant.
- Sidebar menu + card Dashboard "MAN POWER EXPIRED" diarahkan ke sini.
- Ditest live: render dengan data asli (274 hari overdue), filter
  search & plant terverifikasi jalan.
- Commit `725574e`, sudah di-push.

### 2026-08-01 — Setup PHPUnit untuk unit test `src/Support/`
- `composer.json` (script `test`), `phpunit.xml`, `tests/bootstrap.php`,
  `tests/Support/{IdCardNumberFormatter,Paginator,WorkHoursCalculator}Test.php`
  — sebelumnya untracked/menggantung, sudah dikonfirmasi aman (17 test,
  semua lolos) dan di-commit.
- `templates/plant_display.php`: glass-card background diringankan
  jadi `rgba(255,255,255,0.10)` sesuai catatan redesign 2026-07-30.
- Commit `bf8d297`, sudah di-push.

### 2026-08-01 — OCR KTP kacau total: root cause & fix (bahasa Tesseract)
- User lapor hasil OCR kacau total di device asli setelah commit
  `5dff99c` (contoh: Nama "MAMAN" terbaca "Sa ES", Alamat jadi
  "z BOKOaNGg3", NIK meleset banyak digit) meski parsing/crop terlihat
  benar dari screenshot.
- Diagnosis: crop foto & `parseKtpFields` (label→field matching) sudah
  benar — raw OCR text dari panel debug user menunjukkan semua label
  field (NIK, Nama, Alamat, dst.) berhasil terdeteksi, tapi isi/value
  di tiap field-nya acak.
- Root cause dikonfirmasi via uji offline: crop foto KTP dari
  screenshot user diuji pakai `tesseract` CLI dengan berbagai
  kombinasi lang/PSM. Hasil `eng` (upscale 4x + PSM SINGLE_BLOCK/
  SINGLE_COLUMN) membaca hampir sempurna ("Nama MAMAN", "Alamat PERUM
  GRAND SUTERA CILEGON BLOK C3.NO.23", dst.) — mengonfirmasi dugaan
  lama bahwa model bahasa `ind` Tesseract memang jauh lebih buruk utk
  font KTP ini dibanding `eng`.
- Fix (`lib/ocr.ts`): `createWorker("ind", ...)` → `createWorker("eng",
  ...)`, tambah `worker.setParameters({ tessedit_pageseg_mode:
  PSM.SINGLE_BLOCK })`. Build lokal sukses, commit `63da5e0`, di-push
  ke `origin/main`.
- Follow-up dari user setelah dites: Nama & NIK sudah benar via kamera
  HP, tapi Alamat & jalur upload-file masih bermasalah (lihat item
  "Belum" #1).

### 2026-08-01 — Push commit OCR KTP ke origin/main
- Commit `5dff99c` (perbaikan OCR KTP) dicek: local `main` ahead 1
  commit dari `origin/main`, tidak ada divergence (`git fetch` +
  `git log HEAD..origin/main` kosong, jadi tidak ada sesi/agent lain
  yang push duluan ke branch ini).
- `git push origin main` berhasil (`4ae8fce..5dff99c main -> main`).
- File lain yang masih modified/untracked di working tree
  (`.gitignore`, `composer.json`, `templates/plant_display.php`,
  `phpunit.xml`, `tests/`) sengaja tidak ikut di-commit/push — masih
  menunggu klarifikasi pemiliknya.

### 2026-07-30 — Cetak kartu ID (`bulk_id_card.php`)
- Perbaiki background section tengah/default hilang (file `1.png`,
  `2.png`, `3.png` ternyata nyasar ke folder `photos`, dipindah ke
  `public/uploads/background/`).
- Background section tengah kini ikut berubah per-plant (`ca-2.png`
  dst), sebelumnya selalu pakai `2.png` generik.
- Tambah cache-busting (`?v=filemtime`) di semua URL background.
- Ukuran/posisi QR lembar 2 disesuaikan bertahap (60% ukuran, posisi
  diturunkan), nomor ID ditambahkan di bawah QR (hitam, 15px).
- Bold untuk NIK, nama, nama plant.
- Teks nama plant dibuat melengkung (SVG `textPath`) mengikuti
  lingkaran foto.

### 2026-07-30 — Bug "Man Hours Without LTI" & Dashboard
- Dikonfirmasi ke user: data global (bukan per-plant) itu **disengaja**,
  bukan bug.
- Fix: `plant-display` tidak live-update (`getUpdate()` sekarang ikut
  hitung ulang & kirim `plant_working_hours`; JS di-update juga).
- Fix: perhitungan `work_hours` salah untuk sesi check-in/out >24 jam
  (`DateInterval::$h` buang hari penuh) → diganti selisih timestamp.
- Fix: notice PHP saat `SUM()` kosong (NULL + number) → digenerik
  jadi helper `calculatePlantWorkingHours()` yang aman, dipakai di
  `PlantDisplayController` & `DashboardController`.
- **Bug besar ditemukan & diperbaiki**: kontraktor EDC/VCM tidak
  pernah bisa check-in di plant-display gabungan `EDC/VCM PLANT`
  karena mismatch nama plant (`EDC PLANT`/`VCM PLANT` vs
  `EDC/VCM PLANT`). Fix di `AttendanceController::scan()`.

### 2026-07-30 — Foto petugas on duty & path bug `public/`
- Root cause: vhost document root sudah = folder `public/`, jadi
  penambahan `public/` di URL menghasilkan 404. Diperbaiki di:
  `plant_display.php`, `settings/system.php` (preview), `dashboard.php`
  (foto banned), `sanctions/list.php` (foto banned).

### 2026-07-30 — Redesign plant-display (video background)
- Backup file lama: `templates/plant_display.php.bak_20260730_135009`.
- Video safety jadi background full-screen, header selalu tampil,
  kartu info melayang transparan (kiri: Man Hours + Qty Kontraktor;
  kanan: Petugas On Duty, Info, Preview, Scanner).
- Petugas On Duty dipindah ke kolom kiri (di bawah Qty Kontraktor).
- Scanner kamera dikunci di pojok kanan-bawah kolom kanan (tetap
  selalu aktif, tidak jadi popup).
- Popup besar di tengah saat ada scan (foto/nama/MASUK-PULANG),
  video tetap jalan di belakangnya (tidak lagi diganti/berhenti).
- Running text: ticker custom (bukan `<marquee>`) — loop mulus tanpa
  jeda (3 salinan berdampingan), masuk dari sisi kanan di awal lalu
  lanjut loop, ukuran teks & warna kartu disesuaikan (lihat entri di
  bawah).
- Ukuran teks ticker: dinaikkan lalu diperkecil lagi jadi 2.4rem;
  warna kotak menu diringankan jadi glass transparan
  (`rgba(255,255,255,0.10)`) dengan aksen warna semantik per kartu
  (hijau=safety, oranye=activity, merah=alert, teal=live/scanner).

### 2026-07-30 — OCR KTP (`mobile-app`, Next.js/TypeScript)
- Commit `5dff99c`: crop foto KTP/wajah sesuai kotak panduan kamera
  (sebelumnya kotak panduan cuma dekorasi, foto asli selalu
  full-frame termasuk background) — kemungkinan akar masalah utama
  OCR sering gagal.
- Progress bar visual saat OCR jalan.
- Panel status per-field (NIK/Nama/Alamat: Terbaca / Perlu dicek /
  Tidak terbaca), update live saat user koreksi manual.
- Panel debug "Lihat teks mentah hasil OCR" untuk diagnosis lanjutan
  di device asli.
- (Dari sesi/commit sebelumnya, sudah ada sebelum sesi ini: toleransi
  spasi & salah-baca huruf↔angka pada NIK, validasi struktur NIK,
  parser label multi-baris untuk Nama/Alamat, live camera capture.)
