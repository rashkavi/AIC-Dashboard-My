<?php
// api/notifyIntakeTimetable.php
//
// Emails the intake summary timetable PDF to every active student of the intake.
// The page renders the PDF in the browser (same layout as Download PDF) and
// uploads it here.
//
// POST multipart/form-data: timetable_id, pdf (the rendered file)
// Students go in BCC through sendBulkMail(), in batches of 50.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');
require_once('../includes/IntakeTimetableManager.php');
require_once('../includes/phpmailer/EmailLayoutRenderer.php');
require_once('../includes/phpmailer/EmailNotificationService.php');
require_once('../includes/phpmailer/EmailTemplateContent.php');
require_once('../includes/phpmailer/mail-process.php');   // contains sendBulkMail()

$status = new stdClass();

function respond(stdClass $status): void
{
    echo json_encode($status);
    exit;
}

if (!SessionUser::hasValidSession()) {
    $status->error = 'Authentication Failed!';
    respond($status);
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
if ($sessionUserRole == null) {
    $status->error = 'Authentication Failed!';
    respond($status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    $status->error = 'Invalid request method';
    respond($status);
}

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
$userId = (int) $sessionUserObject['user_id'];

// ── Timetable + permission ────────────────────────────────────────────────
$timetableId = isset($_POST['timetable_id']) ? (int) $_POST['timetable_id'] : 0;
$header      = IntakeTimetableManager::getHeader($timetableId);
if (!$header) {
    $status->error = 'Timetable not found.';
    respond($status);
}
$intakeId = (int) $header['intake_id'];

if (!IntakeTimetableManager::canManage($sessionUserRole, $intakeId)) {
    $status->error = 'You are not authorized to this action.';
    respond($status);
}

if ($header['status'] !== IntakeTimetableManager::STATUS_ACTIVE) {
    $status->error = 'Only the active timetable can be sent to students.';
    respond($status);
}

$intake = IntakeTimetableManager::getIntake($intakeId);

// ── Validate the uploaded PDF (real PDF, max 10MB) ────────────────────────
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $status->error = 'Timetable PDF was not received. Please try again.';
    respond($status);
}

$uploadedPath = $_FILES['pdf']['tmp_name'];
$uploadedSize = (int) $_FILES['pdf']['size'];
if ($uploadedSize <= 0 || $uploadedSize > 10 * 1024 * 1024) {
    $status->error = 'Timetable PDF is missing or too large.';
    respond($status);
}

$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedPath);
finfo_close($finfo);
if ($mimeType !== 'application/pdf') {
    $status->error = 'Uploaded file is not a valid PDF.';
    respond($status);
}

// ── Recipients ────────────────────────────────────────────────────────────
$recipients = IntakeTimetableManager::getRecipients($intakeId);
if (empty($recipients)) {
    $status->error = 'There are no active students with an email address in this intake.';
    respond($status);
}

$pdfPath = sys_get_temp_dir() . '/timetable_' . $timetableId . '_' . uniqid() . '.pdf';
if (!move_uploaded_file($uploadedPath, $pdfPath)) {
    $status->error = 'Could not process the uploaded PDF.';
    respond($status);
}

// File name shown to students, e.g. "Timetable-January-2026-Intake.pdf"
$safeName = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $intake['intake']), '-');
$fileName = 'Timetable-' . ($safeName !== '' ? $safeName : $timetableId) . '.pdf';

try {
    $content = EmailNotificationService::build(
        EmailTemplateContent::TIMETABLE_NOTIFICATION,
        [
            'programme'       => $intake['programme'],
            'intake'          => $intake['intake'],
            'timetable_title' => $header['title'],
        ]
    );

    $total = count($recipients);
    $sent  = sendBulkMail(
        $recipients,
        $content['subject'],
        $content['body'],
        50,
        [[$pdfPath, $fileName]]
    );

    IntakeTimetableManager::markNotified($timetableId, $sent, $total, $userId);

    if ($sent === 0) {
        $status->error = 'The email could not be sent. Please try again.';
    } elseif ($sent < $total) {
        $status->done = "Timetable sent to {$sent} of {$total} students. Some batches failed; you can send again.";
    } else {
        $status->done = "Timetable sent to all {$sent} students.";
    }
    $status->sent  = $sent;
    $status->total = $total;
} catch (Throwable $e) {
    error_log('Timetable notification failed: ' . $e->getMessage());
    $status->error = 'The email could not be sent. Please try again.';
} finally {
    if (file_exists($pdfPath)) {
        @unlink($pdfPath);
    }
}

respond($status);
