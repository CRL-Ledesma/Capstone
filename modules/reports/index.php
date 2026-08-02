<?php
// Generate monthly appointment and revenue reports, exportable to PDF.

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_admin();

$page_title = 'Reports';

$month       = $_GET['month'] ?? date('Y-m');

// SECURITY: strictly validate month format (YYYY-MM) before using it anywhere —
// this value comes straight from the query string, so we never trust it as-is.
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

// Monthly Appointments Report
$month_start = $month . '-01';
$month_end   = date('Y-m-t', strtotime($month . '-01'));

$stmt = $conn->prepare("
    SELECT a.appointment_code, CONCAT(p.first_name,' ',p.last_name) as patient_name,
        s.service_name, a.appointment_date, a.appointment_time,
        a.status, b.amount_paid
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN services s ON a.service_id = s.id
    LEFT JOIN bills b ON b.appointment_id = a.id
    WHERE a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute([$month_start, $month_end]);
$monthly_appts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();
$stmt = null;

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount_paid),0) as total
    FROM bills
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$month_start, $month_end]);
$monthly_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt->closeCursor();
$stmt = null;

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT patient_id) as c FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
");
$stmt->execute([$month_start, $month_end]);
$total_patients_month = $stmt->fetch(PDO::FETCH_ASSOC)['c'];
$stmt->closeCursor();
$stmt = null;
?><!DOCTYPE html>
<html lang="en">
<head><?php include '../../includes/head.php'; ?></head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5>Reports</h5>
            <div style="display:flex;gap:8px;">
                <a href="<?php echo BASE_URL; ?>modules/print/daily_schedule.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline-primary" >
                    <i class="bi bi-calendar-day"></i> Today's Schedule
                </a>
                <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print / Save as PDF
                </button>
            </div>
        </div>

        <!-- Filter -->
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-3">
                <label class="form-label small">Month</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($month); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary w-100">Generate</button>
            </div>
        </form>

        <!-- Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Revenue</h6>
                        <h3>₱<?php echo number_format($monthly_revenue, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Completed</h6>
                        <h3><?php echo count(array_filter($monthly_appts, fn($a) => $a['status'] === 'completed')); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="card">
            <div class="card-header">
                Appointments for <?php echo date('F Y', strtotime($month_start)); ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
<table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Patient</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthly_appts)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No appointments for this month.</td></tr>
                        <?php else: ?>
                            <?php foreach ($monthly_appts as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['appointment_code']); ?></td>
                                <td><?php echo htmlspecialchars($a['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($a['service_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                                <td><?php echo ucfirst($a['status']); ?></td>
                                <td>₱<?php echo number_format($a['amount_paid'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- PDF export: uses browser's built-in print-to-PDF (no internet needed) -->
    <style>
    @media print {
        .sidebar, #sidebar, .sidebar-backdrop, .main-header, .page-header-bar,
        form, .d-flex.justify-content-between, .row.g-2.mb-4,
        button, .btn { display: none !important; }
        body, .main-content, .page-content { margin: 0 !important; padding: 0 !important; }
        .card { border: 1px solid #ccc !important; box-shadow: none !important; }
        table { font-size: 11px; }
    }
    </style>
    <script>
    function downloadReportPDF() {
        window.print();
    }
    </script>
</div>
<?php include '../../includes/footer.php'; ?>
</body>
</html>