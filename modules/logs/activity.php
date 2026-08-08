<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_admin();

$page_title = 'Audit Logs';

$per_page = 50;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$search  = trim($_GET['search'] ?? '');
$filter  = trim($_GET['module'] ?? '');

$where = '1=1';
$params = [];

if ($search !== '') {
    $where   .= " AND (l.user_name LIKE ? OR l.action LIKE ? OR l.details LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($filter !== '') {
    $where  .= " AND l.module = ?";
    $params[] = $filter;
}

$count_sql = "SELECT COUNT(*) as c FROM audit_logs l WHERE $where";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params ?: []);
$total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];

$total_pages = max(1, (int)ceil($total / $per_page));

$sql  = "SELECT l.*, u.full_name as user_full FROM audit_logs l
         LEFT JOIN users u ON l.user_id = u.id
         WHERE $where ORDER BY l.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt2 = $conn->prepare($sql);
$stmt2->execute($params);
$logs = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$modules = $conn->query("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_ASSOC);

// Action badge color map
function action_badge_class(string $action): string {
    $action = strtolower($action);
    if (str_contains($action, 'delete') || str_contains($action, 'remove'))  return 'danger';
    if (str_contains($action, 'create') || str_contains($action, 'add'))     return 'success';
    if (str_contains($action, 'update') || str_contains($action, 'edit'))    return 'warning';
    if (str_contains($action, 'login')  || str_contains($action, 'logout'))  return 'info';
    return 'secondary';
}
?><!DOCTYPE html>
<html lang="en">
<head><?php include '../../includes/head.php'; ?>
<style>
/* ── Audit Logs — responsive card table ─────────── */
.audit-filter-bar {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    padding: 14px 16px;
    margin-bottom: 16px;
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
    box-shadow: var(--shadow-xs);
}
.audit-filter-bar input,
.audit-filter-bar select { font-size: 0.85rem; }

.audit-table-wrap {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.audit-table-wrap table { margin: 0; }
.audit-table-wrap thead tr {
    background: var(--gray-50);
    border-bottom: 2px solid var(--gray-200);
}
.audit-table-wrap thead th {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--gray-400);
    padding: 11px 14px;
    border: none;
    white-space: nowrap;
}
.audit-table-wrap tbody tr {
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.12s;
}
.audit-table-wrap tbody tr:last-child { border-bottom: none; }
.audit-table-wrap tbody tr:hover { background: var(--blue-50); }
.audit-table-wrap tbody td {
    padding: 11px 14px;
    vertical-align: middle;
    border: none;
    font-size: 0.83rem;
}
.audit-table-wrap .table-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    background: var(--gray-50);
}

/* Action badges */
.action-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 99px;
    font-size: 0.71rem; font-weight: 700; letter-spacing: 0.02em;
    white-space: nowrap;
}
.action-badge.danger  { background: var(--danger-bg);  color: var(--danger);  border: 1px solid var(--danger-border); }
.action-badge.success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.action-badge.warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.action-badge.info    { background: #eff6ff; color: var(--blue-600); border: 1px solid var(--blue-200); }
.action-badge.secondary { background: var(--gray-100); color: var(--gray-600); border: 1px solid var(--gray-200); }

/* Mobile card layout */
@media (max-width: 640px) {
    .audit-filter-bar { flex-direction: column; align-items: stretch; }
    .audit-filter-bar input,
    .audit-filter-bar select { width: 100%; }

    .audit-table-wrap thead { display: none; }
    .audit-table-wrap tbody tr {
        display: block;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        margin: 10px 10px 0;
        padding: 4px 0 8px;
        box-shadow: var(--shadow-xs);
    }
    .audit-table-wrap tbody tr:last-child { margin-bottom: 10px; }
    .audit-table-wrap tbody td {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 7px 14px;
        border-bottom: 1px solid var(--gray-100);
        font-size: 0.82rem;
    }
    .audit-table-wrap tbody td:last-child { border-bottom: none; }
    .audit-table-wrap tbody td::before {
        content: attr(data-label);
        font-size: 0.64rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--gray-400);
        min-width: 60px;
        padding-top: 2px;
        flex-shrink: 0;
    }
    .audit-table-wrap .table-footer {
        flex-direction: column;
        text-align: center;
    }
}

/* Dark mode */
[data-theme="dark"] .audit-filter-bar,
[data-theme="dark"] .audit-table-wrap { background: var(--gray-200); border-color: var(--gray-300); }
[data-theme="dark"] .audit-table-wrap thead tr { background: var(--gray-100); border-color: var(--gray-300); }
[data-theme="dark"] .audit-table-wrap tbody tr { border-color: var(--gray-300); }
[data-theme="dark"] .audit-table-wrap tbody tr:hover { background: rgba(77,134,240,0.08); }
[data-theme="dark"] .audit-table-wrap .table-footer { background: var(--gray-100); border-color: var(--gray-300); }
</style>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h5>Audit Logs</h5>
                <p style="color:var(--gray-400);font-size:0.82rem;margin:0;">Track all system actions and changes</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="get" class="audit-filter-bar">
            <input type="text" name="search" class="form-control form-control-sm"
                   style="max-width:260px;"
                   placeholder="🔍  User, action, details…"
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="module" class="form-select form-select-sm" style="max-width:180px;">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?php echo htmlspecialchars($m['module']); ?>"
                    <?php echo $filter === $m['module'] ? 'selected' : ''; ?>>
                    <?php echo ucfirst(htmlspecialchars($m['module'])); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="activity.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </form>

        <!-- Logs Table -->
        <div class="audit-table-wrap">
            <div class="mobile-card-table-wrap">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:60px;color:var(--gray-400);">
                            <i class="bi bi-clipboard-x" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.4;"></i>
                            <div style="font-weight:600;margin-bottom:4px;">No audit logs found</div>
                            <div style="font-size:0.78rem;">Try adjusting your filters</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log):
                        $cls = action_badge_class($log['action']);
                    ?>
                    <tr>
                        <td data-label="Date" style="white-space:nowrap;color:var(--gray-500);font-size:0.78rem;">
                            <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                            <div style="font-size:0.72rem;color:var(--gray-400);"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                        </td>
                        <td data-label="User" style="font-weight:600;font-size:0.84rem;">
                            <?php echo htmlspecialchars($log['user_name'] ?? '—'); ?>
                        </td>
                        <td data-label="Action">
                            <span class="action-badge <?php echo $cls; ?>">
                                <?php echo htmlspecialchars(strtoupper($log['action'])); ?>
                            </span>
                        </td>
                        <td data-label="Module" style="font-size:0.82rem;color:var(--gray-600);">
                            <?php echo htmlspecialchars(ucfirst($log['module'] ?? '—')); ?>
                        </td>
                        <td data-label="Details"
                            style="font-size:0.78rem;color:var(--gray-500);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                            <?php echo htmlspecialchars($log['details'] ?? '—'); ?>
                        </td>
                        <td data-label="IP" style="font-size:0.75rem;color:var(--gray-400);white-space:nowrap;">
                            <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>

            <!-- Footer / Pagination -->
            <div class="table-footer">
                <small style="color:var(--gray-400);font-size:0.78rem;">
                    Showing <strong><?php echo number_format(count($logs)); ?></strong>
                    of <strong><?php echo number_format($total); ?></strong> entries
                </small>

                <?php if ($total_pages > 1):
                    $qs = '&search=' . urlencode($search) . '&module=' . urlencode($filter);
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end   = min($total_pages, $page + $range);
                ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $qs; ?>">‹</a>
                        </li>
                        <?php if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1<?php echo $qs; ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $qs; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $qs; ?>"><?php echo $total_pages; ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $qs; ?>">›</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php include '../../includes/footer.php'; ?>
</div>
</body>
</html>
