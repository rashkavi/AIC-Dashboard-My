<?php

/**
 * uploadStudentPaymentReceipt.php - FIXED CURRENCY HANDLING WITH CORRECTED TAX CALCULATION
 *
 * KEY RULES:
 * - paid_amount stored in the currency the student ACTUALLY paid in (original, no conversion)
 * - tax_amount stored in the SAME currency as paid_amount (converted back from base if needed)
 * - exchange_rate stored for reverse conversion when needed
 * - All balance/overpayment calculations done in BASE currency (e.g. GBP)
 * - Before storing, amounts are converted BACK to payment currency (e.g. LKR)
 *
 * TAX CALCULATION FIX:
 * - OLD (WRONG): base = taxInclusive / (1 + taxRate/100)
 * - NEW (CORRECT): base = taxInclusive × (1 - taxRate/100)
 * - Tax is now calculated as a percentage of the FINAL (tax-inclusive) amount
 *
 * Example:
 *   Course in GBP, student pays LKR 5000, exchange rate = 400
 *   → paid_amount = 5000, currency_id = 1 (LKR), exchange_rate = 400
 *   → base (5000/400 = 12.5 GBP) × 0.975 = 12.1875 GBP
 *   → tax = 12.5 × 0.025 = 0.3125 GBP → stored as 0.3125 × 400 = 125 LKR
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
 * 
 * Tax is calculated as a percentage of the FINAL (tax-inclusive) amount.
 * 
 * Formula:
 *   base = taxInclusiveAmount × (1 - taxRate/100)
 *   tax = taxInclusiveAmount × (taxRate/100)
 * 
 * Example (200 GBP with 2.5% tax):
 *   base = 200 × 0.975 = 195 GBP
 *   tax = 200 × 0.025 = 5 GBP
 * 
 * Returns ['base' => float, 'tax' => float]
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
 * 
 * When you have a base amount and need to calculate the tax-inclusive amount:
 * inclusive = base / (1 - taxRate/100)
 * 
 * Example (base 195 GBP with 2.5% tax):
 *   inclusive = 195 / 0.975 = 200 GBP
 * 
 * Returns float
 */
function calculateTaxInclusiveAmount(float $base, float $taxRate): float
{
    if ($taxRate <= 0 || $base <= 0) {
        return $base;
    }

    $taxRateDecimal = $taxRate / 100.0;
    $basePortion = 1.0 - $taxRateDecimal;

    if ($basePortion <= 0) {
        return $base; // Prevent division by zero
    }

    return round($base / $basePortion, 2);
}

/**
 * Calculate tax amount from base using CORRECT formula.
 * 
 * tax = base × (taxRate / (100 - taxRate))
 * 
 * Example (base 200 GBP with 2.5% tax):
 *   tax = 200 × (2.5 / 97.5) = 5.128 GBP
 * 
 * Returns float
 */
function calculateTaxFromBase(float $base, float $taxRate): float
{
    if ($taxRate <= 0 || $base <= 0) {
        return 0.0;
    }

    $divisor = 100.0 - $taxRate;
    if ($divisor <= 0) {
        return 0.0; // Prevent division by zero
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
    // If still null, will be set from payment data below
}

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
        s.lead_student_status_lead_student_status_id,
        ld.email,
        pt.payment_type_id, pt.payment_type,
        shi.students_has_intakes_id,
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
            $dup  = Database::search("SELECT course_payment_slip_id FROM course_payment_slips WHERE slip_hash = '$hash'");
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
   CONVERT ORIGINAL AMOUNTS TO BASE CURRENCY (for all calculations)

   All balance / overpayment logic runs in base currency (e.g. GBP).
   We convert back to payment currency only when storing to DB.

   LKR → GBP : divide by exchange rate
   GBP → GBP : use as-is
════════════════════════════════════════════════════════════════════════════ */
if ($isLkrPaymentForForeignCourse) {
    $paid_base     = round($paid_amount_original / $exchangeRate, 2);
    $discount_base = round($discount_original    / $exchangeRate, 2);
    $penalty_base  = round($penalty_original     / $exchangeRate, 2);
} else {
    $paid_base     = round($paid_amount_original, 2);
    $discount_base = round($discount_original,    2);
    $penalty_base  = round($penalty_original,     2);
}

/* ════════════════════════════════════════════════════════════════════════════
   CALCULATE CURRENT INSTALLMENT PAYABLE (all in base currency)
════════════════════════════════════════════════════════════════════════════ */
$cpDiscountAmt = ($paymentData['discount_type_id'] == 3)
    ? $paymentData['amount'] / 100.0 * $paymentData['discount']
    : (float)$paymentData['discount'];

// Fetch existing paid amounts for this installment.
// IMPORTANT: existing records may be stored in different currencies.
// We use currency_exchange_rate to normalize them all to base currency.
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
                        WHEN currencies_currency_id != '{$baseCurrencyId}' AND COALESCE(currency_exchange_rate, 0) > 0
                        THEN panelty_amount / currency_exchange_rate
                        ELSE panelty_amount
                    END,
                0)
            ELSE 0
        END) AS receipt_penalty_base
    FROM course_payment_has_payments
    WHERE course_payments_course_payments_id = '$payment_id'
");

$exRow              = $existingQ->fetch_assoc();
$existingPaid_base  = (float)($exRow['total_paid_base']       ?? 0); // in base currency
$receiptDisc_base   = (float)($exRow['receipt_discount_base'] ?? 0); // in base currency
$receiptPenalty_base = (float)($exRow['receipt_penalty_base']  ?? 0); // in base currency

$totalDiscountBase = $cpDiscountAmt + $receiptDisc_base + $discount_base;
$totalPenaltyBase  = $receiptPenalty_base + $penalty_base;
$basePayable       = max(0.0, $paymentData['amount'] - $totalDiscountBase + $totalPenaltyBase);

// Calculate total payable INCLUDING TAX using correct formula
// inclusive = base / (1 - taxRate/100)
$totalPayable = calculateTaxInclusiveAmount($basePayable, $latestTaxRate);

/* ════════════════════════════════════════════════════════════════════════════
   EXTRACT TAX FROM THIS PAYMENT (in base currency)
════════════════════════════════════════════════════════════════════════════ */
$extracted   = extractTaxFromInclusive($paid_base, $latestTaxRate);
$paidNetBase = $extracted['base']; // net amount (ex-tax) in base currency
$paidTax_base = $extracted['tax'];  // tax portion in base currency

/* ════════════════════════════════════════════════════════════════════════════
   BALANCE CALCULATION (base currency)
════════════════════════════════════════════════════════════════════════════ */
$totalPaidAfterThisReceipt = $existingPaid_base + $paid_base;

// To get the remaining balance, we need to calculate how much net amount (ex-tax) has been paid
$paidNetSoFar = extractTaxFromInclusive($totalPaidAfterThisReceipt, $latestTaxRate)['base'];

$remainingBase = max(0.0, $basePayable - $paidNetSoFar);

// Remaining balance including tax
$balanceAfter_base = calculateTaxInclusiveAmount($remainingBase, $latestTaxRate);

/* ════════════════════════════════════════════════════════════════════════════
   OVERPAYMENT DETECTION (base currency)
════════════════════════════════════════════════════════════════════════════ */
$remainingBefore_base = max(0.0, $totalPayable - $existingPaid_base);
$overpayAmount_base   = max(0.0, round($paid_base - $remainingBefore_base, 2));
$amountForCurrent_base = min($paid_base, $remainingBefore_base); // portion for this installment

// Tax for just the current installment portion (base currency)
$currentExtracted      = extractTaxFromInclusive($amountForCurrent_base, $latestTaxRate);
$taxForCurrent_base    = $currentExtracted['tax']; // base currency

$remaingBaseFor_Conversion = $remainingBefore_base;

/* ════════════════════════════════════════════════════════════════════════════
   ✅ CONVERT TAX BACK TO PAYMENT CURRENCY FOR STORAGE

   The paid_amount will be stored in LKR (original).
   The tax_amount must also be stored in LKR to be consistent.
════════════════════════════════════════════════════════════════════════════ */
if ($isLkrPaymentForForeignCourse) {
    $taxForCurrent_stored = round($taxForCurrent_base * $exchangeRate, 2); // LKR
    $paidAmount_stored    = round($paid_amount_original, 2);               // LKR — what student actually handed over
} else {
    $taxForCurrent_stored = round($taxForCurrent_base, 2);                 // base currency
    $paidAmount_stored    = round($paid_amount_original, 2);               // base currency
}

/* ════════════════════════════════════════════════════════════════════════════
   ✅ CONVERT CURRENT INSTALLMENT AMOUNT TO PAYMENT CURRENCY FOR STORAGE
════════════════════════════════════════════════════════════════════════════ */
if ($isLkrPaymentForForeignCourse) {
    $paidAmountForCurrent_stored = round($amountForCurrent_base * $exchangeRate, 2); // LKR
} else {
    $paidAmountForCurrent_stored = round($amountForCurrent_base, 2); // base currency
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

    $remainingOverpay_base = $overpayAmount_base;

    while ($remainingOverpay_base > 0.001 && ($nextRow = $nextPayments->fetch_assoc())) {
        $nextId = (int)$nextRow['course_payments_id'];
        $nextDisc = ($nextRow['discount_type_discount_type_id'] == 3)
            ? $nextRow['amount'] / 100.0 * $nextRow['discount']
            : (float)$nextRow['discount'];

        // Fetch existing paid for next installment, normalized to base currency
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
              AND payment_status_payment_status_id IN (2, 3)
        ");
        $nextExistingPaid_base = (float)($nextExistingQ->fetch_assoc()['paid_base'] ?? 0);

        $nextBasePayable = max(0.0, $nextRow['amount'] - $nextDisc);

        // Calculate total payable including tax using CORRECT formula
        $nextTotalPayable_base = calculateTaxInclusiveAmount($nextBasePayable, $latestTaxRate);

        $nextRemaining_base  = max(0.0, $nextTotalPayable_base - $nextExistingPaid_base);
        $applyToNext_base    = min($remainingOverpay_base, $nextRemaining_base);
        $remainingOverpay_base = round($remainingOverpay_base - $applyToNext_base, 2);

        // Tax in base currency
        $nextExtracted    = extractTaxFromInclusive($applyToNext_base, $latestTaxRate);
        $nextTax_base     = $nextExtracted['tax'];

        // ✅ Convert to payment currency for storage
        if ($isLkrPaymentForForeignCourse) {
            $applyToNext_stored = round($applyToNext_base * $exchangeRate, 2); // LKR
            $nextTax_stored     = round($nextTax_base     * $exchangeRate, 2); // LKR
        } else {
            $applyToNext_stored = round($applyToNext_base, 2);
            $nextTax_stored     = round($nextTax_base,     2);
        }

        $nextInstallmentPayments[] = [
            'payment_id'            => $nextId,
            'paid_amount_stored'    => $applyToNext_stored,  // in payment currency
            'tax_amount_stored'     => $nextTax_stored,       // in payment currency
            'next_total_payable_base' => $nextTotalPayable_base,
            'apply_base'            => $applyToNext_base,    // for status-update comparison
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
$receiptNumber = $branchPrefix . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);

Database::iud("
    INSERT INTO course_payment_receipts
        (receipt_number, users_user_id, date_added, students_has_intakes_students_has_intakes_id)
    VALUES
        ('" . addslashes($receiptNumber) . "', '" . $sessionUserObject['user_id'] . "', '$now', '" . $paymentData['students_has_intakes_id'] . "')
");
$receiptId = (int)Database::$connection->insert_id;

/* ════════════════════════════════════════════════════════════════════════════
   INSERT PAYMENT RECORD
   - paid_amount    → original amount in payment currency (e.g. LKR 5000)
   - discount_amount→ original discount in payment currency
   - panelty_amount → original penalty in payment currency
   - tax_amount     → tax converted BACK to payment currency (e.g. LKR 436)
   - currencies_currency_id → payment currency (e.g. 1 for LKR)
   - currency_exchange_rate → rate used (only when currencies differ)
════════════════════════════════════════════════════════════════════════════ */
$qn = fn($v) => $v !== null ? "'" . addslashes((string)$v) . "'" : 'NULL';

$cols = "`course_payments_course_payments_id`, `payment_method_payment_method_id`,
         `date_paid`, `paid_amount`, `discount_amount`, `panelty_amount`,
         `payment_status_payment_status_id`, `currencies_currency_id`,
         `note`, `tax_rate`, `tax_amount`,
         `course_payment_receipts_course_payment_receipt_id`";

$vals = "'{$payment_id}', '{$method}',
         '{$paid_date}',
         '" . round($paidAmountForCurrent_stored, 2) . "',  
         '" . round($discount_original,    2) . "',
         '" . round($penalty_original,     2) . "',
         '3', '{$selectedCurrencyId}',
         " . ($note !== '' ? "'" . addslashes($note) . "'" : 'NULL') . ",
         '{$latestTaxRate}', '{$taxForCurrent_stored}',
         '{$receiptId}'";
// Only store exchange rate when currencies differ
if ($selectedCurrencyId != $baseCurrencyId) {
    $cols .= ", `currency_exchange_rate_id`, `currency_exchange_rate`";
    $vals .= ", " . $qn($exchangeRateId) . ", " . $qn($exchangeRate);
}

Database::iud("INSERT INTO course_payment_has_payments ({$cols}) VALUES ({$vals})");
$paymentReceiptId = (int)Database::$connection->insert_id;

/* ── Mark current installment as settled if fully paid ── */
if ($amountForCurrent_base >= $remainingBefore_base - 0.001) {
    Database::iud("
        UPDATE course_payments SET payment_status_payment_status_id = '5'
        WHERE course_payments_id = '$payment_id'
    ");
}

/* ════════════════════════════════════════════════════════════════════════════
   INSERT OVERPAYMENT RECORDS FOR NEXT INSTALLMENTS
   Same rule: store amounts in payment currency, include exchange rate
════════════════════════════════════════════════════════════════════════════ */
foreach ($nextInstallmentPayments as $nxt) {
    $nxtId   = (int)$nxt['payment_id'];
    $nxtPaid = $nxt['paid_amount_stored']; // already in payment currency
    $nxtTax  = $nxt['tax_amount_stored'];  // already in payment currency

    $nxtCols = "`course_payments_course_payments_id`, `payment_method_payment_method_id`,
                `date_paid`, `paid_amount`, `discount_amount`, `panelty_amount`,
                `payment_status_payment_status_id`, `currencies_currency_id`,
                `note`, `tax_rate`, `tax_amount`,
                `course_payment_receipts_course_payment_receipt_id`";

    $nxtVals = "'{$nxtId}', '{$method}',
                '{$paid_date}', '{$nxtPaid}', '0', '0',
                '3', '{$selectedCurrencyId}',
                'Auto-applied overpayment from {$receiptNumber}',
                '{$latestTaxRate}', '{$nxtTax}',
                '{$receiptId}'";

    if ($selectedCurrencyId != $baseCurrencyId) {
        $nxtCols .= ", `currency_exchange_rate_id`, `currency_exchange_rate`";
        $nxtVals .= ", " . $qn($exchangeRateId) . ", " . $qn($exchangeRate);
    }

    Database::iud("INSERT INTO course_payment_has_payments ({$nxtCols}) VALUES ({$nxtVals})");
    $nxtReceiptId = (int)Database::$connection->insert_id;

    // Mark next installment as settled if fully covered (compare in base currency)
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
    // Log values in base currency for clarity
    $log .= " | base=" . number_format($paidNetBase, 2)
        . " + tax="  . number_format($paidTax_base, 2)
        . " ({$currCode}) | rate={$latestTaxRate}%";
    if ($isLkrPaymentForForeignCourse) {
        $log .= " | exRate={$exchangeRate}";
    }
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
    Database::iud("UPDATE students SET lead_student_status_lead_student_status_id='5' WHERE student_id='" . $paymentData['student_id'] . "'");
    Database::iud("INSERT INTO students_logs (students_student_id, date_updated, users_user_id, log, lead_student_status_lead_student_status_id)
        VALUES ('" . $paymentData['student_id'] . "', '$now', '" . $sessionUserObject['user_id'] . "', 'Registration Payment marked as paid', '5')");
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

/* ════════════════════════════════════════════════════════════════════════════
   RESPONSE
════════════════════════════════════════════════════════════════════════════ */
http_response_code(200);
$status->done            = 'Payment receipt uploaded successfully';
$status->id              = $paymentData['students_has_intakes_id'];
$status->receiptNumber   = $receiptNumber;
$status->taxRate         = $latestTaxRate;
$status->paidAmount      = round($paid_amount_original, 2);  // original payment currency
$status->paidBase        = round($paidNetBase,          2); // base currency (ex-tax)
$status->paidTax         = round($paidTax_base,         2); // base currency tax
$status->paidTaxStored   = round($taxForCurrent_stored, 2); // tax in payment currency (what was saved)
$status->balance         = round($balanceAfter_base,    2); // balance in base currency
$status->paymentCurrency = $currencyLabel;
$status->baseCurrency    = $currCode;

$status->breakdown = [
    'courseAmount'        => (float)$paymentData['amount'],
    'totalDiscount'       => round($totalDiscountBase,     2),
    'totalPenalty'        => round($totalPenaltyBase,      2),
    'basePayable'         => round($basePayable,           2),
    'taxRate'             => $latestTaxRate,
    'totalPayable'        => round($totalPayable,          2),
    'existingPaid'        => round($existingPaid_base,     2),
    'thisPaid'            => round($paid_base,             2),
    'remainingBase'       => round($remainingBase,         2),
    'balanceNextInstall'  => round($balanceAfter_base,     2),
    'exchangeRate'        => $isLkrPaymentForForeignCourse ? $exchangeRate : null,
];

if ($overpayAmount_base > 0.001) {
    $status->overpayment = [
        'detected'         => true,
        'overpayAmountBase' => round($overpayAmount_base, 2),
        'nextInstallments' => count($nextInstallmentPayments),
    ];
}

echo json_encode($status);
