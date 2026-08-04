-- Memperluas view `active_bans` agar tidak hanya berisi sanksi BANNED.
-- SP1 dan SP2 juga men-set status kontraktor menjadi 'Banned' (lihat
-- ContractorService::applySanctionToContractor), jadi kalau ada sanksi
-- SP1/SP2 yang masih aktif, registrasi dari aplikasi mobile juga harus
-- memunculkan peringatan blacklist (bukan hanya BANNED).
--
-- Cara jalankan:
--   mysql -u root idcard_system < 2026_08_04_broaden_active_bans_to_sp1_sp2.sql

CREATE OR REPLACE VIEW `active_bans` AS
SELECT s.*, c.ktp_no, c.name AS contractor_name, c.company_id
FROM sanctions s
JOIN contractors c ON c.id = s.contractor_id
WHERE s.sanction_type IN ('BANNED', 'SP1', 'SP2')
  AND s.revoked_at IS NULL
  AND (s.is_permanent = 1 OR s.end_date IS NULL OR s.end_date >= CURDATE());
