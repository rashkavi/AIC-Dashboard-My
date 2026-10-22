<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

// For the class cancellation email
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/mail-process.php");   // contains sendBulkMail()

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $lecture_id = isset($_POST['lecture_id']) ? trim($_POST['lecture_id']) : 0;
            $programme_id = isset($_POST['programme_id']) ? trim($_POST['programme_id']) : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $lecturer_id = isset($_POST['lecturer_id']) ? trim($_POST['lecturer_id']) : 0;
            $lecture_date = isset($_POST['lecture_date']) ? trim($_POST['lecture_date']) : '';
            $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
            $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
            $time_table_status = isset($_POST['status']) ? trim($_POST['status']) : 0;
            $physical = isset($_POST['physical']) ? trim($_POST['physical']) : false;
            $online = isset($_POST['online']) ? trim($_POST['online']) : false;
            $hybrid = isset($_POST['hybrid']) ? trim($_POST['hybrid']) : false;
            $recording = isset($_POST['recording']) ? trim($_POST['recording']) : false;
            $link = isset($_POST['link']) ? trim($_POST['link']) : '';
            $confirmation = isset($_POST['confirmation']) && $_POST['confirmation'] === 'true' ? true : false;
            $edit_type = isset($_POST['edit_type']) ? trim($_POST['edit_type']) : '';

            if (empty($lecture_id) || $lecture_id == 0) {
                $status->error = "Invalid lecture Id";
            } else {
                $isValidLecture = Database::search("SELECT * FROM `time_table`
                INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                WHERE `time_table_id`='" . $lecture_id . "'");
                if ($isValidLecture->num_rows == 0) {
                    $status->error = "Invalid lecture Id2";
                } else {
                    $lectureData = $isValidLecture->fetch_assoc();
                    $subject_id = $lectureData['subjects_subject_id'];

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0) || UserPermissions::validPermisisonType('course_overview', 0))) {
                        $status->error = "You are not authorized to this action.";
                    } else {

                        if (empty($title)) {
                            $status->title = 'Please enter a title';
                        } else if (strlen($title) > 100) {
                            $status->title = 'Title should be less than 100 characters';
                        }

                        if ($lecturer_id == 0) {
                            $status->lecturer = 'Please select a lecturer';
                        } else if ($lecturer_id != 0) {

                            $query = "SELECT * FROM `lecturers` 
                            INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                            WHERE `lecturers_id` = '" . $lecturer_id . "' 
                            ";

                            $isValidLecturer = Database::search($query);
                            if ($isValidLecturer->num_rows == 0) {
                                $status->lecturer = 'This lecturer is not assigned to this subject';
                            } else {
                                $lecturerData = $isValidLecturer->fetch_assoc();
                            }
                        }

                        if (empty($lecture_date)) {
                            $status->date = 'Please select a lecture date';
                        }
                        if (empty($start_time)) {
                            $status->start_time = 'Please select a start time';
                        }
                        if (empty($end_time)) {
                            $status->end_time = 'Please select an end time';
                        }

                        if (($physical === false) && ($online === false) && ($recording === false) && ($hybrid === false)) {
                            $status->error = "Please select lecture type";
                        }

                        if ((($online === true) || ($hybrid === true) || ($recording === true)) && empty($link)) {
                            $status->link = 'Please enter a link';
                        }

                        if ($time_table_status == 0) {
                            $status->time_table_status = 'Please select a time table status';
                        }

                        $tutes = array();

                        if (isset($_FILES["tutes"])) {
                            $allowedTypes = [
                                "image/jpeg" => ".jpg",
                                "image/jpg" => ".jpg",
                                "image/png" => ".png",
                                "image/gif" => ".gif",
                                "image/webp" => ".webp",
                                "image/bmp" => ".bmp",
                                "image/svg+xml" => ".svg",
                                "application/pdf" => ".pdf",
                                "application/msword" => ".doc",
                                "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => ".docx",
                                "application/zip" => ".zip",
                                "application/x-zip-compressed" => ".zip",
                                "application/x-rar-compressed" => ".rar",
                                "application/vnd.ms-powerpoint" => ".ppt",
                                "application/vnd.openxmlformats-officedocument.presentationml.presentation" => ".pptx"
                            ];

                            foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                                $fileType = $_FILES["tutes"]["type"][$key];

                                if (!array_key_exists($fileType, $allowedTypes)) {
                                    $status->file = "File '$tute_name' is not a valid type.Only PDF, DOC, DOCX, ZIP, RAR, PPT, and PPTX files are allowed";
                                } else if (strlen($tute_name) > 45) {
                                    $status->tute[] = "File '$tute_name' exceeds the maximum allowed character limit of 45.";
                                } else {

                                    if ($_FILES["tutes"]["error"][$key] === 0) {
                                        if ($_FILES["tutes"]["size"][$key] <= 25 * 1024 * 1024) {
                                            $tute = uniqid() . $allowedTypes[$fileType];
                                            array_push($tutes, $tute);
                                        } else {
                                            $status->tute[] = "File '$tute_name' exceeds the maximum allowed size of 25MB. Please compress (ZIP) the file before uploading.";
                                        }
                                    } else {
                                        $status->tute[] = "There was an error uploading the file '$tute_name'.";
                                    }
                                }
                            }
                        }

                        if ($confirmation == false && $edit_type == 1) {
                            if ($lecturer_id != 0 && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {

                                $isAvailableLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                                                            INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                                            WHERE `lecturers`.`users_user_id` = '" . $lecturerData['users_user_id'] . "'
                                                                            AND `time_table`.`date` = '" . $lecture_date . "'
                                                                            AND ((`time_table`.`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                            OR (`time_table`.`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))");

                                $row = $isAvailableLecturer->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->confirm_required = true;
                                    $status->lecturer_confirm = 'The selected time is unavailable for this lecturer.';
                                }
                            }

                            if (!empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                                $isAvailableSelectTimeInIntake = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                            INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                                      WHERE `intakes`.`intake_id` = '" . $lectureData['intakes_intake_id'] . "'
    
                                                                                      AND `date` = '" . $lecture_date . "'
                                                                                      AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                      OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                                                                                                     ");
                                $row = $isAvailableSelectTimeInIntake->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->confirm_required = true;
                                    $status->date_confirm = 'The selected time has already been scheduled for a lecture in this Intake.';
                                }
                            }
                        }

                        if (empty(get_object_vars($status))) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $addTime = $dateTime->format('Y-m-d H:i:s');
                            $today   = $dateTime->format('Y-m-d');

                            $start = new DateTime($start_time);
                            $end = new DateTime($end_time);

                            $interval = $start->diff($end);

                            $duration = ($interval->h * 60) + $interval->i;

                            // ── Cancellation email setup ──────────────────────────
                            // Email goes out only when the status is CHANGED to "Cancelled"
                            // (not when an already-cancelled lecture is edited again),
                            // and only for lectures dated today or later.
                            $cancelledStatusId   = getCancelledStatusId();
                            $isCancelling        = ($cancelledStatusId > 0 && (int)$time_table_status === $cancelledStatusId);
                            $isUpcoming          = ($lecture_date >= $today);
                            $cancelledIntakeIds  = [];

                            $time_table_update_query = "`lecturers_lecturers_id`='" . $lecturer_id . "',`time_table_status_time_table_status_id`='" . $time_table_status . "'
                            ,`date`='" . $lecture_date . "',`start_time`='" . $start_time . "',`end_time`='" . $end_time . "',`duration`='" . $duration . "',`lecture_title`='" . $title . "'";

                            if (filter_var($physical, FILTER_VALIDATE_BOOLEAN)) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='1',`lecture`=''";
                            }

                            if (filter_var($online, FILTER_VALIDATE_BOOLEAN)) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='2',`lecture`='" . $link . "'";
                            }

                            if (filter_var($recording, FILTER_VALIDATE_BOOLEAN)) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='3',`lecture`='" . $link . "'";
                            }

                            if (filter_var($hybrid, FILTER_VALIDATE_BOOLEAN)) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='4',`lecture`='" . $link . "'";
                            }

                            if ($edit_type == 1) {

                                try {
                                    Database::iud("UPDATE `time_table` SET " . $time_table_update_query . " WHERE `time_table_id` = '" . $lecture_id . "'");

                                    if (isset($_FILES["tutes"])) {
                                        for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {

                                            $originalFileName = $_FILES["tutes"]["name"][$i];

                                            $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                            $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $lecture_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                            Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                            $tute_path = "../assets/uploads/lectures-attachments/" . $tutes[$i];
                                            move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                                        }
                                    }

                                    // Newly cancelled → notify this lecture's intake
                                    $previousStatusId = (int)$lectureData['time_table_status_time_table_status_id'];
                                    if ($isCancelling && $isUpcoming && $previousStatusId !== $cancelledStatusId) {
                                        $cancelledIntakeIds[] = (int)$lectureData['intake_id'];
                                    }

                                    $status->done = "Lecture has been successfully updated";
                                } catch (Exception $e) {
                                    $status->error = "Something went wrong";
                                }
                            } else {

                                $query = "SELECT * FROM `time_table`
                                    INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                    INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                    WHERE `date`='" . $lectureData['date'] . "' AND `start_time`='" . $lectureData['start_time'] . "'
                                    AND `end_time`='" . $lectureData['end_time'] . "'
                                    AND `lecturers`.`users_user_id`='" . $lecturerData['user_id'] . "'
                                    ";

                                if ($programme_id != 0) {
                                    $query .= " AND `programmes_programme_id`='" . $programme_id . "'";
                                }


                                $lectures = Database::search($query);

                                if ($lectures->num_rows > 0) {
                                    $attchments = [];
                                    $tutesNames = [];

                                    if (isset($_FILES["tutes"])) {
                                        for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {

                                            $originalFileName = $_FILES["tutes"]["name"][$i];
                                            $tute_path = "../assets/uploads/lectures-attachments/" . $tutes[$i];
                                            move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);

                                            $tutesNames[] = $originalFileName;
                                        }
                                    }
                                    while ($lecturesData = $lectures->fetch_assoc()) {

                                        $time_table_update_query = "`lecturers_lecturers_id`='" . $lectureData['lecturers_lecturers_id'] . "',`time_table_status_time_table_status_id`='" . $time_table_status . "'
                            ,`date`='" . $lecture_date . "',`start_time`='" . $start_time . "',`end_time`='" . $end_time . "',`duration`='" . $duration . "',`lecture_title`='" . $title . "'";

                                        if (filter_var($physical, FILTER_VALIDATE_BOOLEAN)) {
                                            $time_table_update_query .= ",`lecture_type_lecture_type_id`='1',`lecture`=''";
                                        }

                                        if (filter_var($online, FILTER_VALIDATE_BOOLEAN)) {
                                            $time_table_update_query .= ",`lecture_type_lecture_type_id`='2',`lecture`='" . $link . "'";
                                        }

                                        if (filter_var($recording, FILTER_VALIDATE_BOOLEAN)) {
                                            $time_table_update_query .= ",`lecture_type_lecture_type_id`='3',`lecture`='" . $link . "'";
                                        }
                                        if (filter_var($hybrid, FILTER_VALIDATE_BOOLEAN)) {
                                            $time_table_update_query .= ",`lecture_type_lecture_type_id`='4',`lecture`='" . $link . "'";
                                        }
                                        Database::iud("UPDATE `time_table` SET " . $time_table_update_query . " WHERE `time_table_id` = '" . $lecturesData['time_table_id'] . "'");

                                        for ($i = 0; $i < count($tutesNames); $i++) {
                                            $tute_name = $tutesNames[$i];
                                            $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                            $attachment_insert_values = "'" . $tutes[$i] . "','" . $tute_name . "','" . $lecturesData['time_table_id'] . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                            Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");
                                        }

                                        // Newly cancelled → notify each affected intake
                                        $previousStatusId = (int)$lecturesData['time_table_status_time_table_status_id'];
                                        if ($isCancelling && $isUpcoming && $previousStatusId !== $cancelledStatusId) {
                                            $cancelledIntakeIds[] = (int)$lecturesData['intake_id'];
                                        }
                                    }

                                    $status->done = "Lectures has been successfully updated";
                                } else {
                                    $status->error = "Lecture Not Updated";
                                }
                            }

                            // ── Send the cancellation email (never blocks the update) ──
                            if (!empty($cancelledIntakeIds) && isset($status->done)) {
                                $status->email = sendLectureCancellationEmail($cancelledIntakeIds);
                            }
                        }
                    }
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


function checkIfFutureDateTime($lecture_date, $start_time)
{
    if ($lecture_date && $start_time) {
        $startDateTime = new DateTime("$lecture_date $start_time");
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

/**
 * Looks up the "Cancelled" row in time_table_status by name,
 * so no status ID is hard-coded. Returns 0 if not found.
 */
function getCancelledStatusId(): int
{
    $r = Database::search("
        SELECT `time_table_status_id`
        FROM `time_table_status`
        WHERE LOWER(`time_table_status`) LIKE 'cancel%'
        LIMIT 1
    ");
    return ($r && $r->num_rows > 0) ? (int)$r->fetch_assoc()['time_table_status_id'] : 0;
}

/**
 * Sends the Class Cancellation Notification (BCC, via sendBulkMail) to every
 * student in the given intakes. Each student gets ONE email even if several
 * of their lectures were cancelled in the same update.
 * Returns a short status message for the JSON response; never throws.
 */
function sendLectureCancellationEmail(array $intakeIds): string
{
    try {
        $intakeIds = array_values(array_unique(array_filter(array_map('intval', $intakeIds))));
        if (empty($intakeIds)) {
            return 'No intake found for cancellation email';
        }

        $students = Database::search("
            SELECT DISTINCT
                ld.email,
                CONCAT(ld.first_name, ' ', ld.last_name) AS student_name
            FROM students_has_intakes shi
            INNER JOIN students      s  ON shi.students_student_id          = s.student_id
            INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            WHERE shi.intakes_intake_id IN (" . implode(',', $intakeIds) . ")
            AND shi.status_status_id = '1'
            AND ld.status_status_id = '1'
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
            EmailTemplateContent::CLASS_CANCELLATION_NOTIFICATION,
            []
        );

        $sent = sendBulkMail($recipients, $content['subject'], $content['body']);

        return "Cancellation email sent to {$sent} of " . count($recipients) . " student(s)";
    } catch (Throwable $e) {
        error_log('Lecture cancellation email failed: ' . $e->getMessage());
        return 'Lecture updated, but the cancellation email could not be sent';
    }
}
