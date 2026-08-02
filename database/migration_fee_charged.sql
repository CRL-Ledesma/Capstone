-- Migration: Add fee_charged to dental_records
-- Run this once against your database before using the updated system.
--
-- What this does:
--   Adds a nullable decimal column "fee_charged" to dental_records.
--   Each record row = one patient visit. The fee_charged field records
--   exactly what was collected/charged on that specific visit —
--   matching the Date | Treatment Rendered | Fee columns on the paper record.
--
--   If a patient pays in installments across multiple visits, staff enters
--   only the amount collected that day. If nothing was collected (follow-up
--   with no charge), leave it blank (NULL).

ALTER TABLE dental_records
    ADD COLUMN fee_charged DECIMAL(10,2) NULL DEFAULT NULL
    AFTER visit_date;
