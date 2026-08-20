cap-- ============================================================
-- aug2026_12patients_v2.sql  ← USE THIS ONE
-- Dental Clinic Management and Recording System
-- PHINMA University of Iloilo — Capstone
-- ============================================================
-- ✅ FIXED VERSION — auto-detects your current max codes
-- ✅ Safe to re-run — cleans up any partial data first
-- HOW: HeidiSQL → Query tab → paste all → F9
-- ============================================================

USE `cap`;

-- ============================================================
-- STEP 0: CLEANUP
-- Removes any partial inserts from the previous failed attempt
-- (safe even if nothing was inserted yet — 0 rows affected)
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `notifications`
  WHERE `created_at` >= '2026-08-01 00:00:00'
    AND `message` LIKE '%PAT-005%'
     OR `message` LIKE '%PAT-006%'
     OR `message` LIKE '%Ramirez%'
     OR `message` LIKE '%Abella%';

DELETE FROM `bills`
  WHERE `patient_id` IN (
    SELECT `id` FROM (
      SELECT `id` FROM `patients`
      WHERE `patient_code` BETWEEN 'PAT-0050' AND 'PAT-0061'
    ) AS tmp
  );

DELETE FROM `dental_records`
  WHERE `patient_id` IN (
    SELECT `id` FROM (
      SELECT `id` FROM `patients`
      WHERE `patient_code` BETWEEN 'PAT-0050' AND 'PAT-0061'
    ) AS tmp
  );

DELETE FROM `appointments`
  WHERE `patient_id` IN (
    SELECT `id` FROM (
      SELECT `id` FROM `patients`
      WHERE `patient_code` BETWEEN 'PAT-0050' AND 'PAT-0061'
    ) AS tmp
  );

DELETE FROM `patients`
  WHERE `patient_code` BETWEEN 'PAT-0050' AND 'PAT-0061';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- STEP 1: INSERT 12 PATIENTS
-- ============================================================
INSERT INTO `patients`
  (`patient_code`, `first_name`, `last_name`, `middle_name`, `date_of_birth`,
   `gender`, `civil_status`, `address`, `occupation`, `phone`, `email`,
   `emergency_contact_name`, `emergency_contact_phone`, `blood_type`,
   `allergies`, `medical_notes`, `illness_history`, `registered_by`, `created_at`)
VALUES
('PAT-0050','Liza','Gomez','Bautista','1998-04-12',
 'female','single','15 Sanciangko St., Cebu City',
 'Teacher','09301234550','lgomez@gmail.com',
 'Nora Gomez','09301234559','O+','None','None','None',
 1,'2026-08-01 08:30:00'),

('PAT-0051','Brian','Tamayo','Santos','1992-09-07',
 'male','married','28 Tres de Abril, Cebu City',
 'Engineer','09312345651','btamayo@gmail.com',
 'Rita Tamayo','09312345660','A+','None','None','None',
 2,'2026-08-02 09:00:00'),

('PAT-0052','Maricel','Dionisio','Flores','1984-01-23',
 'female','married','44 M. Velez St., Cebu City',
 'Nurse','09323456752','mdionisio@gmail.com',
 'Ben Dionisio','09323456761','B+','Penicillin',
 'Hypertensive, on maintenance medication','None',
 1,'2026-08-04 08:00:00'),

('PAT-0053','Ricky','Palma','Reyes','1971-11-15',
 'male','married','67 Urgello St., Cebu City',
 'Driver','09334567853','rpalma@gmail.com',
 'Elena Palma','09334567862','O+','Aspirin','Hypertensive','None',
 1,'2026-08-05 09:30:00'),

('PAT-0054','Jessa','Macaraeg','Cabral','2004-06-30',
 'female','single','33 Sambag 2, Cebu City',
 'Student','09345678954','jmacaraeg@gmail.com',
 'Lorna Macaraeg','09345678963','A-','None','None','None',
 2,'2026-08-06 10:00:00'),

('PAT-0055','Ferdinand','Lozano','Cruz','1979-08-18',
 'male','married','55 Lahug, Cebu City',
 'Business Owner','09356789055','flozano@gmail.com',
 'Carmen Lozano','09356789064','AB+','None','None','None',
 1,'2026-08-07 08:30:00'),

('PAT-0056','Gloria','Tan','Uy','1963-03-05',
 'female','widowed','12 Carbon, Cebu City',
 'Retired','09367890156','gtan@gmail.com',
 'Alex Tan','09367890165','B+','Sulfa',
 'Diabetic Type 2, Hypertensive','Cataract surgery 2020',
 1,'2026-08-08 09:00:00'),

('PAT-0057','Marco','Villanueva','Dela Cruz','1995-12-21',
 'male','single','89 Mambaling, Cebu City',
 'IT Professional','09378901257','mvillanueva@gmail.com',
 'Sofia Villanueva','09378901266','O-','None','None','None',
 2,'2026-08-09 08:00:00'),

('PAT-0058','Sheila','Ramirez','Madriaga','2000-02-14',
 'female','single','22 Apas, Cebu City',
 'BPO Agent','09389012358','sramirez@gmail.com',
 'Mario Ramirez','09389012367','A+','None','None','None',
 1,'2026-08-11 09:00:00'),

('PAT-0059','Danilo','Cruz','Estrada','1988-07-28',
 'male','married','48 Bacayan, Cebu City',
 'Policeman','09390123459','dcruz@gmail.com',
 'Fe Cruz','09390123468','B-','None','None','None',
 2,'2026-08-11 10:00:00'),

('PAT-0060','Rowena','Abella','Navarro','1976-05-11',
 'female','married','71 Guadalupe, Cebu City',
 'Nurse','09301345560','rabella@gmail.com',
 'Jorge Abella','09301345569','AB-','None','Diabetic Type 2','None',
 1,'2026-08-11 11:00:00'),

('PAT-0061','Gilbert','Ponce','Torres','1997-10-03',
 'male','single','36 Talamban, Cebu City',
 'Programmer','09312456661','gponce@gmail.com',
 'Nora Ponce','09312456670','O+','None','None','None',
 2,'2026-08-11 11:30:00');

-- ============================================================
-- STEP 2: STORE PATIENT IDs
-- ============================================================
SET @p50 = (SELECT id FROM patients WHERE patient_code = 'PAT-0050');
SET @p51 = (SELECT id FROM patients WHERE patient_code = 'PAT-0051');
SET @p52 = (SELECT id FROM patients WHERE patient_code = 'PAT-0052');
SET @p53 = (SELECT id FROM patients WHERE patient_code = 'PAT-0053');
SET @p54 = (SELECT id FROM patients WHERE patient_code = 'PAT-0054');
SET @p55 = (SELECT id FROM patients WHERE patient_code = 'PAT-0055');
SET @p56 = (SELECT id FROM patients WHERE patient_code = 'PAT-0056');
SET @p57 = (SELECT id FROM patients WHERE patient_code = 'PAT-0057');
SET @p58 = (SELECT id FROM patients WHERE patient_code = 'PAT-0058');
SET @p59 = (SELECT id FROM patients WHERE patient_code = 'PAT-0059');
SET @p60 = (SELECT id FROM patients WHERE patient_code = 'PAT-0060');
SET @p61 = (SELECT id FROM patients WHERE patient_code = 'PAT-0061');

-- ============================================================
-- STEP 3: FIND CURRENT MAX APPOINTMENT NUMBER
-- auto-detects whatever is the highest APT code in your DB
-- ============================================================
SET @apt_n = (
  SELECT COALESCE(MAX(CAST(SUBSTRING(appointment_code, 5) AS UNSIGNED)), 0)
  FROM appointments
);

-- ============================================================
-- STEP 4: INSERT APPOINTMENTS (auto-numbered, no conflicts)
-- Each uses @apt_n + offset so it starts AFTER your current max
-- ============================================================

-- APT +1 | Liza Gomez | Dental Cleaning | Aug 1
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 1, 4, '0')),
   @p50, 3, 1, '2026-08-01', '09:00:00', 'walk-in', 'completed',
   'Dental cleaning, routine visit', 1, '2026-08-01 08:45:00');
SET @a1 = LAST_INSERT_ID();

-- APT +2 | Brian Tamayo | Tooth Filling | Aug 2
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 2, 4, '0')),
   @p51, 4, 2, '2026-08-02', '10:00:00', 'walk-in', 'completed',
   'Composite filling lower left molar', 2, '2026-08-02 09:45:00');
SET @a2 = LAST_INSERT_ID();

-- APT +3 | Maricel Dionisio | Root Canal | Aug 4
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 3, 4, '0')),
   @p52, 5, 1, '2026-08-04', '09:00:00', 'walk-in', 'completed',
   'Root canal upper left molar, patient in severe pain', 1, '2026-08-04 08:45:00');
SET @a3 = LAST_INSERT_ID();

-- APT +4 | Ricky Palma | Extraction | Aug 5
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 4, 4, '0')),
   @p53, 2, 2, '2026-08-05', '10:00:00', 'walk-in', 'completed',
   'Lower right wisdom tooth extraction', 1, '2026-08-05 09:45:00');
SET @a4 = LAST_INSERT_ID();

-- APT +5 | Jessa Macaraeg | Dental Checkup | Aug 6
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 5, 4, '0')),
   @p54, 1, 1, '2026-08-06', '10:30:00', 'walk-in', 'completed',
   'Routine dental checkup, first-time patient', 2, '2026-08-06 10:15:00');
SET @a5 = LAST_INSERT_ID();

-- APT +6 | Ferdinand Lozano | Teeth Whitening | Aug 7
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 6, 4, '0')),
   @p55, 7, 1, '2026-08-07', '09:00:00', 'walk-in', 'completed',
   'Teeth whitening full session', 1, '2026-08-07 08:45:00');
SET @a6 = LAST_INSERT_ID();

-- APT +7 | Gloria Tan | Dentures | Aug 8
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 7, 4, '0')),
   @p56, 8, 1, '2026-08-08', '09:30:00', 'walk-in', 'completed',
   'Complete upper denture fabrication and delivery', 1, '2026-08-08 09:15:00');
SET @a7 = LAST_INSERT_ID();

-- APT +8 | Marco Villanueva | X-Ray | Aug 9
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 8, 4, '0')),
   @p57, 9, 2, '2026-08-09', '08:30:00', 'walk-in', 'completed',
   'Dental X-ray full arch, annual checkup', 2, '2026-08-09 08:15:00');
SET @a8 = LAST_INSERT_ID();

-- APT +9 | Sheila Ramirez | Ortho Consult | Aug 14 — UPCOMING
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 9, 4, '0')),
   @p58, 6, 1, '2026-08-14', '09:00:00', 'scheduled', 'pending',
   'Orthodontic consultation, first visit', NULL, '2026-08-11 09:15:00');

-- APT +10 | Danilo Cruz | Dental Cleaning | Aug 15 — UPCOMING
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 10, 4, '0')),
   @p59, 3, 2, '2026-08-15', '10:00:00', 'scheduled', 'confirmed',
   'Dental cleaning appointment', NULL, '2026-08-11 10:15:00');

-- APT +11 | Rowena Abella | Root Canal | Aug 19 — UPCOMING
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 11, 4, '0')),
   @p60, 5, 1, '2026-08-19', '09:00:00', 'scheduled', 'pending',
   'Root canal consult — note: diabetic patient, prepare accordingly', NULL, '2026-08-11 11:15:00');

-- APT +12 | Gilbert Ponce | Tooth Extraction | Aug 21 — UPCOMING
INSERT INTO `appointments`
  (`appointment_code`,`patient_id`,`service_id`,`doctor_id`,
   `appointment_date`,`appointment_time`,`type`,`status`,`notes`,`handled_by`,`created_at`)
VALUES
  (CONCAT('APT-', LPAD(@apt_n + 12, 4, '0')),
   @p61, 2, 2, '2026-08-21', '14:00:00', 'scheduled', 'pending',
   'Tooth extraction lower right molar', NULL, '2026-08-11 11:45:00');

-- ============================================================
-- STEP 5: INSERT DENTAL RECORDS (tooth chart — completed only)
-- Uses @a1-@a8 from LAST_INSERT_ID() above
-- ============================================================
INSERT INTO `dental_records`
  (`patient_id`, `appointment_id`, `service_id`,
   `tooth_number`, `tooth_status`,
   `chief_complaint`, `diagnosis`, `treatment_done`,
   `medications_prescribed`, `next_visit_notes`,
   `recorded_by`, `visit_date`, `created_at`)
VALUES

-- PAT-0050 Liza Gomez | Cleaning | Tooth: normal
(@p50, @a1, 3,
 NULL, 'normal',
 'Teeth feel dirty, gums bleed when brushing at night',
 'Generalized gingivitis, mild supragingival calculus deposits',
 'Supragingival scaling and polishing, oral hygiene instructions given',
 NULL,
 'Return in 6 months for routine cleaning',
 1, '2026-08-01', '2026-08-01 09:45:00'),

-- PAT-0051 Brian Tamayo | Filling | Tooth #36
(@p51, @a2, 4,
 '36', 'filling',
 'Dark spot on lower left back tooth, sensitive to cold drinks',
 'Dentin caries #36 mesial surface, no pulpal involvement',
 'Composite resin filling #36 mesial placed, bite checked and adjusted',
 NULL,
 'Avoid very cold or hot food for 24 hours; annual X-ray recommended',
 2, '2026-08-02', '2026-08-02 10:45:00'),

-- PAT-0052 Maricel Dionisio | Root Canal | Tooth #26
(@p52, @a3, 5,
 '26', 'rootcanal',
 'Severe throbbing pain upper left, cannot sleep, mild cheek swelling',
 'Irreversible pulpitis #26, early periapical abscess',
 'Root canal #26 initiated — canals cleaned, shaped and medicated; temporary filling placed',
 'Amoxicillin 500mg TID x 5 days; Mefenamic acid 500mg Q8H PRN pain',
 'Return in 1 week to complete RCT; permanent crown strongly advised after obturation',
 1, '2026-08-04', '2026-08-04 09:45:00'),

-- PAT-0053 Ricky Palma | Extraction | Tooth #48 (wisdom)
(@p53, @a4, 2,
 '48', 'extraction',
 'Lower right wisdom tooth pain for 2 weeks, jaw stiffness and swelling',
 'Mesioangular impaction #48, pericoronitis, periapical infection present',
 'Surgical extraction #48 under inferior alveolar nerve block; socket irrigated, wound sutured x3',
 'Mefenamic acid 500mg Q8H x 3 days; Amoxicillin 500mg TID x 5 days',
 'Soft diet, no smoking, no straws; return in 1 week for suture removal',
 1, '2026-08-05', '2026-08-05 10:45:00'),

-- PAT-0054 Jessa Macaraeg | Checkup | Tooth: normal
(@p54, @a5, 1,
 NULL, 'normal',
 'First dental visit, no specific complaint, worried about cavities',
 'No active caries detected, mild plaque accumulation, healthy periodontium',
 'Comprehensive oral examination, OHI given, panoramic X-ray recommended',
 NULL,
 'Return in 6 months for first cleaning and follow-up checkup',
 2, '2026-08-06', '2026-08-06 11:15:00'),

-- PAT-0055 Ferdinand Lozano | Whitening | Tooth: normal
(@p55, @a6, 7,
 NULL, 'normal',
 'Wants whiter teeth, staining from coffee and smoking, embarrassed to smile',
 'Moderate extrinsic and intrinsic staining; no active caries; eligible for whitening',
 'In-office power whitening — 3 shades improvement achieved (B3 to A2 on VITA shade guide)',
 NULL,
 'Avoid dark food and coffee for 48 hours; whitening touch-up every 6 months',
 1, '2026-08-07', '2026-08-07 10:00:00'),

-- PAT-0056 Gloria Tan | Dentures | Tooth: denture
(@p56, @a7, 8,
 NULL, 'denture',
 'All upper teeth missing since 2023, cannot chew properly, difficulty speaking clearly',
 'Completely edentulous upper arch; adequate residual ridge for conventional denture',
 'Complete upper denture fabricated, delivered, and adjusted — proper bite and retention achieved',
 NULL,
 'Return in 1 week for post-insertion adjustment; avoid hard and sticky foods initially',
 1, '2026-08-08', '2026-08-08 10:30:00'),

-- PAT-0057 Marco Villanueva | X-Ray | Tooth: normal
(@p57, @a8, 9,
 NULL, 'normal',
 'No pain or complaints; annual dental X-ray recommended by employer clinic',
 'Mild horizontal bone loss upper right region; no active caries or periapical pathology',
 'Full periapical X-ray series taken, upper and lower arches; findings explained to patient',
 NULL,
 'Annual X-ray recommended; improve oral hygiene to slow bone loss progression',
 2, '2026-08-09', '2026-08-09 09:15:00');

-- ============================================================
-- STEP 6: FIND CURRENT MAX BILL NUMBER
-- ============================================================
SET @bill_n = (
  SELECT COALESCE(MAX(CAST(SUBSTRING(bill_code, 6) AS UNSIGNED)), 0)
  FROM bills
);

-- ============================================================
-- STEP 7: INSERT BILLS (auto-numbered, no conflicts)
-- ============================================================

-- BILL +1 | Liza Gomez | ₱800 Cleaning | PAID — cash
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 1, 4, '0')),
   @p50, @a1, 3, 800.00, 800.00, 'cash', NULL, 'paid',
   NULL, 1, '2026-08-01 09:50:00');

-- BILL +2 | Brian Tamayo | ₱600 Filling | PAID — GCash
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 2, 4, '0')),
   @p51, @a2, 4, 600.00, 600.00, 'gcash', 'GC-20260802', 'paid',
   NULL, 2, '2026-08-02 10:50:00');

-- BILL +3 | Maricel Dionisio | ₱3500 Root Canal | PARTIAL — 50% deposit
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 3, 4, '0')),
   @p52, @a3, 5, 3500.00, 1750.00, 'cash', NULL, 'partial',
   '50% downpayment; balance of P1,750.00 due on final RCT visit',
   1, '2026-08-04 09:50:00');

-- BILL +4 | Ricky Palma | ₱500 Extraction | PAID — cash
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 4, 4, '0')),
   @p53, @a4, 2, 500.00, 500.00, 'cash', NULL, 'paid',
   'Surgical extraction fee', 1, '2026-08-05 10:50:00');

-- BILL +5 | Jessa Macaraeg | ₱300 Checkup | PAID — cash
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 5, 4, '0')),
   @p54, @a5, 1, 300.00, 300.00, 'cash', NULL, 'paid',
   NULL, 2, '2026-08-06 11:20:00');

-- BILL +6 | Ferdinand Lozano | ₱2500 Whitening | PAID — GCash
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 6, 4, '0')),
   @p55, @a6, 7, 2500.00, 2500.00, 'gcash', 'GC-20260807', 'paid',
   NULL, 1, '2026-08-07 10:05:00');

-- BILL +7 | Gloria Tan | ₱5000 Dentures | PARTIAL — 50% downpayment
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 7, 4, '0')),
   @p56, @a7, 8, 5000.00, 2500.00, 'cash', NULL, 'partial',
   '50% downpayment for complete upper denture; balance of P2,500.00 on next visit',
   1, '2026-08-08 10:35:00');

-- BILL +8 | Marco Villanueva | ₱200 X-Ray | PAID — cash
INSERT INTO `bills`
  (`bill_code`,`patient_id`,`appointment_id`,`service_id`,
   `amount_due`,`amount_paid`,`payment_method`,`payment_ref`,
   `status`,`notes`,`created_by`,`created_at`)
VALUES
  (CONCAT('BILL-', LPAD(@bill_n + 8, 4, '0')),
   @p57, @a8, 9, 200.00, 200.00, 'cash', NULL, 'paid',
   NULL, 2, '2026-08-09 09:20:00');

-- ============================================================
-- STEP 8: NOTIFICATIONS for upcoming appointments
-- ============================================================
INSERT INTO `notifications`
  (`user_id`, `title`, `message`, `type`, `is_read`, `link`, `created_at`)
VALUES
(NULL,
 'Upcoming: Orthodontic Consultation',
 'Sheila Ramirez (PAT-0058) has an ortho consult on Aug 14 at 9:00 AM.',
 'reminder', 0, 'modules/appointments/list.php', '2026-08-11 09:20:00'),
(NULL,
 'Upcoming: Root Canal — Diabetic Patient',
 'Rowena Abella (PAT-0060) has a root canal consult on Aug 19 at 9:00 AM. Patient is diabetic.',
 'appointment', 0, 'modules/appointments/list.php', '2026-08-11 11:20:00'),
(NULL,
 'Pending Balance: Root Canal',
 'Maricel Dionisio has an outstanding balance of P1,750.00 for root canal (PAT-0052).',
 'payment', 0, 'modules/billing/list.php', '2026-08-04 09:55:00'),
(NULL,
 'Pending Balance: Dentures',
 'Gloria Tan has an outstanding balance of P2,500.00 for complete upper denture (PAT-0056).',
 'payment', 0, 'modules/billing/list.php', '2026-08-08 10:40:00');

-- ============================================================
-- ✅ VERIFY — should show 12, 12, 8, 8
-- ============================================================
SELECT 'PATIENTS'       AS `table`, COUNT(*) AS `added`
  FROM patients WHERE patient_code BETWEEN 'PAT-0050' AND 'PAT-0061';

SELECT 'APPOINTMENTS'   AS `table`, COUNT(*) AS `added`
  FROM appointments
  WHERE patient_id IN (@p50,@p51,@p52,@p53,@p54,@p55,@p56,@p57,@p58,@p59,@p60,@p61);

SELECT 'DENTAL RECORDS' AS `table`, COUNT(*) AS `added`
  FROM dental_records
  WHERE appointment_id IN (@a1,@a2,@a3,@a4,@a5,@a6,@a7,@a8);

SELECT 'BILLS'          AS `table`, COUNT(*) AS `added`
  FROM bills
  WHERE patient_id IN (@p50,@p51,@p52,@p53,@p54,@p55,@p56,@p57);

-- ============================================================
-- EXPECTED RESULTS:
--   PATIENTS       → 12
--   APPOINTMENTS   → 12
--   DENTAL RECORDS → 8
--   BILLS          → 8
-- ============================================================