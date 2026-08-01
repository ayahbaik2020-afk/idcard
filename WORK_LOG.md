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

1. **[Safety/akses] Man power expired masih bisa aktif masuk plant** —
   prioritas tertinggi karena ini celah keamanan fisik (bukan cuma
   masalah data): tidak ada menu/filter yang memisahkan kontraktor
   expired dari yang aktif, tidak ada proteksi yang mencegah mereka
   scan masuk ke plant, dan tidak ada alur perpanjangan (renewal)
   dengan update NIK/KTP terbaru.
2. **[Integritas data] Tidak ada proteksi KTP duplikat** saat
   registrasi — NIK yang sudah terdaftar bisa didaftarkan ulang,
   berisiko data ganda yang makin menumpuk selama belum diperbaiki.
   Perlu validasi cek NIK existing sebelum submit.
3. **[Akurasi/UX] OCR KTP — hasil tes terbaru user** (setelah fix
   `eng`+PSM): via kamera HP, Nama & NIK sudah terbaca benar tapi
   **Alamat masih belum**. Via upload file dari browser (bukan live
   camera), **cuma NIK yang berhasil kebaca** (Nama/Alamat gagal) —
   kemungkinan jalur upload file tidak lewat preprocessing/crop yang
   sama dengan jalur live camera, perlu ditelusuri. Prioritas lebih
   rendah dari #1/#2 karena fungsi inti (NIK) sudah jalan di kedua
   jalur, ini penyempurnaan.
4. **Deploy Vercel (`idcard-brown-delta`) sempat 2 commit tertinggal**
   (`4ae8fce`, `5dff99c` tidak auto-deploy, stuck di commit `197d705`
   selama ~2 jam) — root cause pastinya belum dikonfirmasi (dugaan:
   push oleh akun GitHub `IT-Merak` yang mungkin bukan member tim
   Vercel project ini, sehingga auto-deploy di-skip). Belakangan sudah
   ke-deploy juga (fitur `5dff99c` sudah muncul saat user tes), tapi
   penyebabnya belum diklarifikasi ke pemilik project — perlu dicek di
   Vercel dashboard (tab Deployments untuk status "Skipped"/"Failed",
   dan Settings → Git untuk daftar member tim) supaya commit-commit
   selanjutnya tidak mengalami hal sama.
5. **File tidak terkait masih belum di-commit** di working tree:
   `.gitignore`, `composer.json`, `phpunit.xml`, `tests/` — kelihatannya
   pekerjaan setup testing PHP dari sesi lain, sengaja tidak disentuh.
   Perlu diklarifikasi ke pemiliknya sebelum di-commit/dibuang.
6. **PVC PLANT**: belum ada satupun record attendance tercatat di
   database (beda dengan EDC/VCM yang sudah dikonfirmasi ada bug
   mismatch nama plant). Belum jelas apakah PVC juga ada bug serupa
   atau memang belum pernah dipakai — perlu dicoba scan langsung di
   plant-display PVC untuk konfirmasi.
7. **Data historis `work_hours`** yang mungkin sudah kadung salah
   dihitung (dari bug sesi >24 jam yang sudah diperbaiki) belum
   dikoreksi — perbaikan yang sudah jalan cuma berlaku untuk
   perhitungan baru ke depan.
8. **Redesign plant-display**: sudah dites lewat code review + PHP
   lint, **belum dikonfirmasi tampilannya langsung di layar TV/tablet
   plant** (terutama kontras warna kartu vs video, ukuran font di
   layar besar, dan posisi scanner pojok kanan-bawah).

## ✅ Selesai

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
  "Belum" #3).

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
