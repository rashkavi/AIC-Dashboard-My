<?php

/**
 * api/getReceiptData.php - v8
 *
 * CHANGES IN v8 (on top of v7):
 *   - Added `discount_currency` to each payment row: the currency code that
 *     `discount_amount` is expressed in (the PAID currency, e.g. LKR).
 *     The receipt view uses it as the prefix for the Discount column.
 *
 * CHANGES IN v7:
 *   1. discount_amount is returned in the PAID currency (LKR stays LKR).
 *      A converted copy is returned as `discount_course` and used for totals.
 *   2. Penalty is always LKR for display: use `penalty_lkr` and
 *      `grand_total_penalty_lkr`. `penalty_course` is kept for compatibility.
 *   3. Penalty double-count fix: the penalty subquery no longer joins every
 *      payment row of the receipt, and the penalty of an installment is only
 *      attributed to the FIRST payment row of that installment in this receipt.
 *
 * (v6) Per-installment balance uses the tax-inclusive model of
 *      intake-student-payment.php.
 * (v5) Intake counsellor + per-installment running balance.
 * (v4) Pre-payment-date penalty filtering.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$receiptId = (int)($_GET['receipt_id'] ?? 0);
if (!$receiptId) {
    echo json_encode(['error' => 'Invalid receipt ID']);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   Receipt master row
════════════════════════════════════════════════════════════════════════════ */
$receiptRow = Database::search("
    SELECT
        cpr.receipt_number,
        cpr.date_added,
        cpr.course_payment_receipt_id,

        u.user_fname,
        u.user_lname,
        t_u.title   AS user_title,

        ld.first_name,
        ld.last_name,
        ld.email,
        t_s.title   AS student_title,
        s.mobile,
        s.student_id AS student_reg_id,

        i.intake,
        p.programme,
        f.faculty,

        b.branch        AS branch_name,

        /* ── COUNSELLOR JOIN ── */
        u_c.user_fname   AS counsellor_fname,
        u_c.user_lname   AS counsellor_lname,
        t_c.title        AS counsellor_title

    FROM course_payment_receipts cpr
    INNER JOIN users             u   ON cpr.users_user_id
                                         = u.user_id
    INNER JOIN title             t_u ON u.title_title_id
                                         = t_u.title_id
    INNER JOIN students_has_intakes shi
        ON cpr.students_has_intakes_students_has_intakes_id
             = shi.students_has_intakes_id
    INNER JOIN students          s   ON shi.students_student_id
                                         = s.student_id
    INNER JOIN login_details     ld  ON s.login_details_login_details_id
                                         = ld.login_details_id
    INNER JOIN title             t_s ON s.title_title_id
                                         = t_s.title_id
    INNER JOIN intakes           i   ON shi.intakes_intake_id
                                         = i.intake_id
    INNER JOIN programmes        p   ON i.programmes_programme_id
                                         = p.programme_id
    INNER JOIN faculties         f   ON p.faculties_faculty_id
                                         = f.faculty_id
    INNER JOIN branches          b   ON f.branches_branch_id
                                         = b.branch_id

    LEFT JOIN users               u_c ON shi.counselor_user_id
                                          = u_c.user_id
    LEFT JOIN title                t_c ON u_c.title_title_id
                                          = t_c.title_id

    WHERE cpr.course_payment_receipt_id = '$receiptId'
    LIMIT 1
");

if (!$receiptRow || $receiptRow->num_rows == 0) {
    echo json_encode(['error' => 'Receipt not found']);
    exit;
}
$receipt = $receiptRow->fetch_assoc();

/* ════════════════════════════════════════════════════════════════════════════
   Payments linked to this receipt

   The penalty subquery joins a per-installment "last payment date in this
   receipt" derived table (one row per installment) instead of joining every
   payment row, so the SUM can't be multiplied by the number of payments of
   the same installment.
════════════════════════════════════════════════════════════════════════════ */
$paymentsRow = Database::search("
    SELECT
        cpp.course_payment_has_payment_id,
        cpp.paid_amount,
        cpp.discount_amount,
        cpp.panelty_amount,
        cpp.tax_rate,
        cpp.tax_amount,
        cpp.date_paid,
        cpp.note,
        cpp.currency_exchange_rate,
        cpp.currencies_currency_id   AS paid_currency_db_id,
        cpp.currency_exchange_rate_id,

        pm.payment_method,

        COALESCE(cur.code, c.code)   AS paid_currency_code,

        cp.course_payments_id        AS installment_id,
        cp.refference,
        cp.amount                    AS cp_amount,
        cp.discount                  AS cp_discount,
        cp.due_date,
        cp.discount_type_discount_type_id,
        dt.discount_type,
        pt.payment_type,

        pc.currencies_currency_id    AS course_currency_id,
        c.code                       AS course_currency_code,
        c.decimal_places             AS course_decimal_places,

        COALESCE(pp.total_penalty_lkr, 0) AS total_penalty_lkr

    FROM course_payment_has_payments cpp
    INNER JOIN payment_method   pm   ON cpp.payment_method_payment_method_id
                                          = pm.payment_method_id
    LEFT  JOIN currencies       cur  ON cpp.currencies_currency_id
                                          = cur.currency_id
    INNER JOIN course_payments  cp   ON cpp.course_payments_course_payments_id
                                          = cp.course_payments_id
    INNER JOIN discount_type    dt   ON cp.discount_type_discount_type_id
                                          = dt.discount_type_id
    INNER JOIN payment_type     pt   ON cp.payment_type_payment_type_id
                                          = pt.payment_type_id
    INNER JOIN payment_plan_has_students_has_intakes ppsi
        ON cp.payment_plan_has_students_has_intakes_id
             = ppsi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category pc   ON ppsi.payment_category_payment_category_id
                                          = pc.payment_category_id
    INNER JOIN currencies       c    ON pc.currencies_currency_id
                                          = c.currency_id
    LEFT  JOIN (
        SELECT
            pp_inner.course_payments_course_payments_id,
            SUM(pp_inner.amount) AS total_penalty_lkr
        FROM panelty_payments pp_inner
        INNER JOIN (
            SELECT
                course_payments_course_payments_id,
                MAX(date_paid) AS last_paid
            FROM course_payment_has_payments
            WHERE course_payment_receipts_course_payment_receipt_id = '$receiptId'
              AND payment_status_payment_status_id = 3
            GROUP BY course_payments_course_payments_id
        ) lp ON pp_inner.course_payments_course_payments_id
                  = lp.course_payments_course_payments_id
        WHERE DATE(pp_inner.date_added) <= DATE(lp.last_paid)
        GROUP BY pp_inner.course_payments_course_payments_id
    ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id

    WHERE  cpp.course_payment_receipts_course_payment_receipt_id = '$receiptId'
      AND  cpp.payment_status_payment_status_id = 3
    ORDER  BY cpp.date_paid ASC, cpp.course_payment_has_payment_id ASC
");

/* ════════════════════════════════════════════════════════════════════════════
   Helpers
════════════════════════════════════════════════════════════════════════════ */
$lkrCurrencyId = 1;
$__rateCache   = [];

function resolveRate(array $row, int $courseCurrId): ?float
{
    $paidId = (int)($row['paid_currency_db_id'] ?? 0);

    if (!$paidId || $paidId === $courseCurrId) {
        return null;
    }

    $rate = (float)($row['currency_exchange_rate'] ?? 0);
    if ($rate > 0) return $rate;

    $rateId = (int)($row['currency_exchange_rate_id'] ?? 0);
    if ($rateId) {
        global $__rateCache;
        if (!isset($__rateCache[$rateId])) {
            $r = Database::search(
                "SELECT rate FROM currency_exchange_rates
                 WHERE  currency_exchange_rate_id = '$rateId'
                 LIMIT  1"
            );
            $__rateCache[$rateId] = ($r && $r->num_rows > 0)
                ? (float)$r->fetch_assoc()['rate']
                : 0;
        }
        if ($__rateCache[$rateId] > 0) return $__rateCache[$rateId];
    }

    return null;
}

function toCourse(float $amount, ?float $rate): float
{
    if ($rate === null || $rate <= 0) return $amount;
    return $amount / $rate;
}

/**
 * Latest global tax rate (always the CURRENT latest tax_rates row).
 */
function getLatestTaxRate(): float
{
    static $rate = null;
    if ($rate !== null) return $rate;

    $r = Database::search("
        SELECT `tax_rate` AS `rate` FROM `tax_rates`
        ORDER BY `date_added` DESC LIMIT 1
    ");
    $rate = ($r && $r->num_rows > 0) ? (float)$r->fetch_assoc()['rate'] : 0.0;
    return $rate;
}

/**
 * course currency -> LKR "page" exchange rate (for panelty_payments, stored in LKR).
 */
function getPageExchangeRate(int $courseCurrId, int $lkrCurrencyId): float
{
    static $cache = [];
    if ($courseCurrId === $lkrCurrencyId) return 1.0;
    if (isset($cache[$courseCurrId])) return $cache[$courseCurrId];

    $r = Database::search("
        SELECT rate FROM currency_exchange_rates
        WHERE  base_currency_id   = '$courseCurrId'
          AND  target_currency_id = '$lkrCurrencyId'
        ORDER  BY effective_date DESC, effective_time DESC
        LIMIT  1
    ");
    $rate = ($r && $r->num_rows > 0) ? (float)$r->fetch_assoc()['rate'] : 0.0;
    $cache[$courseCurrId] = $rate;
    return $rate;
}

/**
 * Running balance for a single installment as of a cutoff date_paid (inclusive),
 * using the tax-inclusive model of intake-student-payment.php:
 *
 *     basePayable   = installmentAmount - totalDiscount + totalPenalty
 *     basePortion   = 1 - (latestTaxRate / 100)
 *     paidBaseTotal = cumulativePaidAsOfCutoff * basePortion
 *     remainingBase = max(0, basePayable - paidBaseTotal)
 *     remainingTax  = remainingBase * (latestTaxRate / (100 - latestTaxRate))
 *     balance       = remainingBase + remainingTax
 *
 * NOTE: balance always uses TODAY's tax rate (same as the live page).
 */
function getInstallmentBalance(
    int $installmentId,
    string $cutoffDatePaid,
    int $courseCurrId,
    int $lkrCurrencyId,
    float $installmentAmount,
    float $installmentDiscount
): float {
    $paidRows = Database::search("
        SELECT
            cpp.paid_amount,
            cpp.discount_amount,
            cpp.panelty_amount,
            cpp.currencies_currency_id AS paid_currency_db_id,
            cpp.currency_exchange_rate,
            cpp.currency_exchange_rate_id
        FROM course_payment_has_payments cpp
        WHERE cpp.course_payments_course_payments_id = '$installmentId'
          AND cpp.payment_status_payment_status_id = 3
          AND cpp.date_paid <= '$cutoffDatePaid'
    ");

    $cumulativePaidCourse     = 0.0;
    $cumulativeDiscountCourse = 0.0;
    $cumulativePenaltyCourse  = 0.0;

    if ($paidRows) {
        while ($row = $paidRows->fetch_assoc()) {
            $rate = resolveRate($row, $courseCurrId);
            $cumulativePaidCourse     += toCourse((float)$row['paid_amount'], $rate);
            $cumulativeDiscountCourse += toCourse((float)($row['discount_amount'] ?? 0), $rate);
            $cumulativePenaltyCourse  += toCourse((float)($row['panelty_amount']  ?? 0), $rate);
        }
    }

    $cutoffDateOnly = date('Y-m-d', strtotime($cutoffDatePaid));
    $penRows = Database::search("
        SELECT COALESCE(SUM(amount), 0) AS total_penalty
        FROM panelty_payments
        WHERE course_payments_course_payments_id = '$installmentId'
          AND DATE(date_added) <= '$cutoffDateOnly'
    ");
    $penaltyLKR = ($penRows && $penRows->num_rows > 0)
        ? (float)$penRows->fetch_assoc()['total_penalty']
        : 0.0;

    $pageRate      = getPageExchangeRate($courseCurrId, $lkrCurrencyId);
    $penaltyCourse = ($courseCurrId === $lkrCurrencyId || $pageRate <= 0)
        ? $penaltyLKR
        : ($penaltyLKR / $pageRate);

    $totalDiscount = $installmentDiscount + $cumulativeDiscountCourse;
    $totalPenalty  = $penaltyCourse + $cumulativePenaltyCourse;

    $basePayable = $installmentAmount - $totalDiscount + $totalPenalty;

    $latestTaxRate  = getLatestTaxRate();
    $taxRateDecimal = $latestTaxRate / 100;
    $basePortion    = 1 - $taxRateDecimal;

    $paidBaseTotal = ($basePortion > 0) ? ($cumulativePaidCourse * $basePortion) : 0.0;
    $remainingBase = max(0, $basePayable - $paidBaseTotal);

    $remainingTax = ($basePortion > 0 && $latestTaxRate < 100)
        ? ($remainingBase * ($latestTaxRate / (100 - $latestTaxRate)))
        : 0.0;

    $balance = $remainingBase + $remainingTax;
    return round($balance, 2);
}

/* ════════════════════════════════════════════════════════════════════════════
   Aggregate ALL payments
════════════════════════════════════════════════════════════════════════════ */
$payments              = [];
$grandTotalPaid        = 0.0;
$grandTotalAmount      = 0.0;
$grandTotalDiscount    = 0.0;   // course currency (converted)
$grandTotalPenalty     = 0.0;   // course currency (converted) - legacy
$grandTotalPenaltyLKR  = 0.0;   // always LKR
$grandTotalTax         = 0.0;
$courseCurrencyCode    = 'LKR';

/* installments whose ledger penalty has already been attributed to a row */
$penaltyCounted = [];

while ($p = $paymentsRow->fetch_assoc()) {

    $courseCurrId       = (int)$p['course_currency_id'];
    $isCourseLKR        = ($courseCurrId === $lkrCurrencyId);
    $courseCurrencyCode = $p['course_currency_code'];

    $exRate = resolveRate($p, $courseCurrId);

    $paidInCourse = toCourse((float)$p['paid_amount'], $exRate);

    $installmentId = (int)$p['installment_id'];

    /* ── Penalty (ledger, always LKR) — count once per installment ── */
    if (isset($penaltyCounted[$installmentId])) {
        $penaltyLKR = 0.0;
    } else {
        $penaltyLKR = (float)$p['total_penalty_lkr'];
        $penaltyCounted[$installmentId] = true;
    }

    $penaltyInCourse = ($isCourseLKR || $exRate === null)
        ? $penaltyLKR
        : $penaltyLKR / ($exRate ?: 1);

    $taxInCourse = toCourse((float)($p['tax_amount'] ?? 0), $exRate);

    /* ── Discount: stored in PAID currency; keep for display, convert for totals ── */
    $discountPaidCurrency = (float)$p['discount_amount'];
    $discountInCourse     = toCourse($discountPaidCurrency, $exRate);

    /* ── Per-installment running balance ── */
    $installmentAmount = (float)$p['cp_amount'];

    $installmentDiscountTypeId = (int)$p['discount_type_discount_type_id'];
    $installmentDiscount = ($installmentDiscountTypeId == 3)
        ? ($installmentAmount / 100 * (float)$p['cp_discount'])
        : (float)$p['cp_discount'];

    $installmentBalance = getInstallmentBalance(
        $installmentId,
        $p['date_paid'],
        $courseCurrId,
        $lkrCurrencyId,
        $installmentAmount,
        $installmentDiscount
    );

    $payments[] = [
        'id'                   => $p['course_payment_has_payment_id'],
        'reference'            => $p['refference'],
        'payment_type'         => $p['payment_type'],
        'date_paid'            => $p['date_paid'],
        'due_date'             => $p['due_date'],
        'payment_method'       => $p['payment_method'],
        'course_currency'      => $courseCurrencyCode,
        'paid_currency'        => $p['paid_currency_code'],
        'cp_amount'            => (float)$p['cp_amount'],
        'paid_amount'          => (float)$p['paid_amount'],
        'paid_in_course'       => $paidInCourse,

        /* discount: PAID currency (display) + its currency code + converted copy (totals) */
        'discount_amount'      => $discountPaidCurrency,
        'discount_currency'    => $p['paid_currency_code'],   // NEW v8
        'discount_course'      => $discountInCourse,

        /* penalty is always LKR for display */
        'penalty_lkr'          => $penaltyLKR,
        'penalty_course'       => $penaltyInCourse,

        'tax_rate'             => (float)$p['tax_rate'],
        'tax_amount'           => (float)$p['tax_amount'],
        'tax_course'           => $taxInCourse,
        'exchange_rate'        => $exRate,
        'is_foreign'           => ($exRate !== null),
        'note'                 => $p['note'],
        'discount_type'        => $p['discount_type'],
        'cp_discount'          => (float)$p['cp_discount'],
        'discount_type_id'     => (int)$p['discount_type_discount_type_id'],

        'installment_id'       => $installmentId,
        'installment_balance'  => $installmentBalance,
        'balance_as_of'        => $p['date_paid'],
    ];

    $grandTotalPaid       += $paidInCourse;
    $grandTotalAmount     += (float)$p['cp_amount'];
    $grandTotalDiscount   += $discountInCourse;
    $grandTotalPenalty    += $penaltyInCourse;
    $grandTotalPenaltyLKR += $penaltyLKR;
    $grandTotalTax        += $taxInCourse;
}

/* ════════════════════════════════════════════════════════════════════════════
   Response
════════════════════════════════════════════════════════════════════════════ */
$receipt['payments']                = $payments;
$receipt['course_currency']         = $courseCurrencyCode;
$receipt['grand_total_paid']        = $grandTotalPaid;
$receipt['grand_total_amount']      = $grandTotalAmount;
$receipt['grand_total_discount']    = $grandTotalDiscount;      // course currency
$receipt['grand_total_penalty']     = $grandTotalPenalty;       // course currency (legacy)
$receipt['grand_total_penalty_lkr'] = $grandTotalPenaltyLKR;    // LKR
$receipt['grand_total_tax']         = $grandTotalTax;
$receipt['branch_address']          = "No.154, Havelock Road, Thimbirigasyaya, Colombo 05";
$receipt['branch_phone']            = "+94 114 325 864";
$receipt['branch_email']            = "accounts@aicedu.lk";

echo json_encode($receipt);
