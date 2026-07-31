-- Schema for idcard_system
-- Regenerated 2026-07-31 from the live database to fix drift against the
-- previous version of this file (missing columns/tables, stale FK rules).
-- See database/migrations/ for the tracked history of changes going
-- forward - please add a new dated .sql file there for every future
-- schema change instead of editing the live DB ad-hoc.

CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin Plant','User') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `contractor_companies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `contractors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_card` varchar(255) NOT NULL,
  `ktp_no` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `company_id` int unsigned NOT NULL,
  `plant_location` enum('CA PLANT','EDC PLANT','VCM PLANT','PVC PLANT','MEI PLANT','HPI PLANT') NOT NULL,
  `registration_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Active','Banned','Non-Active') NOT NULL DEFAULT 'Active',
  `photo` varchar(255) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  -- Mobile registration app (Supabase) sync fields: `source` marks
  -- whether the row originated locally or from the mobile app,
  -- `mobile_sync_id` is the staging_contractors id it was synced from
  -- (unique - re-running the sync must never create duplicates).
  `source` enum('local','mobile') NOT NULL DEFAULT 'local',
  `mobile_sync_id` varchar(64) DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contractors_id_card_unique` (`id_card`),
  UNIQUE KEY `contractors_mobile_sync_id_unique` (`mobile_sync_id`),
  KEY `contractors_company_id_foreign` (`company_id`),
  -- RESTRICT (was CASCADE): a company with contractors still on it can no
  -- longer be deleted by accident - see migrations/2026_07_31_fix_data_integrity.sql
  CONSTRAINT `contractors_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `contractor_companies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `violations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `sanctions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` int unsigned NOT NULL,
  `violation_id` int unsigned DEFAULT NULL,
  `sanction_type` enum('SP1','SP2','BANNED') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_permanent` tinyint(1) NOT NULL DEFAULT '0',
  -- Set when an admin manually lifts a sanction early (see `revoked_by` /
  -- `revoke_reason`). The `active_bans` view below excludes any row with
  -- revoked_at set, regardless of end_date/is_permanent.
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int unsigned DEFAULT NULL,
  `revoke_reason` text,
  -- Mobile (P2K3) app sync fields, same convention as contractors above.
  `source` enum('local','mobile') NOT NULL DEFAULT 'local',
  `mobile_sync_id` varchar(64) DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sanctions_mobile_sync_id_unique` (`mobile_sync_id`),
  KEY `sanctions_contractor_id_foreign` (`contractor_id`),
  KEY `sanctions_violation_id_foreign` (`violation_id`),
  KEY `sanctions_revoked_by_foreign` (`revoked_by`),
  -- CASCADE: sanction history is meaningless without the contractor it
  -- belongs to, and this matches attendances' existing convention.
  CONSTRAINT `sanctions_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  -- SET NULL: preserve the sanction record even if the admin who revoked
  -- it later has their user account deleted.
  CONSTRAINT `sanctions_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  -- RESTRICT (was missing entirely / CASCADE): deleting a violation TYPE
  -- must never silently wipe out sanction/ban records that reference it.
  CONSTRAINT `sanctions_violation_id_foreign` FOREIGN KEY (`violation_id`) REFERENCES `violations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Convenience view used by getActiveBansSnapshot() to push the current
-- ban list up to Supabase for the mobile apps: every BANNED sanction that
-- hasn't been revoked and hasn't expired (or is permanent).
CREATE VIEW `active_bans` AS
    SELECT
        s.id, s.contractor_id, s.violation_id, s.sanction_type, s.start_date,
        s.end_date, s.is_permanent, s.revoked_at, s.revoked_by, s.revoke_reason,
        s.source, s.mobile_sync_id, s.synced_at, s.reason, s.created_at, s.updated_at,
        c.ktp_no, c.name AS contractor_name, c.company_id
    FROM sanctions s
    JOIN contractors c ON c.id = s.contractor_id
    WHERE s.sanction_type = 'BANNED'
      AND s.revoked_at IS NULL
      AND (s.is_permanent = 1 OR s.end_date IS NULL OR s.end_date >= CURDATE());

CREATE TABLE `attendances` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` int unsigned NOT NULL,
  `plant_location` varchar(255) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `work_hours` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendances_contractor_id_foreign` (`contractor_id`),
  CONSTRAINT `attendances_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `system_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `activity_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(255) DEFAULT NULL,
  `record_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  -- SET NULL (was CASCADE, user_id now nullable): deleting a user account
  -- must not erase the audit trail of actions they performed.
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Previously undocumented table (existed live, was missing from this
-- file entirely). Not referenced anywhere in the current application
-- code - appears to be an orphaned/unused table from earlier
-- development. Kept here for completeness; safe to drop after
-- confirming with the team it's no longer needed.
CREATE TABLE `id_card_layout_order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_order` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Previously undocumented table (existed live, was missing from this
-- file entirely). Per-plant kiosk settings (currently unused by the
-- application code, which reads plant display config from
-- `system_settings` instead - kept here for completeness/future use).
CREATE TABLE `plant_display_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plant_name` varchar(255) NOT NULL,
  `on_duty_photo_url` varchar(255) DEFAULT NULL,
  `on_duty_name` varchar(255) DEFAULT NULL,
  `on_duty_position` varchar(255) DEFAULT NULL,
  `on_duty_plant` varchar(255) DEFAULT NULL,
  `safety_video_url` varchar(255) DEFAULT NULL,
  `plant_information` text,
  `running_text` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plant_display_settings_plant_name_unique` (`plant_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
