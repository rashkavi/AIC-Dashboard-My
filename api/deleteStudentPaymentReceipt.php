<?php

/**
 * deleteStudentPaymentReceipt.php  (place in /api next to uploadStudentPaymentReceipt.php)
 *
 * action = preview → returns what WILL be deleted (used to fill the confirm modal)
 * action = delete  → deletes it all in ONE transaction, then writes payment_logs
 *
 * What gets removed when a receipt (course_payment_has_payments row) is deleted:
 *   1. the receipt row itself
 *   2. every row that shares the same course_payment_receipts id → these are the
 *      "Auto-applied overpayment" rows created on the NEXT installments by
 *      uploadStudentPaymentReceipt.php
 *   3. all course_payment_slips rows of those payments (+ the files on disk)
 *   4. old payment_logs lines that pointed at those payments are detached (kept as history)
 *
 * NOT deleted on purpose: the course_payment_receipts row (receipt number).
 *   uploadStudentPaymentReceipt.php builds the next number from COUNT(*), so deleting
 *   that row would make the next receipt reuse an existing number.
 *
 * After deleting, any affected installment that was "paid" (status 5) goes back to
 * status 1 (pending), and one log line per affected course_payments_id is written.
 */

header('Content-Type: application/json');

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

if (!SessionUser::hasValidSession()) {
    echo json_encode(['error' => 'Authentication Failed!']);
    exit;
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();

if ($sessionUserRole == null) {
    echo json_encode(['error' => 'Authentication Failed!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$timezone = new DateTimeZone("Asia/Colombo");
$now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');

$action = $_POST['action'] ?? 'preview';
$cppId  = (int)($_POST['payment_receipt_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($cppId <= 0 || !in_array($action, ['preview', 'delete'], true)) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
if (strlen($reason) > 150) {
    echo json_encode(['error' => 'Reason must be less than 150 characters']);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   LOAD THE CLICKED RECEIPT
════════════════════════════════════════════════════════════════════════════ */
$targetQ = Database::search("
    SELECT
        cpp.course_payment_has_payment_id,
        cpp.course_payments_course_payments_id AS course_payments_id,
        cpp.course_payment_receipts_course_payment_receipt_id AS receipt_id,
        cpp.paid_amount, cpp.tax_amount, cpp.discount_amount, cpp.panelty_amount,
        cpp.date_paid, cpp.note,
        cur.code AS currency_code,
        cpr.receipt_number,
        pt.payment_type,
        shi.intakes_intake_id AS intake_id
    FROM course_payment_has_payments cpp
    INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
    INNER JOIN payment_type pt    ON cp.payment_type_payment_type_id = pt.payment_type_id
    INNER JOIN payment_plan_has_students_has_intakes pshi
        ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
    INNER JOIN students_has_intakes shi
        ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    LEFT JOIN currencies cur ON cpp.currencies_currency_id = cur.currency_id
    LEFT JOIN course_payment_receipts cpr
        ON cpp.course_payment_receipts_course_payment_receipt_id = cpr.course_payment_receipt_id
    WHERE cpp.course_payment_has_payment_id = '{$cppId}'
");

if (!$targetQ || $targetQ->num_rows == 0) {
    echo json_encode(['error' => 'Payment not found. It may have been deleted already.']);
    exit;
}
$target = $targetQ->fetch_assoc();

/* ── same permission rule the page uses to show Edit / Add Receipt ── */
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
if (!(
    SessionUser::ifUserIsPro($sessionUserRole)
    || SessionUser::ifUserIsAccounts($sessionUserRole)
    || UserPermissions::validPermisisonType('payments', 0)
    || UserPermissions::validPermisisonType('intake_payments', 24, $target['intake_id'])
)) {
    echo json_encode(['error' => 'You are not authorized to this action.']);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   COLLECT EVERYTHING THAT BELONGS TO THIS RECEIPT
   (same course_payment_receipts id = same upload = main payment + overpayments)
════════════════════════════════════════════════════════════════════════════ */
$receiptId = (int)($target['receipt_id'] ?? 0);

$groupWhere = $receiptId > 0
    ? "(cpp.course_payment_has_payment_id = '{$cppId}' OR cpp.course_payment_receipts_course_payment_receipt_id = '{$receiptId}')"
    : "cpp.course_payment_has_payment_id = '{$cppId}'";

$groupQ = Database::search("
    SELECT
        cpp.course_payment_has_payment_id AS id,
        cpp.course_payments_course_payments_id AS course_payments_id,
        cpp.paid_amount, cpp.tax_amount, cpp.discount_amount, cpp.panelty_amount,
        cpp.date_paid, cpp.note,
        cur.code AS currency_code,
        pt.payment_type
    FROM course_payment_has_payments cpp
    INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
    INNER JOIN payment_type pt    ON cp.payment_type_payment_type_id = pt.payment_type_id
    LEFT JOIN currencies cur      ON cpp.currencies_currency_id = cur.currency_id
    WHERE {$groupWhere}
    ORDER BY cpp.course_payments_course_payments_id ASC, cpp.course_payment_has_payment_id ASC
");

$rows = [];
while ($g = $groupQ->fetch_assoc()) {
    $g['id']                  = (int)$g['id'];
    $g['course_payments_id']  = (int)$g['course_payments_id'];
    $g['is_overpayment']      = (strpos((string)$g['note'], 'Auto-applied overpayment') === 0);
    $rows[] = $g;
}
$ids = array_map(fn($r) => $r['id'], $rows);
$idList = implode(',', $ids);

$slips = [];
$slipQ = Database::search("
    SELECT course_payment_slip_id, slip_name, course_payment_has_payment_id
    FROM course_payment_slips
    WHERE course_payment_has_payment_id IN ({$idList})
");
while ($s = $slipQ->fetch_assoc()) {
    $slips[] = $s;
}

$fmt = fn($r) => ($r['currency_code'] ?? '') . ' ' . number_format((float)$r['paid_amount'], 2);

/* ════════════════════════════════════════════════════════════════════════════
   PREVIEW
════════════════════════════════════════════════════════════════════════════ */
if ($action === 'preview') {
    $linked = [];
    foreach ($rows as $r) {
        if ($r['id'] === (int)$target['course_payment_has_payment_id']) continue;
        $linked[] = [
            'id'             => $r['id'],
            'payment_type'   => $r['payment_type'],
            'amount'         => $fmt($r),
            'is_overpayment' => $r['is_overpayment'],
        ];
    }

    echo json_encode([
        'ok'            => true,
        'id'            => (int)$target['course_payment_has_payment_id'],
        'payment_type'  => $target['payment_type'],
        'receipt_number' => $target['receipt_number'],
        'date_paid'     => $target['date_paid'],
        'amount'        => $fmt($target),
        'tax'           => ($target['currency_code'] ?? '') . ' ' . number_format((float)$target['tax_amount'], 2),
        'slip_count'    => count($slips),
        'linked'        => $linked,
    ]);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   DELETE (one transaction — all or nothing)
════════════════════════════════════════════════════════════════════════════ */
$conn = Database::$connection;
$run = function (string $sql) use ($conn) {
    if ($conn->query($sql) === false) {
        throw new Exception($conn->error);
    }
};

try {
    $conn->begin_transaction();

    // 1. slips (DB rows)
    $run("DELETE FROM course_payment_slips WHERE course_payment_has_payment_id IN ({$idList})");

    // 2. keep old log lines as history, just detach them from the rows being deleted
    $run("UPDATE payment_logs SET course_payment_has_payment_id = NULL WHERE course_payment_has_payment_id IN ({$idList})");

    // 3. the receipt + its auto-applied overpayments
    $run("DELETE FROM course_payment_has_payments WHERE course_payment_has_payment_id IN ({$idList})");

    // 4. installments that were marked paid (5) are open again (1)
    $affectedCpIds = array_values(array_unique(array_map(fn($r) => $r['course_payments_id'], $rows)));
    foreach ($affectedCpIds as $cpId) {
        $run("
            UPDATE course_payments SET payment_status_payment_status_id = '1'
            WHERE course_payments_id = '{$cpId}' AND payment_status_payment_status_id = '5'
        ");
    }

    // 5. logs — one line per affected course_payments_id
    $userId       = (int)$sessionUserObject['user_id'];
    $receiptLabel = $target['receipt_number'] ? ' | Receipt ' . $target['receipt_number'] : '';
    $overCount    = count(array_filter($rows, fn($r) => $r['is_overpayment']));
    $mainCpId     = (int)$target['course_payments_id'];

    foreach ($rows as $r) {
        $line = $r['is_overpayment']
            ? "Auto-applied overpayment DELETED: #R{$r['id']} {$fmt($r)} (tax " . number_format((float)$r['tax_amount'], 2) . ")"
            . " | removed together with receipt #R{$cppId}{$receiptLabel}"
            : "Payment DELETED: #R{$r['id']} {$fmt($r)} (tax " . number_format((float)$r['tax_amount'], 2) . ")"
            . " | paid date {$r['date_paid']}{$receiptLabel}";

        if ((float)$r['discount_amount'] > 0) {
            $line .= " | discount " . number_format((float)$r['discount_amount'], 2);
        }
        if ((float)$r['panelty_amount'] > 0) {
            $line .= " | penalty LKR " . number_format((float)$r['panelty_amount'], 2);
        }

        // summary only on the log of the installment the user clicked
        if ($r['id'] === (int)$target['course_payment_has_payment_id']) {
            $line .= " | total payments deleted: " . count($rows)
                . " (overpayments: {$overCount}) | slips deleted: " . count($slips);
        }

        $noteSql = $reason !== '' ? "'" . addslashes($reason) . "'" : 'NULL';
        $run("
            INSERT INTO payment_logs
                (course_payments_course_payments_id, users_user_id, date_updated, log, note)
            VALUES
                ('{$r['course_payments_id']}', '{$userId}', '{$now}', '" . addslashes($line) . "', {$noteSql})
        ");
    }

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('deleteStudentPaymentReceipt failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not delete the payment: ' . $e->getMessage()]);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   slip FILES — only after the DB commit succeeded
════════════════════════════════════════════════════════════════════════════ */
foreach ($slips as $s) {
    $file = "../assets/uploads/payment-slips/" . basename($s['slip_name']);
    if (is_file($file)) {
        @unlink($file);
    }
}

echo json_encode([
    'done'             => 'Payment deleted successfully',
    'deletedPayments'  => count($rows),
    'deletedOverpays'  => $overCount,
    'deletedSlips'     => count($slips),
]);
