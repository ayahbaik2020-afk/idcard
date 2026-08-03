-- Migration: add missing activity_logs.description column
-- Date: 2026-08-04
--
-- Found while verifying the ID-Card-renewal feature (2026-08-03, see
-- WORK_LOG.md): every call site does
--   logActivity($action, $table, $record_id, $description)
-- and passes a real, useful $description string ("Deleted company: PT
-- X", "Created contractor: John Doe", etc.) - but all 3 duplicated
-- logActivity() implementations (ContractorRepository, SettingController,
-- SanctionController) only ever INSERT user_id/action/table_name/
-- record_id. $description has been silently discarded since the table
-- was first created, making the audit log far less useful than it looks
-- (you can see *that* something happened to record #12 in `contractors`,
-- but not *what* happened).

ALTER TABLE activity_logs
    ADD COLUMN `description` TEXT NULL AFTER `record_id`;
