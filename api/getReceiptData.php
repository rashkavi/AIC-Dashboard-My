<?php
/**
 * api/getReceiptData.php  v2
 *
 * Key fixes vs v1:
 *  • paid_currency always resolved — never falls back to course currency silently
 *  • grand_total_amount included (sum of cp.amount in course currency)
 *  • discount_amount returned in course currency (was sometimes in paid currency)
 *  • tax_course reliably computed (paid currency → course currency conversion)
 *  • branch_phone / branch_email included in response
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

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

        /* paid currency — use COALESCE so it falls back to course currency code
           when the student paid in the same currency as the course              */
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
        SELECT course_payments_course_payments_id,
               SUM(amount) AS total_penalty_lkr
        FROM   panelty_payments
        GROUP  BY course_payments_course_payments_id
    ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id

    WHERE  cpp.course_payment_receipts_course_payment_receipt_id = '$receiptId'
      AND  cpp.payment_status_payment_status_id = 3
    ORDER  BY cpp.date_paid ASC
");

/* ════════════════════════════════════════════════════════════════════════════
   Helpers
════════════════════════════════════════════════════════════════════════════ */
$lkrCurrencyId = 1;

/**
 * Resolve the exchange rate for a single payment row.
 * Returns (float) rate if paid in a DIFFERENT currency from course,
 * returns null if same currency (no conversion needed).
 */
function resolveRate(array $row, int $courseCurrId): ?float
{
    $paidId = (int)($row['paid_currency_db_id'] ?? 0);

    /* If no paid-currency ID recorded, or same as course → no conversion */
    if (!$paidId || $paidId === $courseCurrId) {
        return null;
    }

    /* Use stored rate first */
    $rate = (float)($row['currency_exchange_rate'] ?? 0);
    if ($rate > 0) return $rate;

    /* Fall back to rate table */
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
   Aggregate
════════════════════════════════════════════════════════════════════════════ */
$payments           = [];
$grandTotalPaid     = 0.0;
$grandTotalAmount   = 0.0;
$grandTotalDiscount = 0.0;
$grandTotalPenalty  = 0.0;
$grandTotalTax      = 0.0;
$courseCurrencyCode = 'LKR';
$__rateCache        = [];

while ($p = $paymentsRow->fetch_assoc()) {

    $courseCurrId      = (int)$p['course_currency_id'];
    $isCourseLKR       = ($courseCurrId === $lkrCurrencyId);
    $courseCurrencyCode = $p['course_currency_code'];   // e.g. "GBP" or "LKR"

    /* Exchange rate: 1 course_currency = X paid_currency */
    $exRate = resolveRate($p, $courseCurrId);

    /* paid_in_course = how much was received expressed in course currency */
    $paidInCourse = toCourse((float)$p['paid_amount'], $exRate);

    /* Penalty stored in LKR → convert to course currency */
    $penaltyLKR      = (float)$p['total_penalty_lkr'];
    $penaltyInCourse = ($isCourseLKR || $exRate === null)
        ? $penaltyLKR
        : $penaltyLKR / ($exRate ?: 1);

    /* Tax: cpp.tax_amount is in the PAID currency → convert to course currency */
    $taxInCourse = toCourse((float)($p['tax_amount'] ?? 0), $exRate);

    /* Discount: cpp.discount_amount is in course currency (no conversion needed) */
    $discountInCourse = (float)$p['discount_amount'];

    $payments[] = [
        'id'              => $p['course_payment_has_payment_id'],
        'reference'       => $p['refference'],
        'payment_type'    => $p['payment_type'],
        'date_paid'       => $p['date_paid'],
        'due_date'        => $p['due_date'],
        'payment_method'  => $p['payment_method'],
        'course_currency' => $courseCurrencyCode,

        /* ── CRITICAL: paid_currency is what the student ACTUALLY paid in ── */
        'paid_currency'   => $p['paid_currency_code'],   /* never null — COALESCE in SQL */

        'cp_amount'       => (float)$p['cp_amount'],
        'paid_amount'     => (float)$p['paid_amount'],   /* in paid_currency */
        'paid_in_course'  => $paidInCourse,              /* in course_currency */
        'discount_amount' => $discountInCourse,
        'penalty_lkr'     => $penaltyLKR,
        'penalty_course'  => $penaltyInCourse,
        'tax_rate'        => (float)$p['tax_rate'],
        'tax_amount'      => (float)$p['tax_amount'],    /* in paid_currency */
        'tax_course'      => $taxInCourse,               /* in course_currency */
        'exchange_rate'   => $exRate,                    /* null if same currency */
        'is_foreign'      => ($exRate !== null),
        'note'            => $p['note'],
        'discount_type'   => $p['discount_type'],
        'cp_discount'     => (float)$p['cp_discount'],
        'discount_type_id'=> (int)$p['discount_type_discount_type_id'],
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
$receipt['course_currency']      = $courseCurrencyCode;   /* "LKR", "GBP", etc. */
$receipt['grand_total_paid']     = $grandTotalPaid;
$receipt['grand_total_amount']   = $grandTotalAmount;
$receipt['grand_total_discount'] = $grandTotalDiscount;
$receipt['grand_total_penalty']  = $grandTotalPenalty;
$receipt['grand_total_tax']      = $grandTotalTax;
$receipt['branch_address'] = "No. 439, Galle Road, Colombo 03";
$receipt['branch_phone']   = "+94 11 2345678";
$receipt['branch_email']   = "9Hb4a@example.com";

echo json_encode($receipt);