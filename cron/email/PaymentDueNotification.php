<?php

/**
 * cron/paymentDueNotification.php
 *
 * Sends the "Payment Due Notification" to every student who has an unpaid
 * installment (course_payments.payment_status_payment_status_id = 1)
 * due exactly 5 days from today (Asia/Colombo).
 *
 * One email, students in BCC (via sendBulkMail), in batches of 50.
 * Every run is recorded in PaymentNotificationLog.txt (same folder as this script).
 *
 * Run ONCE a day, e.g. 8:00 AM Sri Lanka time:
 *   0 8 * * * /usr/bin/php /path/to/project/cron/paymentDueNotification.php
 * (If the server clock is UTC, use: 30 2 * * *)
 *
 * Because it matches due_date = today + 5 exactly, each installment is only
 * picked up on one day, so running it once a day never double-sends.
 */

// ── CLI only — block access from the browser ──────────────────────────────
// if (php_sapi_name() !== 'cli') {
//     http_response_code(403);
//     exit('Forbidden');
// }

chdir(__DIR__);

require_once __DIR__ . '/../../includes/connection.php';
require_once __DIR__ . '/../../includes/Project.php';
require_once __DIR__ . '/../../includes/phpmailer/EmailLayoutRenderer.php';
require_once __DIR__ . '/../../includes/phpmailer/EmailNotificationService.php';
require_once __DIR__ . '/../../includes/phpmailer/EmailTemplateContent.php';
require_once __DIR__ . '/../../includes/phpmailer/mail-process.php';   // contains sendBulkMail()

const DAYS_BEFORE_DUE = 5;
const STATUS_NOT_PAID = 1;
const LOG_FILE        = __DIR__ . '/logs/PaymentNotificationLog.txt';

$timezone = new DateTimeZone('Asia/Colombo');
$now      = new DateTime('now', $timezone);
$dueDate  = (clone $now)->modify('+' . DAYS_BEFORE_DUE . ' days')->format('Y-m-d');

/**
 * Append a line to PaymentNotificationLog.txt (and echo it for the console).
 */
function writeLog(string $message): void
{
    $time = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
    $line = '[' . $time . '] ' . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

writeLog('==================== RUN START ====================');
writeLog("Looking for unpaid installments with due_date = {$dueDate}");

// ── Students with an unpaid installment due in 5 days ─────────────────────
// ASSUMPTION: payment_plan_has_students_has_intakes links to students_has_intakes
// through students_has_intakes_students_has_intakes_id. Change that one JOIN
// if your column name is different.
$result = Database::search("
    SELECT
        ld.email,
        CONCAT(ld.first_name, ' ', ld.last_name) AS student_name,
        s.student_id,
        GROUP_CONCAT(cp.course_payments_id ORDER BY cp.course_payments_id) AS installment_ids
    FROM course_payments cp
    INNER JOIN payment_plan_has_students_has_intakes ppsi
        ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
    INNER JOIN students_has_intakes shi
        ON ppsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN students s
        ON shi.students_student_id = s.student_id
    INNER JOIN login_details ld
        ON s.login_details_login_details_id = ld.login_details_id
    WHERE cp.payment_status_payment_status_id = '" . STATUS_NOT_PAID . "'
      AND cp.due_date = '" . $dueDate . "'
    GROUP BY ld.email, student_name, s.student_id
");

if ($result === false || $result === null) {
    writeLog('ERROR: database query failed.');
    writeLog('==================== RUN END (failed) ====================');
    exit(1);
}

$recipients = [];
while ($row = $result->fetch_assoc()) {
    $recipients[$row['email']] = trim($row['student_name']);
    writeLog(sprintf(
        'Queued: student_id=%s | %s <%s> | installment(s): %s',
        $row['student_id'],
        trim($row['student_name']),
        $row['email'],
        $row['installment_ids']
    ));
}

if (empty($recipients)) {
    writeLog("No students with payments due on {$dueDate}. Nothing sent.");
    writeLog('==================== RUN END ====================');
    exit(0);
}

writeLog('Total students queued: ' . count($recipients));

// ── Build and send ─────────────────────────────────────────────────────────
try {
    $content = EmailNotificationService::build(
        EmailTemplateContent::PAYMENT_DUE_NOTIFICATION,
        []
    );
} catch (Throwable $e) {
    writeLog('ERROR: template build failed — ' . $e->getMessage());
    writeLog('==================== RUN END (failed) ====================');
    exit(1);
}

$sent  = sendBulkMail($recipients, $content['subject'], $content['body']);
$total = count($recipients);

if ($sent === $total) {
    writeLog("SUCCESS: email sent to all {$sent} student(s).");
} else {
    writeLog("WARNING: email sent to {$sent} of {$total} student(s). " .
        'Check the PHP error log for the failed batch (sendBulkMail logs it).');
}

writeLog('==================== RUN END ====================');
exit($sent === $total ? 0 : 1);
