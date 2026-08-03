-- Jalankan di Supabase SQL Editor untuk memutakhirkan tabel yang sudah ada.
-- Menambahkan expiry_date ke synced_contractors agar app mobile bisa
-- membedakan NIK yang masih aktif vs sudah expired (untuk tawaran
-- Re Aktivasi ID saat registrasi ulang).
alter table synced_contractors add column if not exists expiry_date date;
