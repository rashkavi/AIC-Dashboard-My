<?php

/**
 * api/sendReceiptEmail.php - v13
 *
 * CHANGES IN v13:
 *   Total paid and balance due are now calculated HERE from receipt_id,
 *   using the same logic as getReceiptData.php v6. The frontend only needs
 *   to send receipt_id + the rendered pdf. total_paid / balance_due POST
 *   fields are no longer read.
 *
 *     total_paid  = SUM(paid_in_course) of all confirmed payments on this
 *                   receipt (= getReceiptData.php grand_total_paid)
 *     balance_due = for each installment on this receipt, take its
 *                   installment_balance as of the LATEST payment on this
 *                   receipt (getInstallmentBalance(), tax-inclusive model),
 *                   then sum across installments.
 *
 *   Removed the stray top-level balance block from the previous draft that
 *   referenced undefined variables ($cumulativePaidCourse, $basePayable).
 *
 * CHANGES IN v12:
 *   For registration payments (payment types 18, 19, 20), the student's
 *   counselor is CC'd on the receipt email. The CC is skipped when no
 *   counselor is assigned, the counselor email is invalid, or it matches
 *   the student's own address. The CC is recorded in payment_logs and in
 *   the JSON response.
 *
 * CHANGES IN v11:
 *   The frontend renders the receipt PDF (same template as on-screen view)
 *   and uploads it here; this endpoint attaches it as-is and sends the email.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');
require_once('../includes/phpmailer/EmailLayoutRenderer.php');
require_once('../includes/phpmailer/EmailNotificationService.php');
require_once('../includes/phpmailer/EmailTemplateContent.php');
require_once('../includes/phpmailer/mail-process.php');

require_once '../includes/phpmailer/src/Exception.php';
require_once '../includes/phpmailer/src/PHPMailer.php';
require_once '../includes/phpmailer/src/SMTP.php';

header('Content-Type: application/json');

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
    SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) ||
    UserPermissions::validPermisisonType('payments')
)) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// ── Parse multipart body (receipt_id + pdf only) ──────────────────────────
$receipt_id = isset($_POST['receipt_id']) ? (int)$_POST['receipt_id'] : 0;

if ($receipt_id <= 0) {
    echo json_encode(['error' => 'Invalid receipt ID']);
    exit;
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Receipt PDF was not received. Please try again.']);
    exit;
}

// ── Validate the upload is actually a PDF, cap size at 10MB ───────────────
$uploadedPath = $_FILES['pdf']['tmp_name'];
$uploadedSize = (int)$_FILES['pdf']['size'];

if ($uploadedSize <= 0 || $uploadedSize > 10 * 1024 * 1024) {
    echo json_encode(['error' => 'Receipt PDF is missing or too large']);
    exit;
}

$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedPath);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    echo json_encode(['error' => 'Uploaded file is not a valid PDF']);
    exit;
}

// ── Receipt master row ─────────────────────────────────────────────────────
$receiptRow = Database::search("
    SELECT
        cpr.course_payment_receipt_id,
        cpr.receipt_number,
        cpr.date_added,
        cpr.students_has_intakes_students_has_intakes_id
    FROM course_payment_receipts cpr
    WHERE cpr.course_payment_receipt_id = '" . $receipt_id . "'
    LIMIT 1
");

if (!$receiptRow || $receiptRow->num_rows == 0) {
    echo json_encode(['error' => 'Receipt not found']);
    exit;
}
$receipt = $receiptRow->fetch_assoc();
$shi_id  = (int)$receipt['students_has_intakes_students_has_intakes_id'];

// ── Student + counselor ────────────────────────────────────────────────────
$studentRow = Database::search("
    SELECT
        s.student_id,
        ld.first_name, ld.last_name, ld.email,
        t.title AS student_title,
        p.programme,
        i.intake,
        counselor.user_fname AS counselor_fname,
        counselor.user_lname AS counselor_lname,
        counselor.user_email AS counselor_email
    FROM students_has_intakes shi
    INNER JOIN students      s   ON shi.students_student_id          = s.student_id
    INNER JOIN login_details ld  ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN title         t   ON s.title_title_id                 = t.title_id
    INNER JOIN intakes       i   ON shi.intakes_intake_id            = i.intake_id
    INNER JOIN programmes    p   ON i.programmes_programme_id        = p.programme_id
    LEFT JOIN users AS counselor ON shi.counselor_user_id = counselor.user_id
    WHERE shi.students_has_intakes_id = '" . $shi_id . "'
    LIMIT 1
");

if (!$studentRow || $studentRow->num_rows == 0) {
    echo json_encode(['error' => 'Student not found']);
    exit;
}
$student = $studentRow->fetch_assoc();

$studentName = $student['student_title'] . '. ' . $student['first_name'] . ' ' . $student['last_name'];
$toEmail     = $student['email'];
$paymentDate = substr($receipt['date_added'], 0, 10);

/* ════════════════════════════════════════════════════════════════════════════
   Total paid + balance due (same logic as getReceiptData.php v6)
════════════════════════════════════════════════════════════════════════════ */
$lkrCurrencyId = 1;
$__rateCache   = [];

$paymentsRow = Database::search("
    SELECT
        cpp.course_payment_has_payment_id,
        cpp.paid_amount,
        cpp.date_paid,
        cpp.currency_exchange_rate,
        cpp.currencies_currency_id   AS paid_currency_db_id,
        cpp.currency_exchange_rate_id,

        cp.course_payments_id        AS installment_id,
        cp.amount                    AS cp_amount,
        cp.discount                  AS cp_discount,
        cp.discount_type_discount_type_id,

        pc.currencies_currency_id    AS course_currency_id,
        c.code                       AS course_currency_code,
        c.decimal_places             AS course_decimal_places

    FROM course_payment_has_payments cpp
    INNER JOIN course_payments  cp   ON cpp.course_payments_course_payments_id
                                          = cp.course_payments_id
    INNER JOIN payment_plan_has_students_has_intakes ppsi
        ON cp.payment_plan_has_students_has_intakes_id
             = ppsi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category pc   ON ppsi.payment_category_payment_category_id
                                          = pc.payment_category_id
    INNER JOIN currencies       c    ON pc.currencies_currency_id
                                          = c.currency_id

    WHERE  cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
      AND  cpp.payment_status_payment_status_id = 3
    ORDER  BY cpp.date_paid ASC
");

$grandTotalPaid       = 0.0;
$installmentBalances  = [];   // installment_id => balance as of latest payment on this receipt
$courseCurrencyCode   = 'LKR';
$courseDecimalPlaces  = 2;

if ($paymentsRow) {
    while ($p = $paymentsRow->fetch_assoc()) {

        $courseCurrId        = (int)$p['course_currency_id'];
        $courseCurrencyCode  = $p['course_currency_code'];
        $courseDecimalPlaces = ($p['course_decimal_places'] !== null)
            ? (int)$p['course_decimal_places']
            : 2;

        $exRate = resolveRate($p, $courseCurrId);
        $grandTotalPaid += toCourse((float)$p['paid_amount'], $exRate);

        // Installment-plan discount: type 3 = percentage of installment amount
        $installmentId     = (int)$p['installment_id'];
        $installmentAmount = (float)$p['cp_amount'];
        $installmentDiscount = ((int)$p['discount_type_discount_type_id'] == 3)
            ? ($installmentAmount / 100 * (float)$p['cp_discount'])
            : (float)$p['cp_discount'];

        $paymentDate = $p['date_paid'];

        // Rows are ordered by date_paid ASC, so the last one per installment wins
        $installmentBalances[$installmentId] = getInstallmentBalance(
            $installmentId,
            $p['date_paid'],
            $courseCurrId,
            $lkrCurrencyId,
            $installmentAmount,
            $installmentDiscount
        );
    }
}

$totalPaid  = round($grandTotalPaid, $courseDecimalPlaces);
$balanceDue = round(array_sum($installmentBalances), $courseDecimalPlaces);

// ── Registration payment check (payment types 18, 19, 20) ─────────────────
$isRegistrationPayment = false;

$isPaymentRegistration = Database::search("
    SELECT 1
    FROM course_payment_has_payments cpp
    INNER JOIN course_payments cp
        ON cpp.course_payments_course_payments_id = cp.course_payments_id
    WHERE cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
      AND cp.payment_type_payment_type_id IN (18,19,20)
    LIMIT 1
");

if ($isPaymentRegistration && $isPaymentRegistration->num_rows > 0) {
    $isRegistrationPayment = true;
}

// ── Counselor CC (registration payments only) ─────────────────────────────
$counselorEmail = trim($student['counselor_email'] ?? '');
$counselorName  = trim(($student['counselor_fname'] ?? '') . ' ' . ($student['counselor_lname'] ?? ''));

$ccCounselor = $isRegistrationPayment
    && $counselorEmail !== ''
    && filter_var($counselorEmail, FILTER_VALIDATE_EMAIL)
    && strcasecmp($counselorEmail, (string)$toEmail) !== 0;

$ccLogText = $ccCounselor ? ' (CC counselor: ' . $counselorEmail . ')' : '';

// ── Persist the uploaded PDF to a temp path PHPMailer can attach ─────────
$pdfPath = sys_get_temp_dir() . '/receipt_' . $receipt_id . '_' . time() . '.pdf';
if (!move_uploaded_file($uploadedPath, $pdfPath)) {
    echo json_encode(['error' => 'Could not process the uploaded PDF']);
    exit;
}

// ── Build email ────────────────────────────────────────────────────────────
$emailData = [
    'student_name'   => $studentName,
    'receipt_number' => $receipt['receipt_number'],
    'programme'      => $student['programme'],
    'intake'         => $student['intake'],
    'total_paid'     => $courseCurrencyCode . ' ' . number_format($totalPaid, $courseDecimalPlaces),
    'balance_due'    => $courseCurrencyCode . ' ' . number_format($balanceDue, $courseDecimalPlaces),
    'payment_date'   => $paymentDate,
];

$emailContent = EmailNotificationService::build(
    EmailTemplateContent::RECEIPT_EMAIL,
    $emailData
);

// ── Send email via PHPMailer ───────────────────────────────────────────────
$mail = new PHPMailer(true);

try {
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

    if ($ccCounselor) {
        $mail->addCC($counselorEmail, $counselorName);
    }

    $mail->addAttachment(
        $pdfPath,
        'Receipt-' . $receipt['receipt_number'] . '.pdf',
        PHPMailer::ENCODING_BASE64,
        'application/pdf'
    );

    $mail->isHTML(true);
    $mail->Subject = $emailContent['subject'];
    $mail->Body    = $emailContent['body'];
    $mail->AltBody = 'Dear ' . $studentName . ', your payment receipt ' . $receipt['receipt_number'] . ' is attached.';

    $mail->send();

    if (file_exists($pdfPath)) @unlink($pdfPath);

    $timezone = new DateTimeZone("Asia/Colombo");
    $now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');
    Database::iud("
        INSERT INTO payment_logs
            (course_payments_course_payments_id, users_user_id, date_updated, log)
        SELECT
            cpp.course_payments_course_payments_id,
            '" . (int)$sessionUserObject['user_id'] . "',
            '" . $now . "',
            'Invoice email sent to " . Database::escape($toEmail) . Database::escape($ccLogText) . " — Receipt " . Database::escape($receipt['receipt_number']) . " with PDF attachment'
        FROM course_payment_has_payments cpp
        WHERE cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
        LIMIT 1
    ");

    echo json_encode([
        'done'        => 'Invoice email sent successfully to ' . $toEmail . $ccLogText . ' with PDF attachment',
        'total_paid'  => $totalPaid,
        'balance_due' => $balanceDue,
        'currency'    => $courseCurrencyCode,
    ]);
} catch (Exception $e) {
    if (file_exists($pdfPath)) @unlink($pdfPath);
    echo json_encode(['error' => 'Failed to send email: ' . $mail->ErrorInfo]);
    exit;
}

/* ════════════════════════════════════════════════════════════════════════════
   Helpers — identical to getReceiptData.php v6
════════════════════════════════════════════════════════════════════════════ */

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

function getInstallmentBalance(
    int $installmentId,
    string $cutoffDatePaid,
    int $courseCurrId,
    int $lkrCurrencyId,
    float $installmentAmount,
    float $installmentDiscount
): float {
    /* 1) Cumulative confirmed payments toward this installment up to cutoff */
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

    /* 2) Ledger penalties (LKR) on/before cutoff date */
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

    /* 3) Tax-inclusive balance, same as intake-student-payment.php */
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
