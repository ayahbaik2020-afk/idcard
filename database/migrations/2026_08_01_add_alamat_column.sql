-- Menambahkan kolom alamat (dari hasil OCR KTP di app mobile registrasi)
-- ke tabel contractors, supaya data alamat yang sudah di-scan tidak hilang
-- saat sinkron dari staging_contractors ke MySQL lokal.

ALTER TABLE contractors ADD COLUMN alamat TEXT NULL AFTER ktp_no;
