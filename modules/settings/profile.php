<?php
// My Account — logged-in user can change their profile photo and update their own name/email/phone.

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

$page_title   = 'My Account';
$uid          = (int)($_SESSION['user_id'] ?? 0);
$error        = '';
$success      = '';

// ── Helpers (same pattern as patient_media.php) ──────────────
function prof_allowed_image($file) {
    $allowed_mime = ['image/jpeg','image/jpg','image/png','image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return in_array($finfo->file($file['tmp_name']), $allowed_mime, true);
}

function prof_upload_dir() {
    $abs = __DIR__ . '/../../uploads/profiles';
    if (!is_dir($abs)) mkdir($abs, 0755, true);
    return $abs;
}

// ── Self-healing migration: add profile_photo column if missing ──
try {
    $cols = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_photo'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE `users` ADD COLUMN `profile_photo` VARCHAR(500) DEFAULT NULL");
    }
} catch (PDOException $e) { /* already exists */ }

// ── Load current user ─────────────────────────────────────────
$ld = $conn->prepare("SELECT full_name, username, email, phone, role, profile_photo FROM users WHERE id = ? LIMIT 1");
$ld->execute([$uid]);
$me = $ld->fetch(PDO::FETCH_ASSOC);
$ld->closeCursor();

if (!$me) { header('Location: ../../index.php'); exit(); }

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    // ── Upload photo ──────────────────────────────────────────
    if ($action === 'upload_photo') {
        if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $err_map = [1=>'File too large.',2=>'File too large.',3=>'Upload incomplete.',4=>'No file chosen.'];
            $error = $err_map[$_FILES['photo']['error'] ?? 4] ?? 'Upload error.';
        } elseif ($_FILES['photo']['size'] > 3 * 1024 * 1024) {
            $error = 'Photo must be under 3 MB.';
        } elseif (!prof_allowed_image($_FILES['photo'])) {
            $error = 'Only JPEG, PNG, or WebP images are accepted.';
        } else {
            $file = $_FILES['photo'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
            $fname = 'user_' . $uid . '_' . time() . '.' . $ext;
            $dir   = prof_upload_dir();
            $dest  = $dir . '/' . $fname;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Failed to save file. Check that uploads/profiles/ is writable.';
            } else {
                // Delete old photo
                if (!empty($me['profile_photo'])) {
                    $old = __DIR__ . '/../../' . $me['profile_photo'];
                    if (file_exists($old)) @unlink($old);
                }
                $rel = 'uploads/profiles/' . $fname;
                $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")->execute([$rel, $uid]);
                // Update session-level name so sidebar doesn't need a reload
                $me['profile_photo'] = $rel;
                log_action($conn, $uid, $me['full_name'], 'Updated Profile Photo', 'users', $uid, $fname);
                $success = 'Profile photo updated!';
            }
        }
    }

    // ── Remove photo ──────────────────────────────────────────
    elseif ($action === 'remove_photo') {
        if (!empty($me['profile_photo'])) {
            $old = __DIR__ . '/../../' . $me['profile_photo'];
            if (file_exists($old)) @unlink($old);
        }
        $conn->prepare("UPDATE users SET profile_photo = NULL WHERE id = ?")->execute([$uid]);
        $me['profile_photo'] = null;
        log_action($conn, $uid, $me['full_name'], 'Removed Profile Photo', 'users', $uid, '');
        $success = 'Profile photo removed.';
    }

    // ── Update info ───────────────────────────────────────────
    elseif ($action === 'update_info') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if (empty($full_name)) {
            $error = 'Full name is required.';
        } elseif (empty($email) || !valid_email($email)) {
            $error = 'Valid email is required.';
        } elseif (!empty($phone) && !valid_phone($phone)) {
            $error = 'Invalid phone number format.';
        } else {
            // Check email not taken by someone else
            $ec = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $ec->execute([$email, $uid]);
            if ($ec->fetch()) {
                $error = 'That email is already used by another account.';
            } else {
                $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?")
                     ->execute([$full_name, $email, $phone, $uid]);
                $_SESSION['full_name'] = $full_name;
                $me['full_name'] = $full_name;
                $me['email']     = $email;
                $me['phone']     = $phone;
                log_action($conn, $uid, $full_name, 'Updated Profile Info', 'users', $uid, '');
                $success = 'Profile updated successfully!';
            }
        }
    }
}

// ── Build initials for fallback avatar ───────────────────────
$parts    = explode(' ', $me['full_name']);
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
$photo_url = !empty($me['profile_photo']) ? BASE_URL . $me['profile_photo'] : '';

?><!DOCTYPE html>
<html lang="en">
<head><?php include '../../includes/head.php'; ?></head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <div class="page-header">
            <div>
                <h5>My Account</h5>
                <p>Update your profile photo and personal details</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?php echo e($success); ?></div>
        <?php endif; ?>

        <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">

            <!-- ── Photo card ───────────────────────────────── -->
            <div class="card" style="width:100%;max-width:300px;">
                <div class="card-header"><i class="bi bi-person-circle"></i> Profile Photo</div>
                <div class="card-body" style="text-align:center;padding:24px 20px;">

                    <!-- Avatar display -->
                    <div id="avatar_wrap" style="margin:0 auto 20px;width:110px;height:110px;border-radius:50%;overflow:hidden;
                         border:3px solid var(--blue-200);box-shadow:0 2px 10px rgba(0,0,0,0.1);cursor:pointer;"
                         onclick="document.getElementById('photo_file').click()"
                         title="Click to change photo">
                        <?php if ($photo_url): ?>
                            <img id="avatar_img" src="<?php echo e($photo_url); ?>"
                                 style="width:100%;height:100%;object-fit:cover;" alt="Profile photo">
                        <?php else: ?>
                            <div id="avatar_initials"
                                 style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;
                                 background:linear-gradient(135deg,#2563eb,#1d4ed8);font-size:2.2rem;
                                 font-weight:700;color:#fff;letter-spacing:2px;">
                                <?php echo e($initials); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <p style="font-size:0.78rem;color:var(--gray-500);margin-bottom:16px;">
                        Click the avatar to upload a new photo.<br>JPEG, PNG or WebP · Max 3 MB
                    </p>

                    <!-- Hidden file input -->
                    <form id="photo_form" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="upload_photo">
                        <input type="file" id="photo_file" name="photo" accept="image/jpeg,image/png,image/webp"
                               style="display:none;" onchange="previewAndSubmit(this)">
                    </form>

                    <?php if ($photo_url): ?>
                    <form method="POST" onsubmit="return confirm('Remove your profile photo?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="remove_photo">
                        <button type="submit" class="btn btn-sm btn-outline-danger" style="width:100%;">
                            <i class="bi bi-trash"></i> Remove Photo
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Info card ────────────────────────────────── -->
            <div class="card" style="flex:1;min-width:280px;max-width:500px;">
                <div class="card-header"><i class="bi bi-person-fill"></i> Personal Details</div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_info">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control"
                                       value="<?php echo e($me['full_name']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control"
                                       value="<?php echo e($me['username']); ?>" disabled
                                       style="background:var(--gray-50);color:var(--gray-500);">
                                <div class="form-text">Username cannot be changed.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo e($me['email']); ?>" required>
                            </div>
                            <div class="col-12">
                                <?php
                                    $phone_field_name     = 'phone';
                                    $phone_field_value    = $me['phone'] ?? '';
                                    $phone_field_label    = 'Phone';
                                    $phone_field_required = false;
                                    include '../../includes/phone_input.php';
                                ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control"
                                       value="<?php echo e(ucfirst($me['role'])); ?>" disabled
                                       style="background:var(--gray-50);color:var(--gray-500);">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

    </div>
<script>
function previewAndSubmit(input) {
    if (!input.files || !input.files[0]) return;

    var file = input.files[0];
    if (file.size > 3 * 1024 * 1024) {
        alert('Photo must be under 3 MB.');
        input.value = '';
        return;
    }

    // Show a preview before submitting
    var reader = new FileReader();
    reader.onload = function(e) {
        var wrap = document.getElementById('avatar_wrap');
        wrap.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;" alt="Preview">';
    };
    reader.readAsDataURL(file);

    // Submit the form automatically
    document.getElementById('photo_form').submit();
}
</script>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
