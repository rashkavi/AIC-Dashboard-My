<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

if (!SessionUser::hasValidSession()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
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
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

/* ══════════════════════════════════════════════════════════════════════════
   INPUT PARSING
══════════════════════════════════════════════════════════════════════════════ */
$paymentId      = (int)($input['paymentId']        ?? 0);
$newStatus      = (int)($input['status']           ?? 0);
$paidAmount     = (float)($input['paidAmount']     ?? 0);   // always in BASE currency from frontend
$discountAmount = (float)($input['discountAmount'] ?? 0);
$penaltyAmount  = (float)($input['penaltyAmount']  ?? 0);
$taxRate        = (float)($input['taxRate']        ?? 0);   // reference only; we re-fetch from DB
$balance        = (float)($input['balance']        ?? 0);   // frontend balance (informational)
$paymentMethod  = (int)($input['paymentMethod']    ?? 0);
$paidDate       = trim($input['paidDate']          ?? '');
$exchangeRate   = (isset($input['exchangeRate'])   && (float)$input['exchangeRate'] > 0)
    ? (float)$input['exchangeRate'] : null;
$exchangeRateId = (isset($input['exchangeRateId']) && $input['exchangeRateId'])
    ? (int)$input['exchangeRateId'] : null;
$currencyId     = (int)($input['currencyId']       ?? 0);
$note           = trim($input['note']              ?? '');
$userId         = (int)$sessionUserObject['user_id'];

/* ══════════════════════════════════════════════════════════════════════════
   BASIC VALIDATION
══════════════════════════════════════════════════════════════════════════════ */
if (!$paymentId) {
    echo json_encode(['error' => 'Invalid payment ID.']);
    exit;
}
if (!in_array($newStatus, [3, 4])) {
    echo json_encode(['status' => 'Invalid status.']);
    exit;
}
if ($newStatus == 3 && $paidAmount <= 0) {
    echo json_encode(['paidAmount' => 'Paid amount required when approving.']);
    exit;
}
if ($newStatus == 3 && !$paymentMethod) {
    echo json_encode(['paymentMethod' => 'Payment method is required.']);
    exit;
}
if ($newStatus == 3 && empty($paidDate)) {
    echo json_encode(['paidDate' => 'Paid date is required.']);
    exit;
}

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0))) {
    echo json_encode(['error' => 'You do not have permission to update payment status.']);
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════
   FETCH PAYMENT RECORD  (course_payment_has_payments → course_payments)
══════════════════════════════════════════════════════════════════════════════ */
$paymentRow = Database::search("
    SELECT
        cpp.*,
        cp.course_payments_id,
        cp.amount                           AS cp_amount,
        cp.discount                         AS cp_discount,
        cp.discount_type_discount_type_id,
        cp.payment_plan_has_students_has_intakes_id,
        cp.panelty_payment_option_panelty_payment_option_id,

        pc.currencies_currency_id           AS course_currency_id,
        c.code                              AS course_currency_code,
        c.symbol                            AS course_currency_symbol,
        c.decimal_places                    AS course_decimal_places,

        shi.students_has_intakes_id,
        shi.students_student_id,
        shi.intakes_intake_id,

        b.branch                            AS branch_name,

        s.student_id                        AS student_reg_id,
        s.lead_student_status_lead_student_status_id,
        ld.first_name,
        ld.last_name,
        ld.email,

        pt.payment_type_id,
        pt.payment_type

    FROM course_payment_has_payments cpp
    INNER JOIN course_payments cp
        ON cpp.course_payments_course_payments_id = cp.course_payments_id
    INNER JOIN payment_plan_has_students_has_intakes ppsi
        ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category pc
        ON ppsi.payment_category_payment_category_id = pc.payment_category_id
    INNER JOIN currencies c
        ON pc.currencies_currency_id = c.currency_id
    INNER JOIN students_has_intakes shi
        ON ppsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN students s
        ON shi.students_student_id = s.student_id
    INNER JOIN login_details ld
        ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN intakes i
        ON shi.intakes_intake_id = i.intake_id
    INNER JOIN programmes pr
        ON i.programmes_programme_id = pr.programme_id
    INNER JOIN faculties f
        ON pr.faculties_faculty_id = f.faculty_id
    INNER JOIN branches b
        ON f.branches_branch_id = b.branch_id
    INNER JOIN payment_type pt
        ON cp.payment_type_payment_type_id = pt.payment_type_id
    WHERE cpp.course_payment_has_payment_id = '$paymentId'
    LIMIT 1
");

if (!$paymentRow || $paymentRow->num_rows == 0) {
    echo json_encode(['error' => 'Payment record not found.']);
    exit;
}

$pay = $paymentRow->fetch_assoc();

/* Already processed */
if ((int)$pay['payment_status_payment_status_id'] != 2) {
    echo json_encode(['error' => 'This payment has already been processed.']);
    exit;
}

$courseCurrencyId   = (int)$pay['course_currency_id'];
$courseCurrencyCode = $pay['course_currency_code'];
$shiId              = (int)$pay['students_has_intakes_id'];
$coursePaymentsId   = (int)$pay['course_payments_id'];
$pplanId            = (int)$pay['payment_plan_has_students_has_intakes_id'];
$lkrCurrencyId      = 1;
$isCourseLKR        = ($courseCurrencyId === $lkrCurrencyId);

/* ══════════════════════════════════════════════════════════════════════════
   FETCH LATEST TAX RATE FROM DB  (same as uploadPaymentReceipt.php)
   We always use the DB value — ignore whatever the frontend sent.
══════════════════════════════════════════════════════════════════════════════ */
$taxRateQuery = Database::search("
    SELECT `tax_rate` AS `rate` FROM `tax_rates`
    ORDER BY `date_added` DESC LIMIT 1
");
$latestTaxRate = 0;
if ($taxRateQuery && $taxRateQuery->num_rows > 0) {
    $latestTaxRate = (float)$taxRateQuery->fetch_assoc()['rate'];
}

/* ══════════════════════════════════════════════════════════════════════════
   CURRENCY / EXCHANGE RATE RESOLUTION
   penalty_payments table ALWAYS stores in LKR
══════════════════════════════════════════════════════════════════════════════ */
function toLKR($amount, $exchangeRate)
{
    if ($exchangeRate === null || $exchangeRate <= 0) return (float)$amount;
    return (float)$amount * $exchangeRate;
}

$resolvedRate = null;

if (!$isCourseLKR) {
    if ($exchangeRate && $exchangeRate > 0) {
        $resolvedRate = $exchangeRate;
    } elseif ($exchangeRateId) {
        $rateRow = Database::search(
            "SELECT `rate` FROM `currency_exchange_rates`
             WHERE `currency_exchange_rate_id` = '$exchangeRateId' LIMIT 1"
        );
        if ($rateRow && $rateRow->num_rows > 0) {
            $resolvedRate = (float)$rateRow->fetch_assoc()['rate'];
        }
    }

    if (!$resolvedRate) {
        $fallback = Database::search("
            SELECT rate FROM currency_exchange_rates
            WHERE base_currency_id   = '$courseCurrencyId'
              AND target_currency_id = '$lkrCurrencyId'
            ORDER BY effective_date DESC, effective_time DESC
            LIMIT 1
        ");
        if ($fallback && $fallback->num_rows > 0) {
            $resolvedRate = (float)$fallback->fetch_assoc()['rate'];
        }
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   TIMEZONE
══════════════════════════════════════════════════════════════════════════════ */
$timezone = new DateTimeZone("Asia/Colombo");
$now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');

/* ══════════════════════════════════════════════════════════════════════════
   REJECTION PATH  (status = 4)
   Simple: just update the receipt row + course_payments status + log.
══════════════════════════════════════════════════════════════════════════════ */
if ($newStatus == 4) {

    Database::iud("
        UPDATE `course_payment_has_payments`
        SET `payment_status_payment_status_id` = '4'
        WHERE `course_payment_has_payment_id` = '$paymentId'
    ");


    $logNote = addslashes($note ?: 'Payment rejected');
    Database::iud("
        INSERT INTO `payment_logs`
            (`course_payments_course_payments_id`, `course_payment_has_payment_id`, `users_user_id`, `date_updated`, `note`, `log`)
        VALUES
            ('$coursePaymentsId', '$paymentId', '$userId', '$now', '$logNote', '$logNote')
    ");

    $status->done = 'Payment has been rejected.';
    echo json_encode($status);
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════
   APPROVAL PATH  (status = 3)
   Mirror the full logic of uploadPaymentReceipt.php:
     1. Extract tax from paid amount (tax-inclusive model)
     2. Calculate current installment payable
     3. Detect overpayment
     4. Distribute overpayment to next installment(s)
     5. Generate receipt number
     6. Update course_payment_has_payments row
     7. Insert overpayment rows for next installment(s)
     8. Mark installment(s) complete where applicable
     9. Insert penalty_payments if penalty > 0
    10. Log everything
══════════════════════════════════════════════════════════════════════════════ */

/* ════════════════════════════════════════════════════════════════════════════
   HELPER — Extract tax from a tax-inclusive amount
   
   paid = base + tax  where tax = base × rate%
   ⟹  base = paid ÷ (1 + rate%)
       tax  = paid − base

   Example: paid = 50,000 | rate = 2.5%
     base = 50,000 ÷ 1.025 = 48,780.4878...  ≈ 48,780.4878
     tax  = 50,000 − 48,780.4878             ≈ 1,219.5122

   (Frontend display rounds to 2 dp, but we store 4 dp for accuracy.)
════════════════════════════════════════════════════════════════════════════ */
function extractTaxFromPaid(float $paid, float $taxRate): array
{
    if ($taxRate <= 0 || $paid <= 0) return ['base' => $paid, 'tax' => 0.0];
    $factor = 1 + ($taxRate / 100);
    $base   = $paid / $factor;
    $tax    = $paid - $base;
    return ['base' => round($base, 2), 'tax' => round($tax, 2)];
}

/* ── paidBase: what the student actually sent, in base/course currency ── */
$paidBase = round($paidAmount, 2);

/* ════════════════════════════════════════════════════════════════════════════
   CURRENT INSTALLMENT PAYABLE CALCULATION

   cpDiscountAmount  = fixed or % discount on this installment
   Fetch existing approved/pending receipts so we know how much is already paid.
   netBase           = installment amount − all discounts + all penalties
   taxOnPaid         = extracted from paidBase using tax-inclusive formula
   currentPayableTotal = netBase + taxExtracted
════════════════════════════════════════════════════════════════════════════ */

/* Installment-level discount */
$cpDiscountAmount = ($pay['discount_type_discount_type_id'] == 3)
    ? $pay['cp_amount'] / 100 * $pay['cp_discount']
    : (float)$pay['cp_discount'];

/* Existing receipts for this installment (approved = 3 and pending = 2) */
$receiptQuery = Database::search("
    SELECT
        SUM(CASE WHEN cpp.payment_status_payment_status_id = 3  THEN cpp.paid_amount               ELSE 0 END) AS approved_paid,
        SUM(CASE WHEN cpp.payment_status_payment_status_id = 2  THEN cpp.paid_amount               ELSE 0 END) AS pending_paid,
        SUM(CASE WHEN cpp.payment_status_payment_status_id IN (2,3) THEN COALESCE(cpp.discount_amount, 0) ELSE 0 END) AS receipt_discount,
        SUM(CASE WHEN cpp.payment_status_payment_status_id IN (2,3) THEN COALESCE(cpp.panelty_amount,  0) ELSE 0 END) AS receipt_penalty
    FROM course_payment_has_payments AS cpp
    WHERE cpp.course_payments_course_payments_id = '$coursePaymentsId'
      AND cpp.course_payment_has_payment_id     != '$paymentId'
");

$existingReceiptData = [
    'approved_paid'    => 0,
    'pending_paid'     => 0,
    'receipt_discount' => 0,
    'receipt_penalty'  => 0,
];
if ($receiptQuery && $receiptQuery->num_rows > 0) {
    $existingReceiptData = $receiptQuery->fetch_assoc();
}

/* NOTE: We exclude the CURRENT receipt row (paymentId) from the sums
   because we are about to update it from pending→approved.
   This gives us how much was paid by OTHER already-processed receipts. */

$totalPaidBefore    = (float)($existingReceiptData['approved_paid']    ?? 0);
// pending from OTHER receipts is not yet real cash, so use only approved
// (matching uploadPaymentReceipt which only counts confirmed cash before deciding overpay)

$totalDiscountBefore = $cpDiscountAmount
    + (float)($existingReceiptData['receipt_discount'] ?? 0);

$totalPenaltyBefore = (float)($existingReceiptData['receipt_penalty'] ?? 0);

/* Net base for this installment */
$netBase = $pay['cp_amount'] - $totalDiscountBefore + $totalPenaltyBefore;

/* ── Extract tax from paid amount ── */
$taxExtracted       = extractTaxFromPaid($paidBase, $latestTaxRate);
$paidBaseAmount     = $taxExtracted['base'];    // fee portion
$taxAmountExtracted = $taxExtracted['tax'];     // tax portion

/* Total the student owes for THIS installment */
$currentPayableTotal = $netBase + $taxAmountExtracted;

/* ════════════════════════════════════════════════════════════════════════════
   OVERPAYMENT DETECTION

   remainingOnCurrent = what still needs to be paid on this installment
   amountForCurrent   = min(paidBase, remainingOnCurrent)
   overpayAmount      = paidBase − amountForCurrent
════════════════════════════════════════════════════════════════════════════ */
$remainingOnCurrent = max(0, $currentPayableTotal - $totalPaidBefore);
$amountForCurrent   = min($paidBase, $remainingOnCurrent);
$overpayAmount      = round($paidBase - $amountForCurrent, 2);

/* Tax stored for this receipt: only if it fully covers the installment */
$taxForCurrentReceipt = $taxAmountExtracted;

$overpaymentInfo         = null;
$nextInstallmentPayments = [];

if ($overpayAmount > 0.001) {

    /* ── Check if a next installment exists ── */
    $nextPaymentsCheck = Database::search("
        SELECT COUNT(*) AS next_count
        FROM `course_payments` AS cp
        INNER JOIN `payment_status` AS ps
            ON cp.`payment_status_payment_status_id` = ps.`payment_status_id`
        WHERE cp.`payment_plan_has_students_has_intakes_id` = '$pplanId'
          AND cp.`course_payments_id` > '$coursePaymentsId'
          AND ps.`payment_status_id` IN (1, 2)
    ");

    $nextCountData      = $nextPaymentsCheck->fetch_assoc();
    $hasNextInstallment = (int)($nextCountData['next_count'] ?? 0) > 0;

    if (!$hasNextInstallment) {
        http_response_code(400);
        $status->error     = 'Cannot process overpayment. No future installment available for this payment plan.';
        $status->errorType = 'OVERPAYMENT_NO_NEXT_INSTALLMENT';
        $status->errorDetails = [
            'message'        => 'The amount entered (' . $courseCurrencyCode . ' ' . number_format($paidBase, 2) . ') exceeds the current installment balance (' . $courseCurrencyCode . ' ' . number_format($remainingOnCurrent, 2) . ').',
            'overpayAmount'  => $overpayAmount,
            'currentBalance' => $remainingOnCurrent,
            'paidAmount'     => $paidBase,
            'suggestion'     => 'Please enter an amount ≤ the current balance, or contact administration if this is for a future installment.',
        ];
        echo json_encode($status);
        exit;
    }

    /* ── Distribute overpay to next installment(s) ── */
    $overpaymentInfo = [
        'current_applied' => $amountForCurrent,
        'current_tax'     => $taxForCurrentReceipt,
        'overpay_amount'  => $overpayAmount,
    ];

    $nextPayments = Database::search("
        SELECT
            cp.`course_payments_id`,
            cp.`amount`,
            cp.`discount`,
            cp.`discount_type_discount_type_id`,
            ps.`payment_status_id`
        FROM `course_payments` AS cp
        INNER JOIN `payment_status` AS ps
            ON cp.`payment_status_payment_status_id` = ps.`payment_status_id`
        WHERE cp.`payment_plan_has_students_has_intakes_id` = '$pplanId'
          AND cp.`course_payments_id` > '$coursePaymentsId'
          AND ps.`payment_status_id` IN (1, 2)
        ORDER BY cp.`due_date` ASC, cp.`course_payments_id` ASC
    ");

    if ($nextPayments && $nextPayments->num_rows > 0) {
        $remainingOverpay = $overpayAmount;

        while ($nextPayment = $nextPayments->fetch_assoc()) {
            if ($remainingOverpay <= 0.001) break;

            $nextPaymentId = (int)$nextPayment['course_payments_id'];

            /* Next installment discount */
            $nextDiscount = ($nextPayment['discount_type_discount_type_id'] == 3)
                ? $nextPayment['amount'] / 100 * $nextPayment['discount']
                : (float)$nextPayment['discount'];

            $nextNetBase = $nextPayment['amount'] - $nextDiscount;

            /* Extract tax from the overpay amount being applied */
            $nextExtracted = extractTaxFromPaid($remainingOverpay, $latestTaxRate);
            $nextTaxAmount = $nextExtracted['tax'];
            $nextTotalOwed = $nextNetBase + $nextTaxAmount;

            /* How much of the overpay applies to this next installment */
            $applyToNext      = min($remainingOverpay, $nextTotalOwed);
            $remainingOverpay = round($remainingOverpay - $applyToNext, 2);

            /* Proportional tax for the portion applied */
            if ($applyToNext >= $nextTotalOwed) {
                $nextTaxStored = $nextTaxAmount;
            } else {
                $nextTaxStored = ($nextTotalOwed > 0)
                    ? round($applyToNext * ($nextTaxAmount / $nextTotalOwed), 2)
                    : 0.0;
            }

            $nextInstallmentPayments[] = [
                'payment_id'      => $nextPaymentId,
                'paid_amount'     => round($applyToNext, 2),
                'tax_amount'      => $nextTaxStored,
                'next_total_owed' => $nextTotalOwed,
            ];
        }

        $overpaymentInfo['next_installments'] = $nextInstallmentPayments;
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   GENERATE RECEIPT NUMBER
   Format: [BRANCH_2_LETTERS][YEAR]-[SEQ_5DIGITS]   e.g. CO2025-00042
════════════════════════════════════════════════════════════════════════════ */
$branchName   = $pay['branch_name'] ?? 'XX';
$branchPrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $branchName), 0, 2));
if (strlen($branchPrefix) < 2) $branchPrefix = str_pad($branchPrefix, 2, 'X');
$year = date('Y');

$countRow = Database::search("
    SELECT COUNT(*) AS cnt
    FROM course_payment_receipts
    WHERE receipt_number LIKE '" . addslashes($branchPrefix) . $year . "-%'
");
$seq = 1;
if ($countRow && $countRow->num_rows > 0) {
    $seq = (int)$countRow->fetch_assoc()['cnt'] + 1;
}
$receiptNumber = $branchPrefix . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);

/* Insert receipt */
Database::iud("
    INSERT INTO `course_payment_receipts`
        (`receipt_number`, `users_user_id`, `date_added`, `students_has_intakes_students_has_intakes_id`)
    VALUES
        ('" . addslashes($receiptNumber) . "', '$userId', '$now', '$shiId')
");
$receiptId = Database::$connection->insert_id;

/* ════════════════════════════════════════════════════════════════════════════
   UPDATE course_payment_has_payments  (pending → approved)
   
   Stores amounts in COURSE / BASE currency.
   Overwrites paid_amount with amountForCurrent (not the full paidBase if
   there was an overpayment — the excess goes to the next installment rows).
════════════════════════════════════════════════════════════════════════════ */
$qn = fn($v) => $v !== null ? "'" . $v . "'" : 'NULL';

$exchangeRateSql   = $resolvedRate    ? "'" . number_format($resolvedRate, 2, '.', '') . "'" : 'NULL';
$exchangeRateIdSql = $exchangeRateId  ? "'$exchangeRateId'" : 'NULL';
$currencyIdSql     = $currencyId      ? "'$currencyId'"     : "'$courseCurrencyId'";
$noteSql           = "'" . addslashes($note) . "'";
$paidDateSql       = "'" . addslashes($paidDate) . "'";

Database::iud("
    UPDATE `course_payment_has_payments`
    SET
        `payment_status_payment_status_id`                  = '3',
        `paid_amount`                                       = '" . number_format($amountForCurrent,   2, '.', '') . "',
        `discount_amount`                                   = '" . number_format($discountAmount,     2, '.', '') . "',
        `panelty_amount`                                    = '" . number_format($penaltyAmount,      2, '.', '') . "',
        `tax_rate`                                          = '" . number_format($latestTaxRate,      2, '.', '') . "',
        `tax_amount`                                        = '" . number_format($taxForCurrentReceipt, 2, '.', '') . "',
        `payment_method_payment_method_id`                  = '$paymentMethod',
        `date_paid`                                         = $paidDateSql,
        `currencies_currency_id`                            = $currencyIdSql,
        `currency_exchange_rate`                            = $exchangeRateSql,
        `currency_exchange_rate_id`                         = $exchangeRateIdSql,
        `note`                                              = $noteSql,
        `course_payment_receipts_course_payment_receipt_id` = '$receiptId'
    WHERE `course_payment_has_payment_id` = '$paymentId'
");

/* ════════════════════════════════════════════════════════════════════════════
   MARK CURRENT INSTALLMENT AS FULLY PAID IF NOW COVERED
════════════════════════════════════════════════════════════════════════════ */
$newTotalPaidOnCurrent = $totalPaidBefore + $amountForCurrent;
if ($newTotalPaidOnCurrent >= $currentPayableTotal) {
    Database::iud("
        UPDATE `course_payments`
        SET `payment_status_payment_status_id` = '5'
        WHERE `course_payments_id` = '$coursePaymentsId'
    ");
}

/* ════════════════════════════════════════════════════════════════════════════
   INSERT OVERPAYMENT RECORDS FOR NEXT INSTALLMENTS
════════════════════════════════════════════════════════════════════════════ */
if (!empty($nextInstallmentPayments)) {
    foreach ($nextInstallmentPayments as $nextPmt) {
        $nextPaymentId  = $nextPmt['payment_id'];
        $nextPaidAmount = $nextPmt['paid_amount'];
        $nextTaxStored  = $nextPmt['tax_amount'];
        $nextTotalOwed  = $nextPmt['next_total_owed'];

        $next_col = "
            `course_payments_course_payments_id`,
            `payment_method_payment_method_id`,
            `date_paid`,
            `paid_amount`,
            `discount_amount`,
            `panelty_amount`,
            `payment_status_payment_status_id`,
            `currencies_currency_id`,
            `note`,
            `tax_rate`,
            `tax_amount`,
            `course_payment_receipts_course_payment_receipt_id`";

        $next_val = "
            '$nextPaymentId',
            '$paymentMethod',
            $paidDateSql,
            '" . number_format($nextPaidAmount, 2, '.', '') . "',
            '0',
            '0',
            '3',
            $currencyIdSql,
            'Auto-applied overpayment from receipt {$receiptNumber}',
            '" . number_format($latestTaxRate,  2, '.', '') . "',
            '" . number_format($nextTaxStored,  2, '.', '') . "',
            '$receiptId'";

        if ($currencyId && $currencyId != $courseCurrencyId) {
            $next_col .= ", `currency_exchange_rate_id`, `currency_exchange_rate`";
            $next_val .= ", $exchangeRateIdSql, $exchangeRateSql";
        }

        Database::iud("
            INSERT INTO `course_payment_has_payments` ($next_col)
            VALUES ($next_val)
        ");
        $nextPaymentReceiptId = Database::$connection->insert_id;

        /* Mark next installment as fully paid if covered */
        if ($nextPaidAmount >= $nextTotalOwed) {
            Database::iud("
                UPDATE `course_payments`
                SET `payment_status_payment_status_id` = '5'
                WHERE `course_payments_id` = '$nextPaymentId'
            ");
        }

        /* Log the overpayment application */
        $overpay_log = Database::escape(
            'Auto-applied overpayment from ' . $receiptNumber
                . ' [' . $courseCurrencyCode . ' ' . number_format($nextPaidAmount, 2)
                . ' (incl. tax ' . number_format($nextTaxStored, 2) . ')]'
        );
        Database::iud("
            INSERT INTO `payment_logs`
                (`course_payments_course_payments_id`, `course_payment_has_payment_id`, `users_user_id`, `date_updated`, `log`)
            VALUES
                ('$nextPaymentId', '$nextPaymentReceiptId', '$userId', '$now', '$overpay_log')
        ");
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   REGISTRATION PAYMENT STATUS UPDATE
   (mirrors uploadPaymentReceipt.php — payment_type_id = 18, student status = 4)
════════════════════════════════════════════════════════════════════════════ */
if ((int)$pay['payment_type_id'] == 18 && (int)$pay['lead_student_status_lead_student_status_id'] == 4) {
    Database::iud("
        UPDATE `students`
        SET `lead_student_status_lead_student_status_id` = '5'
        WHERE `student_id` = '{$pay['student_reg_id']}'
    ");
    Database::iud("
        INSERT INTO `students_logs`
            (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`)
        VALUES
            ('{$pay['student_reg_id']}', '$now', '$userId', 'Registration Payment marked as paid', '5')
    ");
}

/* ════════════════════════════════════════════════════════════════════════════
   FIRST INSTALLMENT LOG
   (mirrors uploadPaymentReceipt.php — student status 5 or 9)
════════════════════════════════════════════════════════════════════════════ */
if (in_array((int)$pay['lead_student_status_lead_student_status_id'], [8, 9])) {
    $nextRs = Database::search("
        SELECT cp.`course_payments_id`
        FROM `course_payments` cp
        INNER JOIN `payment_type` pt
            ON cp.`payment_type_payment_type_id` = pt.`payment_type_id`
        INNER JOIN `payment_plan_has_students_has_intakes` pshi
            ON cp.`payment_plan_has_students_has_intakes_id` = pshi.`payment_plan_has_students_has_intakes_id`
        WHERE cp.`payment_plan_has_students_has_intakes_id` = '$pplanId'
          AND pshi.`students_has_intakes_students_has_intakes_id` = '$shiId'
          AND cp.`payment_status_payment_status_id` != '5'
          AND pt.`payment_type` NOT LIKE '%Registration%'
        ORDER BY cp.`due_date` ASC, cp.`course_payments_id` ASC
        LIMIT 1
    ");
    if ($nextRs && $nextRs->num_rows > 0) {
        $nextFirst = $nextRs->fetch_assoc();
        if ((int)$nextFirst['course_payments_id'] === $coursePaymentsId) {

            Database::iud("
            UPDATE `students`
            SET `lead_student_status_lead_student_status_id` = '11'
            WHERE `student_id` = '{$pay['student_reg_id']}'
        ");

            Database::iud("
                INSERT INTO `students_logs`
                    (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`)
                VALUES
                    ('{$pay['student_reg_id']}', '$now', '$userId', 'First installment payment receipt approved', '10')
            ");
        }
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   MAIN APPROVAL LOG
════════════════════════════════════════════════════════════════════════════ */
$displayAmount = $courseCurrencyCode . ' ' . number_format($paidBase, 2);

$log  = 'Payment receipt approved';
$log .= ' | Paid: ' . $displayAmount;
if ($latestTaxRate > 0) {
    $log .= ' | Tax Rate: ' . $latestTaxRate . '%'
        . ' | Tax on current installment: ' . $courseCurrencyCode . ' ' . number_format($taxForCurrentReceipt, 2);
}
if ($overpayAmount > 0.001) {
    $log .= ' | Overpayment: ' . $courseCurrencyCode . ' ' . number_format($overpayAmount, 2) . ' applied to next installment(s)';
}

$log_cols = "`course_payments_course_payments_id`, `course_payment_has_payment_id`, `users_user_id`, `date_updated`, `log`";
$log_vals = "'$coursePaymentsId', '$paymentId', '$userId', '$now', '" . Database::escape($log) . "'";

if (!empty($note)) {
    $log_cols .= ", `note`";
    $log_vals .= ", '" . Database::escape($note) . "'";
}

Database::iud("INSERT INTO `payment_logs` ($log_cols) VALUES ($log_vals)");

/* ════════════════════════════════════════════════════════════════════════════
   RESPONSE
════════════════════════════════════════════════════════════════════════════ */
http_response_code(200);

$statusMsg = 'Payment approved successfully.';
if ($newTotalPaidOnCurrent >= $currentPayableTotal) {
    $statusMsg .= ' Installment is now fully paid.';
}
$statusMsg .= ' Receipt No: ' . $receiptNumber;

$status->done          = $statusMsg;
$status->receiptNumber = $receiptNumber;
$status->taxRate       = $latestTaxRate;
$status->paidBase      = $paidBase;
$status->taxAmount     = $taxForCurrentReceipt;

if ($overpaymentInfo) {
    $status->overpayment = [
        'detected'         => true,
        'currentApplied'   => $overpaymentInfo['current_applied'],
        'overpayAmount'    => $overpaymentInfo['overpay_amount'],
        'nextInstallments' => count($nextInstallmentPayments),
    ];
}

echo json_encode($status);
