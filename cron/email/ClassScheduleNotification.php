<?php

/**
 * cron/classScheduleNotification.php
 *
 * Sends the "Class Schedule Notification" to every student who has a lecture
 * in time_table dated exactly 2 days from today (Asia/Colombo).
 *
 * Which students get it — based on the most specific level set on the lecture:
 *   intakes_intake_id set        → students in that intake
 *   else programmes_programme_id → students in any intake of that programme
 *   else faculties_faculty_id    → students in any intake of that faculty
 *   else branches_branch_id      → students in any intake of that branch
 *
 * One email, students in BCC (via sendBulkMail), in batches of 50.
 * A student with several lectures that day gets only ONE email.
 * Every run is recorded in ClassScheduleNotificationLog.txt (same folder).
 *
 * Run ONCE a day, e.g. 8:00 AM Sri Lanka time:
 *   0 8 * * * /usr/bin/php /path/to/project/cron/classScheduleNotification.php
 * (If the server clock is UTC, use: 30 2 * * *)
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

const DAYS_BEFORE_CLASS = 2;

// ASSUMPTION: time_table_status_time_table_status_id = 1 means an active /
// scheduled lecture (not cancelled). Change this if your status IDs differ.
const ACTIVE_LECTURE_STATUS_ID = 1;

const LOG_FILE = __DIR__ . '/logs/ClassScheduleNotificationLog.txt';

$timezone  = new DateTimeZone('Asia/Colombo');
$now       = new DateTime('now', $timezone);
$classDate = (clone $now)->modify('+' . DAYS_BEFORE_CLASS . ' days')->format('Y-m-d');

/**
 * Append a line to ClassScheduleNotificationLog.txt (and echo it for the console).
 */
function writeLog(string $message): void
{
    $time = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
    $line = '[' . $time . '] ' . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

writeLog('==================== RUN START ====================');
writeLog("Looking for lectures on {$classDate}");

// ── Lectures on that date (logged for traceability) ───────────────────────
$lectures = Database::search("
    SELECT
        tt.time_table_id,
        tt.start_time,
        tt.end_time,
        tt.lecture_title,
        tt.branches_branch_id,
        tt.faculties_faculty_id,
        tt.programmes_programme_id,
        tt.intakes_intake_id
    FROM time_table tt
    WHERE tt.date = '" . $classDate . "'
      AND tt.time_table_status_time_table_status_id = '" . ACTIVE_LECTURE_STATUS_ID . "'
    ORDER BY tt.start_time
");

if ($lectures === false || $lectures === null) {
    writeLog('ERROR: lecture query failed.');
    writeLog('==================== RUN END (failed) ====================');
    exit(1);
}

if ($lectures->num_rows == 0) {
    writeLog("No lectures scheduled on {$classDate}. Nothing sent.");
    writeLog('==================== RUN END ====================');
    exit(0);
}

while ($lec = $lectures->fetch_assoc()) {
    writeLog(sprintf(
        'Lecture: time_table_id=%s | %s-%s | %s | branch=%s faculty=%s programme=%s intake=%s',
        $lec['time_table_id'],
        $lec['start_time'] ?? '-',
        $lec['end_time'] ?? '-',
        $lec['lecture_title'] ?: '(no title)',
        $lec['branches_branch_id'] ?? '-',
        $lec['faculties_faculty_id'] ?? '-',
        $lec['programmes_programme_id'] ?? '-',
        $lec['intakes_intake_id'] ?? '-'
    ));
}

// ── Students who belong to any of those lectures ──────────────────────────
$result = Database::search("
    SELECT
        ld.email,
        CONCAT(ld.first_name, ' ', ld.last_name) AS student_name,
        s.student_id,
        GROUP_CONCAT(DISTINCT tt.time_table_id ORDER BY tt.time_table_id) AS lecture_ids
    FROM time_table tt
    INNER JOIN subjects sub ON tt.subjects_subject_id = sub.subject_id
    INNER JOIN levels    l ON sub.levels_level_id          = l.level_id
    INNER JOIN students_has_intakes shi ON shi.intakes_intake_id       = l.intakes_intake_id
    INNER JOIN students s ON shi.students_student_id = s.student_id
    INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
    WHERE tt.date = '" . $classDate . "'
      AND tt.time_table_status_time_table_status_id = '" . ACTIVE_LECTURE_STATUS_ID . "'
      AND shi.status_status_id = '1'
      AND ld.status_status_id = '1'
      AND ld.email IS NOT NULL
      AND ld.email <> ''
    GROUP BY ld.email, s.student_id
");

if ($result === false || $result === null) {
    writeLog('ERROR: student query failed.');
    writeLog('==================== RUN END (failed) ====================');
    exit(1);
}

$recipients = [];
while ($row = $result->fetch_assoc()) {
    $recipients[$row['email']] = trim($row['student_name']);
    writeLog(sprintf(
        'Queued: student_id=%s | %s <%s> | lecture(s): %s',
        $row['student_id'],
        trim($row['student_name']),
        $row['email'],
        $row['lecture_ids']
    ));
}

if (empty($recipients)) {
    writeLog("Lectures found on {$classDate}, but no matching students. Nothing sent.");
    writeLog('==================== RUN END ====================');
    exit(0);
}

writeLog('Total students queued: ' . count($recipients));

// ── Build and send ─────────────────────────────────────────────────────────
try {
    $content = EmailNotificationService::build(
        EmailTemplateContent::CLASS_SCHEDULE_NOTIFICATION,
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
