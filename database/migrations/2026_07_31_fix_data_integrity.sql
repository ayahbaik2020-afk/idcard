-- Migration: Fix data integrity issues
-- Date: 2026-07-31
--
-- Context: schema.sql had drifted from the live database (no tracked
-- migration history existed before this file). While reconciling the two,
-- we found:
--   1. `sanctions` had NO foreign key constraints at all (only plain
--      indexes were left) - this let a sanction row survive its
--      contractor being deleted. One such orphan row (sanctions.id = 6,
--      contractor_id = 43, already-expired test data) was found and is
--      removed by this migration before the constraint is re-added.
--   2. `contractors.company_id` was ON DELETE CASCADE, meaning deleting a
--      company silently deleted every contractor under it with no
--      warning. Changed to RESTRICT.
--   3. `sanctions.violation_id` (if a constraint existed) would have been
--      ON DELETE CASCADE, meaning deleting a violation TYPE (a reference/
--      lookup value) would silently wipe every sanction record that used
--      it - including active BANNED records. Changed to RESTRICT so the
--      violation type must be reassigned/kept as long as history
--      references it.
--   4. `sanctions.contractor_id` is set to CASCADE to stay consistent with
--      the existing `attendances.contractor_id` behavior: deleting a
--      contractor already wipes their attendance history by design, so
--      their sanction history is treated the same way.
--
-- Run once: mysql -u root idcard_system < 2026_07_31_fix_data_integrity.sql
START TRANSACTION;

-- 1. Remove the known orphaned sanction row so the new FK can be added.
--    (Verified: sanction_type=BANNED, end_date=2025-10-20 already expired,
--    reason="test aja" - test data, not a real contractor record.)
DELETE FROM sanctions WHERE id = 6 AND contractor_id = 43;

-- 2. contractor_companies -> contractors: RESTRICT instead of CASCADE.
--    An admin must now reassign/remove contractors first before a company
--    can be deleted, instead of losing them silently.
ALTER TABLE contractors DROP FOREIGN KEY contractors_company_id_foreign;
ALTER TABLE contractors
    ADD CONSTRAINT contractors_company_id_foreign
    FOREIGN KEY (company_id) REFERENCES contractor_companies (id)
    ON DELETE RESTRICT;

-- 3. sanctions -> contractors: re-add (was missing entirely). CASCADE to
--    match attendances' existing convention.
ALTER TABLE sanctions
    ADD CONSTRAINT sanctions_contractor_id_foreign
    FOREIGN KEY (contractor_id) REFERENCES contractors (id)
    ON DELETE CASCADE;

-- 4. sanctions -> violations: re-add with RESTRICT so deleting a violation
--    type can never silently delete sanction/ban history.
ALTER TABLE sanctions
    ADD CONSTRAINT sanctions_violation_id_foreign
    FOREIGN KEY (violation_id) REFERENCES violations (id)
    ON DELETE RESTRICT;

COMMIT;

-- 5. activity_logs -> users: preserve the audit trail even after a user
--    account is deleted, instead of silently cascading their entire
--    history away. Column must be made nullable first since SET NULL
--    requires it.
ALTER TABLE activity_logs MODIFY user_id INT UNSIGNED NULL;
ALTER TABLE activity_logs DROP FOREIGN KEY activity_logs_user_id_foreign;
ALTER TABLE activity_logs
    ADD CONSTRAINT activity_logs_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL;
