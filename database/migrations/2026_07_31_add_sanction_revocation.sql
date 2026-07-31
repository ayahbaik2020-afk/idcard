-- Menambahkan kemampuan "cabut sanksi" (revoke) yang belum ada di schema.
-- Tanpa ini, sanksi BANNED permanen tidak bisa dibedakan antara:
--   a) masih aktif/berlaku
--   b) sudah dicabut manajemen tapi datanya tetap disimpan untuk histori
--
-- Jalankan file ini terhadap database idcard_system (mysql -u root idcard_system < 2026_07_31_add_sanction_revocation.sql)

ALTER TABLE `sanctions`
  ADD COLUMN `revoked_at` DATETIME DEFAULT NULL AFTER `is_permanent`,
  ADD COLUMN `revoked_by` INT UNSIGNED DEFAULT NULL AFTER `revoked_at`,
  ADD COLUMN `revoke_reason` TEXT DEFAULT NULL AFTER `revoked_by`,
  ADD CONSTRAINT `sanctions_revoked_by_foreign`
    FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Kolom untuk melacak asal data: input dari aplikasi lokal (desktop) atau
-- dari aplikasi mobile (Vercel) via sinkronisasi. Dipakai juga sebagai
-- penanda "sudah di-pull" oleh proses sync lokal.
ALTER TABLE `contractors`
  ADD COLUMN `source` ENUM('local','mobile') NOT NULL DEFAULT 'local' AFTER `qr_code`,
  ADD COLUMN `mobile_sync_id` VARCHAR(64) DEFAULT NULL AFTER `source`,
  ADD COLUMN `synced_at` DATETIME DEFAULT NULL AFTER `mobile_sync_id`,
  ADD UNIQUE KEY `contractors_mobile_sync_id_unique` (`mobile_sync_id`);

ALTER TABLE `sanctions`
  ADD COLUMN `source` ENUM('local','mobile') NOT NULL DEFAULT 'local' AFTER `revoke_reason`,
  ADD COLUMN `mobile_sync_id` VARCHAR(64) DEFAULT NULL AFTER `source`,
  ADD COLUMN `synced_at` DATETIME DEFAULT NULL AFTER `mobile_sync_id`,
  ADD UNIQUE KEY `sanctions_mobile_sync_id_unique` (`mobile_sync_id`);

-- View pembantu: status blacklist "yang berlaku" (belum dicabut, dan
-- belum kedaluwarsa jika bukan permanen). Dipakai oleh filter blacklist
-- di aplikasi mobile maupun lokal supaya logikanya konsisten di satu tempat.
CREATE OR REPLACE VIEW `active_bans` AS
SELECT s.*, c.ktp_no, c.name AS contractor_name, c.company_id
FROM sanctions s
JOIN contractors c ON c.id = s.contractor_id
WHERE s.sanction_type = 'BANNED'
  AND s.revoked_at IS NULL
  AND (s.is_permanent = 1 OR s.end_date IS NULL OR s.end_date >= CURDATE());
