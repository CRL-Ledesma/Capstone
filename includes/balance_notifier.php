<?php
// =============================================================================
//  balance_notifier.php — Auto-notify staff of outstanding patient balances
// =============================================================================
//  Runs at most once every 5 minutes per session (session-throttled).
//  Inserts into the notifications table using the existing notify() helper.
//  Skips bills that already have an unread outstanding-balance notification
//  so the bell never gets spammed.
//
//  Called by includes/header.php — no direct URL access needed.
// =============================================================================

function trigger_balance_reminders(PDO $conn): void
{
    // ── Throttle: only run once every 5 minutes per browser session ──────────
    $throttle_key = 'balance_reminder_last_run';
    if (
        isset($_SESSION[$throttle_key]) &&
        (time() - $_SESSION[$throttle_key]) < 300
    ) {
        return;
    }
    $_SESSION[$throttle_key] = time();

    // ── Find unpaid/partial bills with no existing unread reminder ────────────
    // The NOT EXISTS subquery prevents duplicate bell notifications for the
    // same bill — once a notification is unread, we don't insert another one.
    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.bill_code,
            b.status,
            b.amount_due,
            b.amount_paid,
            (b.amount_due - b.amount_paid)         AS balance,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            p.id                                   AS patient_id
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        WHERE b.status IN ('unpaid', 'partial')
          AND NOT EXISTS (
              SELECT 1
              FROM notifications n
              WHERE n.type    = 'payment'
                AND n.is_read = FALSE
                AND n.link    LIKE CONCAT('%bill_id=', b.id)
          )
        ORDER BY b.created_at DESC
        LIMIT 15
    ");

    if (!$stmt || !$stmt->execute()) return;
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bills as $bill) {
        $balance       = number_format((float) $bill['balance'], 2);
        $status_label  = $bill['status'] === 'partial' ? 'Partial Payment' : 'Unpaid';
        $patient_name  = htmlspecialchars($bill['patient_name'], ENT_QUOTES);

        notify(
            $conn,
            'payment',
            '💳 Outstanding Balance — ' . $patient_name,
            $patient_name . ' has an ' . $status_label . ' bill'
                . ' (#' . $bill['bill_code'] . ')'
                . ' with ₱' . $balance . ' remaining.',
            'modules/billing/list.php?bill_id=' . $bill['id'],
            null   // null = broadcast to all staff (same as system-wide notifications)
        );
    }
}