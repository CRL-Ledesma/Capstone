<?php
// List all system users. Toggle active/inactive status.

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_admin();

if (!isset($current_user_id)) {
    $current_user_id = $_SESSION['user_id'] ?? $_SESSION['uid'] ?? $_SESSION['id'] ?? 0;
}
if (!isset($current_user_name)) {
    $current_user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System';
}

$page_title = 'User Management';

// Handle toggle active — fully prepared
if (isset($_POST['toggle']) && isset($_POST['uid'])) {
    validate_csrf();
    $uid = secure_int($_POST['uid'] ?? 0);
    if ($uid > 0 && $uid !== $current_user_id) {
        $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = null;
        if ($user) {
            $new_status = $user['is_active'] ? 0 : 1;
            $stmt2 = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt2->execute([$new_status, $uid]);
            $label = $new_status ? 'Activated User' : 'Deactivated User';
            log_action($conn, $current_user_id, $current_user_name, $label, 'users', $uid);
        }
    }
    header('Location: list.php');
    exit();
}

// Self-healing: add profile_photo column if missing
try {
    $cols = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_photo'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE `users` ADD COLUMN `profile_photo` VARCHAR(500) DEFAULT NULL");
    }
} catch (PDOException $e) { /* already exists */ }

$users = $conn->query("SELECT * FROM users ORDER BY role ASC, full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
                <h5>User Management</h5>
            </div>
            <div class="page-header-actions">
                <a href="add.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-person-plus"></i> Add User
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="mobile-card-table-wrap">
<table class="table table-hover mb-0 mobile-card-table">
                    <thead>
                        <tr>
                            <th style="width:48px;"></th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <?php
                            // Build initials for fallback
                            $u_words = explode(' ', trim($u['full_name']));
                            $u_ini   = strtoupper(count($u_words) > 1 ? $u_words[0][0] . end($u_words)[0] : substr($u['full_name'], 0, 1));
                            $u_has_photo = !empty($u['profile_photo']) && file_exists('../../' . $u['profile_photo']);
                            $u_photo_url = $u_has_photo ? BASE_URL . $u['profile_photo'] : '';
                        ?>
                        <tr>
                            <td style="padding:6px 10px;width:44px;">
                                <?php if ($u_has_photo): ?>
                                    <img src="<?php echo htmlspecialchars($u_photo_url); ?>" alt=""
                                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--gray-200);display:block;">
                                <?php else: ?>
                                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light,#3b82f6));display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                                        <?php echo htmlspecialchars($u_ini); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Full Name"><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td data-label="Username"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td data-label="Role">
                                <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </td>
                            <td data-label="Email"><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                            <td data-label="Phone"><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                            <td data-label="Status">
                                <span class="badge bg-<?php echo $u['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td data-label="Created"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td data-label="Actions">
                                <a href="edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <?php if ($u['id'] !== $current_user_id): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="toggle" value="1">
                                        <input type="hidden" name="uid" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'warning' : 'success'; ?>">
                                            <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
