<?php

/**
 * api/getReceiptData.php - FIXED v4
 *
 * KEY FIX: Penalties added AFTER payment date are completely excluded
 * 
 * Business Logic:
 * - Payment on 2026-06-10: include only penalties added before/on 2026-06-10
 * - Penalties added 2026-06-12 (after payment): EXCLUDE from this receipt
 * - Receipt created 2026-06-14: still uses same logic
 *
 * Formula:
 * Balance Due = Course Fee - Discount + (Penalties added BEFORE payment) + Tax - Amount Paid
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

        b.branch        AS branch_name

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
   
   🔴 CRITICAL: Penalty filtering by payment date
   
   Rule: Only count penalties where date_created <= date_paid
   This ensures penalties added AFTER the payment are NOT included in this receipt.
   
   Example:
   - Payment made: 2026-06-10
   - Penalty added: 2026-06-12 (AFTER payment)
   - Receipt created: 2026-06-14
   → Penalty should NOT appear in receipt
   
   SQL filters using: pp.created_at <= cpp.date_paid (or equivalent date field)
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

        /*
         * 🔴 PENALTY FILTER FIX:
         * 
         * This subquery ONLY sums penalties where:
         *   created_at <= payment.date_paid
         *
         * If payment was made 2026-06-10, only penalties created 
         * on or before 2026-06-10 are counted.
         * 
         * Penalties added later are completely excluded.
         */
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
        /*
         * Correlated subquery: Count penalties ONLY if created BEFORE/ON payment date
         * 
         * Adjust the date field based on your schema:
         *   - panelty_payments.created_at  (recommended)
         *   - panelty_payments.date_added
         *   - panelty_payments.penalty_date
         */
        SELECT
            pp_inner.course_payments_course_payments_id,
            SUM(pp_inner.amount) AS total_penalty_lkr
        FROM panelty_payments pp_inner
        INNER JOIN course_payment_has_payments cpp_inner
               ON pp_inner.course_payments_course_payments_id
                    = cpp_inner.course_payments_course_payments_id
        WHERE  cpp_inner.course_payment_receipts_course_payment_receipt_id = '$receiptId'
          /* 🔴 KEY FIX: Only count penalties created ON OR BEFORE payment date */
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

/**
 * Resolve the exchange rate for a single payment row.
 * Returns (float) rate if paid in a DIFFERENT currency from course,
 * returns null if same currency (no conversion needed).
 */
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

/** Convert an amount from paid currency to course currency */
function toCourse(float $amount, ?float $rate): float
{
    if ($rate === null || $rate <= 0) return $amount;
    return $amount / $rate;
}

/* ════════════════════════════════════════════════════════════════════════════
   Aggregate ALL payments (with pre-payment-date penalty filtering)
════════════════════════════════════════════════════════════════════════════ */
$payments           = [];
$grandTotalPaid     = 0.0;
$grandTotalAmount   = 0.0;
$grandTotalDiscount = 0.0;
$grandTotalPenalty  = 0.0;  // Only pre-payment penalties!
$grandTotalTax      = 0.0;
$courseCurrencyCode = 'LKR';

while ($p = $paymentsRow->fetch_assoc()) {

    $courseCurrId      = (int)$p['course_currency_id'];
    $isCourseLKR       = ($courseCurrId === $lkrCurrencyId);
    $courseCurrencyCode = $p['course_currency_code'];

    $exRate = resolveRate($p, $courseCurrId);

    $paidInCourse = toCourse((float)$p['paid_amount'], $exRate);

    /*
     * 🔴 PENALTY FIX: This is ALREADY filtered by SQL
     * total_penalty_lkr is 0 if penalty was added after payment date
     * (no need for additional checks here)
     */
    $penaltyLKR      = (float)$p['total_penalty_lkr'];
    $penaltyInCourse = ($isCourseLKR || $exRate === null)
        ? $penaltyLKR
        : $penaltyLKR / ($exRate ?: 1);

    $taxInCourse      = toCourse((float)($p['tax_amount'] ?? 0), $exRate);
    $discountInCourse = (float)$p['discount_amount'];

    $payments[] = [
        'id'              => $p['course_payment_has_payment_id'],
        'reference'       => $p['refference'],
        'payment_type'    => $p['payment_type'],
        'date_paid'       => $p['date_paid'],
        'due_date'        => $p['due_date'],
        'payment_method'  => $p['payment_method'],
        'course_currency' => $courseCurrencyCode,
        'paid_currency'   => $p['paid_currency_code'],
        'cp_amount'       => (float)$p['cp_amount'],
        'paid_amount'     => (float)$p['paid_amount'],
        'paid_in_course'  => $paidInCourse,
        'discount_amount' => $discountInCourse,
        'penalty_lkr'     => $penaltyLKR,
        'penalty_course'  => $penaltyInCourse,
        'tax_rate'        => (float)$p['tax_rate'],
        'tax_amount'      => (float)$p['tax_amount'],
        'tax_course'      => $taxInCourse,
        'exchange_rate'   => $exRate,
        'is_foreign'      => ($exRate !== null),
        'note'            => $p['note'],
        'discount_type'   => $p['discount_type'],
        'cp_discount'     => (float)$p['cp_discount'],
        'discount_type_id' => (int)$p['discount_type_discount_type_id'],
    ];

    $grandTotalPaid     += $paidInCourse;
    $grandTotalAmount   += (float)$p['cp_amount'];
    $grandTotalDiscount += $discountInCourse;
    $grandTotalPenalty  += $penaltyInCourse;  // Only pre-payment penalties
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
$receipt['grand_total_penalty']  = $grandTotalPenalty;  // Pre-payment penalties only
$receipt['grand_total_tax']      = $grandTotalTax;
$receipt['branch_address']       = "No.154, Havelock Road, Thimbirigasyaya, Colombo 05";
$receipt['branch_phone']         = "+94 114 325 864";
$receipt['branch_email']         = "accounts@aicedu.lk";

echo json_encode($receipt);
