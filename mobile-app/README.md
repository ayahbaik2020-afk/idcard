# IDCard Mobile — Registrasi Man Power & Pengawasan P2K3

Companion app dari sistem `idcard` (lihat `../MOBILE_APP_PLAN.md` untuk
blueprint lengkap), dijalankan sebagai web app mobile-friendly di Vercel.

## Status implementasi

- [x] Scaffold Next.js 16 (App Router) + TypeScript + Tailwind
- [x] `/register` — pilih PT, scan KTP (OCR client-side via Tesseract.js),
      cek blacklist (`synced_active_bans`), foto wajah, submit ke Supabase
- [x] `/p2k3` — scan QR (html5-qrcode), lihat profil + histori sanksi,
      input sanksi baru
- [x] Build lokal (`npm run build`) sukses
- [ ] Supabase project asli belum dibuat — `.env.local` masih placeholder
- [ ] `supabase/schema.sql` belum dijalankan di Supabase manapun
- [ ] Script sync PHP di sisi `idcard` (lokal) belum dibuat
- [ ] Belum di-deploy ke Vercel
- [ ] Belum diuji kamera/OCR/QR di HP fisik

## Catatan lingkungan (penting, sudah ditemukan saat build pertama)

1. **`NODE_ENV=production` ter-set global di komputer ini** menyebabkan npm
   otomatis melewati `devDependencies` (termasuk `@tailwindcss/postcss`,
   `typescript`). Kalau `npm install` terasa "kurang lengkap" atau build
   gagal karena modul devDependency tidak ketemu, jalankan:
   ```powershell
   $env:NODE_ENV=""
   npm install --include=dev
   ```
2. **`fonts.googleapis.com` diblokir** di jaringan ini (sama seperti dicatat
   di project `p2k3-keselamatan`). Karena itu `app/layout.tsx` sengaja tidak
   memakai `next/font/google` — pakai system font stack (`font-sans` dari
   Tailwind) supaya build tidak butuh akses ke domain tsb.

## Setup Supabase (belum dilakukan — langkah selanjutnya)

1. Buat project baru di https://supabase.com
2. Buka **SQL Editor** → jalankan isi `supabase/schema.sql`
3. Buka **Project Settings → API** → salin `Project URL`, `anon public key`,
   `service_role key`
4. Copy `.env.local.example` jadi `.env.local`, isi dengan nilai asli di
   atas, generate `SYNC_API_KEY` bebas (mis. `openssl rand -hex 32`)

## Development

```bash
npm run dev
```

Buka dari HP di jaringan yang sama lewat `http://<IP-komputer>:3000` untuk
mengetes kamera (kamera HP butuh HTTPS atau `localhost` — untuk tes dari HP
di jaringan lokal, pakai tunnel HTTPS seperti `ngrok http 3000` sementara
development, atau langsung uji di deployment Vercel yang otomatis HTTPS).

## Deploy ke Vercel

```bash
npx vercel
```

Isi environment variables yang sama seperti `.env.local` di Vercel project
settings (Settings → Environment Variables) sebelum atau setelah deploy
pertama.

## Belum dikerjakan / langkah selanjutnya

Lihat `../MOBILE_APP_PLAN.md` bagian 6 untuk desain script sync PHP
(`idcard/scripts/sync_from_cloud.php`) yang menjembatani Supabase ↔ MySQL
lokal — ini bagian besar berikutnya yang belum dibuat.
