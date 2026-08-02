<?php
// patient_media.php — Upload & delete patient photos and X-rays.
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Ensure auth variables are defined
$current_user_id   = $current_user_id ?? 0;
$current_user_name = $current_user_name ?? 'Unknown';

// ── Helpers ────────────────────────────────────────────────
function json_ok($data = [])  { echo json_encode(['ok' => true]  + $data); exit; }
function json_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function allowed_image($file) {
    $allowed_mime = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return in_array($finfo->file($file['tmp_name']), $allowed_mime, true);
}

function make_upload_dir($rel) {
    $abs = __DIR__ . '/../uploads/' . $rel;
    if (!is_dir($abs)) mkdir($abs, 0755, true);
    return $abs;
}

// ── Auto-create tables if migration hasn't been run ─────────
function ensure_tables(PDO $conn): void {
    // Add photo_path column to patients if missing
    try {
        $cols = $conn->query("SHOW COLUMNS FROM `patients` LIKE 'photo_path'")->fetchAll();
        if (empty($cols)) {
            $conn->exec("ALTER TABLE `patients` ADD COLUMN `photo_path` VARCHAR(500) DEFAULT NULL");
        }
    } catch (PDOException $e) {
        // Ignore — column may already exist
    }

    // Create patient_xrays table if missing
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `patient_xrays` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `patient_id`  INT NOT NULL,
            `file_path`   VARCHAR(500) NOT NULL,
            `file_name`   VARCHAR(200) NOT NULL,
            `file_size`   INT DEFAULT 0,
            `label`       VARCHAR(200) DEFAULT NULL,
            `notes`       TEXT DEFAULT NULL,
            `uploaded_by` INT DEFAULT NULL,
            `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_xray_patient` (`patient_id`),
            FOREIGN KEY (`patient_id`)  REFERENCES `patients`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    } catch (PDOException $e) {
        // Table already exists — fine
    }
}

ensure_tables($conn);

// ── Upload patient photo ────────────────────────────────────
if ($action === 'upload_photo') {
    validate_csrf();
    $pid = secure_int($_POST['patient_id'] ?? 0);
    if (!$pid) json_err('Invalid patient.');

    $chk = $conn->prepare("SELECT id FROM patients WHERE id=? AND is_active=TRUE LIMIT 1");
    $chk->execute([$pid]);
    if (!$chk->fetch()) json_err('Patient not found.');

    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $err_map = [1=>'File too large (server limit).',2=>'File too large.',3=>'Upload incomplete.',4=>'No file chosen.'];
        $code = $_FILES['photo']['error'] ?? 4;
        json_err($err_map[$code] ?? 'Upload error code ' . $code . '.');
    }

    $file = $_FILES['photo'];
    if ($file['size'] > 5 * 1024 * 1024) json_err('Photo must be under 5 MB.');
    if (!allowed_image($file)) json_err('Only JPEG, PNG, WebP or GIF images are accepted.');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
    $filename = 'photo_' . $pid . '_' . time() . '.' . $ext;
    $dir  = make_upload_dir('photos');
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest))
        json_err('Failed to save file. Check that uploads/photos/ is writable.');

    // Delete old photo file
    $old = $conn->prepare("SELECT photo_path FROM patients WHERE id=? LIMIT 1");
    $old->execute([$pid]);
    $row = $old->fetch(PDO::FETCH_ASSOC);
    if (!empty($row['photo_path'])) {
        $oldpath = __DIR__ . '/../' . $row['photo_path'];
        if (file_exists($oldpath)) @unlink($oldpath);
    }

    $rel_path = 'uploads/photos/' . $filename;
    $conn->prepare("UPDATE patients SET photo_path=? WHERE id=?")->execute([$rel_path, $pid]);

    log_action($conn, $current_user_id, $current_user_name, 'Uploaded Patient Photo', 'patients', $pid, $filename);
    json_ok(['path' => BASE_URL . $rel_path, 'rel' => $rel_path]);
}

// ── Delete patient photo ────────────────────────────────────
if ($action === 'delete_photo') {
    validate_csrf();
    $pid = secure_int($_POST['patient_id'] ?? 0);
    if (!$pid) json_err('Invalid patient.');

    $row = $conn->prepare("SELECT photo_path FROM patients WHERE id=? LIMIT 1");
    $row->execute([$pid]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    if (!empty($r['photo_path'])) {
        $abs = __DIR__ . '/../' . $r['photo_path'];
        if (file_exists($abs)) @unlink($abs);
    }
    $conn->prepare("UPDATE patients SET photo_path=NULL WHERE id=?")->execute([$pid]);
    log_action($conn, $current_user_id, $current_user_name, 'Deleted Patient Photo', 'patients', $pid, '');
    json_ok();
}

// ── Upload X-ray ────────────────────────────────────────────
if ($action === 'upload_xray') {
    validate_csrf();
    $pid   = secure_int($_POST['patient_id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if (!$pid) json_err('Invalid patient.');

    $chk = $conn->prepare("SELECT id FROM patients WHERE id=? AND is_active=TRUE LIMIT 1");
    $chk->execute([$pid]);
    if (!$chk->fetch()) json_err('Patient not found.');

    if (empty($_FILES['xray']) || $_FILES['xray']['error'] !== UPLOAD_ERR_OK) {
        $err_map = [1=>'File too large (server limit).',2=>'File too large.',3=>'Upload incomplete.',4=>'No file chosen.'];
        $code = $_FILES['xray']['error'] ?? 4;
        json_err($err_map[$code] ?? 'Upload error code ' . $code . '.');
    }

    $file = $_FILES['xray'];
    if ($file['size'] > 10 * 1024 * 1024) json_err('X-ray file must be under 10 MB.');
    if (!allowed_image($file)) json_err('Only JPEG, PNG, WebP or GIF images are accepted.');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
    $filename = 'xray_' . $pid . '_' . time() . '_' . uniqid() . '.' . $ext;
    $dir  = make_upload_dir('xrays');
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest))
        json_err('Failed to save file. Check that uploads/xrays/ is writable.');

    $rel_path = 'uploads/xrays/' . $filename;
    $stmt = $conn->prepare("INSERT INTO patient_xrays (patient_id, file_path, file_name, file_size, label, notes, uploaded_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$pid, $rel_path, $file['name'], $file['size'], $label ?: null, $notes ?: null, $current_user_id]);
    $xray_id = (int)$conn->lastInsertId();

    log_action($conn, $current_user_id, $current_user_name, 'Uploaded X-Ray', 'patient_xrays', $xray_id, "Patient #$pid — " . ($label ?: $file['name']));
    json_ok([
        'id'          => $xray_id,
        'path'        => BASE_URL . $rel_path,
        'label'       => $label,
        'filename'    => $file['name'],
        'uploaded_at' => date('M d, Y'),
    ]);
}

// ── Delete X-ray ────────────────────────────────────────────
if ($action === 'delete_xray') {
    validate_csrf();
    $xid = secure_int($_POST['xray_id'] ?? 0);
    if (!$xid) json_err('Invalid X-ray ID.');

    $row = $conn->prepare("SELECT * FROM patient_xrays WHERE id=? LIMIT 1");
    $row->execute([$xid]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    if (!$r) json_err('X-ray not found.');

    $abs = __DIR__ . '/../' . $r['file_path'];
    if (file_exists($abs)) @unlink($abs);

    $conn->prepare("DELETE FROM patient_xrays WHERE id=?")->execute([$xid]);
    log_action($conn, $current_user_id, $current_user_name, 'Deleted X-Ray', 'patient_xrays', $xid, "Patient #" . $r['patient_id']);
    json_ok();
}

json_err('Unknown action.');
