<?php

/**
 * api/sendReceiptEmail.php - v11
 *
 * CHANGES IN v11 (full rework, per Kavee's request):
 *   The backend no longer re-derives payment totals/tax/currency math or
 *   builds its own separate receipt HTML (as v9/v10 did). Instead, the
 *   frontend generates the PDF client-side from getReceiptData.php's
 *   response, rendered through the SAME template used for the on-screen
 *   print/view receipt, and uploads the finished PDF here. This removes
 *   the duplicate HTML template and calculation logic entirely — one
 *   render, one source of truth, guaranteed pixel parity with what the
 *   student sees on screen.
 *
 *   This endpoint now:
 *     1. Accepts multipart/form-data: receipt_id, pdf (the rendered file),
 *        total_paid, balance_due (both already computed client-side from
 *        getReceiptData.php — used only for the email BODY text, not the
 *        attachment).
 *     2. Validates the upload (real PDF via finfo, size cap).
 *     3. Looks up the receipt/student purely for recipient name/email and
 *        email-body context — no payment recalculation happens here.
 *     4. Attaches the uploaded PDF as-is and sends the email.
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

// ── Parse multipart body (receipt_id, pdf file, display-only totals) ──────
$receipt_id = isset($_POST['receipt_id']) ? (int)$_POST['receipt_id'] : 0;
$totalPaid  = $_POST['total_paid']  ?? '';
$balanceDue = $_POST['balance_due'] ?? '';

if ($receipt_id <= 0) {
    echo json_encode(['error' => 'Invalid receipt ID 1']);
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

// ── Fetch receipt + student — ONLY for recipient name/email/programme.
//    No payment recalculation happens in this file anymore. ────────────
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

$studentRow = Database::search("
    SELECT
        s.student_id,
        ld.first_name, ld.last_name, ld.email,
        t.title AS student_title,
        p.programme,
        i.intake
    FROM students_has_intakes shi
    INNER JOIN students      s   ON shi.students_student_id          = s.student_id
    INNER JOIN login_details ld  ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN title         t   ON s.title_title_id                 = t.title_id
    INNER JOIN intakes       i   ON shi.intakes_intake_id            = i.intake_id
    INNER JOIN programmes    p   ON i.programmes_programme_id        = p.programme_id
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
    'total_paid'     => $totalPaid,
    'balance_due'    => $balanceDue,
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
            'Invoice email sent to " . Database::escape($toEmail) . " — Receipt " . Database::escape($receipt['receipt_number']) . " with PDF attachment'
        FROM course_payment_has_payments cpp
        WHERE cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
        LIMIT 1
    ");

    echo json_encode(['done' => 'Invoice email sent successfully to ' . $toEmail . ' with PDF attachment']);
} catch (Exception $e) {
    if (file_exists($pdfPath)) @unlink($pdfPath);
    echo json_encode(['error' => 'Failed to send email: ' . $mail->ErrorInfo]);
    exit;
}
