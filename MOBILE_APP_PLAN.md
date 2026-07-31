# Rencana: Aplikasi Mobile Registrasi Man Power + Pengawasan P2K3 (Vercel)

Dokumen ini adalah blueprint kerja untuk aplikasi companion dari sistem
`idcard` yang sudah ada, dijalankan sebagai aplikasi web mobile-friendly
di Vercel. Ditulis setelah meninjau langsung struktur project `idcard`
(PHP + MySQL, di D:\laragon\www\idcard).

## 1. Kondisi Existing System (idcard - lokal)

- PHP native (tanpa framework) + MySQL, jalan di Laragon (192.168.20.17:8081), LAN only.
- Tabel utama: `users`, `contractor_companies`, `contractors`, `violations`,
  `sanctions`, `attendances`, `system_settings`, `activity_logs`.
- `contractors.qr_code` sudah ada (library `endroid/qr-code` terpasang) → QR
  per kontraktor sudah bisa dibuat, tinggal dipakai untuk scan di app P2K3.
- `sanctions.sanction_type` = SP1/SP2/BANNED, `is_permanent`, `end_date` —
  **belum ada status "dicabut"**. Ditambahkan lewat migrasi
  `database/migrations/2026_07_31_add_sanction_revocation.sql` (kolom
  `revoked_at`, `revoked_by`, `revoke_reason`, + view `active_bans`).
- Belum ada REST/JSON API sama sekali — semua controller render HTML.
- Belum ada OCR KTP, belum ada scan QR dari HP, belum ada mekanisme sync.

## 2. Keputusan Arsitektur (sudah dikonfirmasi user)

| Area | Keputusan |
|---|---|
| OCR KTP | Client-side (Tesseract.js di browser HP), hasil OCR **wajib** direview/diedit user sebelum submit — jangan auto-trust hasil OCR untuk NIK. |
| Database/storage cloud | **Supabase** (Postgres + Storage untuk foto KTP & foto orang) |
| Sinkronisasi ke lokal | Kombinasi: terjadwal otomatis (Windows Task Scheduler, default tiap 10 menit) **+** tombol "Sync Now" manual di aplikasi lokal |

### Kenapa bukan real-time langsung?
Server lokal ada di jaringan LAN pabrik (192.168.20.17), tidak reachable dari
internet publik tempat Vercel berjalan. Maka arah komunikasi yang aman:
**Vercel/Supabase = tempat singgah (staging)** → **aplikasi lokal yang aktif
menjemput (pull) data**, bukan sebaliknya. Ini juga berarti tidak perlu
membuka port/firewall di jaringan pabrik.

## 3. Alur Data

```
[HP kontraktor/admin]                [HP tim P2K3]
   |  scan KTP (OCR) + foto             |  scan QR
   |  pilih/buat PT                     |  lihat histori + input sanksi
   v                                    v
        Next.js App (Vercel) --- REST API (Vercel Functions)
                     |
                     v
        Supabase (Postgres + Storage)
           tabel: staging_contractors, staging_sanctions
                     |
                     |  <-- PULL berkala / tombol Sync Now
                     v
   PHP script sync (jalan di server lokal, via cron/Task Scheduler)
                     |
                     v
        MySQL lokal (idcard_system) -- source of truth akhir
                     |
                     v
        Aplikasi idcard existing (cetak ID card, dashboard, dll)
```

Blacklist check di HP tetap bisa near-real-time karena app mobile membaca
langsung dari Supabase (bukan dari MySQL lokal) — jadi begitu tim P2K3 input
sanksi baru dari HP, kontraktor lain langsung ke-filter di semua HP lain,
tanpa menunggu sync ke lokal. Sync ke MySQL lokal berjalan di belakang layar
untuk kebutuhan cetak ID card & laporan resmi di kantor.

## 4. Struktur Data Tambahan di Supabase

```sql
-- staging_contractors: hasil input dari HP, menunggu di-pull aplikasi lokal
create table staging_contractors (
  id uuid primary key default gen_random_uuid(),
  ktp_no text not null,
  name text not null,
  company_name text not null,     -- PT baru atau existing (dicocokkan saat pull)
  plant_location text not null,
  ktp_photo_url text,             -- Supabase Storage
  face_photo_url text,            -- Supabase Storage
  ocr_raw jsonb,                  -- hasil mentah OCR untuk audit
  submitted_by text,
  status text default 'pending',  -- pending | synced | rejected
  synced_at timestamptz,
  created_at timestamptz default now()
);

-- staging_sanctions: input sanksi baru dari tim P2K3 via HP
create table staging_sanctions (
  id uuid primary key default gen_random_uuid(),
  ktp_no text not null,           -- dicocokkan ke contractors.ktp_no saat pull
  sanction_type text not null,    -- SP1 | SP2 | BANNED
  is_permanent boolean default false,
  end_date date,
  reason text,
  input_by text,
  status text default 'pending',
  synced_at timestamptz,
  created_at timestamptz default now()
);

-- Data hasil pull dari MySQL lokal, untuk keperluan filter blacklist di HP
-- (di-refresh oleh script sync setiap kali jalan)
create table synced_active_bans (
  ktp_no text primary key,
  contractor_name text,
  sanction_type text,
  is_permanent boolean,
  end_date date,
  reason text,
  updated_at timestamptz default now()
);
```

## 5. Fitur Aplikasi Mobile (Next.js di Vercel)

### A. Registrasi Man Power
1. Pilih PT (dropdown dari daftar company yang sudah sync, atau buat PT baru).
2. Buka kamera → foto KTP → OCR (Tesseract.js) mengisi otomatis: NIK, Nama,
   Alamat, TTL → **user wajib review/edit sebelum lanjut**.
3. **Cek blacklist** terhadap NIK (query ke `synced_active_bans`): jika
   ditemukan status BANNED yang belum dicabut → tampilkan halaman "detail
   sanksi" (jenis sanksi, alasan, tanggal mulai, permanen/tidak) dan blokir
   lanjut ke step foto, kecuali role Admin yang override dengan alasan.
4. Jika lolos filter → lanjut foto wajah/orang.
5. Submit → masuk ke `staging_contractors` (status pending) + upload 2 foto
   ke Supabase Storage.

### B. Pengawasan P2K3
1. Scan QR (library `html5-qrcode` atau `@zxing/browser`) dari kartu ID
   kontraktor.
2. Tampilkan profil + histori kehadiran + histori sanksi (gabungan dari
   `synced_active_bans` + hasil pull sanksi lama dari lokal).
3. Form input sanksi baru → masuk `staging_sanctions`.
4. (opsional lanjutan) Approve/tolak, cabut sanksi, laporan kepatuhan APD, dst.

## 6. Sync Engine (jalan di server lokal)

Script PHP baru `scripts/sync_from_cloud.php` di project `idcard`:
- Dipanggil oleh Windows Task Scheduler tiap 10 menit, dan oleh tombol
  "Sync Now" di dashboard (lewat AJAX ke endpoint lokal baru).
- Alur: GET ke Vercel API `/api/sync/pull` (auth pakai API key di header)
  → dapat daftar `staging_contractors`/`staging_sanctions` berstatus pending
  → insert ke MySQL (`contractors`, `sanctions`, buat `contractor_companies`
  baru bila perlu) → tandai `source='mobile'`, isi `mobile_sync_id`
  → download foto dari Supabase Storage URL ke folder lokal `public/uploads`
  → POST balik ke Vercel API `/api/sync/ack` untuk update status jadi
  'synced' di Supabase.
- Arah sebaliknya (lokal → cloud): setelah pull di atas, script yang sama
  juga mengirim snapshot `active_bans` (dari view SQL yang sudah dibuat)
  ke Supabase, replace isi `synced_active_bans`, supaya filter blacklist di
  HP selalu up to date dengan keputusan resmi dari kantor.

## 7. Struktur Project Next.js (rencana folder)

```
idcard/mobile-app/
  app/
    register/           halaman alur registrasi (pilih PT -> OCR -> blacklist check -> foto)
    p2k3/                halaman scan QR + histori + input sanksi
    api/sync/pull/       endpoint yang dipanggil script PHP lokal
    api/sync/ack/
    api/blacklist/check/
  lib/supabase.ts
  lib/ocr.ts             wrapper Tesseract.js
  components/
  .env.local              SUPABASE_URL, SUPABASE_SERVICE_KEY, SYNC_API_KEY
```

## 8. Langkah Selanjutnya

Scaffolding Next.js (install dependency, setup Supabase client, build tiap
halaman, testing kamera/OCR/QR di HP asli, lalu `vercel deploy`) adalah kerja
iteratif yang butuh eksekusi `npm install` / `git` / `vercel` berkali-kali
dan uji-coba langsung — ini paling efektif dikerjakan di **Claude Code**
(desktop app), bukan lewat chat satu-arah begini, karena di sana saya bisa
menjalankan perintah, melihat error build, dan langsung memperbaikinya.

Rencana di dokumen ini + migrasi SQL sudah siap dipakai sebagai starting
point begitu sesi Claude Code dimulai di folder `idcard/mobile-app`.
