<?php
// Database Backup — admin-only. Generates a complete SQL dump (every table's
// structure + data) and serves it as a downloadable .sql file.
//
// Pure PHP/PDO — no shell_exec('mysqldump ...'), works on Laragon, XAMPP,
// or Railway without needing mysqldump on the PATH.

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_admin();

$page_title = 'Database Backup';

// ── SQL Generator ─────────────────────────────────────────────────────────────
function generate_full_backup_sql(PDO $conn): string {
    $out  = "-- ============================================================\n";
    $out .= "-- Dental Clinic Management and Recording System — Database Backup\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Restore:   mysql -u root cap < this_file.sql\n";
    $out .= "--         or phpMyAdmin → select database → Import\n";
    $out .= "-- ============================================================\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $out .= "SET NAMES utf8mb4;\n\n";

    $tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $out .= "-- --------------------------------------------------------\n";
        $out .= "-- Table: `$table`\n";
        $out .= "-- --------------------------------------------------------\n";
        $out .= "DROP TABLE IF EXISTS `$table`;\n";
        $createRow = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $out      .= $createRow['Create Table'] . ";\n\n";

        $rowCount = (int) $conn->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($rowCount > 0) {
            $stmt    = $conn->query("SELECT * FROM `$table`");
            $columns = null;
            $batch   = [];
            $batchSize = 200;

            $flush = function () use (&$batch, &$out, $table, &$columns) {
                if (empty($batch)) return;
                $out .= "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES\n"
                      . implode(",\n", $batch) . ";\n";
                $batch = [];
            };

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($columns === null) $columns = array_keys($row);
                $values  = array_map(fn($v) => $v === null ? 'NULL' : $conn->quote((string)$v), array_values($row));
                $batch[] = '(' . implode(',', $values) . ')';
                if (count($batch) >= $batchSize) $flush();
            }
            $flush();
            $out .= "\n";
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

// ── POST: Download ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    validate_csrf();

    $filename = 'cap_backup_' . date('Y-m-d_His') . '.sql';

    // Persist last-backup timestamp BEFORE dump so the snapshot includes it
    $conn->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES ('last_backup_at', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    )->execute([date('Y-m-d H:i:s')]);

    // Count tables and rows for the log detail
    $tables     = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $totalRows  = 0;
    foreach ($tables as $t) $totalRows += (int)$conn->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();

    log_action(
        $conn, $_SESSION['user_id'] ?? null, $_SESSION['full_name'] ?? 'Unknown',
        'Generated database backup', 'settings', null,
        $filename . ' — ' . count($tables) . ' tables, ' . number_format($totalRows) . ' records'
    );

    $sql = generate_full_backup_sql($conn);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $sql;
    exit();
}

// ── GET: Info page ─────────────────────────────────────────────────────────────
$tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

// Per-table row counts
$table_stats = [];
$total_rows  = 0;
foreach ($tables as $t) {
    $cnt = (int)$conn->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $table_stats[$t] = $cnt;
    $total_rows += $cnt;
}

// Estimate compressed SQL size: average ~120 bytes overhead per row + schema
$estimated_kb = round((($total_rows * 120) + (count($tables) * 800)) / 1024);

// Last backup time
$last_backup_at = null;
try {
    $lb  = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_backup_at' LIMIT 1");
    $lb->execute();
    $row = $lb->fetch(PDO::FETCH_ASSOC);
    $last_backup_at = $row['setting_value'] ?? null;
} catch (Exception $e) { $last_backup_at = null; }

// Days since last backup (for the warning)
$days_since = null;
if ($last_backup_at) {
    $days_since = (int)floor((time() - strtotime($last_backup_at)) / 86400);
}

// Backup history from activity log (last 10)
$history = [];
try {
    $hs = $conn->prepare(
        "SELECT user_name, details, created_at FROM activity_logs
         WHERE action = 'Generated database backup'
         ORDER BY created_at DESC LIMIT 10"
    );
    $hs->execute();
    $history = $hs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $history = []; }

?><!DOCTYPE html>
<html lang="en">
<head><?php include '../../includes/head.php'; ?>
<style>
/* ── Backup page: at-a-glance stat strip ─────────────────────── */
.bkp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
.bkp-stat {
    display: flex; align-items: center; gap: 14px;
    background: #fff; border: 1px solid var(--gray-100); border-radius: 14px;
    padding: 16px 18px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
[data-theme="dark"] .bkp-stat { background: var(--gray-100); border-color: var(--gray-200); }
.bkp-stat-icon {
    width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
}
.bkp-stat-icon.teal { background: rgba(13,110,110,0.1); color: var(--primary); }
.bkp-stat-icon.gold { background: rgba(201,168,76,0.16); color: var(--accent-dark); }
.bkp-stat-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
[data-theme="dark"] .bkp-stat-icon.blue { color: #60a5fa; }
.bkp-stat-value { font-size: 1.5rem; font-weight: 800; line-height: 1.1; color: var(--gray-900); }
.bkp-stat-label { font-size: 0.72rem; color: var(--gray-500); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px; }

/* ── Table breakdown — compact chip grid instead of a tall list ── */
.bkp-table-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 1px; background: var(--gray-100);
    max-height: 320px; overflow-y: auto;
}
[data-theme="dark"] .bkp-table-grid { background: var(--gray-200); }
.bkp-table-chip {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 10px 16px; background: var(--white); font-size: 0.78rem;
}
[data-theme="dark"] .bkp-table-chip { background: var(--gray-100); }
.bkp-table-chip .name { font-family: monospace; color: var(--gray-700); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bkp-table-chip .cnt  { font-weight: 700; color: var(--gray-500); flex-shrink: 0; }

/* ── Backup history list ──────────────────────────────────────── */
.bkp-hist-item { padding: 10px 16px; border-bottom: 1px solid var(--gray-100); font-size: 0.8rem; }
[data-theme="dark"] .bkp-hist-item { border-color: var(--gray-200); }
.bkp-hist-item:last-child { border-bottom: none; }
.bkp-hist-when   { font-weight: 600; color: var(--gray-800); }
.bkp-hist-by     { color: var(--gray-500); font-size: 0.74rem; }
.bkp-hist-detail { color: var(--gray-500); font-size: 0.73rem; margin-top: 2px; font-family: monospace; word-break: break-all; }
</style>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <div class="page-header" style="margin-bottom:20px;">
            <div>
                <h5>Database Backup</h5>
                <small class="text-muted">Download a full SQL snapshot of your database</small>
            </div>
        </div>

        <!-- Status banner -->
        <?php if (!$last_backup_at): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No backup has been taken yet. Download one now.</div>
        <?php elseif ($days_since !== null && $days_since > 7): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Last backup was <strong><?php echo $days_since; ?> days ago</strong>. Consider downloading a fresh one.</div>
        <?php else: ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Backup is up to date — last taken <?php echo $days_since === 0 ? 'today' : $days_since . ' day' . ($days_since !== 1 ? 's' : '') . ' ago'; ?>.</div>
        <?php endif; ?>

        <div class="bkp-stats">
            <div class="bkp-stat">
                <div class="bkp-stat-icon teal"><i class="bi bi-table"></i></div>
                <div><div class="bkp-stat-value"><?php echo count($tables); ?></div><div class="bkp-stat-label">Tables</div></div>
            </div>
            <div class="bkp-stat">
                <div class="bkp-stat-icon gold"><i class="bi bi-stack"></i></div>
                <div><div class="bkp-stat-value"><?php echo number_format($total_rows); ?></div><div class="bkp-stat-label">Total Records</div></div>
            </div>
            <div class="bkp-stat">
                <div class="bkp-stat-icon blue"><i class="bi bi-hdd"></i></div>
                <div><div class="bkp-stat-value">~<?php echo number_format($estimated_kb); ?><span style="font-size:0.95rem;">KB</span></div><div class="bkp-stat-label">Estimated Size</div></div>
            </div>
        </div>

        <div class="row g-3 align-items-start">

            <!-- Download card -->
            <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-download me-2" style="color:var(--primary);"></i>Download Backup</div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:0.875rem;margin-bottom:16px;">
                        Downloads a <code>.sql</code> file with every table's structure and all current data.
                        Nothing is uploaded anywhere — the file goes straight to your Downloads folder.
                    </p>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="backup">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-download"></i> Download Backup <span class="text-white-50" style="font-size:0.8rem;">~<?php echo $estimated_kb; ?>KB</span>
                        </button>
                    </form>
                    <small class="text-muted d-block mt-2"><i class="bi bi-shield-lock me-1"></i>Contains real patient data — store the file securely.</small>
                    <?php if ($last_backup_at): ?>
                    <div style="margin-top:14px;padding-top:14px;border-top:var(--border);font-size:0.8rem;color:var(--gray-500);">
                        <i class="bi bi-clock me-1"></i>Last backup: <strong><?php echo date('M d, Y g:i A', strtotime($last_backup_at)); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>

            <!-- Table breakdown -->
            <div class="col-12 col-lg-7">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-grid-3x3-gap me-2" style="color:var(--primary);"></i>Table Breakdown</span>
                        <span class="text-muted" style="font-size:0.72rem;font-weight:400;"><?php echo count($tables); ?> tables</span>
                    </div>
                    <div class="bkp-table-grid">
                        <?php arsort($table_stats); foreach ($table_stats as $tname => $cnt): ?>
                        <div class="bkp-table-chip">
                            <span class="name"><?php echo e($tname); ?></span>
                            <span class="cnt"><?php echo number_format($cnt); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup history -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-clock-history me-2" style="color:var(--primary);"></i>Backup History</div>
            <?php if (empty($history)): ?>
            <div class="card-body text-center text-muted" style="font-size:0.85rem;padding:28px;">
                No backups taken yet — your first download will show up here.
            </div>
            <?php else: ?>
            <div style="max-height:280px;overflow-y:auto;">
                <?php foreach ($history as $h): ?>
                <div class="bkp-hist-item">
                    <div class="bkp-hist-when"><?php echo date('M d, Y g:i A', strtotime($h['created_at'])); ?></div>
                    <div class="bkp-hist-by">by <?php echo e($h['user_name'] ?? 'Unknown'); ?></div>
                    <?php if (!empty($h['details'])): ?><div class="bkp-hist-detail"><?php echo e($h['details']); ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- How to restore -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-arrow-counterclockwise me-2" style="color:var(--warning);"></i>How to Restore</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <p style="font-size:0.84rem;font-weight:600;margin-bottom:6px;">Option A — phpMyAdmin</p>
                        <ol style="font-size:0.84rem;color:var(--gray-600);margin:0;padding-left:18px;line-height:2;">
                            <li>Open phpMyAdmin and select the <code>cap</code> database</li>
                            <li>Go to the <strong>Import</strong> tab</li>
                            <li>Choose the downloaded <code>.sql</code> file → click Go</li>
                        </ol>
                    </div>
                    <div class="col-12 col-md-6">
                        <p style="font-size:0.84rem;font-weight:600;margin-bottom:6px;">Option B — Terminal</p>
                        <code style="display:block;background:var(--gray-50);padding:10px 14px;border-radius:8px;font-size:0.82rem;border:var(--border);">mysql -u root cap &lt; cap_backup_YYYYMMDD.sql</code>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>