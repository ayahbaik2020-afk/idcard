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

1. **[Akurasi OCR] Alamat masih belum akurat via kamera live** — Nama &
   NIK sudah benar, tapi Alamat (field multi-baris, paling rentan salah
   baca) masih sering perlu koreksi manual. Kemungkinan batas akurasi
   Tesseract untuk teks kecil/padat di font KTP, bukan bug logic —
   mitigasi yang sudah ada (user wajib review/edit sebelum submit,
   status per-field, panel raw text) tetap jalan sebagai jaring
   pengaman. **Perlu foto/device asli dari lapangan untuk tuning lebih
   lanjut** — tidak bisa diprogress lebih jauh dari sesi remote.
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
