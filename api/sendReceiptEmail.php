<?php

/**
 * api/sendReceiptEmail.php - v10
 *
 * CHANGES IN v10 (on top of v9):
 *   1. Payments query now filters payment_status_payment_status_id = 3
 *      (confirmed only) — v9 had no status filter and could pull
 *      pending/void rows into the totals.
 *   2. Added cp.discount / cp.discount_type_discount_type_id to the
 *      query so the installment-plan-level discount is resolved the
 *      SAME way as intake-student-payment.php / getReceiptData.php v6:
 *      discount_type_id == 3 -> percentage of installment amount,
 *      otherwise a flat course-currency amount.
 *   3. Balance/paid-status logic replaced with the SAME tax-inclusive
 *      model as getReceiptData.php v6 (getLatestTaxRate(),
 *      getPageExchangeRate(), getInstallmentBalance() ported directly):
 *          basePayable   = installmentAmount - totalDiscount + totalPenalty
 *          basePortion   = 1 - (latestTaxRate / 100)
 *          paidBaseTotal = cumulativePaidAsOfCutoff * basePortion
 *          remainingBase = max(0, basePayable - paidBaseTotal)
 *          remainingTax  = remainingBase * (latestTaxRate / (100 - latestTaxRate))
 *          balance       = remainingBase + remainingTax
 *      Penalty now comes from the panelty_payments ledger (date-filtered,
 *      LKR -> course currency), not just the payment row's panelty_amount.
 *      Same flagged assumption as v6: balance uses TODAY's tax rate, not
 *      a historical one.
 *   4. Each payment row now carries its own running installment_balance,
 *      and the email's "Balance Due" figure is the sum of the latest
 *      per-installment balance for every installment touched by this
 *      receipt (mirrors the receipt-data endpoint's semantics).
 *   5. PDF layout restyled to match openInvoicePrintWindow.js: Arial
 *      font stack, info-label/info-value pattern, signature block, and
 *      the enviiz-logo footer — replacing the old text-only ENLIXO footer.
 */

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');
require_once('../includes/phpmailer/EmailLayoutRenderer.php');
require_once('../includes/phpmailer/EmailNotificationService.php');
require_once('../includes/phpmailer/EmailTemplateContent.php');
require_once('../includes/phpmailer/mail-process.php');

header('Content-Type: application/json');
$status = new stdClass();

if (!SessionUser::hasValidSession()) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();

if ($sessionUserRole == null) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(
    SessionUser::ifUserIsPro($sessionUserRole) ||
    UserPermissions::validPermisisonType('payments')
)) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// ── Parse JSON body ────────────────────────────────────────────────────────
$body       = json_decode(file_get_contents('php://input'), true);
$receipt_id = isset($body['receipt_id']) ? (int)$body['receipt_id'] : 0;

if ($receipt_id <= 0) {
    echo json_encode(['error' => 'Invalid receipt ID']);
    exit;
}

// ── Fetch receipt data ─────────────────────────────────────────────────────
$receiptRow = Database::search("
    SELECT
        cpr.course_payment_receipt_id,
        cpr.receipt_number,
        cpr.date_added,
        cpr.students_has_intakes_students_has_intakes_id,
        u.user_fname, u.user_lname,
        t2.title AS user_title
    FROM course_payment_receipts cpr
    LEFT JOIN users u  ON cpr.users_user_id  = u.user_id
    LEFT JOIN title t2 ON u.title_title_id   = t2.title_id
    WHERE cpr.course_payment_receipt_id = '" . $receipt_id . "'
    LIMIT 1
");

if (!$receiptRow || $receiptRow->num_rows == 0) {
    echo json_encode(['error' => 'Receipt not found']);
    exit;
}
$receipt = $receiptRow->fetch_assoc();
$shi_id  = (int)$receipt['students_has_intakes_students_has_intakes_id'];

// ── Fetch student + programme + intake + faculty + branch ──────────────────
$studentRow = Database::search("
    SELECT
        s.student_id,
        ld.first_name, ld.last_name, ld.email,
        s.mobile,
        t.title AS student_title,
        i.intake_id, i.intake,
        p.programme_id, p.programme,
        f.faculty_id, f.faculty,
        b.branch_id, b.branch

    FROM students_has_intakes shi
    INNER JOIN students      s   ON shi.students_student_id          = s.student_id
    INNER JOIN login_details ld  ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN title         t   ON s.title_title_id                 = t.title_id
    INNER JOIN intakes       i   ON shi.intakes_intake_id            = i.intake_id
    INNER JOIN programmes    p   ON i.programmes_programme_id        = p.programme_id
    INNER JOIN faculties     f   ON p.faculties_faculty_id           = f.faculty_id
    INNER JOIN branches      b   ON f.branches_branch_id             = b.branch_id
    WHERE shi.students_has_intakes_id = '" . $shi_id . "'
    LIMIT 1
");

if (!$studentRow || $studentRow->num_rows == 0) {
    echo json_encode(['error' => 'Student not found']);
    exit;
}
$student = $studentRow->fetch_assoc();

// ── Fetch all CONFIRMED payment rows linked to this receipt ───────────────
// v10: added payment_status filter + cp.discount / discount_type_id so the
// installment-plan discount can be resolved the same way as v6.
$paymentsResult = Database::search("
    SELECT
        cpp.paid_amount, cpp.discount_amount, cpp.panelty_amount,
        cpp.tax_rate,    cpp.tax_amount,      cpp.date_paid,
        cpp.currencies_currency_id,
        cpp.currency_exchange_rate,
        cpp.currency_exchange_rate_id,
        cur.code          AS paid_currency,
        pm.payment_method,
        cp.course_payments_id            AS installment_id,
        cp.refference                    AS reference,
        cp.amount                        AS cp_amount,
        cp.discount                      AS cp_discount,
        cp.discount_type_discount_type_id,
        pgp.payment_type,
        c_course.code                    AS course_currency,
        c_course.currency_id             AS course_currency_id
    FROM course_payment_has_payments cpp
    INNER JOIN payment_method pm   ON cpp.payment_method_payment_method_id  = pm.payment_method_id
    INNER JOIN course_payments cp  ON cpp.course_payments_course_payments_id = cp.course_payments_id
    INNER JOIN payment_type pgp    ON cp.payment_type_payment_type_id        = pgp.payment_type_id
    INNER JOIN payment_plan_has_students_has_intakes phsi
                                   ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category pc ON phsi.payment_category_payment_category_id  = pc.payment_category_id
    INNER JOIN currencies c_course ON pc.currencies_currency_id                  = c_course.currency_id
    LEFT JOIN currencies cur       ON cpp.currencies_currency_id = cur.currency_id
    WHERE cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
      AND cpp.payment_status_payment_status_id = 3
    ORDER BY cpp.date_paid ASC
");

// ── Helper functions ────────────────────────────────────────────────────────
$lkrCurrencyId = 1;

function resolveRate($row, $courseCurrId)
{
    if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrId) return null;
    if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) return (float)$row['currency_exchange_rate'];
    if (!empty($row['currency_exchange_rate_id'])) {
        $r = Database::search("SELECT rate FROM currency_exchange_rates WHERE currency_exchange_rate_id='" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $rd = $r->fetch_assoc();
            if (!empty($rd['rate']) && $rd['rate'] > 0) return (float)$rd['rate'];
        }
    }
    return null;
}

function toCourse($amount, $rate)
{
    if ($rate === null || $rate <= 0) return (float)$amount;
    return (float)$amount / $rate;
}

function fmtN($n, $dp = 2)
{
    return number_format((float)($n ?? 0), $dp);
}

function esc($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * v10 — ported directly from getReceiptData.php v6. Latest global tax rate,
 * cached for the life of the request.
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
 * v10 — ported directly from getReceiptData.php v6. Course currency -> LKR
 * "page" exchange rate, used only to convert panelty_payments (always LKR)
 * into course currency. Cached per course currency for the request.
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
 * v10 — ported directly from getReceiptData.php v6's getInstallmentBalance().
 * Same tax-inclusive model, same flagged assumption (uses TODAY's latest
 * tax rate, not a historical one — matches the live dashboard exactly).
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

    return round($remainingBase + $remainingTax, 2);
}

// ── Calculate totals ──────────────────────────────────────────────────────
$rows                = [];
$cc                  = 'LKR';
$courseCurrId        = 1;
$grandPaid           = 0;
$grandDiscount       = 0;
$grandPenalty        = 0;
$grandTax            = 0;
$grandAmount         = 0;
$installmentBalances = []; // installment_id => latest running balance

if ($paymentsResult && $paymentsResult->num_rows > 0) {
    while ($row = $paymentsResult->fetch_assoc()) {
        $cc           = $row['course_currency'];
        $courseCurrId = (int)$row['course_currency_id'];
        $rate         = resolveRate($row, $courseCurrId);
        $paidInCourse = toCourse($row['paid_amount'], $rate);
        $taxInCourse  = toCourse((float)($row['tax_amount'] ?? 0), $rate);
        $penInCourse  = toCourse((float)($row['panelty_amount'] ?? 0), $rate);
        $discInCourse = toCourse((float)($row['discount_amount'] ?? 0), $rate);

        // v10: resolve the installment-plan-level discount the same way as
        // intake-student-payment.php / getReceiptData.php v6.
        $installmentId       = (int)$row['installment_id'];
        $installmentAmount   = (float)$row['cp_amount'];
        $discountTypeId      = (int)$row['discount_type_discount_type_id'];
        $installmentDiscount = ($discountTypeId == 3)
            ? ($installmentAmount / 100 * (float)$row['cp_discount'])
            : (float)$row['cp_discount'];

        $installmentBalance = getInstallmentBalance(
            $installmentId,
            $row['date_paid'],
            $courseCurrId,
            $lkrCurrencyId,
            $installmentAmount,
            $installmentDiscount
        );
        // Last occurrence per installment wins (rows are date_paid ASC),
        // so this ends up as the running balance as of this receipt's data.
        $installmentBalances[$installmentId] = $installmentBalance;

        $rows[] = [
            'reference'          => $row['reference'],
            'payment_type'       => $row['payment_type'],
            'date_paid'          => $row['date_paid'] ? substr($row['date_paid'], 0, 10) : '',
            'payment_method'     => $row['payment_method'],
            'cp_amount'          => $installmentAmount,
            'paid_amount_raw'    => (float)$row['paid_amount'],
            'paid_in_course'     => $paidInCourse,
            'paid_currency'      => $row['paid_currency'] ?: $cc,
            'exchange_rate'      => $rate,
            'tax_rate'           => (float)($row['tax_rate'] ?? 0),
            'tax_amount_raw'     => (float)($row['tax_amount'] ?? 0),
            'tax_in_course'      => $taxInCourse,
            'discount_course'    => $discInCourse,
            'penalty_course'     => $penInCourse,
            'installment_balance' => $installmentBalance,
        ];

        $grandAmount   += $installmentAmount;
        $grandDiscount += $discInCourse;
        $grandPenalty  += $penInCourse;
        $grandTax      += $taxInCourse;
        $grandPaid     += $paidInCourse;
    }
}

// v10: outstanding balance = sum of the latest per-installment balance for
// every installment touched by this receipt (tax-inclusive model), not a
// flat grandPaid - grandTax subtraction.
$totalOutstandingBalance = round(array_sum($installmentBalances), 2);
$isPaid = ($totalOutstandingBalance <= 0.01);

$paymentDate = substr($receipt['date_added'], 0, 10);

// ── Generate PDF HTML ─────────────────────────────────────────────────────
$receiptHtml = buildReceiptPdf(
    $receipt,
    $student,
    $rows,
    $cc,
    $grandAmount,
    $grandDiscount,
    $grandPenalty,
    $grandTax,
    $grandPaid,
    $totalOutstandingBalance,
    $isPaid
);

// ── Generate PDF from HTML ─────────────────────────────────────────────────
$pdfPath = null;
$usePdf  = false;

$dompdfPaths = [
    '../vendor/autoload.php',
    '../includes/dompdf/autoload.inc.php',
];

foreach ($dompdfPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $usePdf = true;
        break;
    }
}

if ($usePdf && class_exists('Dompdf\Dompdf')) {
    try {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($receiptHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $pdfPath   = sys_get_temp_dir() . '/receipt_' . $receipt_id . '_' . time() . '.pdf';
        file_put_contents($pdfPath, $pdfOutput);
    } catch (Exception $e) {
        echo json_encode(['error' => 'PDF Error', 'message' => $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['error' => 'PDF generation library not found']);
    exit;
}

// ── Build email ────────────────────────────────────────────────────────────
$studentName = $student['student_title'] . '. ' . $student['first_name'] . ' ' . $student['last_name'];
$toEmail     = $student['email'];

$emailData = [
    'student_name'   => $studentName,
    'receipt_number' => $receipt['receipt_number'],
    'programme'      => $student['programme'],
    'intake'         => $student['intake'],
    'total_paid'     => $cc . ' ' . fmtN($grandPaid),
    'balance_due'    => $isPaid ? 'FULLY PAID' : $cc . ' ' . fmtN($totalOutstandingBalance),
    'payment_date'   => $paymentDate,
];

$emailContent = EmailNotificationService::build(
    EmailTemplateContent::RECEIPT_EMAIL,
    $emailData
);

// ── Send email via PHPMailer ───────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../includes/phpmailer/src/Exception.php';
require_once '../includes/phpmailer/src/PHPMailer.php';
require_once '../includes/phpmailer/src/SMTP.php';

try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug  = 0;
    $mail->isSMTP();
    $mail->Host       = 'smtp.titan.email';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'no-reply@aiccampuslms.com';
    $mail->Password   = 'AicLMS#@!2026Campus';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('no-reply@aiccampuslms.com', Project::getBaseName());
    $mail->addAddress($toEmail, $studentName);

    if ($usePdf && $pdfPath && file_exists($pdfPath)) {
        $mail->addAttachment(
            $pdfPath,
            'Receipt-' . $receipt['receipt_number'] . '.pdf',
            PHPMailer::ENCODING_BASE64,
            'application/pdf'
        );
    }

    $mail->isHTML(true);
    $mail->Subject = $emailContent['subject'];
    $mail->Body    = $emailContent['body'];
    $mail->AltBody = 'Dear ' . $studentName . ', your payment receipt ' . $receipt['receipt_number'] . ' is attached. Total Paid: ' . $cc . ' ' . fmtN($grandPaid);

    $mail->send();

    if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);

    $timezone = new DateTimeZone("Asia/Colombo");
    $now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');
    Database::iud("
        INSERT INTO payment_logs
            (course_payments_course_payments_id, users_user_id, date_updated, log)
        SELECT
            cpp.course_payments_course_payments_id,
            '" . (int)$sessionUserObject['user_id'] . "',
            '" . $now . "',
            'Invoice email sent to " . Database::escape($toEmail) . " — Receipt " . Database::escape($receipt['receipt_number']) . " with PDF attachment'
        FROM course_payment_has_payments cpp
        WHERE cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
        LIMIT 1
    ");

    echo json_encode(['done' => 'Invoice email sent successfully to ' . $toEmail . ' with PDF attachment']);
} catch (Exception $e) {
    if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);
    echo json_encode(['error' => 'Failed to send email: ' . $mail->ErrorInfo]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// BUILD RECEIPT PDF — v10: restyled to match openInvoicePrintWindow.js
// (Arial stack, info-label/info-value pattern, signature block, enviiz footer)
// ═══════════════════════════════════════════════════════════════════════════
function buildReceiptPdf(
    $receipt,
    $student,
    $rows,
    $cc,
    $grandAmount,
    $grandDiscount,
    $grandPenalty,
    $grandTax,
    $grandPaid,
    $totalOutstandingBalance,
    $isPaid
) {
    $recDate = substr($receipt['date_added'], 0, 10);
    $recNum  = esc($receipt['receipt_number']);

    $statusClass = $isPaid ? 'status-paid' : 'status-pending';
    $statusLabel = $isPaid ? 'PAID' : 'PARTIAL';
    $year        = date('Y');

    // ── Build payment rows ─────────────────────────────────────────────────
    $payRows = '';
    $idx     = 0;

    foreach ($rows as $p) {
        $idx++;

        $paidMain = ($p['exchange_rate'] && $p['paid_currency'] !== $cc)
            ? esc($p['paid_currency']) . ' ' . fmtN($p['paid_amount_raw'])
            . '<div style="font-size:9px;color:#617188;margin-top:2px;">= ' . $cc . ' ' . fmtN($p['paid_in_course']) . '</div>'
            : $cc . ' ' . fmtN($p['paid_in_course']);

        $taxMain     = $p['tax_in_course']   > 0 ? $cc . ' ' . fmtN($p['tax_in_course'])   : '&mdash;';
        $discMain    = $p['discount_course'] > 0 ? $cc . ' ' . fmtN($p['discount_course']) : '&mdash;';
        $penMain     = $p['penalty_course']  > 0 ? $cc . ' ' . fmtN($p['penalty_course'])  : '&mdash;';
        $taxRateDisp = $p['tax_rate']        > 0 ? fmtN($p['tax_rate']) . '%'              : '&mdash;';
        $balMain     = $p['installment_balance'] > 0.01
            ? $cc . ' ' . fmtN($p['installment_balance'])
            : '<span style="color:#2f855a;font-weight:700;">Settled</span>';

        $payRows .= '<tr>'
            . '<td>' . $idx . '</td>'
            . '<td style="font-weight:700;color:#4c555b;">' . esc($p['reference'])      . '</td>'
            . '<td>' . esc($p['payment_type'])   . '</td>'
            . '<td style="white-space:nowrap;">' . $p['date_paid']           . '</td>'
            . '<td>' . esc($p['payment_method']) . '</td>'
            . '<td class="amount-col">' . $cc . ' ' . fmtN($p['cp_amount']) . '</td>'
            . '<td class="amount-col">' . $discMain    . '</td>'
            . '<td class="amount-col">' . $penMain     . '</td>'
            . '<td style="text-align:center;">' . $taxRateDisp . '</td>'
            . '<td class="amount-col">' . $taxMain     . '</td>'
            . '<td class="amount-col" style="font-weight:700;color:#4c555b;">' . $paidMain . '</td>'
            . '<td class="amount-col">' . $balMain . '</td>'
            . '</tr>';
    }

    if (!$payRows) {
        $payRows = '<tr><td colspan="12" style="text-align:center;padding:24px;color:#9ca3af;">No payment records found</td></tr>';
    }

    // ── Summary rows ──────────────────────────────────────────────────────
    $discDisplay = $grandDiscount > 0.005 ? $cc . ' ' . fmtN($grandDiscount) : '&mdash;';
    $penDisplay  = $grandPenalty  > 0.005 ? $cc . ' ' . fmtN($grandPenalty)  : '&mdash;';
    $taxDisplay  = $grandTax      > 0.005 ? $cc . ' ' . fmtN($grandTax)      : '&mdash;';
    $balDisplay  = $isPaid ? 'FULLY PAID' : $cc . ' ' . fmtN($totalOutstandingBalance);

    $summaryRows =
        '<tr><td>Total Course Fee</td><td class="amount-col">' . $cc . ' ' . fmtN($grandAmount) . '</td></tr>'
        . '<tr><td>Total Discount</td><td class="amount-col">' . $discDisplay . '</td></tr>'
        . '<tr><td>Total Penalty</td><td class="amount-col">' . $penDisplay . '</td></tr>'
        . '<tr><td>Total Tax</td><td class="amount-col">' . $taxDisplay . '</td></tr>'
        . '<tr><td>Total Paid</td><td class="amount-col">' . $cc . ' ' . fmtN($grandPaid) . '</td></tr>'
        . '<tr class="balance-row"><td>Balance Due</td><td class="amount-col">' . $balDisplay . '</td></tr>';

    $studentName = esc($student['student_title'] . '. ' . $student['first_name'] . ' ' . $student['last_name']);
    $processedBy = esc($receipt['user_title'] . '. ' . $receipt['user_fname'] . ' ' . $receipt['user_lname']);

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payment Receipt {$recNum}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11px;
        line-height: 1.5;
        color: #4c555b;
        background: #fff;
    }

    @media print {
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { size: A4 portrait; margin: 8mm 10mm; }
    }

    .brand-logo {
        height: 44px;
        width: auto;
        display: block;
        margin-bottom: 8px;
    }

    .company-address {
        margin-top: 4px;
        font-size: 10px;
        line-height: 1.6;
        color: #617188;
        font-weight: 500;
    }

    .receipt-title { text-align: right; }

    .receipt-title h1 {
        font-size: 26px;
        font-weight: 700;
        color: #4c555b;
        letter-spacing: 0.1px;
    }

    .receipt-number {
        margin-top: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #4c555b;
    }

    .status-pill {
        display: inline-block;
        margin-top: 8px;
        border: 1px solid #d8dde5;
        border-radius: 20px;
        padding: 3px 12px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    .status-paid    { color: #2f855a; border-color: #9ae6b4; }
    .status-pending { color: #b7791f; border-color: #f6e05e; }

    .info-section { margin-top: 26px; }

    .info-label {
        display: block;
        font-size: 9px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 4px;
    }

    .info-value {
        font-size: 11px;
        line-height: 1.6;
        color: #4c555b;
        font-weight: 500;
        word-break: break-word;
    }

    .invoice-table-wrap { margin-top: 30px; }

    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        border-top: 1px solid #F3F4F4;
    }

    .invoice-table thead th {
        font-size: 9px;
        font-weight: 700;
        color: #4c555b;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 8px 6px;
        text-align: left;
        border-bottom: 1px solid #d8dde5;
    }

    .invoice-table tbody td {
        font-size: 9.5px;
        color: #617188;
        padding: 8px 6px;
        vertical-align: top;
        border-bottom: 1px solid #F3F4F4;
    }

    .amount-col { text-align: right; white-space: nowrap; }

    .summary-wrap { margin-top: 24px; }

    .summary-table {
        width: 100%;
        max-width: 260px;
        margin-left: auto;
        border-collapse: collapse;
        border: 1px solid #e5e7eb;
    }

    .summary-table td {
        padding: 7px 12px;
        font-size: 10.5px;
        color: #617188;
        font-weight: 500;
        border-bottom: 1px solid #f3f4f6;
    }

    .balance-row td {
        background: #4c555b;
        color: #fff;
        font-weight: 700;
        font-size: 12px;
        border-bottom: none;
    }

    .notes {
        font-size: 9px;
        color: #9ca3af;
        line-height: 1.9;
        margin-top: 24px;
    }

    .signature-section {
        margin-top: 46px;
        width: 150px;
        text-align: center;
    }

    .signature-line {
        width: 150px;
        border-top: 1px solid #4f647c;
        margin-bottom: 5px;
    }

    .signature-text { font-size: 10px; color: #617188; font-weight: 500; }

    .invoice-footer {
        border-top: 1px solid #d8dde5;
        margin-top: 34px;
        padding-top: 9px;
        text-align: center;
    }

    .content-div-bottom {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sub-text { font-size: 9px; color: #6e7b8c; margin: 0; line-height: 1.35; font-weight: 500; }

    .footer-link { text-decoration: none; }

    .enviiz-logo { width: 22px; height: auto; vertical-align: middle; margin-left: 3px; }
</style>
</head>
<body>

<table style="width:100%;border-collapse:collapse;">
<tr>
<td style="vertical-align:top;width:55%;">
    <img src="https://dashboard.aiccampuslms.com/assets/imgs/common/logo.png" alt="AIC Campus" class="brand-logo">
    <div class="company-address">
        No.154, Havelock Road, Thimbirigasyaya, Colombo 05<br>
        +94 114 325 864 &nbsp;|&nbsp; accounts@aicedu.lk
    </div>
</td>
<td style="vertical-align:top;">
    <div class="receipt-title">
        <h1>Receipt</h1>
        <div class="receipt-number"># {$recNum}</div>
        <span class="status-pill {$statusClass}">{$statusLabel}</span>
    </div>
</td>
</tr>
</table>

<table style="width:100%;border-collapse:collapse;" class="info-section">
<tr>
<td style="width:33%;vertical-align:top;">
    <span class="info-label">Billed To</span>
    <div class="info-value">
        {$studentName}<br>
        ID: {$student['student_id']}<br>
        {$student['email']}<br>
        {$student['mobile']}
    </div>
</td>
<td style="width:34%;vertical-align:top;">
    <span class="info-label">Programme</span>
    <div class="info-value">
        {$student['faculty']}<br>
        {$student['programme']}<br>
        {$student['intake']}
    </div>
</td>
<td style="width:33%;vertical-align:top;text-align:right;">
    <span class="info-label">Date</span>
    <div class="info-value">{$recDate}</div>
    <span class="info-label" style="margin-top:10px;">Processed By</span>
    <div class="info-value">{$processedBy}</div>
</td>
</tr>
</table>

<div class="invoice-table-wrap">
<table class="invoice-table">
<thead>
<tr>
    <th>#</th>
    <th>Reference</th>
    <th>Type</th>
    <th>Date</th>
    <th>Method</th>
    <th class="amount-col">Schedule Amt</th>
    <th class="amount-col">Discount</th>
    <th class="amount-col">Penalty</th>
    <th style="text-align:center;">Tax %</th>
    <th class="amount-col">Tax Amt</th>
    <th class="amount-col">Paid</th>
    <th class="amount-col">Balance</th>
</tr>
</thead>
<tbody>{$payRows}</tbody>
</table>
</div>

<table style="width:100%;border-collapse:collapse;" class="summary-wrap">
<tr>
<td style="width:60%;vertical-align:top;">
    <div class="notes">
        &#8226; Payments made will not be refundable or transferable under any circumstances.<br>
        &#8226; This receipt is valid subject to realisation of cheque.<br>
        &#8226; Taxes or bank charges may be applied where required.<br>
        &#8226; Only penalties added on or before the payment date are included in this receipt.<br>
        &#8226; Balance reflects the current tax rate and may differ from the rate applied at the time of payment.
    </div>
    <div class="signature-section">
        <div class="signature-line"></div>
        <div class="signature-text">Prepared by</div>
    </div>
</td>
<td style="width:40%;vertical-align:top;">
    <table class="summary-table">{$summaryRows}</table>
</td>
</tr>
</table>

<div class="invoice-footer">
    <div class="content-div-bottom">
        <p class="sub-text">
            &copy;{$year} AIC Campus. All Rights Reserved | Powered by
            <a href="https://enviiz.com" target="_blank" class="footer-link">
                <img src="./assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" class="enviiz-logo">
            </a>
        </p>
    </div>
</div>

</body>
</html>
HTML;
}
