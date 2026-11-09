<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Project.php");

require_once("../includes/UserPermissionsClass.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/mail-process.php");   // contains sendBulkMail()

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $subject_ids = isset($_POST['subject_ids']) ? $_POST['subject_ids'] : [];
            $code = isset($_POST['code']) ? $_POST['code'] : '';
            $topic = isset($_POST['topic']) ? $_POST['topic'] : '';
            $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
            $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
            $plagiarism_report = isset($_POST['plagiarism_report']) ? $_POST['plagiarism_report'] : 0;
            $attachment = '';
            $extra = ['late' => null, 'recorrection' => null, 'fee' => null];

            if (empty($subject_ids) || !is_array($subject_ids)) {
                $status->subjects = 'Please select at least one subject.';
            } else {
                // Normalize to a de-duped list of ints
                $subject_ids = array_values(array_unique(array_filter(array_map('intval', $subject_ids))));
                if (empty($subject_ids)) {
                    $status->subjects = 'Please select at least one valid subject.';
                }
            }

            $validSubjects = []; // subject_id => subject/level/intake row

            if (!property_exists($status, 'subjects')) {
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                foreach ($subject_ids as $subject_id) {
                    $isValidSubject = Database::search("SELECT * FROM `subjects` 
                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                    WHERE `subject_id` = '" . $subject_id . "'");

                    if ($isValidSubject->num_rows == 0) {
                        $status->subjects = 'One or more selected subjects are invalid.';
                        break;
                    }

                    $subjectData = $isValidSubject->fetch_assoc();

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 2, $subject_id))) {
                        $status->error = "You are not authorized to add assignments for one or more of the selected subjects.";
                        break;
                    }

                    $validSubjects[$subject_id] = $subjectData;
                }
            }

            if (!property_exists($status, 'subjects') && !property_exists($status, 'error')) {
                $timezone = new DateTimeZone("Asia/Colombo");
                $dateTime = new DateTime("now", $timezone);
                $addTime = $dateTime->format('Y-m-d H:i:s');
                $today = $dateTime->format('Y-m-d');

                if (empty($code)) {
                    $status->code = 'Assignment code is required';
                } else if (strlen($code) > 45) {
                    $status->code = 'Assignment code should be less than 45 characters';
                } else {
                    // Code must be unique per intake — the selected subjects may span several intakes,
                    // so every distinct intake among them needs to be checked.
                    $intakeIds = array_values(array_unique(array_map(function ($s) {
                        return $s['intakes_intake_id'];
                    }, $validSubjects)));

                    foreach ($intakeIds as $intakeId) {
                        $isValidCode = Database::search("SELECT COUNT(*) as 'count' FROM `assignments` 
                        INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                        WHERE `assignment_code` = '" . $code . "'
                        AND `intakes_intake_id` = '" . $intakeId . "'");
                        $row = $isValidCode->fetch_assoc();
                        if ($row['count'] > 0) {
                            $status->code = 'Assignment code already exists for one or more of the selected intakes';
                            break;
                        }
                    }
                }

                if (empty($topic)) {
                    $status->topic = 'Assignment topic is required';
                }
                if (!empty($start_date) || !empty($start_time) || !empty($end_date) || !empty($end_time)) {
                    if (empty($start_date)) {
                        $status->start_date = 'Select a start date.';
                    }
                    if (empty($start_time)) {
                        $status->start_time = 'Select a start time.';
                    }
                    if (empty($end_date)) {
                        $status->end_date = 'Select an end date.';
                    }
                    if (empty($end_time)) {
                        $status->end_time = 'Select an end time.';
                    }
                }
                if (!empty($start_date) || !empty($start_time)) {
                    if (empty($start_date)) {
                        $status->start_date = 'A start date is required for the selected start time.';
                    }
                    if (empty($start_time)) {
                        $status->start_time = 'A start time is required for the selected start date.';
                    }
                    if (!property_exists($status, 'start_date') && !property_exists($status, 'start_time')) {
                        if (!checkIfFutureDateTime($start_date, $start_time)) {
                            $status->start_date = 'The selected start date and time is in the past.';
                        }
                    }
                }
                if (!empty($end_date) || !empty($end_time)) {
                    if (empty($end_date)) {
                        $status->end_date = 'An end date is required for the selected end time.';
                    }
                    if (empty($end_time)) {
                        $status->end_time = 'An end time is required for the selected end date.';
                    }
                    if (!property_exists($status, 'end_date') && !property_exists($status, 'end_time')) {
                        if (!checkIfFutureDateTime($end_date, $end_time)) {
                            $status->end_date = 'The selected end date and time is in the past.';
                        }
                    }
                }
                if (!empty($start_date) && (new DateTime($start_date))->format('Y-m-d') === $today) {
                    if (!isset($_FILES["file"])) {
                        $status->file = 'Schedule the assignment for today and make sure to attach the necessary document.';
                    }
                }
                if (!empty($start_date) && !empty($end_date)) {
                    if (!isDateAfter($start_date, $end_date)) {
                        $status->end_date = "The selected end date is not matching";
                    }
                }
                if (empty($plagiarism_report)) {
                    $status->plagiarism_report = 'Please select a plagiarism report status';
                }

                // ---- Late submission + recorrection (dates + fee) ----
                $extra = parseLateAndRecorrection($status, $_POST, $end_date, $end_time);

                if (isset($_FILES["file"])) {
                    $allowed_file_extensions = array("application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
                    $file_ex = $_FILES["file"]["type"];
                    if (!in_array($file_ex, $allowed_file_extensions)) {
                        $status->file = "Only PDF, DOC, and DOCX files are allowed";
                    } else {
                        $new_file_extension = '';
                        switch ($file_ex) {
                            case "application/pdf":
                                $new_file_extension = ".pdf";
                                break;
                            case "application/msword":
                                $new_file_extension = ".doc";
                                break;
                            case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                $new_file_extension = ".docx";
                                break;
                        }
                        if ($_FILES["file"]["error"] === 0) {
                            if ($_FILES["file"]["size"] <= 10 * 1024 * 1024) {
                                $attachment = uniqid() . $new_file_extension;
                            } else {
                                $status->file = "Maximum allowed file size is 10MB";
                            }
                        } else {
                            $status->file = "There is an error with the selected file file";
                        }
                    }
                }

                if (empty(get_object_vars($status))) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $date = $dateTime->format('Y-m-d H:i:s');

                    $start = '';
                    $end = '';
                    if (!empty($start_date) && !empty($end_date)) {
                        $start = (new DateTime("$start_date $start_time"))->format('Y-m-d H:i');
                        $end = (new DateTime("$end_date $end_time"))->format('Y-m-d H:i');
                    }

                    $createdCount = 0;
                    foreach ($validSubjects as $subject_id => $subjectData) {
                        $assignment_table_insert_columns = "`assignment_code`,`assignment`,`added_date`,`users_user_id`,`subjects_subject_id`,`plagiarism_status_plagiarism_status_id`,`assignments_order`";
                        $assignment_table_insert_values = "'" . $code . "','" . $topic . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "','" . $subject_id . "','" . $plagiarism_report . "','0'";

                        if (!empty($start) && !empty($end)) {
                            $assignment_table_insert_columns .= ",`start_date`,`end_date`";
                            $assignment_table_insert_values .= ",'" . $start . "','" . $end . "'";
                        }

                        $isScheduled = (!empty($start_date) && !empty($end_date) && isset($_FILES["file"]));

                        if ($isScheduled) {
                            $assignment_table_insert_columns .= ",`assignment_status_assignment_status_id`";
                            $assignment_table_insert_values .= ",'2'";
                        } else {
                            $assignment_table_insert_columns .= ",`assignment_status_assignment_status_id`";
                            $assignment_table_insert_values .= ",'1'";
                        }

                        // Late submission end + recorrection apply end (nullable columns)
                        appendLateAndRecorrection($assignment_table_insert_columns, $assignment_table_insert_values, $extra);

                        if (isset($_FILES["file"])) {
                            $assignment_table_insert_columns .= ",`attachment`";
                            $assignment_table_insert_values .= ",'" . $attachment . "'";
                        }

                        Database::iud("INSERT INTO `assignments` (" . $assignment_table_insert_columns . ") VALUES (" . $assignment_table_insert_values . ")");
                        $assignmentId = Database::$connection->insert_id;

                        $assignmnet_log_table_insert_columns = "`users_user_id`,`assignments_assignment_id`,`log`,`date_time`";
                        $assignmnet_log_table_insert_values = "'" . $sessionUserObject['user_id'] . "','" . $assignmentId . "','Add Assignmnet','" . $date . "'";
                        Database::iud("INSERT INTO `assignments_logs` (" . $assignmnet_log_table_insert_columns . ") VALUES (" . $assignmnet_log_table_insert_values . ")");

                        // Recorrection fee goes in its own table, one row per created assignment
                        insertRecorrectionFee($extra, (int)$assignmentId, (int)$sessionUserObject['user_id'], $date);

                        if ($isScheduled) {
                            $status->email = sendNewAssignmentEmail(
                                (int)$subjectData['intake_id'],
                                (int)$assignmentId,
                                (int)$sessionUserObject['user_id']
                            );
                        }

                        $createdCount++;
                    }

                    // The uploaded file is shared across the whole batch — move it once and every
                    // created row above already references the same $attachment filename.
                    if (isset($_FILES["file"]) && !empty($attachment)) {
                        $qcv_path = "../assets/uploads/assignments-attachments/" . $attachment;
                        move_uploaded_file($_FILES["file"]["tmp_name"], $qcv_path);
                    }

                    $status->done = $createdCount . " assignment(s) added successfully!";
                }
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}
echo (json_encode($status));

function checkIfFutureDateTime($start_date, $start_time)
{
    if ($start_date && $start_time) {
        $startDateTime = new DateTime("$start_date $start_time");
        $now = new DateTime();
        $isToday = $startDateTime->format('Y-m-d') === $now->format('Y-m-d');
        if (($isToday && $startDateTime > $now) || $startDateTime > $now) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function isDateAfter($startDate, $endDate)
{
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);

    return $end > $start;
}

/**
 * Validates late-submission + recorrection inputs.
 * Writes errors onto $status, returns values ready for INSERT (null = not provided).
 */
function parseLateAndRecorrection(stdClass $status, array $post, string $end_date, string $end_time): array
{
    $late_date = trim($post['late_date'] ?? '');
    $late_time = trim($post['late_time'] ?? '');
    $rec_date  = trim($post['recorrection_date'] ?? '');
    $rec_time  = trim($post['recorrection_time'] ?? '');
    $rec_fee   = trim($post['recorrection_fee'] ?? '');

    $out = ['late' => null, 'recorrection' => null, 'fee' => null];

    $endDT  = (!empty($end_date) && !empty($end_time)) ? new DateTime("$end_date $end_time") : null;
    $lateDT = null;

    // ---- Late submission ----
    if ($late_date !== '' || $late_time !== '') {
        if ($late_date === '') {
            $status->late_date = 'A late submission date is required for the selected time.';
        }
        if ($late_time === '') {
            $status->late_time = 'A late submission time is required for the selected date.';
        }
        if (!isset($status->late_date) && !isset($status->late_time)) {
            if ($endDT === null) {
                $status->late_date = 'Set the assignment end date & time before adding a late submission date.';
            } else {
                $lateDT = new DateTime("$late_date $late_time");
                if ($lateDT <= $endDT) {
                    $status->late_date = 'Late submission end must be after the assignment end date & time.';
                } else {
                    $out['late'] = $lateDT->format('Y-m-d H:i');
                }
            }
        }
    }

    // ---- Recorrection ----
    if ($rec_date !== '' || $rec_time !== '') {
        if ($rec_date === '') {
            $status->recorrection_date = 'A recorrection end date is required for the selected time.';
        }
        if ($rec_time === '') {
            $status->recorrection_time = 'A recorrection end time is required for the selected date.';
        }
        if (!isset($status->recorrection_date) && !isset($status->recorrection_time)) {
            $baseDT = $lateDT ?? $endDT;
            if ($baseDT === null) {
                $status->recorrection_date = 'Set the assignment end date & time before adding a recorrection date.';
            } else {
                $recDT = new DateTime("$rec_date $rec_time");
                if ($recDT <= $baseDT) {
                    $status->recorrection_date = $lateDT
                        ? 'Recorrection end must be after the late submission end.'
                        : 'Recorrection end must be after the assignment end date & time.';
                } else {
                    $out['recorrection'] = $recDT->format('Y-m-d H:i');
                }
            }
        }
    }

    // ---- Recorrection fee ----
    if ($rec_fee !== '') {
        if (!is_numeric($rec_fee) || (float)$rec_fee < 0) {
            $status->recorrection_fee = 'Recorrection fee must be a valid non-negative number.';
        } else if ((float)$rec_fee > 0 && $out['recorrection'] === null && !isset($status->recorrection_date)) {
            $status->recorrection_date = 'Set a recorrection end date & time for the fee.';
        } else {
            $out['fee'] = round((float)$rec_fee, 2);
        }
    }

    return $out;
}

/** Appends the optional date columns/values to the assignments INSERT strings. */
function appendLateAndRecorrection(string &$cols, string &$vals, array $extra): void
{
    if ($extra['late'] !== null) {
        $cols .= ",`late_submission_end_date`";
        $vals .= ",'" . $extra['late'] . "'";
    }
    if ($extra['recorrection'] !== null) {
        $cols .= ",`recorrection_apply_end_date`";
        $vals .= ",'" . $extra['recorrection'] . "'";
    }
}

/** Inserts the recorrection fee row for a newly created assignment (no-op when no fee was given). */
function insertRecorrectionFee(array $extra, int $assignmentId, int $userId, string $dateTime): void
{
    if ($extra['fee'] === null) {
        return;
    }
    Database::iud("INSERT INTO `assignment_recorrection_fee`
        (`recorrection_fee`,`assignments_assignment_id`,`users_user_id`,`date_added`)
        VALUES ('" . $extra['fee'] . "','" . $assignmentId . "','" . $userId . "','" . $dateTime . "')");
}

function sendNewAssignmentEmail(int $intakeId, int $assignmentId, int $userId): string
{
    try {
        if ($intakeId <= 0) {
            return 'No intake found for assignment email';
        }

        $students = Database::search("
            SELECT DISTINCT
                ld.email,
                CONCAT(ld.first_name, ' ', ld.last_name) AS student_name
            FROM students_has_intakes shi
            INNER JOIN students      s  ON shi.students_student_id          = s.student_id
            INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            WHERE shi.intakes_intake_id = '" . $intakeId . "'
              AND ld.email IS NOT NULL
              AND ld.email <> ''
        ");

        $recipients = [];
        if ($students) {
            while ($row = $students->fetch_assoc()) {
                $recipients[$row['email']] = trim($row['student_name']);
            }
        }

        if (empty($recipients)) {
            return 'No students found to notify';
        }

        $content = EmailNotificationService::build(
            EmailTemplateContent::NEW_ASSIGNMENT_NOTIFICATION,
            []
        );

        $sent  = sendBulkMail($recipients, $content['subject'], $content['body']);
        $total = count($recipients);

        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        Database::iud("
            INSERT INTO `assignments_logs` (`users_user_id`,`assignments_assignment_id`,`log`,`date_time`)
            VALUES ('" . $userId . "','" . $assignmentId . "',
                    'New assignment email sent to " . $sent . " of " . $total . " student(s)','" . $now . "')
        ");

        return "Assignment email sent to {$sent} of {$total} student(s)";
    } catch (Throwable $e) {
        error_log('New assignment email failed: ' . $e->getMessage());
        return 'Assignment added, but the notification email could not be sent';
    }
}
