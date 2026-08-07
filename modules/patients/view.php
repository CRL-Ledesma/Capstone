<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
$current_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

$page_title = 'Patient Profile';

// ── Handle inline dental record submission ────────────────────────────────
$inline_success = '';
$inline_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_inline_dental_record'])) {
    validate_csrf();
    $pid          = intval($_POST['patient_id'] ?? 0);
    $appt_link_id = intval($_POST['appointment_id'] ?? 0) ?: null;
    $svc_id       = intval($_POST['service_id'] ?? 0) ?: null;
    $tooth_num    = trim($_POST['tooth_number'] ?? '');
    $tooth_st     = trim($_POST['tooth_status'] ?? 'normal');
    $valid_sts    = ['normal','caries','filling','extraction','missing','crown','rootcanal','bridge','implant','denture'];
    if (!in_array($tooth_st, $valid_sts)) $tooth_st = 'normal';
    $chief        = trim($_POST['chief_complaint'] ?? '');
    $diagnosis    = trim($_POST['diagnosis'] ?? '');
    $treatment    = trim($_POST['treatment_done'] ?? '');
    $materials    = trim($_POST['materials_used'] ?? '');
    $plan         = trim($_POST['treatment_plan'] ?? '');
    $meds         = trim($_POST['medications_prescribed'] ?? '');
    $next         = trim($_POST['next_visit_notes'] ?? '');
    $raw_date     = trim($_POST['visit_date'] ?? '');
    $visit_date   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date) ? $raw_date : date('Y-m-d');
    $raw_fee      = $_POST['fee_charged'] ?? '';
    $fee_charged  = ($raw_fee !== '' && is_numeric($raw_fee) && $raw_fee >= 0) ? (float)$raw_fee : null;

    if (!$pid || $pid !== (int)($_GET['id'] ?? 0)) {
        $inline_error = 'Invalid patient.';
    } elseif ($treatment === '') {
        $inline_error = 'Treatment Done is required.';
    } else {
        try {
            $ins = $conn->prepare(
                "INSERT INTO dental_records
                 (patient_id, appointment_id, service_id, tooth_number, tooth_status,
                  chief_complaint, diagnosis, treatment_done, materials_used,
                  treatment_plan, medications_prescribed, next_visit_notes,
                  visit_date, fee_charged, recorded_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $pid, $appt_link_id, $svc_id, $tooth_num, $tooth_st,
                $chief, $diagnosis, $treatment, $materials,
                $plan, $meds, $next, $visit_date, $fee_charged, $current_user_id
            ]);
            $inline_success = 'Dental record saved!';

            // ── Auto-create a bill so Payment History & Billing reflect the fee ──
            // The inline form labels this "Fee Collected" (cash in hand at visit),
            // so we record it as a paid cash bill. Skip if the linked appointment
            // already has a bill (avoids duplicates on repeat submissions).
            if ($fee_charged > 0) {
                $no_dup = true;
                if ($appt_link_id) {
                    $dup_chk = $conn->prepare("SELECT id FROM bills WHERE appointment_id = ? LIMIT 1");
                    $dup_chk->execute([$appt_link_id]);
                    $no_dup = !$dup_chk->fetchColumn();
                    $dup_chk->closeCursor();
                }
                if ($no_dup) {
                    $bc    = generate_code($conn, 'bills', 'BILL');
                    $bstmt = $conn->prepare(
                        "INSERT INTO bills
                         (bill_code, patient_id, appointment_id, service_id,
                          amount_due, amount_paid, payment_method, payment_ref,
                          status, notes, created_by)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                    );
                    $bstmt->execute([
                        $bc, $pid, $appt_link_id, $svc_id,
                        $fee_charged, $fee_charged,
                        'cash', '', 'paid',
                        'Auto-created from inline dental record entry.',
                        $current_user_id
                    ]);
                    $bstmt->closeCursor();
                    $inline_success = 'Dental record saved and payment recorded!';
                }
            }
            // ─────────────────────────────────────────────────────────────────
        } catch (Exception $e) {
            $inline_error = 'Save failed: ' . $e->getMessage();
        }
    }
}
// ─────────────────────────────────────────────────────────────────────────

$id = secure_int($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit(); }

$pt_stmt = $conn->prepare("SELECT * FROM patients WHERE id = ? AND is_active = TRUE LIMIT 1");
$pt_stmt->execute([$id]);
$patient = $pt_stmt->fetch(PDO::FETCH_ASSOC);
$pt_stmt->closeCursor();
if (!$patient) { header('Location: list.php'); exit(); }

$dr_stmt = $conn->prepare("
    SELECT dr.*, s.service_name, CONCAT(u.full_name) as recorded_by_name,
           a.appointment_code as linked_appt_code,
           a.appointment_date as linked_appt_date
    FROM dental_records dr
    LEFT JOIN services s ON dr.service_id = s.id
    LEFT JOIN users u ON dr.recorded_by = u.id
    LEFT JOIN appointments a ON dr.appointment_id = a.id
    WHERE dr.patient_id = ?
    ORDER BY dr.visit_date DESC
");
$dr_stmt->execute([$id]);
$dental_records = $dr_stmt->fetchAll(PDO::FETCH_ASSOC);
$dr_stmt->closeCursor();

$ap_stmt2 = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=?");
$ap_stmt2->execute([$id]);
$ap_count = (int)$ap_stmt2->fetchColumn();

$la_stmt = $conn->prepare("
    SELECT id, appointment_code, appointment_date, status
    FROM appointments
    WHERE patient_id = ? AND status IN ('pending','confirmed','completed')
    ORDER BY appointment_date DESC LIMIT 20
");
$la_stmt->execute([$id]);
$linkable_appts = $la_stmt->fetchAll(PDO::FETCH_ASSOC);
$la_stmt->closeCursor();

$py_stmt = $conn->prepare("
    SELECT b.*, s.service_name,
           b.amount_paid, b.amount_due, b.status as payment_status,
           b.payment_method, b.created_at
    FROM bills b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.patient_id = ?
    ORDER BY b.created_at DESC
");
$py_stmt->execute([$id]);
$payments = $py_stmt->fetchAll(PDO::FETCH_ASSOC);
$py_stmt->closeCursor();



$total_paid = array_sum(array_column($payments, 'amount_paid'));

// For inline add-record form
$svc_list = $conn->query("SELECT id, service_name FROM services WHERE is_active=TRUE ORDER BY service_name ASC")->fetchAll(PDO::FETCH_ASSOC);


$age = '';
if (!empty($patient['date_of_birth'])) {
    $age = (int)floor((time() - strtotime($patient['date_of_birth'])) / 31557600);
}

$has_photo = !empty($patient['photo_path']) && file_exists('../../' . $patient['photo_path']);
$photo_url = $has_photo ? BASE_URL . $patient['photo_path'] : '';
?><!DOCTYPE html>
<html lang="en">
<head><?php include '../../includes/head.php'; ?>
<style>
/* Patient Profile — local styles */
.profile-grid {
    display: grid;
    grid-template-columns: 310px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 960px) { .profile-grid { grid-template-columns: 1fr; } }

/* Hero card */
.patient-hero {
    background: var(--white);
    border: var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 16px;
}
.hero-banner {
    height: 76px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 55%, var(--accent) 100%);
    position: relative;
    overflow: hidden;
}
.hero-banner::after {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='20' cy='20' r='20' fill='rgba(255,255,255,0.05)'/%3E%3C/svg%3E") repeat;
}
.hero-avatar-wrap {
    display: flex;
    justify-content: center;
    margin-top: -40px;
    position: relative;
    z-index: 2;
}
.hero-avatar {
    width: 80px; height: 80px;
    border-radius: 50%;
    border: 4px solid var(--white);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    background: var(--gray-100);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s ease;
    position: relative;
}
.hero-avatar:hover { transform: scale(1.06); box-shadow: var(--shadow-lg); }
.hero-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.photo-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.45);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.18s ease;
    color: #fff; font-size: 1rem; cursor: pointer;
}
.hero-avatar:hover .photo-overlay { opacity: 1; }
@keyframes lbFadeIn  { from { opacity:0; } to { opacity:1; } }
@keyframes lbScaleIn { from { transform:scale(0.82); opacity:0; } to { transform:scale(1); opacity:1; } }
.hero-body { padding: 12px 18px 18px; text-align: center; }
.hero-name { font-family: var(--font-display); font-size: 1.05rem; font-weight: 700; color: var(--gray-900); letter-spacing: -0.02em; line-height: 1.25; margin-bottom: 3px; }
.hero-code { font-size: 0.76rem; color: var(--gray-600); font-weight: 600; letter-spacing: 0.03em; margin-bottom: 10px; }
.hero-tags { display: flex; align-items: center; justify-content: center; gap: 5px; flex-wrap: wrap; }

/* Info table */
.info-table { width: 100%; border-collapse: collapse; }
.info-table tr { border-bottom: 1px solid var(--gray-100); }
.info-table tr:last-child { border-bottom: none; }
.info-table th { width: 42%; padding: 9px 14px; font-size: 0.80rem; font-weight: 700; color: var(--gray-700); text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; vertical-align: top; }
.info-table td { padding: 9px 14px; font-size: 0.95rem; color: var(--gray-900); font-weight: 600; word-break: break-word; }

/* Quick stats */
.quick-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px; }
.qs-card {
    background: var(--white); border: var(--border); border-radius: var(--radius-md);
    padding: 12px 10px; text-align: center; box-shadow: var(--shadow-xs);
    transition: all 0.22s cubic-bezier(0.34,1.56,0.64,1);
    cursor: default;
}
.qs-card:hover { border-color: var(--primary); box-shadow: var(--shadow-teal); transform: translateY(-3px); }
.qs-num { font-size: 1.45rem; font-weight: 800; color: var(--primary); line-height: 1; font-family: var(--font-display); }
.qs-lbl { font-size: 0.72rem; font-weight: 700; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 3px; }

/* Payment summary */
.pay-summary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: var(--radius-md); padding: 16px; color: #fff; margin-bottom: 12px;
    position: relative; overflow: hidden;
}
.pay-summary::after { content: 'P'; position: absolute; right: 12px; bottom: -8px; font-size: 4.5rem; font-weight: 900; opacity: 0.07; font-family: var(--font-display); line-height: 1; }
.pay-summary .amount { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.03em; }
.pay-summary .label  { font-size: 0.74rem; opacity: 0.8; margin-top: 2px; }

/* Record accordion */
.rec-accordion { border: var(--border); border-radius: var(--radius-md); overflow: hidden; }
.rec-item + .rec-item { border-top: 1px solid var(--gray-100); }
.rec-toggle {
    width: 100%; text-align: left; background: var(--white);
    border: none; padding: 12px 16px; cursor: pointer;
    display: flex; align-items: center; gap: 10px;
    font-size: 0.875rem;
    transition: background 0.15s ease;
}
.rec-toggle:hover { background: var(--gray-50); }
.rec-toggle .rec-date { font-weight: 700; color: var(--gray-800); white-space: nowrap; }
.rec-toggle .rec-svc  { color: var(--gray-700); font-size: 0.83rem; flex: 1; }
.rec-toggle .rec-arrow { margin-left: auto; color: var(--gray-400); transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1); }
.rec-toggle.open .rec-arrow { transform: rotate(180deg); }
.rec-body { display: none; padding: 16px; background: var(--gray-50); border-top: 1px solid var(--gray-100); font-size: 0.875rem; }
.rec-body.show { display: block; animation: slideDown 0.22s cubic-bezier(0,0,0.2,1) forwards; }
@keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
.rec-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 600px) { .rec-grid { grid-template-columns: 1fr; } }
.rec-field { margin-bottom: 8px; }
.rec-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-600); margin-bottom: 2px; }
.rec-value { color: var(--gray-900); font-weight: 500; line-height: 1.5; }





/* Mobile tabs */
.profile-tabs { display: none; }
@media (max-width: 960px) {
    .profile-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 16px; scrollbar-width: none; }
    .profile-tabs::-webkit-scrollbar { display: none; }
    .ptab { white-space: nowrap; padding: 7px 14px; border-radius: 20px; border: 1.5px solid var(--gray-200); background: var(--white); font-size: 0.78rem; font-weight: 600; color: var(--gray-600); cursor: pointer; transition: all 0.18s ease; flex-shrink: 0; }
    .ptab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .tab-section { display: none; }
    .tab-section.active { display: block; }
    .profile-left  { display: none; }
    .profile-left.active  { display: block; }
    .profile-right { display: none; }
    .profile-right.active { display: block; }
}
@media (min-width: 961px) {
    .tab-section { display: block !important; }
    .profile-left, .profile-right { display: block !important; }
}

/* Dark mode extras */
[data-theme="dark"] .info-table th { color: #9ab8d4; }
[data-theme="dark"] .info-table td { color: #e2eef8; }
[data-theme="dark"] .info-table tr { border-bottom-color: #1E293B; }
[data-theme="dark"] .rec-toggle { background: #1E293B; color: #E2E8F0; }
[data-theme="dark"] .rec-toggle:hover { background: #263348; }
[data-theme="dark"] .rec-date { color: #E2E8F0; }
[data-theme="dark"] .rec-svc { color: #94A3B8; }
[data-theme="dark"] .rec-body { background: #0F172A; border-top-color: #1E293B; }
[data-theme="dark"] .rec-value { color: #ddeaf6; }
[data-theme="dark"] .rec-label { color: #9ab8d4; }
[data-theme="dark"] .rec-accordion { border-color: #1E293B; }
[data-theme="dark"] .rec-item + .rec-item { border-top-color: #1E293B; }
[data-theme="dark"] .qs-card { background: #1E293B; border-color: #334155; }
[data-theme="dark"] .qs-lbl { color: #90b0cc; }
[data-theme="dark"] .patient-hero { background: #1E293B; border-color: #334155; }
[data-theme="dark"] .hero-name { color: #E2E8F0; }
[data-theme="dark"] .hero-avatar { background: #263348; border-color: #1E293B; }


</style>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header" style="margin-bottom:16px;">
            <div>
                <h5 style="margin-bottom:2px;">
                    <?php echo e($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    <?php if ($age !== ''): ?><span style="font-size:0.9rem;font-weight:400;color:var(--gray-400);margin-left:6px;"><?php echo $age; ?> yrs</span><?php endif; ?>
                    <?php if (!empty($patient['is_incomplete'])): ?> <span class="badge bg-warning" style="font-size:0.65rem;vertical-align:middle;"><i class="bi bi-exclamation-circle"></i> Incomplete</span><?php endif; ?>
                </h5>
                <small class="text-muted"><?php echo e($patient['patient_code']); ?><?php if (!empty($patient['blood_type'])): ?> &nbsp;&middot;&nbsp; <span style="color:var(--danger);font-weight:600;"><?php echo e($patient['blood_type']); ?></span><?php endif; ?></small>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
                <button type="button" class="btn btn-sm btn-success" onclick="openInlineRecord()"><i class="bi bi-journal-plus"></i> Add Record</button>
                <a href="../appointments/list.php?walkin=1&patient_id=<?php echo $id; ?>" class="btn btn-sm btn-primary"><i class="bi bi-calendar-plus"></i> Book</a>
                <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </div>

        <?php if ($inline_success): ?><div class="alert alert-success" style="margin-bottom:14px;"><i class="bi bi-check-circle-fill me-2"></i><?php echo e($inline_success); ?></div><?php endif; ?>
        <?php if ($inline_error):   ?><div class="alert alert-danger"  style="margin-bottom:14px;"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo e($inline_error); ?></div><?php endif; ?>

        <?php if (!empty($patient['is_incomplete'])): ?>
        <div class="alert alert-warning" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <span><i class="bi bi-exclamation-triangle"></i> Quick-added patient &mdash; complete the profile to add date of birth, gender, and address.</span>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-sm btn-warning" style="white-space:nowrap;"><i class="bi bi-pencil"></i> Complete Profile</a>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="qs-card">
                <div class="qs-num"><?php echo count($dental_records); ?></div>
                <div class="qs-lbl">Records</div>
            </div>
            <div class="qs-card">
                <div class="qs-num"><?php echo $ap_count; ?></div>
                <div class="qs-lbl">Appts</div>
            </div>
            <div class="qs-card">
                <div class="qs-num">P<?php echo number_format($total_paid, 0); ?></div>
                <div class="qs-lbl">Total Paid</div>
            </div>
        </div>

        <!-- Mobile Tabs -->
        <div class="profile-tabs">
            <button class="ptab active" onclick="switchTab('info', this)">Info</button>
            <button class="ptab" onclick="switchTab('records', this)">Records (<?php echo count($dental_records); ?>)</button>
            <button class="ptab" onclick="switchTab('billing', this)">Billing (<?php echo count($payments); ?>)</button>
        </div>

        <!-- Main Grid -->
        <div class="profile-grid">

            <!-- LEFT COLUMN -->
            <div class="profile-left tab-section active" data-tab="info">

                <!-- Hero / Photo Card -->
                <div class="patient-hero">
                    <div class="hero-banner"></div>
                    <div class="hero-avatar-wrap">
                        <div class="hero-avatar" onclick="openPhotoLightbox()" title="Click to view photo" id="heroAvatarCircle">
                            <?php if ($has_photo): ?>
                                <img src="<?php echo e($photo_url); ?>" alt="Patient photo" id="heroPhotoImg">
                            <?php else: ?>
                                <span id="heroPhotoPlaceholder" style="color:var(--gray-300);font-size:2.5rem;line-height:1;"><i class="bi bi-person-fill"></i></span>
                                <img src="" alt="" id="heroPhotoImg" style="display:none;">
                            <?php endif; ?>
                            <div class="photo-overlay"><i class="bi bi-zoom-in"></i></div>
                        </div>

                        <!-- Lightbox overlay -->
                        <div id="photoLightbox" onclick="closePhotoLightbox()" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.82);align-items:center;justify-content:center;animation:lbFadeIn 0.18s ease;">
                            <div onclick="event.stopPropagation()" style="position:relative;max-width:420px;width:90%;animation:lbScaleIn 0.18s cubic-bezier(0.34,1.56,0.64,1);">
                                <img id="lightboxImg" src="" alt="Patient photo" style="width:100%;border-radius:50%;aspect-ratio:1/1;object-fit:cover;border:5px solid #fff;box-shadow:0 8px 40px rgba(0,0,0,0.5);display:block;">
                                <div id="lightboxPlaceholder" style="display:none;width:100%;aspect-ratio:1/1;border-radius:50%;background:var(--gray-100);border:5px solid #fff;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-person-fill" style="font-size:6rem;color:var(--gray-300);"></i>
                                </div>
                                <button onclick="closePhotoLightbox()" style="position:absolute;top:-14px;right:-14px;width:34px;height:34px;border-radius:50%;background:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.25);font-size:1rem;color:var(--gray-700);">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <div style="text-align:center;margin-top:14px;color:#fff;font-size:0.82rem;opacity:0.7;">Press Esc or click outside to close</div>
                            </div>
                        </div>
                    </div>
                    <div class="hero-body">
                        <div class="hero-name"><?php echo e($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'][0].'. ' : '') . $patient['last_name']); ?></div>
                        <div class="hero-code"><?php echo e($patient['patient_code']); ?></div>
                        <div class="hero-tags">
                            <?php if ($age !== ''): ?><span class="badge bg-secondary"><?php echo $age; ?> yrs</span><?php endif; ?>
                            <?php if (!empty($patient['gender'])): ?><span class="badge bg-secondary"><?php echo ucfirst($patient['gender']); ?></span><?php endif; ?>
                            <?php if (!empty($patient['blood_type'])): ?><span class="badge bg-danger"><?php echo e($patient['blood_type']); ?></span><?php endif; ?>
                        </div>
                        <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                            <button id="photoCameraBtn" class="btn btn-sm btn-outline-secondary" onclick="triggerPhotoUpload()" style="font-size:0.72rem;padding:4px 12px;"><i class="bi bi-camera"></i> <?php echo $has_photo ? 'Change' : 'Add'; ?> Photo</button>
                            <?php if ($has_photo): ?>
                            <button id="photoRemoveBtn" class="btn btn-sm btn-outline-danger" onclick="deletePhoto()" style="font-size:0.72rem;padding:4px 12px;"><i class="bi bi-trash"></i> Remove</button>
                            <?php else: ?>
                            <button id="photoRemoveBtn" class="btn btn-sm btn-outline-danger" onclick="deletePhoto()" style="font-size:0.72rem;padding:4px 12px;display:none;"><i class="bi bi-trash"></i> Remove</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="uploadPhoto(this)">

                <!-- Personal Info -->
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person-fill me-2" style="color:var(--primary);"></i>Personal Information</div>
                    <div class="card-body p-0">
                        <table class="info-table">
                            <tr><th>Full Name</th><td><?php echo e($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']); ?></td></tr>
                            <tr><th>Date of Birth</th><td><?php if ($patient['date_of_birth']): echo date('M d, Y', strtotime($patient['date_of_birth'])); echo ' <span style="font-size:0.75rem;color:var(--gray-400);">(' . $age . ' yrs)</span>'; else: echo '&mdash;'; endif; ?></td></tr>
                            <tr><th>Gender</th><td><?php echo ucfirst($patient['gender'] ?? '&mdash;'); ?></td></tr>
                            <tr><th>Civil Status</th><td><?php echo ucfirst($patient['civil_status'] ?? '&mdash;'); ?></td></tr>
                            <tr><th>Blood Type</th><td><?php echo em($patient['blood_type']); ?></td></tr>
                            <tr><th>Address</th><td><?php echo em($patient['address']); ?></td></tr>
                            <tr><th>Occupation</th><td><?php echo em($patient['occupation']); ?></td></tr>
                            <tr><th>Phone</th><td><a href="tel:<?php echo e($patient['phone'] ?? ''); ?>"><?php echo em($patient['phone']); ?></a></td></tr>
                            <tr><th>Email</th><td><?php echo em($patient['email']); ?></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-telephone-fill me-2" style="color:var(--danger);"></i>Emergency Contact</div>
                    <div class="card-body p-0">
                        <table class="info-table">
                            <tr><th>Name</th><td><?php echo em($patient['emergency_contact_name']); ?></td></tr>
                            <tr><th>Phone</th><td><a href="tel:<?php echo e($patient['emergency_contact_phone'] ?? ''); ?>"><?php echo em($patient['emergency_contact_phone']); ?></a></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Medical Background -->
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-heart-pulse-fill me-2" style="color:var(--danger);"></i>Medical Background</div>
                    <div class="card-body">
                        <p class="mb-1" style="font-size:0.77rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-700);">Allergies</p>
                        <p class="mb-3" style="font-size:0.875rem;"><?php echo nl2br(e($patient['allergies'] ?? 'None reported')); ?></p>
                        <p class="mb-1" style="font-size:0.77rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-700);">Medical Notes</p>
                        <p class="<?php echo !empty($patient['illness_history']) ? 'mb-3' : 'mb-0'; ?>" style="font-size:0.875rem;"><?php echo nl2br(e($patient['medical_notes'] ?? 'None')); ?></p>
                        <?php if (!empty($patient['illness_history'])): ?>
                        <p class="mb-1" style="font-size:0.77rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-700);">History of Illness</p>
                        <p class="mb-0" style="font-size:0.875rem;"><?php echo nl2br(e($patient['illness_history'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <div class="pay-summary">
                            <div class="amount">P<?php echo number_format($total_paid, 2); ?></div>
                            <div class="label">Total paid (all time)</div>
                        </div>
                    </div>
                </div>

            </div><!-- /LEFT -->

            <!-- RIGHT COLUMN -->
            <div class="profile-right" style="min-width:0;">

                <!-- Dental Records -->
                <div id="dentalRecordsSection" class="card mb-4 tab-section" data-tab="records">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-journal-medical me-2" style="color:var(--primary);"></i>Dental / Treatment Records <span class="badge bg-primary ms-1"><?php echo count($dental_records); ?></span></span>
                        <button type="button" class="btn btn-sm btn-success" onclick="openInlineRecord()"><i class="bi bi-plus-lg"></i> Add Record</button>
                    </div>
                    <!-- ── INLINE ADD RECORD FORM ───────────────────────────── -->
                    <div id="inlineRecordPanel" style="display:none;border-bottom:2px solid var(--primary);background:var(--gray-50);">
                        <div style="padding:16px 18px 8px;display:flex;align-items:center;justify-content:space-between;">
                            <strong style="color:var(--primary);font-size:0.9rem;"><i class="bi bi-journal-plus me-2"></i>New Visit Record</strong>
                            <button type="button" onclick="closeInlineRecord()" style="background:none;border:none;cursor:pointer;color:var(--gray-400);font-size:1.1rem;"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <form method="POST" action="view.php?id=<?php echo $id; ?>" style="padding:0 18px 18px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="_inline_dental_record" value="1">
                            <input type="hidden" name="patient_id" value="<?php echo $id; ?>">

                            <!-- ── CORE FIELDS (always visible) ── -->
                            <div class="row g-2 mb-2 align-items-end">
                                <div class="col-sm-3">
                                    <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Date</label>
                                    <input type="date" name="visit_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Treatment Rendered <span style="color:var(--danger);">*</span></label>
                                    <textarea name="treatment_done" class="form-control form-control-sm" rows="2" required placeholder="What was done today..."></textarea>
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Fee (₱)</label>
                                    <input type="number" name="fee_charged" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>

                            <!-- ── MORE DETAILS TOGGLE ── -->
                            <div class="mb-2">
                                <button type="button" class="btn btn-link btn-sm p-0" style="font-size:0.78rem;color:var(--primary);text-decoration:none;" onclick="toggleRecDetails(this)">
                                    <i class="bi bi-chevron-right" style="font-size:0.7rem;transition:transform 0.2s;vertical-align:middle;" id="recDetailsChevron"></i>
                                    <span id="recDetailsLabel">&nbsp;Add details — teeth, diagnosis, medications...</span>
                                </button>
                            </div>
                            <div id="recDetailsPanel" style="display:none;">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Service</label>
                                        <select name="service_id" class="form-select form-select-sm">
                                            <option value="">— select —</option>
                                            <?php foreach ($svc_list as $sv): ?><option value="<?php echo $sv['id']; ?>"><?php echo e($sv['service_name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Link to Appointment <span style="font-weight:400;text-transform:none;">(optional)</span></label>
                                        <select name="appointment_id" class="form-select form-select-sm">
                                            <option value="">— no linked appointment —</option>
                                            <?php foreach ($linkable_appts as $la): ?>
                                            <option value="<?php echo $la['id']; ?>">
                                                <?php echo e($la['appointment_code']); ?> — <?php echo date('M d, Y', strtotime($la['appointment_date'])); ?> (<?php echo ucfirst($la['status']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Chief Complaint <span style="font-weight:400;text-transform:none;">(patient's own words)</span></label>
                                    <input type="text" name="chief_complaint" class="form-control form-control-sm" placeholder="e.g. Masakit ang ngipin ko sa kanan">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-8">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Teeth Involved <span style="font-weight:400;text-transform:none;">(FDI numbers, e.g. 17, 36)</span></label>
                                        <input type="text" name="tooth_number" class="form-control form-control-sm" placeholder="e.g. 17, 36">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Condition</label>
                                        <select name="tooth_status" class="form-select form-select-sm">
                                            <option value="normal">Normal / Healthy</option>
                                            <option value="caries">Caries (Cavity)</option>
                                            <option value="filling">Filling</option>
                                            <option value="extraction">Extraction</option>
                                            <option value="missing">Missing</option>
                                            <option value="crown">Crown</option>
                                            <option value="rootcanal">Root Canal</option>
                                            <option value="bridge">Bridge</option>
                                            <option value="implant">Implant</option>
                                            <option value="denture">Denture</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Diagnosis / Clinical Findings</label>
                                        <textarea name="diagnosis" class="form-control form-control-sm" rows="2" placeholder="Clinical findings..."></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Medications Prescribed</label>
                                        <input type="text" name="medications_prescribed" class="form-control form-control-sm" placeholder="e.g. Amoxicillin 500mg">
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Materials Used</label>
                                        <input type="text" name="materials_used" class="form-control form-control-sm" placeholder="e.g. GIC, Composite">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Next Visit Notes</label>
                                        <input type="text" name="next_visit_notes" class="form-control form-control-sm" placeholder="Follow-up instructions...">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);">Treatment Plan</label>
                                    <textarea name="treatment_plan" class="form-control form-control-sm" rows="2" placeholder="Future treatment plan..."></textarea>
                                </div>
                            </div>

                            <div style="display:flex;gap:8px;">
                                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-floppy2-fill me-1"></i> Save Record</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeInlineRecord()">Cancel</button>
                            </div>
                        </form>
                    </div>
                    <!-- ── END INLINE FORM ──────────────────────────────────── -->

                    <div class="card-body p-0">
                        <?php if (empty($dental_records)): ?>
                            <div class="text-center py-4 text-muted"><i class="bi bi-journal-x" style="font-size:2rem;opacity:0.3;"></i><p class="mt-2 mb-0 small">No dental records yet. Click "Add Record" above to start.</p></div>
                        <?php else: ?>
                            <div class="rec-accordion">
                                <?php foreach ($dental_records as $i => $rec): ?>
                                <div class="rec-item">
                                    <button class="rec-toggle <?php echo $i === 0 ? 'open' : ''; ?>" onclick="toggleRec(this,'rec<?php echo $rec['id']; ?>')">
                                        <span class="rec-date"><?php echo date('M d, Y', strtotime($rec['visit_date'])); ?></span>
                                        <span class="rec-svc"><?php echo e($rec['service_name'] ?? 'General'); ?></span>
                                        <?php if (!empty($rec['fee_charged'])): ?><span style="font-size:0.78rem;font-weight:700;color:var(--success);white-space:nowrap;margin-right:4px;">₱<?php echo number_format($rec['fee_charged'], 2); ?></span><?php endif; ?>
                                        <i class="bi bi-chevron-down rec-arrow"></i>
                                    </button>
                                    <div class="rec-body <?php echo $i === 0 ? 'show' : ''; ?>" id="rec<?php echo $rec['id']; ?>">

                                        <?php
                                        // Build tooth => status map for this record
                                        $rec_chart_teeth = [];
                                        if (!empty($rec['tooth_number']) && !empty($rec['tooth_status'])) {
                                            foreach (preg_split('/[\s,;]+/', $rec['tooth_number']) as $_t) {
                                                $_t = trim($_t);
                                                if ($_t !== '') $rec_chart_teeth[$_t] = $rec['tooth_status'];
                                            }
                                        }
                                        $chart_uid   = 'rec' . $rec['id'];
                                        $tc_mode     = 'display';
                                        $chart_teeth = $rec_chart_teeth;
                                        include dirname(__FILE__) . '/../../includes/tooth_chart_grid.php';
                                        // reset so next iteration starts clean
                                        $tc_mode = 'input'; $chart_teeth = []; $chart_uid = '';
                                        ?>

                                        <div class="rec-grid" style="margin-top:14px;">
                                            <div>
                                                <?php if (!empty($rec['chief_complaint'])): ?><div class="rec-field"><div class="rec-label">Chief Complaint</div><div class="rec-value"><?php echo e($rec['chief_complaint']); ?></div></div><?php endif; ?>
                                                <?php if (!empty($rec['tooth_number'])): ?><div class="rec-field"><div class="rec-label">Teeth Tagged</div><div class="rec-value" style="font-size:0.8rem;"><?php echo e($rec['tooth_number']); ?> <span style="color:var(--gray-400);font-weight:400;">(<?php echo ucfirst($rec['tooth_status'] ?? ''); ?>)</span></div></div><?php endif; ?>
                                                <div class="rec-field"><div class="rec-label">Diagnosis</div><div class="rec-value"><?php echo nl2br(em($rec['diagnosis'])); ?></div></div>
                                                <div class="rec-field"><div class="rec-label">Treatment Done</div><div class="rec-value"><?php echo nl2br(e($rec['treatment_done'])); ?></div></div>
                                                <?php if (!empty($rec['treatment_plan'])): ?><div class="rec-field"><div class="rec-label">Treatment Plan</div><div class="rec-value"><?php echo nl2br(e($rec['treatment_plan'])); ?></div></div><?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="rec-field"><div class="rec-label">Medications</div><div class="rec-value"><?php echo nl2br(em($rec['medications_prescribed'])); ?></div></div>
                                                <div class="rec-field"><div class="rec-label">Next Visit Notes</div><div class="rec-value"><?php echo nl2br(em($rec['next_visit_notes'])); ?></div></div>
                                                <?php if (!empty($rec['fee_charged'])): ?><div class="rec-field"><div class="rec-label">Fee Collected</div><div class="rec-value" style="font-weight:700;color:var(--success);">₱<?php echo number_format($rec['fee_charged'], 2); ?></div></div><?php endif; ?>
                                                <div class="rec-field"><div class="rec-label">Recorded By</div><div class="rec-value"><?php echo em($rec['recorded_by_name']); ?></div></div>
                                                <div class="rec-field" style="margin-top:8px;">
                                                    <div class="dropdown d-inline-block">
                                                        <button class="btn btn-xs btn-outline-secondary dropdown-toggle" style="font-size:0.72rem;padding:3px 10px;border-radius:6px;" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bi bi-printer"></i> Print
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.82rem;min-width:180px;">
                                                            <li><a class="dropdown-item" href="../print/dental_record.php?id=<?php echo $rec['id']; ?>&autoprint=1"><i class="bi bi-file-medical me-2"></i>Dental Record</a></li>
                                                            <li><a class="dropdown-item" href="../print/dental_certificate.php?id=<?php echo $rec['id']; ?>"><i class="bi bi-patch-check me-2"></i>Dental Certificate</a></li>
                                                            <li><a class="dropdown-item" href="../print/prescription.php?id=<?php echo $rec['id']; ?>"><i class="bi bi-prescription2 me-2"></i>Prescription</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="card tab-section" data-tab="billing">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-receipt me-2" style="color:var(--success);"></i>Payment History <span class="badge bg-success ms-1"><?php echo count($payments); ?></span></span>
                        <a href="../billing/create.php?patient_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-plus"></i> Add</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($payments)): ?>
                            <div class="text-center py-4 text-muted"><i class="bi bi-receipt-cutoff" style="font-size:2rem;opacity:0.3;"></i><p class="mt-2 mb-0 small">No payment records.</p></div>
                        <?php else: ?>
                        <div class="mobile-card-table-wrap">
                        <table class="table table-sm table-hover mb-0 mobile-card-table">
                            <thead><tr><th>Service</th><th>Due</th><th>Paid</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($payments as $py): ?>
                                <tr>
                                    <td data-label="Service"><?php echo e($py['service_name'] ?? 'N/A'); ?></td>
                                    <td data-label="Due">P<?php echo number_format($py['amount_due'],2); ?></td>
                                    <td data-label="Paid">P<?php echo number_format($py['amount_paid'],2); ?></td>
                                    <td data-label="Method"><?php echo ucfirst($py['payment_method'] ?? '&mdash;'); ?></td>
                                    <td data-label="Status"><span class="badge bg-<?php echo match($py['payment_status']){'paid'=>'success','partial'=>'warning',default=>'danger'}; ?>"><?php echo ucfirst($py['payment_status']); ?></span></td>
                                    <td data-label="Date"><?php echo date('M d, Y', strtotime($py['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /RIGHT -->
        </div>
    </div><!-- /page-content -->
</div><!-- /main-content -->



<script>
const PATIENT_ID = <?php echo $id; ?>;
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
const MEDIA_URL  = '<?php echo BASE_URL; ?>api/patient_media.php';

function triggerPhotoUpload() { document.getElementById('photoFileInput').click(); }

function openPhotoLightbox() {
    const img = document.getElementById('heroPhotoImg');
    const lb  = document.getElementById('photoLightbox');
    const lbImg = document.getElementById('lightboxImg');
    const lbPh  = document.getElementById('lightboxPlaceholder');
    if (!lb) return;
    const hasPhoto = img && img.src && img.style.display !== 'none' && img.src !== window.location.href;
    if (hasPhoto) {
        lbImg.src = img.src;
        lbImg.style.display = 'block';
        if (lbPh) lbPh.style.display = 'none';
    } else {
        lbImg.style.display = 'none';
        if (lbPh) lbPh.style.display = 'flex';
    }
    lb.style.display = 'flex';
    document.addEventListener('keydown', lbKeyClose);
}

function closePhotoLightbox() {
    const lb = document.getElementById('photoLightbox');
    if (lb) lb.style.display = 'none';
    document.removeEventListener('keydown', lbKeyClose);
}

function lbKeyClose(e) { if (e.key === 'Escape') closePhotoLightbox(); }

function apiPost(url, fd) {
    return fetch(url, {method:'POST', body:fd})
        .then(r => r.json())
        .then(data => {
            if (data.status === 'error' && !('ok' in data))
                return {ok: false, error: data.message || 'Server error.'};
            return data;
        })
        .catch(() => ({ok: false, error: 'Network error or invalid response.'}));
}



function uploadPhoto(input) {
    const file = input.files[0]; if (!file) return;
    const fd = new FormData();
    fd.append('action','upload_photo'); fd.append('patient_id',PATIENT_ID);
    fd.append('photo',file); fd.append('_csrf',CSRF_TOKEN);
    apiPost(MEDIA_URL, fd).then(data=>{
        if (!data.ok) return showToast(data.error || 'Upload failed.','danger');
        const img=document.getElementById('heroPhotoImg');
        const ph=document.getElementById('heroPhotoPlaceholder');
        img.src=data.path+'?t='+Date.now(); img.style.display='block';
        if(ph) ph.style.display='none';
        const lbImg=document.getElementById('lightboxImg'); if(lbImg) lbImg.src=img.src;
        // Show "Change" label and reveal Remove button without full reload
        const cameraBtn=document.getElementById('photoCameraBtn');
        if(cameraBtn) cameraBtn.innerHTML='<i class="bi bi-camera"></i> Change Photo';
        const removeBtn=document.getElementById('photoRemoveBtn');
        if(removeBtn) removeBtn.style.display='';
        showToast('Photo updated!','success');
    }).catch(()=>showToast('Upload failed. Try again.','danger'));
}

function deletePhoto() {
    if(!confirm('Remove this patient photo?')) return;
    const fd=new FormData(); fd.append('action','delete_photo'); fd.append('patient_id',PATIENT_ID); fd.append('_csrf',CSRF_TOKEN);
    apiPost(MEDIA_URL, fd).then(d=>{
        if(!d.ok) return showToast(d.error || 'Delete failed.','danger');
        const img=document.getElementById('heroPhotoImg');
        const ph=document.getElementById('heroPhotoPlaceholder');
        if(img){img.src='';img.style.display='none';}
        if(ph) ph.style.display='';
        const cameraBtn=document.getElementById('photoCameraBtn');
        if(cameraBtn) cameraBtn.innerHTML='<i class="bi bi-camera"></i> Add Photo';
        const removeBtn=document.getElementById('photoRemoveBtn');
        if(removeBtn) removeBtn.style.display='none';
        showToast('Photo removed','info');
    });
}

function openInlineRecord() {
    var panel = document.getElementById('inlineRecordPanel');
    if (!panel) return;
    panel.style.display = 'block';
    panel.scrollIntoView({behavior:'smooth', block:'start'});
}
function closeInlineRecord() {
    var panel = document.getElementById('inlineRecordPanel');
    if (panel) panel.style.display = 'none';
    var det = document.getElementById('recDetailsPanel');
    if (det) det.style.display = 'none';
    var chev = document.getElementById('recDetailsChevron');
    if (chev) chev.style.transform = '';
}
function toggleRecDetails(btn) {
    var det = document.getElementById('recDetailsPanel');
    var chev = document.getElementById('recDetailsChevron');
    var label = document.getElementById('recDetailsLabel');
    if (!det) return;
    var open = det.style.display !== 'none';
    det.style.display = open ? 'none' : 'block';
    if (chev) chev.style.transform = open ? '' : 'rotate(90deg)';
    if (label) label.textContent = open
        ? '\u00a0Add details \u2014 teeth, diagnosis, medications...'
        : '\u00a0Hide details';
}
// Auto-open if there was a form error on submit
<?php if ($inline_error): ?>
document.addEventListener('DOMContentLoaded', function(){ openInlineRecord(); });
<?php endif; ?>

function toggleRec(btn,id) {
    const body=document.getElementById(id); const isOpen=body.classList.contains('show');
    body.classList.toggle('show',!isOpen); btn.classList.toggle('open',!isOpen);
}

function switchTab(tab, btn) {
    document.querySelectorAll('.ptab').forEach(t=>t.classList.remove('active'));
    if(btn) btn.classList.add('active');
    const left=document.querySelector('.profile-left'), right=document.querySelector('.profile-right');
    if(tab==='info'){left.classList.add('active');right.classList.remove('active');}
    else{left.classList.remove('active');right.classList.add('active');
        document.querySelectorAll('.profile-right .tab-section').forEach(s=>s.style.display=s.dataset.tab===tab?'block':'none');}
}
if(window.innerWidth>960) document.querySelectorAll('.profile-right .tab-section').forEach(s=>s.style.display='block');
window.addEventListener('resize',()=>{
    if(window.innerWidth>960){
        document.querySelectorAll('.profile-right .tab-section').forEach(s=>s.style.display='block');
        document.querySelector('.profile-left')?.classList.add('active');
        document.querySelector('.profile-right')?.classList.add('active');
    }
});



function showToast(msg,type='info'){
    const t=document.createElement('div');
    const colors={'success':'var(--success)','danger':'var(--danger)','info':'var(--primary)','warning':'var(--warning-light)'};
    t.style.cssText='position:fixed;bottom:24px;right:24px;z-index:99999;background:'+(colors[type]||colors.info)+';color:#fff;padding:10px 18px;border-radius:10px;font-size:0.84rem;font-weight:600;box-shadow:var(--shadow-lg);animation:cardIn 0.25s cubic-bezier(0,0,0.2,1) both;display:flex;align-items:center;gap:8px;min-width:180px;';
    const icons={'success':'bi-check-circle-fill','danger':'bi-exclamation-circle-fill','info':'bi-info-circle-fill'};
    t.innerHTML='<i class="bi '+(icons[type]||icons.info)+'"></i>'+msg;
    document.body.appendChild(t);
    setTimeout(()=>{t.style.opacity='0';t.style.transform='translateY(8px)';t.style.transition='0.3s ease';setTimeout(()=>t.remove(),300);},2800);
}
</script>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
