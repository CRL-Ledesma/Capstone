-- ============================================================
-- MIGRATION: Dental Records v2
-- Adds missing columns that may not exist in older databases.
-- SAFE TO RE-RUN — uses ADD COLUMN IF NOT EXISTS.
-- ============================================================

-- Add treatment_plan column (was missing in earlier DB versions)
ALTER TABLE `dental_records`
    ADD COLUMN IF NOT EXISTS `treatment_plan` TEXT DEFAULT NULL
    AFTER `treatment_done`;

-- Add materials_used as its own column (previously was appended to treatment_done)
ALTER TABLE `dental_records`
    ADD COLUMN IF NOT EXISTS `materials_used` TEXT DEFAULT NULL
    AFTER `treatment_plan`;

-- ============================================================
-- HOW TO RUN:
-- In HeidiSQL: File → Run SQL file → pick this file → Execute
-- In phpMyAdmin: Import → choose this file → Go
-- In Laragon terminal: mysql -u root cap < migration_dental_records_v2.sql
-- ============================================================
