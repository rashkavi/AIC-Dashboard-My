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
$paymentId      = (int)($input['paymentId']      ?? 0);
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
        cp.amount                                   AS cp_amount,
        cp.discount                                 AS cp_discount,
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
   FETCH LATEST TAX RATE FROM DB
   We always use the DB value — ignore whatever the frontend sent.
══════════════════════════════════════════════════════════════════════════════ */
$taxRateQuery = Database::search("
    SELECT `tax_rate` AS `rate` FROM `tax_rates`
    ORDER BY `date_added` DESC LIMIT 1
");
$latestTaxRate = 0.0;
if ($taxRateQuery && $taxRateQuery->num_rows > 0) {
    $latestTaxRate = (float)$taxRateQuery->fetch_assoc()['rate'];
}

/* ══════════════════════════════════════════════════════════════════════════
   CURRENCY / EXCHANGE RATE RESOLUTION
══════════════════════════════════════════════════════════════════════════════ */
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
══════════════════════════════════════════════════════════════════════════════ */

/**
 * Extract tax component from a final tax-inclusive amount.
 * * base = paid × (1 - rate/100)
 * tax  = paid × (rate/100)
 *
 * Returns ['base' => float, 'tax' => float]
 */
function extractTaxFromPaid(float $paid, float $taxRate): array
{
    if ($taxRate <= 0 || $paid <= 0) return ['base' => $paid, 'tax' => 0.0];
    $taxRateDecimal = $taxRate / 100.0;
    $basePortion    = 1.0 - $taxRateDecimal;

    $base = $paid * $basePortion;
    $tax  = $paid * $taxRateDecimal;
    return ['base' => round($base, 4), 'tax' => round($tax, 4)];
}

/* ── paidBase: what the admin entered, in base/course currency ── */
$paidBase = round($paidAmount, 4);

/* ════════════════════════════════════════════════════════════════════════════
   CURRENT INSTALLMENT PAYABLE CALCULATION
     1. Compute installment-level discount from course_payments record.
     2. Fetch ALL existing receipts (pending=2 AND approved=3) for this
        installment, normalising each to base currency via exchange rate.
     3. Compute basePayable = amount - totalDiscount + totalPenalty
        totalPayable = basePayable / (1 - tax_rate/100)
════════════════════════════════════════════════════════════════════════════ */

/* Installment-level discount (from the course_payments record) */
$cpDiscountAmount = ($pay['discount_type_discount_type_id'] == 3)
    ? $pay['cp_amount'] / 100.0 * $pay['cp_discount']
    : (float)$pay['cp_discount'];

$existingReceiptQuery = Database::search("
    SELECT
        COALESCE(SUM(
            CASE
                WHEN payment_status_payment_status_id IN (2, 3) THEN
                    CASE
                        WHEN currencies_currency_id != '{$courseCurrencyId}'
                             AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN paid_amount / currency_exchange_rate
                        ELSE paid_amount
                    END
                ELSE 0
            END
        ), 0) AS existing_paid_base,

        COALESCE(SUM(
            CASE
                WHEN payment_status_payment_status_id IN (2, 3) THEN
                    CASE
                        WHEN currencies_currency_id != '{$courseCurrencyId}'
                             AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN discount_amount / currency_exchange_rate
                        ELSE discount_amount
                    END
                ELSE 0
            END
        ), 0) AS existing_discount_base,

        COALESCE(SUM(
            CASE
                WHEN payment_status_payment_status_id IN (2, 3) THEN
                    CASE
                        WHEN currencies_currency_id != '{$courseCurrencyId}'
                             AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN panelty_amount / currency_exchange_rate
                        ELSE panelty_amount
                    END
                ELSE 0
            END
        ), 0) AS existing_penalty_base

    FROM course_payment_has_payments
    WHERE course_payments_course_payments_id = '$coursePaymentsId'
      AND course_payment_has_payment_id     != '$paymentId'
");

$existingPaid_base    = 0.0;
$receiptDiscount_base = 0.0;
$receiptPenalty_base  = 0.0;

if ($existingReceiptQuery && $existingReceiptQuery->num_rows > 0) {
    $exRow = $existingReceiptQuery->fetch_assoc();
    $existingPaid_base    = (float)($exRow['existing_paid_base']    ?? 0);
    $receiptDiscount_base = (float)($exRow['existing_discount_base'] ?? 0);
    $receiptPenalty_base  = (float)($exRow['existing_penalty_base']  ?? 0);
}

$totalDiscountBase = $cpDiscountAmount + $receiptDiscount_base + $discountAmount;
$totalPenaltyBase  = $receiptPenalty_base + $penaltyAmount;

$basePayable      = max(0.0, $pay['cp_amount'] - $totalDiscountBase + $totalPenaltyBase);

/* Calculate Tax-Inclusive Total from Base Amount using structural Divisor */
if ($latestTaxRate > 0 && $latestTaxRate < 100) {
    $divisor = 1.0 - ($latestTaxRate / 100.0);
    $installmentTotal = round($basePayable / $divisor, 4);
} else {
    $installmentTotal = round($basePayable, 4);
}

/* ════════════════════════════════════════════════════════════════════════════
   OVERPAYMENT DETECTION  (all in base currency)
════════════════════════════════════════════════════════════════════════════ */
$remainingBefore_base = round(max(0.0, $installmentTotal - $existingPaid_base), 4);
$amountForCurrent     = round(min($paidBase, $remainingBefore_base), 4);
$overpayAmount        = round($paidBase - $amountForCurrent, 4);

/* Tax extracted from the portion applied to this installment only */
$taxExtractedForCurrent = extractTaxFromPaid($amountForCurrent, $latestTaxRate);
$taxForCurrentReceipt   = $taxExtractedForCurrent['tax'];

$nextInstallmentPayments = [];
$overpaymentInfo         = null;

if ($overpayAmount > 0.01) {

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
            'message'        => 'The amount entered (' . $courseCurrencyCode . ' ' . number_format($paidBase, 2) . ') exceeds the current installment balance (' . $courseCurrencyCode . ' ' . number_format($remainingBefore_base, 2) . ').',
            'overpayAmount'  => round($overpayAmount, 2),
            'currentBalance' => round($remainingBefore_base, 2),
            'paidAmount'     => round($paidBase, 2),
            'suggestion'     => 'Please enter an amount ≤ the current balance, or contact administration if this is for a future installment.',
        ];
        echo json_encode($status);
        exit;
    }

    $overpaymentInfo = [
        'current_applied' => $amountForCurrent,
        'current_tax'     => $taxForCurrentReceipt,
        'overpay_amount'  => $overpayAmount,
    ];

    /* ── Distribute overpay to next installments ── */
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
                ? $nextPayment['amount'] / 100.0 * $nextPayment['discount']
                : (float)$nextPayment['discount'];

            $nextBasePayable = max(0.0, $nextPayment['amount'] - $nextDiscount);

            /* Structural inclusive formula logic applied for cascading assignments */
            if ($latestTaxRate > 0 && $latestTaxRate < 100) {
                $nextDivisor = 1.0 - ($latestTaxRate / 100.0);
                $nextTotalOwed = round($nextBasePayable / $nextDivisor, 4);
            } else {
                $nextTotalOwed = round($nextBasePayable, 4);
            }

            $nextExistingQ = Database::search("
                SELECT COALESCE(SUM(
                    CASE
                        WHEN currencies_currency_id != '{$courseCurrencyId}'
                             AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN paid_amount / currency_exchange_rate
                        ELSE paid_amount
                    END
                ), 0) AS paid_base
                FROM course_payment_has_payments
                WHERE course_payments_course_payments_id = '$nextPaymentId'
                  AND payment_status_payment_status_id IN (2, 3)
            ");
            $nextExistingPaid = (float)(($nextExistingQ && $nextExistingQ->num_rows > 0)
                ? $nextExistingQ->fetch_assoc()['paid_base']
                : 0);

            $nextRemaining    = round(max(0.0, $nextTotalOwed - $nextExistingPaid), 4);
            $applyToNext      = round(min($remainingOverpay, $nextRemaining), 4);
            $remainingOverpay = round($remainingOverpay - $applyToNext, 4);

            /* Extract tax from the portion applied to this next installment */
            $nextExtracted = extractTaxFromPaid($applyToNext, $latestTaxRate);
            $nextTaxStored = $nextExtracted['tax'];

            $nextInstallmentPayments[] = [
                'payment_id'      => $nextPaymentId,
                'paid_amount'     => $applyToNext,
                'tax_amount'      => $nextTaxStored,
                'next_total_owed' => $nextTotalOwed,
                'next_remaining'  => $nextRemaining,
            ];
        }

        $overpaymentInfo['next_installments'] = $nextInstallmentPayments;
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   GENERATE RECEIPT NUMBER
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
   CONVERT AMOUNTS TO PAYMENT CURRENCY FOR STORAGE
════════════════════════════════════════════════════════════════════════════ */
$isPayingInForeignCurrency = ($currencyId && $currencyId != $courseCurrencyId && $resolvedRate);

if ($isPayingInForeignCurrency) {
    $amountForCurrent_stored = round($amountForCurrent * $resolvedRate, 2);
    $discountAmount_stored   = round($discountAmount * $resolvedRate, 2);
    $penaltyAmount_stored    = round($penaltyAmount * $resolvedRate, 2);
    $taxForCurrent_stored    = round($taxForCurrentReceipt * $resolvedRate, 2);
} else {
    $amountForCurrent_stored = round($amountForCurrent, 2);
    $discountAmount_stored   = round($discountAmount, 2);
    $penaltyAmount_stored    = round($penaltyAmount, 2);
    $taxForCurrent_stored    = round($taxForCurrentReceipt, 2);
}

/* ════════════════════════════════════════════════════════════════════════════
   UPDATE course_payment_has_payments  (pending → approved)
════════════════════════════════════════════════════════════════════════════ */
$exchangeRateSql   = $resolvedRate    ? "'" . number_format($resolvedRate, 4, '.', '') . "'" : 'NULL';
$exchangeRateIdSql = $exchangeRateId  ? "'$exchangeRateId'" : 'NULL';
$currencyIdSql     = $currencyId      ? "'$currencyId'"     : "'$courseCurrencyId'";
$noteSql           = "'" . addslashes($note) . "'";
$paidDateSql       = "'" . addslashes($paidDate) . "'";

Database::iud("
    UPDATE `course_payment_has_payments`
    SET
        `payment_status_payment_status_id`                  = '3',
        `paid_amount`                                       = '" . number_format($amountForCurrent_stored, 2, '.', '') . "',
        `discount_amount`                                   = '" . number_format($discountAmount_stored, 2, '.', '') . "',
        `panelty_amount`                                    = '" . number_format($penaltyAmount_stored, 2, '.', '') . "',
        `tax_rate`                                          = '" . number_format($latestTaxRate, 2, '.', '') . "',
        `tax_amount`                                        = '" . number_format($taxForCurrent_stored, 2, '.', '') . "',
        `payment_method_payment_method_id`                  = '$paymentMethod',
        `date_paid`                                         = $paidDateSql,
        `currencies_currency_id`                            = $currencyIdSql,
        `currency_exchange_rate`                            = $exchangeRateSql,
        `currency_exchange_rate_id`                         = $exchangeRateIdSql,
        `note`                                              = $noteSql,
        `course_payment_receipts_course_payment_receipt_id` = '$receiptId'
    WHERE `course_payment_has_payment_id` = '$paymentId'
");

Database::iud("
            INSERT INTO `payment_logs`
                (`course_payments_course_payments_id`, `course_payment_has_payment_id`, `users_user_id`, `date_updated`, `log`)
            VALUES
                ('" . $pay['course_payments_id'] . "', '$paymentId', '$userId', '$now', 'Payment receipt approved')
        ");

/* ════════════════════════════════════════════════════════════════════════════
   MARK CURRENT INSTALLMENT AS FULLY PAID IF NOW COVERED
════════════════════════════════════════════════════════════════════════════ */
$newTotalPaidOnCurrent = round($existingPaid_base + $amountForCurrent, 4);
if ($newTotalPaidOnCurrent >= $installmentTotal - 0.001) {
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

        if ($isPayingInForeignCurrency) {
            $nextPaidAmount_stored = round($nextPaidAmount * $resolvedRate, 2);
            $nextTaxStored_amount  = round($nextTaxStored * $resolvedRate, 2);
        } else {
            $nextPaidAmount_stored = round($nextPaidAmount, 2);
            $nextTaxStored_amount  = round($nextTaxStored, 2);
        }

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
            '" . number_format($nextPaidAmount_stored, 2, '.', '') . "',
            '0',
            '0',
            '3',
            $currencyIdSql,
            'Auto-applied overpayment from receipt {$receiptNumber}',
            '" . number_format($latestTaxRate, 2, '.', '') . "',
            '" . number_format($nextTaxStored_amount, 2, '.', '') . "',
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

        if ($nextPaidAmount >= $nextPmt['next_remaining'] - 0.001) {
            Database::iud("
                UPDATE `course_payments`
                SET `payment_status_payment_status_id` = '5'
                WHERE `course_payments_id` = '$nextPaymentId'
            ");
        }

        /* Log the overpayment application */
        $overpay_log = Database::escape(
            'Auto-applied overpayment from ' . $receiptNumber
                . ' [' . ($currencyId == 1 ? 'LKR' : $courseCurrencyCode) . ' ' . number_format($nextPaidAmount_stored, 2)
                . ' (incl. tax ' . number_format($nextTaxStored_amount, 2) . ')]'
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
════════════════════════════════════════════════════════════════════════════ */
if (in_array((int)$pay['lead_student_status_lead_student_status_id'], [5, 9])) {
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

$status->done = 'Payment processing has completed successfully.';
echo json_encode($status);
exit;
