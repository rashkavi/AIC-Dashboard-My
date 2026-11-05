<?php

/**
 * uploadStudentPaymentReceipt.php - FIXED CURRENCY HANDLING WITH CORRECTED TAX CALCULATION
 *
 * KEY RULES:
 * - paid_amount stored in the currency the student ACTUALLY paid in (original, no conversion)
 * - tax_amount stored in the SAME currency as paid_amount (converted back from base if needed)
 * - panelty_amount is ALWAYS stored in LKR (both the per-receipt column here, and the CP-level
 *   panelty_payments table) — regardless of what currency paid_amount/discount_amount are in.
 * - exchange_rate stored for reverse conversion when needed
 * - All balance/overpayment calculations done in BASE currency (e.g. GBP)
 * - Before storing, amounts are converted BACK to payment currency (e.g. LKR)
 *
 * ✅ v9 FIX (overpayment double-rounding): base-currency intermediates are no longer rounded
 *   to 2dp before use; overpayment is computed directly in the payment currency first, then
 *   the base-currency equivalent is derived from that (not the other way round).
 *
 * ✅ v10 FIX (multi-installment overpayment drift): the distribution loop tracks and applies
 *   the overpayment directly in the payment currency throughout, rounding only once per
 *   installment at the point of storage.
 *
 * ✅ v11 FIX (penalty currency mismatch): this receipt's entered penalty is normalized to LKR
 *   BEFORE storage, independent of which currency mode the modal was in when it was typed —
 *   panelty_amount is never stored in course currency.
 *
 * ✅ v12 FIX (CP-level penalty consistency): the CP-level penalty total (panelty_payments
 *   table, always LKR) is now converted to base currency using the SAME live exchange rate
 *   this submission carries, and combined with this receipt's entered penalty and all prior
 *   receipts' penalties (also normalized as LKR→base, not paid_amount-rate based) — exactly
 *   mirroring how add-receipt.php computes EXISTING_TOTAL_PENALTY / EXISTING_TOTAL_PENALTY_LKR
 *   on the page the user is looking at. This is what makes "what you see is what gets saved"
 *   actually true — including when the user changes the paid date or types a custom rate.
 *
 * ✅ v13 FIX (tiny overpayment silently lost): overpayment is now detected in the PAYMENT
 *   currency ($hasOverpayment, smallest unit 0.01). Before, the check used the base-currency
 *   equivalent (> 0.001), so e.g. LKR 0.13 became EUR 0.0003, was treated as "no overpayment",
 *   yet the current receipt was still stored as (paid - 0.13) → that 0.13 vanished.
 *   The distribution loop condition is also >= 0.01 so a 0.01 overpayment is not dropped.
 */

$status = new stdClass();

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/DateTimeClass.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/mail-process.php");

if (!SessionUser::hasValidSession()) {
    echo json_encode(['error' => 'Authentication Failed!']);
    exit;
}

$timezone = new DateTimeZone("Asia/Colombo");
$now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');
$today    = date('Y-m-d', strtotime($now));


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

function extractTaxFromInclusive(float $taxInclusiveAmt, float $taxRate): array
{
    if ($taxRate <= 0 || $taxInclusiveAmt <= 0) {
        return ['base' => $taxInclusiveAmt, 'tax' => 0.0];
    }
    $taxRateDecimal = $taxRate / 100.0;
    $basePortion = 1.0 - $taxRateDecimal;
    $base = $taxInclusiveAmt * $basePortion;
    $tax = $taxInclusiveAmt * $taxRateDecimal;
    return ['base' => round($base, 2), 'tax' => round($tax, 2)];
}

function calculateTaxInclusiveAmount(float $base, float $taxRate): float
{
    if ($taxRate <= 0 || $base <= 0) {
        return $base;
    }
    $taxRateDecimal = $taxRate / 100.0;
    $basePortion = 1.0 - $taxRateDecimal;
    if ($basePortion <= 0) {
        return $base;
    }
    return round($base / $basePortion, 2);
}

function calculateTaxFromBase(float $base, float $taxRate): float
{
    if ($taxRate <= 0 || $base <= 0) {
        return 0.0;
    }
    $divisor = 100.0 - $taxRate;
    if ($divisor <= 0) {
        return 0.0;
    }
    return round($base * ($taxRate / $divisor), 2);
}

/* ════════════════════════════════════════════════════════════════════════════
   READ INPUT — KEEP ORIGINAL AMOUNTS (NO CONVERSION HERE)
════════════════════════════════════════════════════════════════════════════ */
$payment_id           = (int)($_POST['payment_id']      ?? 0);
$method               = (int)($_POST['method']          ?? 0);
$paid_date            = trim($_POST['paid_date']        ?? '');
$note                 = trim($_POST['note']             ?? '');

$paid_amount_original = (float)($_POST['paid_amount']     ?? 0);
$discount_original    = (float)($_POST['discount_amount'] ?? 0);
$penalty_original     = (float)($_POST['penalty_amount']  ?? 0); // this receipt's newly entered penalty only

/* ── Currency / exchange rate data from JS ── */
$currency           = isset($_POST['currency']) ? json_decode($_POST['currency'], true) : null;
$selectedCurrencyId = null;
$exchangeRateId     = null;
$exchangeRate       = 1.0;
$currencyMode       = 'base';
$isCustomRate       = false;
$customRateValue    = null;

if ($currency !== null) {
    $selectedCurrencyId = isset($currency['selectedCurrencyId']) ? (int)$currency['selectedCurrencyId']   : null;
    $exchangeRateId     = isset($currency['exchangeRateId'])     ? (int)$currency['exchangeRateId']       : null;
    $exchangeRate       = isset($currency['exchangeRate'])       ? (float)$currency['exchangeRate']       : 1.0;
    $currencyMode       = isset($currency['mode'])               ? trim($currency['mode'])                : 'base';
    $isCustomRate       = isset($currency['isCustomRate'])       ? (bool)$currency['isCustomRate']        : false;
    $customRateValue    = isset($currency['customRateValue'])    ? (float)$currency['customRateValue']    : null;

    if ($isCustomRate && $customRateValue > 0) {
        $exchangeRate   = $customRateValue;
        $exchangeRateId = null;
    }
}
if ($exchangeRate <= 0) $exchangeRate = 1.0;

if (!$selectedCurrencyId) {
    if ($currencyMode === 'lkr') {
        $selectedCurrencyId = 1; // LKR
    }
}

$lkrCurrencyId = 1;

/* ════════════════════════════════════════════════════════════════════════════
   FETCH LATEST TAX RATE
════════════════════════════════════════════════════════════════════════════ */
$taxRateRow = Database::search("
    SELECT `tax_rate` AS `rate` FROM `tax_rates`
    ORDER BY `date_added` DESC LIMIT 1
");
$latestTaxRate = 0.0;
if ($taxRateRow && $taxRateRow->num_rows > 0) {
    $latestTaxRate = (float)$taxRateRow->fetch_assoc()['rate'];
}

/* ════════════════════════════════════════════════════════════════════════════
   FETCH PAYMENT PLAN
════════════════════════════════════════════════════════════════════════════ */
$paymentPlan = Database::search("
    SELECT
        s.student_id,
        cp.course_payments_id,
        cp.refference, cp.amount, cp.discount, cp.due_date,
        cp.panelty_payment_option_panelty_payment_option_id,
        cp.discount_note,
        ps.payment_status, ps.payment_status_id,
        i.intake_id, i.intake,
        pr.programme_id, pr.programme,
        f.faculty_id, f.faculty,
        b.branch_id, b.branch,
        ld.first_name, ld.last_name,
        t.title,
        shi.lead_student_status_lead_student_status_id,
        ld.email,
        pt.payment_type_id, pt.payment_type,
        shi.students_has_intakes_id,
        dt.discount_type, dt.discount_type_id,
        c.code   AS currency_code,
        c.symbol AS currency_symbol,
        c.decimal_places,
        c.currency_id,
        shi.status_status_id
    FROM course_payments cp
    INNER JOIN payment_type   pt  ON cp.payment_type_payment_type_id   = pt.payment_type_id
    INNER JOIN discount_type  dt  ON cp.discount_type_discount_type_id = dt.discount_type_id
    INNER JOIN payment_status ps  ON cp.payment_status_payment_status_id = ps.payment_status_id
    INNER JOIN payment_plan_has_students_has_intakes pshi
        ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category pc ON pshi.payment_category_payment_category_id = pc.payment_category_id
    INNER JOIN currencies c        ON pc.currencies_currency_id = c.currency_id
    INNER JOIN students_has_intakes shi
        ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN intakes    i   ON shi.intakes_intake_id       = i.intake_id
    INNER JOIN programmes pr  ON i.programmes_programme_id   = pr.programme_id
    INNER JOIN faculties  f   ON pr.faculties_faculty_id     = f.faculty_id
    INNER JOIN branches   b   ON f.branches_branch_id        = b.branch_id
    INNER JOIN students   s   ON shi.students_student_id     = s.student_id
    INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN title      t   ON s.title_title_id            = t.title_id
    WHERE cp.course_payments_id = '" . $payment_id . "'
");

if (!$paymentPlan || $paymentPlan->num_rows == 0) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
$paymentData = $paymentPlan->fetch_assoc();

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
if (!(
    SessionUser::ifUserIsPro($sessionUserRole)          ||
    SessionUser::ifUserIsAccounts($sessionUserRole)     ||
    UserPermissions::validPermisisonType('leads')        ||
    UserPermissions::validPermisisonType('payments')     ||
    UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id'])
)) {
    echo json_encode(['error' => 'You are not authorized to this action.']);
    exit;
}

$baseCurrencyId = (int)$paymentData['currency_id'];
$currCode       = $paymentData['currency_code'];

if (!$selectedCurrencyId) {
    $selectedCurrencyId = $baseCurrencyId;
}

$isLkrPaymentForForeignCourse = ($selectedCurrencyId == 1 && $baseCurrencyId != 1);

/* ════════════════════════════════════════════════════════════════════════════
   VALIDATION
════════════════════════════════════════════════════════════════════════════ */
$paymentSlips = [];

if ($method == 0)                   $status->method    = 'Please select payment method';
if (empty($paid_date))              $status->paid_date = 'Please select paid date';
elseif (CheckDateTime::isFutureDate($paid_date)) $status->paid_date = 'Paid date cannot be in the future';
if ($paid_amount_original <= 0)     $status->amount    = 'Please enter paid amount';

if (isset($_FILES['slips'])) {
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $ext_map = ['application/pdf' => '.pdf', 'image/jpeg' => '.jpg', 'image/png' => '.png', 'image/jpg' => '.jpg'];
    foreach ($_FILES['slips']['name'] as $k => $slipName) {
        $mimeType = $_FILES['slips']['type'][$k];
        if (!in_array($mimeType, $allowed)) {
            $status->slip[] = "File '$slipName' is not a valid type. Only PDF, JPEG, PNG allowed.";
        } elseif ($_FILES['slips']['error'][$k] !== 0) {
            $status->slip[] = "Error uploading '$slipName'.";
        } elseif ($_FILES['slips']['size'][$k] > 10 * 1024 * 1024) {
            $status->slip[] = "File '$slipName' exceeds 10MB limit.";
        } else {
            $hash = hash_file('sha256', $_FILES['slips']['tmp_name'][$k]);
            $dup  = Database::search("SELECT course_payment_slip_id FROM course_payment_slips  INNER JOIN `course_payment_has_payments` AS `cpp`
                                    ON `course_payment_slips`.`course_payment_has_payment_id` = `cpp`.`course_payment_has_payment_id`
                                     WHERE `slip_hash` = '" . $hash . "' AND `cpp`.`payment_status_payment_status_id` IN (2,3)");
            if ($dup->num_rows > 0) {
                $status->slip[] = "$slipName — Duplicate slip detected.";
            } else {
                $paymentSlips[$k] = 'R' . $payment_id . '_' . uniqid() . $ext_map[$mimeType];
            }
        }
    }
}

if (!empty($note) && strlen($note) > 150) $status->note = 'Note must be less than 150 characters';

foreach (['error', 'amount', 'slip', 'paid_date', 'method', 'discount', 'panelty', 'note'] as $p) {
    if (property_exists($status, $p)) {
        echo json_encode($status);
        exit;
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   ✅ v11 FIX: normalize this receipt's entered penalty to LKR, always —
   independent of whether the modal was in 'base' or 'lkr' currency mode when
   it was typed. panelty_amount is never stored in course currency.
════════════════════════════════════════════════════════════════════════════ */
if ($selectedCurrencyId == $lkrCurrencyId) {
    $penaltyEntered_LKR = $penalty_original;
} elseif ($baseCurrencyId != $lkrCurrencyId) {
    $penaltyEntered_LKR = $penalty_original * $exchangeRate;
} else {
    $penaltyEntered_LKR = $penalty_original;
}

/* ════════════════════════════════════════════════════════════════════════════
   ✅ v12: CP-level penalty (panelty_payments table) — always LKR, same figure
   add-receipt.php shows as `panelty_amount_lkr`. This is separate from any
   penalty entered on individual receipts and must be added on top, not
   duplicated.
════════════════════════════════════════════════════════════════════════════ */
$mainPenaltyQ = Database::search("
    SELECT SUM(amount) AS penalty FROM `panelty_payments`
    WHERE course_payments_course_payments_id = '$payment_id'
");
$mainPenaltyAmount_LKR = 0.0;
if ($mainPenaltyQ && $mainPenaltyQ->num_rows > 0) {
    $mainPenaltyAmount_LKR = (float)($mainPenaltyQ->fetch_assoc()['penalty'] ?? 0);
}

/* ════════════════════════════════════════════════════════════════════════════
   CONVERT ORIGINAL AMOUNTS TO BASE CURRENCY (for all calculations)

   paid_amount / discount_amount follow the PAYMENT currency mode (LKR → base
   by dividing by rate, or already-base as-is).

   Penalty (this receipt's entry + the CP-level total) is always LKR → base,
   via the course's own live exchange rate — regardless of payment mode.
   This mirrors add-receipt.php's currentPenaltyBase() exactly.

   ✅ These are INTERMEDIATE values — kept at full float precision, not
   rounded to 2dp, to avoid compounding currency-conversion rounding error.
════════════════════════════════════════════════════════════════════════════ */
if ($isLkrPaymentForForeignCourse) {
    $paid_base     = $paid_amount_original / $exchangeRate;
    $discount_base = $discount_original    / $exchangeRate;
} else {
    $paid_base     = $paid_amount_original;
    $discount_base = $discount_original;
}

$penaltyEntered_base = ($baseCurrencyId != $lkrCurrencyId)
    ? $penaltyEntered_LKR / $exchangeRate
    : $penaltyEntered_LKR;

$mainPenalty_base = ($baseCurrencyId != $lkrCurrencyId)
    ? $mainPenaltyAmount_LKR / $exchangeRate
    : $mainPenaltyAmount_LKR;

$penalty_base = $penaltyEntered_base + $mainPenalty_base;

/* ════════════════════════════════════════════════════════════════════════════
   CALCULATE CURRENT INSTALLMENT PAYABLE (all in base currency)
════════════════════════════════════════════════════════════════════════════ */
$cpDiscountAmt = ($paymentData['discount_type_id'] == 3)
    ? $paymentData['amount'] / 100.0 * $paymentData['discount']
    : (float)$paymentData['discount'];

// ✅ v12 FIX: receipt_penalty_base no longer piggybacks on paid_amount's
// currency/rate (that column describes paid_amount only). panelty_amount on
// every row is ALWAYS LKR, so it must convert LKR → base via the COURSE's
// own exchange rate — which is why this now branches on $baseCurrencyId vs
// LKR, not on that row's currencies_currency_id / currency_exchange_rate.
$existingQ = Database::search("
    SELECT
        SUM(CASE
            WHEN payment_status_payment_status_id IN (2,3) THEN
                COALESCE(
                    CASE
                        WHEN currencies_currency_id != '{$baseCurrencyId}' AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN paid_amount / currency_exchange_rate
                        ELSE paid_amount
                    END,
                0)
            ELSE 0
        END) AS total_paid_base,
        SUM(CASE
            WHEN payment_status_payment_status_id IN (2,3) THEN
                COALESCE(
                    CASE
                        WHEN currencies_currency_id != '{$baseCurrencyId}' AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN discount_amount / currency_exchange_rate
                        ELSE discount_amount
                    END,
                0)
            ELSE 0
        END) AS receipt_discount_base,
        SUM(CASE
            WHEN payment_status_payment_status_id IN (2,3) THEN
                COALESCE(
                    CASE
                        WHEN '{$baseCurrencyId}' != {$lkrCurrencyId} AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN panelty_amount / currency_exchange_rate
                        WHEN '{$baseCurrencyId}' = {$lkrCurrencyId}
                        THEN panelty_amount
                        ELSE 0
                    END,
                0)
            ELSE 0
        END) AS receipt_penalty_base
    FROM course_payment_has_payments
    WHERE course_payments_course_payments_id = '$payment_id'
");

$exRow               = $existingQ->fetch_assoc();
$existingPaid_base    = (float)($exRow['total_paid_base']       ?? 0);
$receiptDisc_base     = (float)($exRow['receipt_discount_base'] ?? 0);
$receiptPenalty_base  = (float)($exRow['receipt_penalty_base']  ?? 0); // prior receipts' entered penalties, in base currency

$totalDiscountBase = $cpDiscountAmt + $receiptDisc_base + $discount_base;

// ✅ v12: total penalty = prior receipts' penalties + CP-level (panelty_payments)
// + this receipt's newly entered penalty — all normalized to base currency via
// the COURSE's own live rate. This is the exact structure add-receipt.php uses
// for EXISTING_TOTAL_PENALTY + rawPenalty, so the two always agree.
$totalPenaltyBase  = $receiptPenalty_base + $penalty_base;
$basePayable       = max(0.0, $paymentData['amount'] - $totalDiscountBase + $totalPenaltyBase);

$totalPayable = calculateTaxInclusiveAmount($basePayable, $latestTaxRate);

/* ════════════════════════════════════════════════════════════════════════════
   EXTRACT TAX FROM THIS PAYMENT (in base currency)
════════════════════════════════════════════════════════════════════════════ */
$extracted   = extractTaxFromInclusive($paid_base, $latestTaxRate);
$paidNetBase = $extracted['base'];
$paidTax_base = $extracted['tax'];

/* ════════════════════════════════════════════════════════════════════════════
   BALANCE CALCULATION (base currency)
════════════════════════════════════════════════════════════════════════════ */
$totalPaidAfterThisReceipt = $existingPaid_base + $paid_base;
$paidNetSoFar = extractTaxFromInclusive($totalPaidAfterThisReceipt, $latestTaxRate)['base'];
$remainingBase = max(0.0, $basePayable - $paidNetSoFar);
$balanceAfter_base = calculateTaxInclusiveAmount($remainingBase, $latestTaxRate);

/* ════════════════════════════════════════════════════════════════════════════
   OVERPAYMENT DETECTION — computed directly in the PAYMENT currency first,
   base-currency equivalent derived FROM that figure (single rounding point).
════════════════════════════════════════════════════════════════════════════ */
$remainingBefore_base = max(0.0, $totalPayable - $existingPaid_base);

$remainingBeforeInPaymentCcy = $isLkrPaymentForForeignCourse
    ? $remainingBefore_base * $exchangeRate
    : $remainingBefore_base;

$overpayAmount_paymentCcy = max(0.0, round($paid_amount_original - $remainingBeforeInPaymentCcy, 2));

$overpayAmount_base = $isLkrPaymentForForeignCourse
    ? $overpayAmount_paymentCcy / $exchangeRate
    : $overpayAmount_paymentCcy;

// ✅ v13 FIX: overpayment is judged in the PAYMENT currency (smallest unit 0.01),
// not in base currency — LKR 0.13 is only EUR 0.0003 and was being ignored
// while still being cut off the stored paid_amount (so it vanished).
$hasOverpayment = ($overpayAmount_paymentCcy >= 0.01);

$amountForCurrent_base = min($paid_base, $remainingBefore_base);

$currentExtracted      = extractTaxFromInclusive($amountForCurrent_base, $latestTaxRate);
$taxForCurrent_base    = $currentExtracted['tax'];

$remaingBaseFor_Conversion = $remainingBefore_base;

/* ════════════════════════════════════════════════════════════════════════════
   ✅ CRITICAL: ALWAYS store the ORIGINAL paid amount / discount as entered.
   Penalty is stored as this receipt's own LKR-normalized entry ONLY — the
   CP-level panelty_payments figure stays in its own table and is never
   duplicated into course_payment_has_payments.panelty_amount.
════════════════════════════════════════════════════════════════════════════ */
$paidAmountForCurrent_stored = round($paid_amount_original, 2);
$discountForStorage          = round($discount_original,    2);
$penaltyForStorage           = round($penaltyEntered_LKR,   2); // ✅ always LKR, this receipt's entry only

if ($isLkrPaymentForForeignCourse) {
    $taxForCurrent_stored = round($taxForCurrent_base * $exchangeRate, 2);
} else {
    $taxForCurrent_stored = round($taxForCurrent_base, 2);
}

/* ════════════════════════════════════════════════════════════════════════════
   OVERPAYMENT → distribute to next installments
════════════════════════════════════════════════════════════════════════════ */
$nextInstallmentPayments = [];

if ($hasOverpayment) { // ✅ v13: was ($overpayAmount_base > 0.001)
    $nextCheck = Database::search("
        SELECT COUNT(*) AS cnt
        FROM course_payments cp
        INNER JOIN payment_status ps ON cp.payment_status_payment_status_id = ps.payment_status_id
        WHERE cp.payment_plan_has_students_has_intakes_id = (
            SELECT payment_plan_has_students_has_intakes_id
            FROM course_payments WHERE course_payments_id = '$payment_id' LIMIT 1
        )
        AND cp.course_payments_id > '$payment_id'
        AND ps.payment_status_id IN (1, 2)
    ");
    $nextCnt = (int)($nextCheck->fetch_assoc()['cnt'] ?? 0);

    if ($nextCnt == 0) {
        http_response_code(400);
        $status->error     = 'Cannot process overpayment. No future installment available.';
        $status->errorType = 'OVERPAYMENT_NO_NEXT_INSTALLMENT';
        $status->errorDetails = [
            'message'        => "Paid amount exceeds current installment balance.",
            'overpayAmount'  => $overpayAmount_base,
            'currentBalance' => $remainingBefore_base,
            'suggestion'     => 'Please enter an amount ≤ the current balance.',
        ];
        echo json_encode($status);
        exit;
    }

    $nextPayments = Database::search("
        SELECT
            cp.course_payments_id,
            cp.amount,
            cp.discount,
            cp.discount_type_discount_type_id
        FROM course_payments cp
        INNER JOIN payment_status ps ON cp.payment_status_payment_status_id = ps.payment_status_id
        WHERE cp.payment_plan_has_students_has_intakes_id = (
            SELECT payment_plan_has_students_has_intakes_id
            FROM course_payments WHERE course_payments_id = '$payment_id' LIMIT 1
        )
        AND cp.course_payments_id > '$payment_id'
        AND ps.payment_status_id IN (1, 2)
        ORDER BY cp.due_date ASC, cp.course_payments_id ASC
    ");

    $remainingOverpay_paymentCcy = $overpayAmount_paymentCcy;

    // ✅ v13: >= 0.01 (was > 0.01) so an overpayment of exactly 0.01 is not dropped
    while ($remainingOverpay_paymentCcy >= 0.01 && ($nextRow = $nextPayments->fetch_assoc())) {
        $nextId = (int)$nextRow['course_payments_id'];
        $nextDisc = ($nextRow['discount_type_discount_type_id'] == 3)
            ? $nextRow['amount'] / 100.0 * $nextRow['discount']
            : (float)$nextRow['discount'];

        // ✅ this installment's own penalty ledger total, always LKR — same
        // source table/column used for $mainPenaltyQ above, filtered by ITS OWN id
        $nextPenaltyQ = Database::search("
            SELECT SUM(amount) AS penalty FROM `panelty_payments`
            WHERE course_payments_course_payments_id = '{$nextId}'
        ");
        $nextPenaltyAmount_LKR = 0.0;
        if ($nextPenaltyQ && $nextPenaltyQ->num_rows > 0) {
            $nextPenaltyAmount_LKR = (float)($nextPenaltyQ->fetch_assoc()['penalty'] ?? 0);
        }
        // ✅ LKR → base via the course's own live rate — identical logic to
        // $mainPenalty_base above, not the payment-currency exchange rate
        $nextPenalty_base = ($baseCurrencyId != $lkrCurrencyId)
            ? $nextPenaltyAmount_LKR / $exchangeRate
            : $nextPenaltyAmount_LKR;

        $nextExistingQ = Database::search("
            SELECT
                COALESCE(SUM(
                    CASE
                        WHEN currencies_currency_id != '{$baseCurrencyId}' AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN paid_amount / currency_exchange_rate
                        ELSE paid_amount
                    END
                ), 0) AS paid_base,
                COALESCE(SUM(
                    CASE
                        WHEN currencies_currency_id != '{$baseCurrencyId}' AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN discount_amount / currency_exchange_rate
                        ELSE discount_amount
                    END
                ), 0) AS receipt_discount_base,
                COALESCE(SUM(
                    CASE
                        WHEN '{$baseCurrencyId}' != {$lkrCurrencyId} AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN panelty_amount / currency_exchange_rate
                        WHEN '{$baseCurrencyId}' = {$lkrCurrencyId}
                        THEN panelty_amount
                        ELSE 0
                    END
                ), 0) AS receipt_penalty_base
            FROM course_payment_has_payments
            WHERE course_payments_course_payments_id = '$nextId'
              AND payment_status_payment_status_id IN (2, 3)
        ");
        $nextExRow = $nextExistingQ->fetch_assoc();
        $nextExistingPaid_base   = (float)($nextExRow['paid_base']            ?? 0);
        $nextReceiptDisc_base    = (float)($nextExRow['receipt_discount_base'] ?? 0);
        $nextReceiptPenalty_base = (float)($nextExRow['receipt_penalty_base']  ?? 0);

        // course amount − (plan-level discount + this installment's own receipt discounts)
        //              + (panelty_payments ledger + this installment's own receipt penalties)
        $nextTotalDiscountBase = $nextDisc + $nextReceiptDisc_base;
        $nextTotalPenaltyBase  = $nextPenalty_base + $nextReceiptPenalty_base;

        $nextBasePayable = max(0.0, $nextRow['amount'] - $nextTotalDiscountBase + $nextTotalPenaltyBase);
        $nextTotalPayable_base = calculateTaxInclusiveAmount($nextBasePayable, $latestTaxRate);

        $nextTotalPayable_paymentCcy = $isLkrPaymentForForeignCourse
            ? $nextTotalPayable_base * $exchangeRate
            : $nextTotalPayable_base;
        $nextExistingPaid_paymentCcy = $isLkrPaymentForForeignCourse
            ? $nextExistingPaid_base * $exchangeRate
            : $nextExistingPaid_base;

        $nextRemaining_paymentCcy = max(0.0, $nextTotalPayable_paymentCcy - $nextExistingPaid_paymentCcy);
        $applyToNext_paymentCcy   = min($remainingOverpay_paymentCcy, $nextRemaining_paymentCcy);
        $remainingOverpay_paymentCcy = round($remainingOverpay_paymentCcy - $applyToNext_paymentCcy, 2);

        $nextExtracted = extractTaxFromInclusive($applyToNext_paymentCcy, $latestTaxRate);

        $applyToNext_stored = round($applyToNext_paymentCcy, 2);
        $nextTax_stored      = $nextExtracted['tax'];

        $applyToNext_base = $isLkrPaymentForForeignCourse
            ? $applyToNext_paymentCcy / $exchangeRate
            : $applyToNext_paymentCcy;

        $nextInstallmentPayments[] = [
            'payment_id'              => $nextId,
            'paid_amount_stored'      => $applyToNext_stored,
            'tax_amount_stored'       => $nextTax_stored,
            'next_total_payable_base' => $nextTotalPayable_base,
            'apply_base'              => $applyToNext_base,
        ];
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   BUILD RECEIPT NUMBER
════════════════════════════════════════════════════════════════════════════ */
$tz  = new DateTimeZone("Asia/Colombo");
$now = (new DateTime("now", $tz))->format('Y-m-d H:i:s');

$branchName   = $paymentData['branch'] ?? 'XX';
$branchPrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $branchName), 0, 2));
if (strlen($branchPrefix) < 2) $branchPrefix = str_pad($branchPrefix, 2, 'X');
$year = date('Y');

$seqQ  = Database::search("SELECT COUNT(*) AS cnt FROM course_payment_receipts WHERE receipt_number LIKE '" . addslashes($branchPrefix . $year) . "-%'");
$seq   = 1 + (int)(($seqQ && $seqQ->num_rows > 0) ? $seqQ->fetch_assoc()['cnt'] : 0);
$receiptNumber = $branchPrefix . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

Database::iud("
    INSERT INTO course_payment_receipts
        (receipt_number, users_user_id, date_added, students_has_intakes_students_has_intakes_id)
    VALUES
        ('" . addslashes($receiptNumber) . "', '" . $sessionUserObject['user_id'] . "', '$now', '" . $paymentData['students_has_intakes_id'] . "')
");
$receiptId = (int)Database::$connection->insert_id;

/* ════════════════════════════════════════════════════════════════════════════
   INSERT PAYMENT RECORD
   - panelty_amount → ✅ ALWAYS LKR (v11), this receipt's own entry only —
     mainPenaltyAmount is tracked separately in panelty_payments and never
     re-written here.
   - currency_exchange_rate → ✅ v12: stored whenever the COURSE itself is
     non-LKR (not just when the payment currency differs from it), so a
     penalty on this row can always be converted back later even if this
     particular payment happened to be made directly in the course's own
     currency.
════════════════════════════════════════════════════════════════════════════ */
$qn = fn($v) => $v !== null ? "'" . addslashes((string)$v) . "'" : 'NULL';

if ($hasOverpayment) { // ✅ v13: was ($overpayAmount_paymentCcy > 0.001)
    $paidAmountForCurrent_stored = round($paid_amount_original - $overpayAmount_paymentCcy, 2);
}

$cols = "`course_payments_course_payments_id`, `payment_method_payment_method_id`,
         `date_paid`, `paid_amount`, `discount_amount`, `panelty_amount`,
         `payment_status_payment_status_id`, `currencies_currency_id`,
         `note`, `tax_rate`, `tax_amount`,
         `course_payment_receipts_course_payment_receipt_id`,`date_added`";

$vals = "'{$payment_id}', '{$method}',
         '{$paid_date}',
         '" . round($paidAmountForCurrent_stored, 2) . "',
         '" . round($discountForStorage,     2) . "',
         '" . round($penaltyForStorage,      2) . "',
         '3', '{$selectedCurrencyId}',
         " . ($note !== '' ? "'" . addslashes($note) . "'" : 'NULL') . ",
         '{$latestTaxRate}', '{$taxForCurrent_stored}',
         '{$receiptId}', '{$now}'";

if ($baseCurrencyId != $lkrCurrencyId) {
    $cols .= ", `currency_exchange_rate_id`, `currency_exchange_rate`";
    $vals .= ", " . $qn($exchangeRateId) . ", " . $qn($exchangeRate);
}

Database::iud("INSERT INTO course_payment_has_payments ({$cols}) VALUES ({$vals})");
$paymentReceiptId = (int)Database::$connection->insert_id;

if ($amountForCurrent_base >= $remainingBefore_base - 0.001) {
    Database::iud("
        UPDATE course_payments SET payment_status_payment_status_id = '5'
        WHERE course_payments_id = '$payment_id'
    ");
}

/* ════════════════════════════════════════════════════════════════════════════
   INSERT OVERPAYMENT RECORDS FOR NEXT INSTALLMENTS
   panelty_amount hardcoded to '0' — overpayment never carries a penalty.
════════════════════════════════════════════════════════════════════════════ */
foreach ($nextInstallmentPayments as $nxt) {
    $nxtId   = (int)$nxt['payment_id'];
    $nxtPaid = $nxt['paid_amount_stored'];
    $nxtTax  = $nxt['tax_amount_stored'];

    $nxtCols = "`course_payments_course_payments_id`, `payment_method_payment_method_id`,
                `date_paid`, `paid_amount`, `discount_amount`, `panelty_amount`,
                `payment_status_payment_status_id`, `currencies_currency_id`,
                `note`, `tax_rate`, `tax_amount`,
                `course_payment_receipts_course_payment_receipt_id`,`date_added`";

    $nxtVals = "'{$nxtId}', '{$method}',
                '{$paid_date}', '{$nxtPaid}', '0', '0',
                '3', '{$selectedCurrencyId}',
                'Auto-applied overpayment from {$receiptNumber}',
                '{$latestTaxRate}', '{$nxtTax}',
                '{$receiptId}', '{$now}'";

    if ($baseCurrencyId != $lkrCurrencyId) {
        $nxtCols .= ", `currency_exchange_rate_id`, `currency_exchange_rate`";
        $nxtVals .= ", " . $qn($exchangeRateId) . ", " . $qn($exchangeRate);
    }

    Database::iud("INSERT INTO course_payment_has_payments ({$nxtCols}) VALUES ({$nxtVals})");
    $nxtReceiptId = (int)Database::$connection->insert_id;

    if ($nxt['apply_base'] >= $nxt['next_total_payable_base'] - 0.001) {
        Database::iud("
            UPDATE course_payments SET payment_status_payment_status_id = '5'
            WHERE course_payments_id = '$nxtId'
        ");
    }

    $overpayLog = "Auto-applied overpayment from {$receiptNumber}: "
        . round($nxtPaid, 2) . " (incl. tax " . round($nxtTax, 2) . ")";
    Database::iud("
        INSERT INTO payment_logs
            (course_payments_course_payments_id, course_payment_has_payment_id, users_user_id, date_updated, log)
        VALUES
            ('{$nxtId}', '{$nxtReceiptId}', '" . $sessionUserObject['user_id'] . "', '$now', '" . addslashes($overpayLog) . "')
    ");
}

/* ════════════════════════════════════════════════════════════════════════════
   SAVE PAYMENT SLIPS
════════════════════════════════════════════════════════════════════════════ */
if (!empty($paymentSlips)) {
    foreach ($_FILES['slips']['name'] as $i => $slipName) {
        if (!isset($paymentSlips[$i])) continue;
        $hash = hash_file('sha256', $_FILES['slips']['tmp_name'][$i]);
        $ref  = strtoupper(substr($hash, 0, 10));
        Database::iud("
            INSERT INTO course_payment_slips
                (course_payment_has_payment_id, slip_name, reference_number, slip_hash)
            VALUES
                ('{$paymentReceiptId}', '" . addslashes($paymentSlips[$i]) . "', '{$ref}', '{$hash}')
        ");
        move_uploaded_file($_FILES['slips']['tmp_name'][$i], "../assets/uploads/payment-slips/" . $paymentSlips[$i]);
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   PAYMENT LOG
════════════════════════════════════════════════════════════════════════════ */
$currencyLabel = ($selectedCurrencyId == 1) ? 'LKR' : $currCode;
$log = "Payment receipt uploaded. {$currencyLabel} " . number_format($paid_amount_original, 2);
if ($latestTaxRate > 0) {
    $log .= " | base=" . number_format($paidNetBase, 2)
        . " + tax="  . number_format($paidTax_base, 2)
        . " ({$currCode}) | rate={$latestTaxRate}%";
    if ($isLkrPaymentForForeignCourse) {
        $log .= " | exRate={$exchangeRate}";
    }
}
if ($penaltyEntered_LKR > 0.001) {
    $log .= " | penalty=LKR " . number_format($penaltyEntered_LKR, 2);
}
if ($hasOverpayment) { // ✅ v13: was ($overpayAmount_base > 0.001); now logs the amount too
    $log .= " | Overpayment " . $currencyLabel . " " . number_format($overpayAmount_paymentCcy, 2) . " → next installment(s)";
}

$logCols = "`course_payments_course_payments_id`, `course_payment_has_payment_id`, `users_user_id`, `date_updated`, `log`";
$logVals = "'{$payment_id}', '{$paymentReceiptId}', '" . $sessionUserObject['user_id'] . "', '$now', '" . addslashes($log) . "'";
if (!empty($note)) {
    $logCols .= ", `note`";
    $logVals .= ", '" . addslashes($note) . "'";
}
Database::iud("INSERT INTO payment_logs ({$logCols}) VALUES ({$logVals})");

/* ── Registration status update ── */
if ($paymentData['payment_type_id'] == 18 && $paymentData['lead_student_status_lead_student_status_id'] == 4) {
    Database::iud("UPDATE students_has_intakes SET lead_student_status_lead_student_status_id='3' WHERE students_has_intakes_id='" . $paymentData['students_has_intakes_id'] . "'");

    Database::iud("INSERT INTO students_logs (students_has_intakes_students_has_intakes_id, date_updated, users_user_id, log, lead_student_status_lead_student_status_id)
        VALUES ('" . $paymentData['students_has_intakes_id'] . "', '$now', '" . $sessionUserObject['user_id'] . "', 'Registration Payment marked as paid', '3')");
}

/* ── First installment log ── */
if (in_array($paymentData['lead_student_status_lead_student_status_id'], [5, 9])) {
    $firstQ = Database::search("
        SELECT cp.course_payments_id FROM course_payments cp
        INNER JOIN payment_type pt ON cp.payment_type_payment_type_id = pt.payment_type_id
        INNER JOIN payment_plan_has_students_has_intakes pshi
            ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
        WHERE cp.payment_plan_has_students_has_intakes_id = (
            SELECT payment_plan_has_students_has_intakes_id FROM course_payments WHERE course_payments_id = '$payment_id' LIMIT 1
        )
        AND pshi.students_has_intakes_students_has_intakes_id = '" . $paymentData['students_has_intakes_id'] . "'
        AND cp.payment_status_payment_status_id != '9'
        AND pt.payment_type NOT LIKE '%Registration%'
        ORDER BY cp.due_date ASC, cp.course_payments_id ASC LIMIT 1
    ");
    if ($firstQ->num_rows > 0 && (int)$firstQ->fetch_assoc()['course_payments_id'] === $payment_id) {
        Database::iud("INSERT INTO students_logs (students_student_id, date_updated, users_user_id, log, lead_student_status_lead_student_status_id)
            VALUES ('" . $paymentData['student_id'] . "', '$now', '" . $sessionUserObject['user_id'] . "', 'First installment payment receipt uploaded', '8')");
    }
}



if ($paymentData['status_status_id'] == 2) {

    $query = "
        SELECT
            `cp`.`course_payments_id`,
            `cp`.`amount`,
            `cp`.`discount`,
            `cp`.`due_date`,
            `cp`.`discount_type_discount_type_id`,
            IFNULL(pp.panelty_amount, 0) AS panelty_amount,
            IFNULL(cpp.paid_amount, 0) AS paid_amount,
            IFNULL(cpp.total_discount, 0) AS total_discount,
            IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
        FROM `course_payments` AS `cp`
        INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi`
            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
        LEFT JOIN (
            SELECT
                course_payments_course_payments_id,
                SUM(amount) AS panelty_amount
            FROM panelty_payments
            GROUP BY course_payments_course_payments_id
        ) pp
            ON cp.course_payments_id = pp.course_payments_course_payments_id
        LEFT JOIN (
            SELECT
                course_payments_course_payments_id,
                SUM(paid_amount) AS paid_amount,
                SUM(discount_amount) AS total_discount,
                SUM(panelty_amount) AS total_sub_panelty_amount
            FROM course_payment_has_payments
            WHERE payment_status_payment_status_id IN (2,3)
            GROUP BY course_payments_course_payments_id
        ) cpp
            ON cp.course_payments_id = cpp.course_payments_course_payments_id
        WHERE `pphsi`.`students_has_intakes_students_has_intakes_id` = '" . $paymentData['students_has_intakes_id'] . "'
            AND `cp`.`payment_status_payment_status_id` = '1'
            AND `cp`.`due_date` < '$today'
        GROUP BY `cp`.`course_payments_id`
    ";

    $payments = Database::search($query);

    $isOverdueWithBalance = false;
    $overdueDetails = [];

    if ($payments->num_rows > 0) {
        while ($payment = $payments->fetch_assoc()) {

            $discountAmount = $payment["discount"];
            if ($payment["discount_type_discount_type_id"] == 3) {
                $discountAmount = $payment["amount"] / 100 * $payment["discount"];
            }

            $payableAmount = $payment["amount"]
                - $discountAmount
                - $payment["total_discount"]
                + $payment["panelty_amount"]
                + $payment["total_sub_panelty_amount"];

            $balance = $payableAmount - $payment["paid_amount"];

            if ($balance > 0) {
                $isOverdueWithBalance = true;
                $overdueDetails[] = "course_payments_id=" . $payment["course_payments_id"]
                    . " due_date=" . $payment["due_date"]
                    . " balance=" . number_format($balance, 2, '.', '');
            }
        }
    }
    if (!$isOverdueWithBalance) {
        Database::iud("UPDATE `students_has_intakes` SET `status_status_id`='1' WHERE `students_has_intakes_id`='" . $paymentData['students_has_intakes_id'] . "'");
    }
}



/* ════════════════════════════════════════════════════════════════════════════
   RESPONSE
════════════════════════════════════════════════════════════════════════════ */
http_response_code(200);
$status->done            = 'Payment receipt uploaded successfully';
$status->id              = $paymentData['students_has_intakes_id'];
$status->receiptNumber   = $receiptNumber;
$status->taxRate         = $latestTaxRate;
$status->paidAmount      = round($paid_amount_original, 2);
$status->paidBase        = round($paidNetBase,          2);
$status->paidTax         = round($paidTax_base,         2);
$status->paidTaxStored   = round($taxForCurrent_stored, 2);
$status->penaltyLKR      = round($penaltyEntered_LKR,   2); // this receipt's own penalty, LKR
$status->balance         = round($balanceAfter_base,    2);
$status->paymentCurrency = $currencyLabel;
$status->baseCurrency    = $currCode;

$status->breakdown = [
    'courseAmount'        => (float)$paymentData['amount'],
    'totalDiscount'       => round($totalDiscountBase,     2),
    'totalPenalty'        => round($totalPenaltyBase,      2), // base currency
    'mainPenaltyLKR'      => round($mainPenaltyAmount_LKR,  2), // CP-level, LKR
    'basePayable'         => round($basePayable,           2),
    'taxRate'             => $latestTaxRate,
    'totalPayable'        => round($totalPayable,          2),
    'existingPaid'        => round($existingPaid_base,     2),
    'thisPaid'             => round($paid_base,             2),
    'remainingBase'       => round($remainingBase,         2),
    'balanceNextInstall'  => round($balanceAfter_base,     2),
    'exchangeRate'        => $isLkrPaymentForForeignCourse ? $exchangeRate : null,
];

if ($hasOverpayment) { // ✅ v13: was ($overpayAmount_base > 0.001)
    $status->overpayment = [
        'detected'         => true,
        'overpayAmountBase' => round($overpayAmount_base, 2),
        'overpayAmountPaymentCurrency' => round($overpayAmount_paymentCcy, 2),
        'nextInstallments' => count($nextInstallmentPayments),
    ];
}

echo json_encode($status);