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

/* ── Input ── */
$paymentId      = (int)($input['paymentId']      ?? 0);
$newStatus      = (int)($input['status']         ?? 0);
$paidAmount     = (float)($input['paidAmount']   ?? 0);
$discountAmount = (float)($input['discountAmount'] ?? 0);
$penaltyAmount  = (float)($input['penaltyAmount'] ?? 0);
$taxRate        = (float)($input['taxRate']       ?? 0);
$paymentMethod  = (int)($input['paymentMethod']  ?? 0);
$paidDate       = trim($input['paidDate']        ?? '');
$exchangeRate   = isset($input['exchangeRate'])  && $input['exchangeRate'] > 0
    ? (float)$input['exchangeRate'] : null;
$exchangeRateId = isset($input['exchangeRateId']) && $input['exchangeRateId']
    ? (int)$input['exchangeRateId'] : null;
$currencyId     = (int)($input['currencyId']     ?? 0);
$note           = trim($input['note']            ?? '');
$userId         = (int)$sessionUserObject['user_id'];

/* ── Basic validation ── */
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
if (!$paymentMethod) {
    echo json_encode(['paymentMethod' => 'Payment method is required.']);
    exit;
}
if (empty($paidDate)) {
    echo json_encode(['paidDate' => 'Paid date is required.']);
    exit;
}

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0))) {
    echo json_encode(['error' => 'You do not have permission to update payment status.']);
    exit;
}

/* ══════════════════════════════════════════════════════════
   FETCH PAYMENT RECORD
══════════════════════════════════════════════════════════ */
$paymentRow = Database::search("
    SELECT
        cpp.*,
        cp.course_payments_id,
        cp.amount            AS cp_amount,
        cp.discount          AS cp_discount,
        cp.discount_type_discount_type_id,
        cp.payment_plan_has_students_has_intakes_id,

        pc.currencies_currency_id AS course_currency_id,
        c.code                    AS course_currency_code,
        c.decimal_places          AS course_decimal_places,

        shi.students_has_intakes_id,
        shi.students_student_id,

        b.branch AS branch_name,

        s.student_id AS student_reg_id,
        ld.first_name,
        ld.last_name

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
    WHERE cpp.course_payment_has_payment_id = '$paymentId'
    LIMIT 1
");

if (!$paymentRow || $paymentRow->num_rows == 0) {
    echo json_encode(['error' => 'Payment record not found.']);
    exit;
}

$pay = $paymentRow->fetch_assoc();

/* Already processed */
if ($pay['payment_status_payment_status_id'] != 2) {
    echo json_encode(['error' => 'This payment has already been processed.']);
    exit;
}

$courseCurrencyId   = (int)$pay['course_currency_id'];
$shiId              = (int)$pay['students_has_intakes_id'];
$coursePaymentsId   = (int)$pay['course_payments_id'];
$lkrCurrencyId      = 1;
$isCourseLKR        = ($courseCurrencyId === $lkrCurrencyId);

/* ══════════════════════════════════════════════════════════
   CURRENCY HELPERS
══════════════════════════════════════════════════════════ */

/**
 * Convert an amount from course currency → LKR using given rate.
 * If course IS LKR, rate is null and we return as-is.
 */
function toCourseCurrency($amount, $exchangeRate)
{
    // $amount is in the currency the user entered (base or LKR)
    // $exchangeRate = how many LKR per 1 course-currency unit
    // If user entered in LKR mode: divide by rate to get course currency
    // amounts sent from JS are ALREADY in base/course currency (JS does toBase() before sending)
    return (float)$amount;
}

/**
 * Convert course-currency amount → LKR for penalty storage.
 */
function toLKR($amount, $exchangeRate)
{
    if ($exchangeRate === null || $exchangeRate <= 0) return (float)$amount;
    return (float)$amount * $exchangeRate;
}

/**
 * Calculate tax amount from balance.
 */
function calcTax($balance, $taxRate)
{
    return $balance * ($taxRate / 100);
}

/* ── Resolve exchange rate ── */
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
        /* Fallback: latest rate */
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

/* ── Amounts (JS already sends in course-currency base) ── */
$paidAmountBase     = (float)$paidAmount;     // in course currency
$discountAmountBase = (float)$discountAmount; // in course currency
$penaltyAmountBase  = (float)$penaltyAmount;  // in course currency

/* Tax on (balance - discount + penalty) */
$cpDiscount = ($pay['discount_type_discount_type_id'] == 3)
    ? ($pay['cp_amount'] / 100 * $pay['cp_discount'])
    : (float)$pay['cp_discount'];

$balanceBase = $pay['cp_amount'] - $cpDiscount - $discountAmountBase + $penaltyAmountBase;
if ($balanceBase < 0) $balanceBase = 0;

$taxAmountBase = calcTax($balanceBase, $taxRate);

/* Convert to LKR for panelty_payments table (always stored in LKR) */
$paidAmountLKR     = $isCourseLKR ? $paidAmountBase     : toLKR($paidAmountBase,     $resolvedRate);
$discountAmountLKR = $isCourseLKR ? $discountAmountBase : toLKR($discountAmountBase, $resolvedRate);
$penaltyAmountLKR  = $isCourseLKR ? $penaltyAmountBase  : toLKR($penaltyAmountBase,  $resolvedRate);
$taxAmountLKR      = $isCourseLKR ? $taxAmountBase      : toLKR($taxAmountBase,      $resolvedRate);
$balanceLKR        = $isCourseLKR ? $balanceBase        : toLKR($balanceBase,        $resolvedRate);

/* ══════════════════════════════════════════════════════════
   TIMEZONE
══════════════════════════════════════════════════════════ */
$timezone = new DateTimeZone("Asia/Colombo");
$dateTime = new DateTime("now", $timezone);
$now      = $dateTime->format('Y-m-d H:i:s');

/* ══════════════════════════════════════════════════════════
   RECEIPT NUMBER GENERATION (only when approving → status 3)
   Format: [BRANCH_2_LETTERS][STUDENT_REG_ID]-[YEAR][SEQ_5DIGITS]
   e.g. CO2024-00042  (branch = "Colombo", reg = "2024")
══════════════════════════════════════════════════════════ */
$receiptId = NULL;

if ($newStatus == 3) {

    /* Branch prefix — first 2 letters uppercase */
    $branchName   = $pay['branch_name'] ?? 'XX';
    $branchPrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $branchName), 0, 2));
    if (strlen($branchPrefix) < 2) $branchPrefix = str_pad($branchPrefix, 2, 'X');

    /* Year */
    $year = date('Y');

    /* Sequential number: count existing receipts for this branch+year */
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
    /* e.g.  CO2024-00001 */

    /* Insert receipt */
    Database::iud("
        INSERT INTO `course_payment_receipts`
            (`receipt_number`, `users_user_id`, `date_added`, `students_has_intakes_students_has_intakes_id`)
        VALUES
            ('" . addslashes($receiptNumber) . "', '$userId', '$now', '$shiId')
    ");

    $receiptId = Database::$connection->insert_id;
}

/* ══════════════════════════════════════════════════════════
   UPDATE course_payment_has_payments
══════════════════════════════════════════════════════════ */
$exchangeRateSql       = $resolvedRate        ? "'$resolvedRate'"  : 'NULL';
$exchangeRateIdSql     = $exchangeRateId      ? "'$exchangeRateId'" : 'NULL';
$currencyIdSql         = $currencyId          ? "'$currencyId'"    : 'NULL';
$receiptIdSql          = $receiptId           ? "'$receiptId'"     : 'NULL';
$taxRateSql            = $taxRate > 0         ? "'$taxRate'"       : '0';
$taxAmountSql          = $taxAmountBase > 0   ? "'" . number_format($taxAmountBase, 10, '.', '') . "'" : '0';
$discountAmountSql     = "'" . number_format($discountAmountBase, 10, '.', '') . "'";
$penaltyAmountSql      = "'" . number_format($penaltyAmountBase,  10, '.', '') . "'";
$paidAmountSql         = "'" . number_format($paidAmountBase,     10, '.', '') . "'";
$noteSql               = "'" . addslashes($note) . "'";
$paidDateSql           = "'" . addslashes($paidDate) . "'";

Database::iud("
    UPDATE `course_payment_has_payments`
    SET
        `payment_status_payment_status_id`                    = '$newStatus',
        `paid_amount`                                         = $paidAmountSql,
        `discount_amount`                                     = $discountAmountSql,
        `panelty_amount`                                      = $penaltyAmountSql,
        `tax_rate`                                            = $taxRateSql,
        `tax_amount`                                          = $taxAmountSql,
        `payment_method_payment_method_id`                    = '$paymentMethod',
        `date_paid`                                           = $paidDateSql,
        `currencies_currency_id`                              = $currencyIdSql,
        `currency_exchange_rate`                              = $exchangeRateSql,
        `currency_exchange_rate_id`                           = $exchangeRateIdSql,
        `note`                                                = $noteSql,
        `course_payment_receipts_course_payment_receipt_id`   = $receiptIdSql
    WHERE `course_payment_has_payment_id` = '$paymentId'
");

/* ══════════════════════════════════════════════════════════
   UPDATE course_payments status (only if approving)
══════════════════════════════════════════════════════════ */
if ($newStatus == 3 && $balanceBase == 0) {
    Database::iud("
        UPDATE `course_payments`
        SET `payment_status_payment_status_id` = '3'
        WHERE `course_payments_id` = '$coursePaymentsId'
    ");
}


/* ══════════════════════════════════════════════════════════
   INSERT PAYMENT LOG
══════════════════════════════════════════════════════════ */
$logNote = $note ?: ($newStatus == 3 ? 'Payment approved' : 'Payment rejected');
$note = addslashes($logNote);

Database::iud("
    INSERT INTO `payment_logs`
        (`course_payments_course_payments_id`, `users_user_id`, `date_updated`, `note`,`log`)
    VALUES
        ('$coursePaymentsId', '$userId', '$now', '$note','$logNote')
");

/* ══════════════════════════════════════════════════════════
   RESPONSE
══════════════════════════════════════════════════════════ */
if ($newStatus == 3) {
    $status->done = "Payment approved successfully. Receipt No: " . ($receiptNumber ?? 'N/A');
} else {
    $status->done = "Payment has been rejected.";
}

echo json_encode($status);
