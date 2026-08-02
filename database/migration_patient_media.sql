-- ============================================================
-- MIGRATION: Patient Media (Photos & X-Rays)
-- Run this on your existing database to add photo/x-ray support.
-- Safe to re-run: uses IF NOT EXISTS / checks before altering.
-- ============================================================

-- Add photo_path to patients (safe: only if column doesn't already exist)
ALTER TABLE `patients`
    ADD COLUMN IF NOT EXISTS `photo_path` VARCHAR(500) DEFAULT NULL
    COMMENT 'Relative path to patient profile photo';

-- Patient X-Ray images table
CREATE TABLE IF NOT EXISTS `patient_xrays` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id`  INT NOT NULL,
  `file_path`   VARCHAR(500) NOT NULL COMMENT 'Relative path under uploads/xrays/',
  `file_name`   VARCHAR(200) NOT NULL,
  `file_size`   INT DEFAULT 0 COMMENT 'Size in bytes',
  `label`       VARCHAR(200) DEFAULT NULL COMMENT 'Staff-supplied label e.g. "Upper Left Molar"',
  `notes`       TEXT DEFAULT NULL,
  `uploaded_by` INT DEFAULT NULL,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_xray_patient` (`patient_id`),
  FOREIGN KEY (`patient_id`)  REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
