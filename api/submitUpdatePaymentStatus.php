<?php

/**
 * uploadStudentPaymentReceipt.php (approve/reject flow) - FIXED CURRENCY HANDLING
 * WITH CORRECTED TAX CALCULATION AND PENALTY-LKR NORMALIZATION
 *
 * KEY RULES:
 * - paid_amount stored in the currency the student ACTUALLY paid in (original, no conversion)
 * - tax_amount stored in the SAME currency as paid_amount (converted back from base if needed)
 * - panelty_amount is ALWAYS stored in LKR, regardless of what currency mode the
 *   modal was in when it was typed — this matches panelty_payments (the ledger
 *   table) and every read path (student-payment-details.php, add-receipt.php)
 * - exchange_rate stored for reverse conversion when needed
 * - All balance/overpayment calculations done in BASE currency (e.g. GBP)
 * - Before storing, amounts are converted BACK to payment currency (e.g. LKR)
 *
 * ✅ CRITICAL FIX: NEVER overwrite the original paid_amount
 *
 * TAX CALCULATION FIX:
 * - base = taxInclusive × (1 - taxRate/100); tax = taxInclusive × (taxRate/100)
 *
 * ✅ v9 FIX (overpayment double-rounding bug): base-currency internal math kept
 *    unrounded; overpayment computed in PAYMENT currency first, base-currency
 *    figure derived FROM it. Rounding happens exactly once, at storage.
 *
 * ✅ v10 FIX (multi-installment overpayment drift): the distribution loop tracks
 *    and distributes the overpayment directly in the PAYMENT currency throughout,
 *    rounding only once per installment at the point of storage.
 *
 * ✅ v11 FIX (missing penalty-ledger amount): basePayable now includes the
 *    `panelty_payments` ledger total (converted LKR → course currency), matching
 *    what payment-details.php already shows the admin. Previously this endpoint's
 *    own payable calculation silently ignored that ledger, so a discount entered
 *    to offset a penalty the admin could SEE on screen shrank basePayable with
 *    nothing on the other side to cancel it — misclassifying part of a correct
 *    payment as "overpayment" and leaving a phantom balance on the current
 *    installment.
 *
 * ✅ v12 FIX (penalty currency mismatch — the root cause of inconsistent
 *    overpayment detection):
 * - `panelty_amount` is treated EVERYWHERE ELSE in this system as ALWAYS LKR.
 *   This endpoint was still converting the newly-entered penalty amount
 *   ($penalty_original) using the PAYMENT-MODE direction (same treatment as
 *   paid_amount/discount_amount) instead of always normalizing it to LKR first.
 *   When the modal was in course-currency mode, a course-currency penalty value
 *   was silently stored as if it were LKR — understating/overstating the true
 *   penalty by roughly the exchange-rate factor, which corrupted basePayable,
 *   totalPayable, and therefore the overpayment decision.
 * - The existingQ SQL for prior receipts' penalty (`receipt_penalty_base`) had
 *   the same bug: it converted using the PAID_AMOUNT's currency
 *   (`currencies_currency_id`) rather than treating panelty_amount as always
 *   LKR. Fixed to check whether the COURSE itself is non-LKR instead.
 * - Exchange rate is now stored whenever the course itself is non-LKR — not
 *   only when the payment's own currency differs from the course currency —
 *   so a penalty on a payment made directly in the course's own currency can
 *   still be converted back to/from LKR later.
 *
 * ✅ v12 FIX (reject-path bug, unrelated to currency):
 * - The reject branch's UPDATE used `$payment_id` (which is course_payments_id)
 *   as the course_payment_has_payment_id — wrong row identifier. Fixed to use
 *   `$paid_payment_id`, matching the approve path below it.
 * - The reject branch's log insert referenced an undefined `$coursePaymentsId`
 *   variable. Fixed to `$payment_id` (the correct course_payments_id for the
 *   log's own column).
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

/**
 * Extract tax component from a tax-inclusive amount using CORRECT formula.
 * base = taxInclusiveAmount × (1 - taxRate/100)
 * tax  = taxInclusiveAmount × (taxRate/100)
 */
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

/**
 * Calculate tax-inclusive amount from base amount using CORRECT formula.
 * inclusive = base / (1 - taxRate/100)
 */
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

/**
 * Calculate tax amount from base using CORRECT formula.
 * tax = base × (taxRate / (100 - taxRate))
 */
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

/**
 * Convert an amount that is ALWAYS stored in LKR (panelty_payments.amount,
 * course_payment_has_payments.panelty_amount) into the course's own currency,
 * mirroring convertLKRToCourseCurrency() in payment-details.php exactly, so
 * every page that shows or calculates a penalty always agrees.
 *
 *   if course IS LKR  → use the LKR value directly
 *   if course is GBP  → divide by exchangeRateValue (LKR per 1 GBP)
 */
function convertLKRToCourseCurrency(float $lkrAmount, bool $isLKRCourse, float $exchangeRateValue): float
{
    if ($isLKRCourse || $exchangeRateValue <= 0) {
        return $lkrAmount;
    }
    return $lkrAmount / $exchangeRateValue;
}

/* ════════════════════════════════════════════════════════════════════════════
   READ INPUT — KEEP ORIGINAL AMOUNTS (NO CONVERSION HERE)
════════════════════════════════════════════════════════════════════════════ */
$payment_id           = (int)($_POST['payment_id']      ?? 0); // course_payments_id
$paid_payment_id      = (int)($_POST['paid_payment_id'] ?? 0); // course_payment_has_payment_id (the row being updated)
$payment_status       = (int)($_POST['payment_status']  ?? 0);
$method               = (int)($_POST['method']          ?? 0);
$paid_date            = trim($_POST['paid_date']        ?? '');
$note                 = trim($_POST['note']             ?? '');

// Original amounts as entered by the user (in whatever currency they paid)
$paid_amount_original = (float)($_POST['paid_amount']     ?? 0);
$discount_original    = (float)($_POST['discount_amount'] ?? 0);
$penalty_original     = (float)($_POST['penalty_amount']  ?? 0);

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

/* ════════════════════════════════════════════════════════════════════════════
   DETERMINE ACTUAL PAYMENT CURRENCY ID

   currencyMode = 'lkr'  → student paid in LKR  (currency_id = 1)
   currencyMode = 'base' → student paid in the course's base currency
════════════════════════════════════════════════════════════════════════════ */
if (!$selectedCurrencyId) {
    if ($currencyMode === 'lkr') {
        $selectedCurrencyId = 1; // LKR
    }
}

// ✅ v11/v12: LKR currency id and "is the course itself LKR" flag, used
// throughout for penalty normalization — independent of what currency THIS
// payment happens to be in.
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
        shi.status_status_id,
        dt.discount_type, dt.discount_type_id,
        c.code   AS currency_code,
        c.symbol AS currency_symbol,
        c.decimal_places,
        c.currency_id
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

// Default to base currency if not determined yet
if (!$selectedCurrencyId) {
    $selectedCurrencyId = $baseCurrencyId;
}

// Is student paying in a foreign currency (e.g. LKR) for a base-currency course (e.g. GBP)?
$isLkrPaymentForForeignCourse = ($selectedCurrencyId == 1 && $baseCurrencyId != 1);

// Is the COURSE ITSELF denominated in LKR? Independent of what currency THIS
// specific payment happens to be in — needed for every penalty conversion.
$isBaseCurrencyLKR = ($baseCurrencyId == $lkrCurrencyId);

/* ════════════════════════════════════════════════════════════════════════════
   VALIDATION
════════════════════════════════════════════════════════════════════════════ */
if ($method == 0)                   $status->method    = 'Please select payment method';
if (empty($paid_date))              $status->paid_date = 'Please select paid date';
elseif (CheckDateTime::isFutureDate($paid_date)) $status->paid_date = 'Paid date cannot be in the future';
if ($paid_amount_original <= 0)     $status->amount    = 'Please enter paid amount';

if (!empty($note) && strlen($note) > 150) $status->note = 'Note must be less than 150 characters';

foreach (['error', 'amount', 'slip', 'paid_date', 'method', 'discount', 'panelty', 'note'] as $p) {
    if (property_exists($status, $p)) {
        echo json_encode($status);
        exit;
    }
}

$timezone = new DateTimeZone("Asia/Colombo");
$now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');
$today    = date('Y-m-d', strtotime($now));

/* ════════════════════════════════════════════════════════════════════════════
   REJECT PATH

   ✅ v12 FIX: previously used the WRONG row identifier ($payment_id, which is
   course_payments_id, not course_payment_has_payment_id) and referenced an
   undefined $coursePaymentsId variable in the log insert. Both fixed below —
   $paid_payment_id identifies the row, $payment_id is the correct value for
   the log's own course_payments_course_payments_id column.
════════════════════════════════════════════════════════════════════════════ */
if ($payment_status == 4) {

    Database::iud("
        UPDATE `course_payment_has_payments`
        SET `payment_status_payment_status_id` = '4'
        WHERE `course_payment_has_payment_id` = '$paid_payment_id'
    ");

    $logNote = addslashes($note ?: 'Payment rejected');
    Database::iud("
        INSERT INTO `payment_logs`
            (`course_payments_course_payments_id`, `course_payment_has_payment_id`, `users_user_id`, `date_updated`, `note`, `log`)
        VALUES
            ('$payment_id', '$paid_payment_id', '" . $sessionUserObject['user_id'] . "', '$now', '$logNote', '$logNote')
    ");

    $status->done = 'Payment has been rejected.';
    echo json_encode($status);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   ✅ v12 FIX: NORMALIZE PENALTY TO LKR — ALWAYS, REGARDLESS OF PAYMENT MODE

   panelty_amount is stored EVERYWHERE in this system as ALWAYS LKR. The
   currency toggle on this form controls what currency PAID/DISCOUNT were
   entered in; it must NOT be applied to penalty.

   $penalty_original as posted is in:
     - LKR              → if the modal's currency toggle was set to 'lkr'
     - course currency  → if the modal's currency toggle was set to 'base'
                            (only meaningful when the course itself is not
                            already LKR)
════════════════════════════════════════════════════════════════════════════ */
if ($selectedCurrencyId == $lkrCurrencyId) {
    // Entered directly in LKR — use as-is.
    $penalty_LKR = $penalty_original;
} elseif ($baseCurrencyId != $lkrCurrencyId) {
    // Entered in the course's (foreign) currency — convert UP to LKR.
    $penalty_LKR = $penalty_original * $exchangeRate;
} else {
    // Course itself is LKR — course-currency amount already IS the LKR amount.
    $penalty_LKR = $penalty_original;
}

/* ════════════════════════════════════════════════════════════════════════════
   CONVERT ORIGINAL AMOUNTS TO BASE CURRENCY (for all calculations)

   All balance / overpayment logic runs in base currency (e.g. GBP).
   We convert back to payment currency only when storing to DB.

   ✅ FIX (v9): these are INTERMEDIATE values used only for internal math — do
   NOT round them to 2dp here.

   ✅ FIX (v12): penalty_base is now ALWAYS derived from $penalty_LKR (LKR →
   course currency), matching convertLKRToCourseCurrency() — never from
   $penalty_original directly, since that may be in either currency depending
   on payment mode.
════════════════════════════════════════════════════════════════════════════ */
if ($isLkrPaymentForForeignCourse) {
    $paid_base     = $paid_amount_original / $exchangeRate;
    $discount_base = $discount_original    / $exchangeRate;
} else {
    $paid_base     = $paid_amount_original;
    $discount_base = $discount_original;
}

// Penalty: LKR → course currency, independent of payment mode.
$penalty_base = convertLKRToCourseCurrency($penalty_LKR, $isBaseCurrencyLKR, $exchangeRate);

/* ════════════════════════════════════════════════════════════════════════════
   CALCULATE CURRENT INSTALLMENT PAYABLE (all in base currency)
════════════════════════════════════════════════════════════════════════════ */
$cpDiscountAmt = ($paymentData['discount_type_id'] == 3)
    ? $paymentData['amount'] / 100.0 * $paymentData['discount']
    : (float)$paymentData['discount'];

// Fetch existing paid amounts for this installment.
// IMPORTANT: paid_amount/discount_amount may be stored in different
// currencies per row — normalized via currency_exchange_rate.
//
// ✅ v12 FIX: panelty_amount is ALWAYS LKR regardless of the row's own
// currencies_currency_id (that column describes paid_amount's currency only).
// Convert it to base currency via whether the COURSE ITSELF is non-LKR, using
// that row's stored currency_exchange_rate — not via the paid-amount-currency
// check the old query used. Rows with no stored rate are excluded (0) rather
// than silently mis-summed.
$existingQ = Database::search("
    SELECT
        SUM(CASE
            WHEN payment_status_payment_status_id = 3 THEN
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
            WHEN payment_status_payment_status_id = 3 THEN
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
            WHEN payment_status_payment_status_id = 3 THEN
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
$existingPaid_base   = (float)($exRow['total_paid_base']       ?? 0); // in base currency
$receiptDisc_base    = (float)($exRow['receipt_discount_base'] ?? 0); // in base currency
$receiptPenalty_base = (float)($exRow['receipt_penalty_base']  ?? 0); // in base currency

/* ════════════════════════════════════════════════════════════════════════════
   ✅ v11: PENALTY LEDGER (panelty_payments table)

   panelty_payments.amount is ALWAYS stored in LKR, and represents penalties
   assessed through the dedicated "Penalty Payment Enabled" feature —
   separate from any ad-hoc penalty typed into this approval form.
   payment-details.php already folds this into the balance shown to the
   admin; this endpoint must too, or the two silently disagree.
════════════════════════════════════════════════════════════════════════════ */
$cpPenaltyLKRQ = Database::search("
    SELECT COALESCE(SUM(amount), 0) AS penalty_lkr
    FROM panelty_payments
    WHERE course_payments_course_payments_id = '$payment_id'
");
$cpPenaltyLKR = (float)(($cpPenaltyLKRQ && $cpPenaltyLKRQ->num_rows > 0)
    ? $cpPenaltyLKRQ->fetch_assoc()['penalty_lkr']
    : 0);

$cpPenaltyBase = convertLKRToCourseCurrency($cpPenaltyLKR, $isBaseCurrencyLKR, $exchangeRate);

$totalDiscountBase = $cpDiscountAmt + $receiptDisc_base + $discount_base;

// ✅ v11 + v12: ledger penalty + prior receipts' penalty (now correctly LKR-
// normalized) + this transaction's own newly-entered penalty (now correctly
// LKR-normalized).
$totalPenaltyBase  = $cpPenaltyBase + $receiptPenalty_base + $penalty_base;

$basePayable       = max(0.0, $paymentData['amount'] - $totalDiscountBase + $totalPenaltyBase);

// Calculate total payable INCLUDING TAX using correct formula
$totalPayable = calculateTaxInclusiveAmount($basePayable, $latestTaxRate);

/* ════════════════════════════════════════════════════════════════════════════
   EXTRACT TAX FROM THIS PAYMENT (in base currency)
════════════════════════════════════════════════════════════════════════════ */
$extracted    = extractTaxFromInclusive($paid_base, $latestTaxRate);
$paidNetBase  = $extracted['base']; // net amount (ex-tax) in base currency
$paidTax_base = $extracted['tax'];  // tax portion in base currency

/* ════════════════════════════════════════════════════════════════════════════
   BALANCE CALCULATION (base currency)
════════════════════════════════════════════════════════════════════════════ */
$totalPaidAfterThisReceipt = $existingPaid_base + $paid_base;
$paidNetSoFar = extractTaxFromInclusive($totalPaidAfterThisReceipt, $latestTaxRate)['base'];
$remainingBase = max(0.0, $basePayable - $paidNetSoFar);
$balanceAfter_base = calculateTaxInclusiveAmount($remainingBase, $latestTaxRate);

/* ════════════════════════════════════════════════════════════════════════════
   OVERPAYMENT DETECTION

   ✅ FIX (v9): Compute the overpayment amount directly in the PAYMENT currency
   first, THEN derive the base-currency equivalent FROM that figure.
════════════════════════════════════════════════════════════════════════════ */
$remainingBefore_base = max(0.0, $totalPayable - $existingPaid_base);

$remainingBeforeInPaymentCcy = $isLkrPaymentForForeignCourse
    ? $remainingBefore_base * $exchangeRate
    : $remainingBefore_base;

$overpayAmount_paymentCcy = max(0.0, round($paid_amount_original - $remainingBeforeInPaymentCcy, 2));

$overpayAmount_base = $isLkrPaymentForForeignCourse
    ? $overpayAmount_paymentCcy / $exchangeRate
    : $overpayAmount_paymentCcy;

$amountForCurrent_base = min($paid_base, $remainingBefore_base);

$currentExtracted   = extractTaxFromInclusive($amountForCurrent_base, $latestTaxRate);
$taxForCurrent_base = $currentExtracted['tax'];

$remaingBaseFor_Conversion = $remainingBefore_base;

/* ════════════════════════════════════════════════════════════════════════════
   ✅ CRITICAL FIX: ALWAYS store the ORIGINAL paid amount as entered

   paid_amount     = what student actually paid
   tax_amount      = tax extracted from that amount
   discount_amount = discount given (original currency)
   panelty_amount  = ✅ v12: ALWAYS LKR, never $penalty_original raw
════════════════════════════════════════════════════════════════════════════ */
$paidAmountForCurrent_stored = round($paid_amount_original, 2);  // ✅ ALWAYS original
$discountForStorage          = round($discount_original,    2);  // Original discount
$penaltyForStorage           = round($penalty_LKR,           2);  // ✅ v12: always LKR

// Tax is extracted from the paid amount (in base currency) then converted back to payment currency
if ($isLkrPaymentForForeignCourse) {
    $taxForCurrent_stored = round($taxForCurrent_base * $exchangeRate, 2); // LKR
} else {
    $taxForCurrent_stored = round($taxForCurrent_base, 2);  // base currency
}

/* ════════════════════════════════════════════════════════════════════════════
   OVERPAYMENT → distribute to next installments
════════════════════════════════════════════════════════════════════════════ */
$nextInstallmentPayments = [];

if ($overpayAmount_base > 0.001) {
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

    /**
     * ✅ FIX (v10, multi-installment overpayment drift): track and distribute
     * the overpayment directly in the PAYMENT currency throughout the loop.
     *
     * NOTE (v11): next installments' own penalty ledger amounts are NOT pulled
     * in here — an overpayment auto-applied to a future installment settles
     * principal/tax only; any penalty on that future installment still needs
     * its own explicit approval action.
     */
    $remainingOverpay_paymentCcy = $overpayAmount_paymentCcy;

    while ($remainingOverpay_paymentCcy > 0.01 && ($nextRow = $nextPayments->fetch_assoc())) {
        $nextId = (int)$nextRow['course_payments_id'];
        $nextDisc = ($nextRow['discount_type_discount_type_id'] == 3)
            ? $nextRow['amount'] / 100.0 * $nextRow['discount']
            : (float)$nextRow['discount'];

        $nextExistingQ = Database::search("
            SELECT COALESCE(SUM(
                CASE
                    WHEN currencies_currency_id != '{$baseCurrencyId}' AND COALESCE(currency_exchange_rate, 0) > 0
                    THEN paid_amount / currency_exchange_rate
                    ELSE paid_amount
                END
            ), 0) AS paid_base
            FROM course_payment_has_payments
            WHERE course_payments_course_payments_id = '$nextId'
              AND payment_status_payment_status_id = 3
        ");
        $nextExistingPaid_base = (float)($nextExistingQ->fetch_assoc()['paid_base'] ?? 0);

        $nextBasePayable = max(0.0, $nextRow['amount'] - $nextDisc);
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
   BUILD RECEIPT NUMBER (approve path only)
════════════════════════════════════════════════════════════════════════════ */
if ($payment_status == 3) {
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
}

/* ════════════════════════════════════════════════════════════════════════════
   UPDATE PAYMENT RECORD
   - paid_amount    → original amount in payment currency, MINUS any overpayment
                       carried forward to next installment(s)
   - discount_amount→ original discount in payment currency
   - panelty_amount → ✅ v12: ALWAYS LKR
   - tax_amount     → tax converted BACK to payment currency
   - currency_exchange_rate → ✅ v12: stored whenever the COURSE itself is
                       non-LKR (not only when the payment currency differs
                       from the course currency), so penalty can always be
                       converted on read
════════════════════════════════════════════════════════════════════════════ */
$qn = fn($v) => $v !== null ? "'" . addslashes((string)$v) . "'" : 'NULL';

if ($overpayAmount_paymentCcy > 0.001) {
    $paidAmountForCurrent_stored = round($paid_amount_original - $overpayAmount_paymentCcy, 2);
}

$updateVals = "`payment_status_payment_status_id` = '" . $payment_status . "',
`payment_method_payment_method_id` = '" . $method . "',
`date_paid` = '" . $paid_date . "',
`paid_amount` = '" . round($paidAmountForCurrent_stored, 2) . "',
`discount_amount` = '" . round($discountForStorage, 2) . "',
`panelty_amount` = '" . round($penaltyForStorage, 2) . "',
`note` = " . ($note !== '' ? "'" . addslashes($note) . "'" : 'NULL') . ",
`tax_rate` = '{$latestTaxRate}',
`tax_amount` = '{$taxForCurrent_stored}',
`currencies_currency_id` = '{$selectedCurrencyId}'
";

// ✅ v12 FIX: store the rate whenever the COURSE itself isn't LKR — replaces
// the fragile ternary and the old `$selectedCurrencyId != $baseCurrencyId`
// condition (which left non-LKR-course, non-LKR-payment rows with no rate,
// making their penalty unconvertible on read).
if ($baseCurrencyId != $lkrCurrencyId) {
    if ($exchangeRateId !== null && $exchangeRateId !== 0) {
        $updateVals .= ", `currency_exchange_rate_id` = '{$exchangeRateId}'";
    } else {
        $updateVals .= ", `currency_exchange_rate_id` = NULL";
    }
    $updateVals .= ", `currency_exchange_rate` = '{$exchangeRate}'";
}

if ($payment_status == 3) {
    $updateVals .= ", `course_payment_receipts_course_payment_receipt_id` = '{$receiptId}'";
}

Database::iud("UPDATE course_payment_has_payments SET {$updateVals} WHERE course_payment_has_payment_id = '{$paid_payment_id}'");
$paymentReceiptId = $paid_payment_id;

/* ── Mark current installment as settled if fully paid ── */
if ($amountForCurrent_base >= $remainingBefore_base - 0.001) {
    Database::iud("
        UPDATE course_payments SET payment_status_payment_status_id = '5'
        WHERE course_payments_id = '$payment_id'
    ");
}

/* ════════════════════════════════════════════════════════════════════════════
   INSERT OVERPAYMENT RECORDS FOR NEXT INSTALLMENTS
   panelty_amount hardcoded to '0' — overpayment never carries a penalty
   component — so the v12 penalty-currency fix does not apply to these rows.
════════════════════════════════════════════════════════════════════════════ */
foreach ($nextInstallmentPayments as $nxt) {
    $nxtId   = (int)$nxt['payment_id'];
    $nxtPaid = $nxt['paid_amount_stored'];
    $nxtTax  = $nxt['tax_amount_stored'];

    $nxtCols = "`course_payments_course_payments_id`, `payment_method_payment_method_id`,
                `date_paid`, `paid_amount`, `discount_amount`, `panelty_amount`,
                `payment_status_payment_status_id`, `currencies_currency_id`,
                `tax_rate`, `tax_amount`,
                `date_added`";

    $nxtVals = "'{$nxtId}', '{$method}',
                '{$paid_date}', '{$nxtPaid}', '0', '0',
                '" . $payment_status . "', '{$selectedCurrencyId}',
                '{$latestTaxRate}', '{$nxtTax}',
                '{$now}'";

    if ($payment_status == 3) {
        $nxtCols .= " ,`note`,`course_payment_receipts_course_payment_receipt_id`";
        $nxtVals .= ", 'Auto-applied overpayment from {$receiptNumber}', '{$receiptId}'";
    } else {
        $nxtCols .= " ,`note`";
        $nxtVals .= ", 'Auto-applied overpayment'";
    }

    // ✅ v12: same rate-storage condition as the main update above.
    if ($baseCurrencyId != $lkrCurrencyId) {
        $nxtCols .= ", `currency_exchange_rate_id`, `currency_exchange_rate`";
        $nxtVals .= ", " . $qn($exchangeRateId) . ", " . $qn($exchangeRate);
    }

    Database::iud("INSERT INTO course_payment_has_payments ({$nxtCols}) VALUES ({$nxtVals})");
    $nxtReceiptId = (int)Database::$connection->insert_id;

    if ($payment_status == 3) {
        if ($nxt['apply_base'] >= $nxt['next_total_payable_base'] - 0.001) {
            Database::iud("
                UPDATE course_payments SET payment_status_payment_status_id = '5'
                WHERE course_payments_id = '$nxtId'
            ");
        }
    }

    $overpayLog = "Auto-applied overpayment" . (isset($receiptNumber) ? " from {$receiptNumber}" : "") . ": "
        . round($nxtPaid, 2) . " (incl. tax " . round($nxtTax, 2) . ")";
    Database::iud("
        INSERT INTO payment_logs
            (course_payments_course_payments_id, course_payment_has_payment_id, users_user_id, date_updated, log)
        VALUES
            ('{$nxtId}', '{$nxtReceiptId}', '" . $sessionUserObject['user_id'] . "', '$now', '" . addslashes($overpayLog) . "')
    ");
}

/* ════════════════════════════════════════════════════════════════════════════
   PAYMENT LOG
════════════════════════════════════════════════════════════════════════════ */
$currencyLabel = ($selectedCurrencyId == 1) ? 'LKR' : $currCode;
$payment_update_status = ($payment_status == 3) ? 'Approved' : 'Rejected';
$log = "Payment receipt status updated {$payment_update_status}. {$currencyLabel} " . number_format($paid_amount_original, 2);
if ($latestTaxRate > 0) {
    $log .= " | base=" . number_format($paidNetBase, 2)
        . " + tax="  . number_format($paidTax_base, 2)
        . " ({$currCode}) | rate={$latestTaxRate}%";
    if ($isLkrPaymentForForeignCourse) {
        $log .= " | exRate={$exchangeRate}";
    }
}
if ($penalty_LKR > 0.001) {
    // ✅ v12: log the actual stored (LKR) figure, not the raw posted value.
    $log .= " | penalty=LKR " . number_format($penalty_LKR, 2);
}
if ($cpPenaltyLKR > 0) {
    $log .= " | ledgerPenalty=LKR" . number_format($cpPenaltyLKR, 2);
}
if ($overpayAmount_base > 0.001) {
    $log .= " | Overpayment → next installment(s)";
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
    Database::iud("UPDATE students_has_intakes SET lead_student_status_lead_student_status_id='5' WHERE students_has_intakes_id='" . $paymentData['students_has_intakes_id'] . "'");
    Database::iud("INSERT INTO students_logs (students_has_intakes_students_has_intakes_id, date_updated, users_user_id, log, lead_student_status_lead_student_status_id)
        VALUES ('" . $paymentData['students_has_intakes_id'] . "', '$now', '" . $sessionUserObject['user_id'] . "', 'Registration Payment marked as paid', '5')");
}

/* ── First installment log ── */
if (in_array($paymentData['lead_student_status_lead_student_status_id'], [5, 8, 9])) {
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
        Database::iud("UPDATE students_has_intakes SET lead_student_status_lead_student_status_id='10' WHERE students_has_intakes_id='" . $paymentData['students_has_intakes_id'] . "'");
        Database::iud("INSERT INTO students_logs (students_has_intakes_students_has_intakes_id, date_updated, users_user_id, log, lead_student_status_lead_student_status_id)
            VALUES ('" . $paymentData['students_has_intakes_id'] . "', '$now', '" . $sessionUserObject['user_id'] . "', 'First installment payment receipt uploaded', '10')");

        Database::iud("UPDATE students_has_intakes SET lead_student_status_lead_student_status_id='11' WHERE students_has_intakes_id='" . $paymentData['students_has_intakes_id'] . "'");
    }
}

/* ── Reassess overdue "hold" flag on the student's intake, if it was flagged ── */
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
$status->receiptNumber   = $receiptNumber ?? null;
$status->taxRate         = $latestTaxRate;
$status->paidAmount      = round($paid_amount_original, 2);
$status->paidBase        = round($paidNetBase,          2);
$status->paidTax         = round($paidTax_base,         2);
$status->paidTaxStored   = round($taxForCurrent_stored, 2);
$status->penaltyLKR      = round($penalty_LKR,          2); // ✅ v12: actual stored LKR penalty for THIS receipt
$status->balance         = round($balanceAfter_base,    2);
$status->paymentCurrency = $currencyLabel;
$status->baseCurrency    = $currCode;

$status->breakdown = [
    'courseAmount'        => (float)$paymentData['amount'],
    'totalDiscount'       => round($totalDiscountBase,     2),
    'totalPenalty'        => round($totalPenaltyBase,      2),
    'penaltyLedgerLKR'    => round($cpPenaltyLKR,          2),
    'basePayable'         => round($basePayable,           2),
    'taxRate'             => $latestTaxRate,
    'totalPayable'        => round($totalPayable,          2),
    'existingPaid'        => round($existingPaid_base,     2),
    'thisPaid'            => round($paid_base,             2),
    'remainingBase'       => round($remainingBase,         2),
    'balanceNextInstall'  => round($balanceAfter_base,     2),
    'exchangeRate'        => ($baseCurrencyId != $lkrCurrencyId) ? $exchangeRate : null,
];

if ($overpayAmount_base > 0.001) {
    $status->overpayment = [
        'detected'         => true,
        'overpayAmountBase' => round($overpayAmount_base, 2),
        'overpayAmountPaymentCurrency' => round($overpayAmount_paymentCcy, 2),
        'nextInstallments' => count($nextInstallmentPayments),
    ];
}

echo json_encode($status);
