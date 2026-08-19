<?php

/**
 * api/getReceiptData.php - v6
 *
 * CHANGES IN v6 (on top of v5):
 *   Per-installment balance now uses the SAME tax-inclusive model as
 *   intake-student-payment.php, instead of a plain subtraction:
 *
 *       basePayable   = installmentAmount - totalDiscount + totalPenalty
 *       basePortion   = 1 - (latestTaxRate / 100)
 *       paidBaseTotal = cumulativePaidAsOfCutoff * basePortion
 *       remainingBase = max(0, basePayable - paidBaseTotal)
 *       remainingTax  = remainingBase * (latestTaxRate / (100 - latestTaxRate))
 *       balance       = remainingBase + remainingTax
 *
 *   Also fixed: installment-plan discount is now resolved as a PERCENTAGE
 *   when discount_type_id == 3 (installmentAmount/100 * discount), matching
 *   intake-student-payment.php's $cpDiscount — the previous version used
 *   the raw cp.discount value directly, which was wrong for percentage
 *   discounts.
 *
 *   See getInstallmentBalance(), getLatestTaxRate(), getPageExchangeRate()
 *   below for full detail and one flagged assumption (balance always uses
 *   TODAY's tax rate, not a historical one — inherited from the source page).
 *
 * ══════════════════════════════════════════════════════════════════════
 * CHANGES IN v5 (on top of v4):
 * 1. Added Intake Counsellor to the receipt header data.
 *    ASSUMPTION (please confirm/fix): students_has_intakes.counsellor_user_id -> users.user_id
 *    If your actual column/table is different, only the JOIN block marked
 *    "COUNSELLOR JOIN" below needs to change — nothing else depends on it.
 *
 * 2. Added per-installment running balance to each payment row (v5 used a
 *    simple subtraction; see v6 notes above for the current tax-aware model).
 * (Existing pre-payment-date penalty filtering logic from v4 is untouched.)
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

        /* ── COUNSELLOR JOIN ──────────────────────────────────────────────
           ASSUMPTION: students_has_intakes.counsellor_user_id -> users.user_id
           Update this block if your schema differs. */
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

    /* ── COUNSELLOR JOIN (LEFT — receipt should still render if no counsellor set) ── */
    LEFT JOIN users               u_c ON s.users_user_id
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
   (v4 pre-payment-date penalty filtering logic — unchanged)
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
        INNER JOIN course_payment_has_payments cpp_inner
               ON pp_inner.course_payments_course_payments_id
                    = cpp_inner.course_payments_course_payments_id
        WHERE  cpp_inner.course_payment_receipts_course_payment_receipt_id = '$receiptId'
          AND  DATE(pp_inner.date_added) <= DATE(cpp_inner.date_paid)
        GROUP  BY pp_inner.course_payments_course_payments_id
    ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id

    WHERE  cpp.course_payment_receipts_course_payment_receipt_id = '$receiptId'
      AND  cpp.payment_status_payment_status_id = 3
    ORDER  BY cpp.date_paid ASC
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
 * NEW v6 — latest global tax rate (mirrors intake-student-payment.php exactly:
 * always the CURRENT latest tax_rates row, not a historical/per-payment rate).
 * Cached for the life of the request.
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
 * NEW v6 — course currency -> LKR "page" exchange rate, used only for
 * converting panelty_payments (always stored in LKR) into course currency.
 * Mirrors intake-student-payment.php's $pageExchangeRateValue. Cached per
 * course currency for the life of the request.
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
 * NEW v6 — computes the running balance for a single installment as of a
 * given cutoff date_paid (inclusive), across ALL receipts, using the SAME
 * tax-inclusive model as intake-student-payment.php:
 *
 *     basePayable   = installmentAmount - totalDiscount + totalPenalty
 *     basePortion   = 1 - (latestTaxRate / 100)
 *     paidBaseTotal = cumulativePaidAsOfCutoff * basePortion
 *     remainingBase = max(0, basePayable - paidBaseTotal)
 *     remainingTax  = remainingBase * (latestTaxRate / (100 - latestTaxRate))
 *     balance       = remainingBase + remainingTax
 *
 * ⚠ FLAGGED ASSUMPTION (inherited from intake-student-payment.php): the
 * balance projection always uses TODAY's latest tax rate, not the tax rate
 * that was actually in effect on the cutoff date. This matches the live
 * dashboard page exactly, so a historical receipt's balance figure will
 * reflect the current tax rate, not a rate frozen at the time of payment.
 * Flagging this in case you want receipt balances to be a true historical
 * snapshot instead — that would need each payment's own tax_rate.
 *
 * totalDiscount / totalPenalty here include BOTH:
 *   - the installment-plan-level discount (cpDiscount, percentage-resolved)
 *   - any receipt-level discount_amount / panelty_amount recorded on
 *     individual payments up to the cutoff date (converted to course currency
 *     using each payment's own exchange rate)
 * plus penalties from the panelty_payments ledger (LKR, date-filtered,
 * converted via the page-level course→LKR rate).
 */
function getInstallmentBalance(
    int $installmentId,
    string $cutoffDatePaid,
    int $courseCurrId,
    int $lkrCurrencyId,
    float $installmentAmount,
    float $installmentDiscount   // cp-level discount, already percentage-resolved by caller
): float {
    /* 1) Cumulative confirmed payments toward this installment, any receipt,
          up to and including this row's own date_paid — plus any
          receipt-level discount/penalty recorded on those same rows. */
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

    $cumulativePaidCourse    = 0.0;
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

    /* 2) Confirmed penalties (panelty_payments ledger, always LKR) added
          on/before the cutoff DATE for this installment. */
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

    /* 3) Roll up totals exactly like intake-student-payment.php */
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
   Aggregate ALL payments (with pre-payment-date penalty filtering)
════════════════════════════════════════════════════════════════════════════ */
$payments           = [];
$grandTotalPaid     = 0.0;
$grandTotalAmount   = 0.0;
$grandTotalDiscount = 0.0;
$grandTotalPenalty  = 0.0;
$grandTotalTax      = 0.0;
$courseCurrencyCode = 'LKR';

while ($p = $paymentsRow->fetch_assoc()) {

    $courseCurrId      = (int)$p['course_currency_id'];
    $isCourseLKR       = ($courseCurrId === $lkrCurrencyId);
    $courseCurrencyCode = $p['course_currency_code'];

    $exRate = resolveRate($p, $courseCurrId);

    $paidInCourse = toCourse((float)$p['paid_amount'], $exRate);

    $penaltyLKR      = (float)$p['total_penalty_lkr'];
    $penaltyInCourse = ($isCourseLKR || $exRate === null)
        ? $penaltyLKR
        : $penaltyLKR / ($exRate ?: 1);

    $taxInCourse      = toCourse((float)($p['tax_amount'] ?? 0), $exRate);
    $discountInCourse = (float)$p['discount_amount'];

    /* ── NEW: per-installment running balance ── */
    $installmentId       = (int)$p['installment_id'];
    $installmentAmount   = (float)$p['cp_amount'];

    /* Resolve the installment-plan-level discount exactly like
       intake-student-payment.php's $cpDiscount: discount_type_id == 3
       means the stored value is a PERCENTAGE of the installment amount,
       otherwise it's already a flat course-currency amount. */
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
        'discount_amount'      => $discountInCourse,
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

        /* NEW fields */
        'installment_id'       => $installmentId,
        'installment_balance'  => $installmentBalance,   // > 0 = still owing, <= 0 = settled/overpaid
        'balance_as_of'        => $p['date_paid'],        // full timestamp for display
    ];

    $grandTotalPaid     += $paidInCourse;
    $grandTotalAmount   += (float)$p['cp_amount'];
    $grandTotalDiscount += $discountInCourse;
    $grandTotalPenalty  += $penaltyInCourse;
    $grandTotalTax      += $taxInCourse;
}

/* ════════════════════════════════════════════════════════════════════════════
   Response
════════════════════════════════════════════════════════════════════════════ */
$receipt['payments']             = $payments;
$receipt['course_currency']      = $courseCurrencyCode;
$receipt['grand_total_paid']     = $grandTotalPaid;
$receipt['grand_total_amount']   = $grandTotalAmount;
$receipt['grand_total_discount'] = $grandTotalDiscount;
$receipt['grand_total_penalty']  = $grandTotalPenalty;
$receipt['grand_total_tax']      = $grandTotalTax;
$receipt['branch_address']       = "No.154, Havelock Road, Thimbirigasyaya, Colombo 05";
$receipt['branch_phone']         = "+94 114 325 864";
$receipt['branch_email']         = "accounts@aicedu.lk";

echo json_encode($receipt);