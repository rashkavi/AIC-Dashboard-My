<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d');
                    $nowTime = $today->format('H:i:s');

                    $lectureId = isset($decoded['lectureId']) ? $decoded['lectureId'] : '';
                    $duration = isset($decoded['duration']) ? $decoded['duration'] : '';
                    $amount = isset($decoded['amount']) ? $decoded['amount'] : '';
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if (empty($lectureId) || $lectureId == 0) {
                        $status->error = 'Lecture is required';
                    } else {
                        $query = "SELECT 
                        tt.time_table_id,
                        tt.date,
                        tt.start_time,
                        tt.end_time,
                        lecturers.lecturers_id,
                        title.title,
                        users.user_id,
                        users.user_fname,
                        users.user_lname,
                        users.profile_img,
                        users.user_id,
                        lecture_type.lecture_type,
                          lecture_type.lecture_type_id,
                        tt.lecture_title,
                        tt.duration,
                        COUNT(tt.subjects_subject_id) AS batch_count,
                        `lecturer_payments`.`earning_status_earning_status_id`,
                        `earning_status`.`earning_status`,
                        `lecturers`.`hourly_rate`
                    FROM time_table AS tt
                    INNER JOIN `lecturers`  ON `lecturers`.`lecturers_id` = `tt`.`lecturers_lecturers_id`
                    INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
                    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
                    INNER JOIN `lecture_type` ON `lecture_type`.`lecture_type_id` = `tt`.`lecture_type_lecture_type_id`
                    LEFT JOIN `lecturer_payments_has_lectures` ON `lecturer_payments_has_lectures`.`time_table_time_table_id` = `tt`.`time_table_id`
                    LEFT JOIN `lecturer_payments` ON `lecturer_payments`.`lecturer_payments_id` = `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`
                    LEFT JOIN `earning_status` ON `earning_status`.`earning_status_id` = `lecturer_payments`.`earning_status_earning_status_id`
                    WHERE tt.time_table_status_time_table_status_id = '1'
                    AND tt.lecture_type_lecture_type_id != 3
                    AND tt.time_table_id='" . $lectureId . "'
                    GROUP BY 
                        tt.date,
                        tt.start_time,
                        tt.end_time,
                        tt.lecturers_lecturers_id
                   ";

                        $isValidLecture = Database::search($query);
                        if ($isValidLecture->num_rows == 0) {
                            $status->error = 'Lecture not found';
                        } else {
                            $lectureData = $isValidLecture->fetch_assoc();

                            if ($lectureData['date'] > $todayDate && $lectureData['date'] != $todayDate) {
                                $status->error = 'Cannot reject payment for future lecture';
                            } else if ($lectureData['date'] == $todayDate) {
                                if ($lectureData['end_time'] < $nowTime) {
                                    $status->error = 'Cannot reject payment for future lecture';
                                }
                            }
                        }
                    }

                    if (empty($duration) || $duration == 0) {
                        $status->duration = 'Duration is required';
                    }


                    $requiredProperties = [
                        'lectureId',
                        'start',
                        'end',
                        'amount',
                        'error',
                        'duration'
                    ];
                    $allPropertiesExist = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allPropertiesExist = false;
                            break;
                        }
                    }

                    if ($allPropertiesExist) {

                        if (empty($amount)) {
                            $amount = 0;
                        }

                        $payment_col = "`users_user_id`,`amount`,`duration`,`earning_status_earning_status_id`,`date_added`";
                        $payment_val = "'" . $lectureData['user_id'] . "','" . $amount . "','" . $duration . "','3','" . $today->format('Y-m-d H:i:s') . "'";

                        if (!empty($note)) {
                            $payment_col .= ", `note`";
                            $payment_val .= ", '" . Database::escape($note) . "'";
                        }

                        Database::iud("INSERT INTO `lecturer_payments`(" . $payment_col . ") VALUES(" . $payment_val . ")");
                        $paymentId = Database::$connection->insert_id;

                        $query = "SELECT `time_table_id` FROM `time_table` 
                        INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                        INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id`=`lecturers`.`lecturers_id`
                        INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                        WHERE `users`.`user_id` = '" . $lectureData['user_id'] . "'
                        AND `time_table`.`date` = '" . $lectureData['date'] . "'
                        AND `time_table`.`start_time` = '" . $lectureData['start_time'] . "'
                        AND `time_table`.`end_time` = '" . $lectureData['end_time'] . "'";

                        $batches = Database::search($query);

                        if ($batches->num_rows > 0) {
                            while ($batch = $batches->fetch_assoc()) {
                                $lecturer_payment_has_lectures_col = "`lecturer_payments_lecturer_payments_id`,`time_table_time_table_id`";
                                $lecturer_payment_has_lectures_val = "'" . $paymentId . "','" . $batch['time_table_id'] . "'";
                                Database::iud("INSERT INTO `lecturer_payments_has_lectures`(" . $lecturer_payment_has_lectures_col . ") VALUES(" . $lecturer_payment_has_lectures_val . ")");
                            }
                        }

                        $lecturer_payments_log_columns = "`lecturer_payments_lecturer_payments_id`, `log`, `date_updated`, `users_user_id`";
                        $lecturer_payments_log_values = "'" . $paymentId . "','Rejected Lecture Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                        if (!empty($note)) {
                            $lecturer_payments_log_columns .= ", `note`";
                            $lecturer_payments_log_values .= ", '" . Database::escape($note) . "'";
                        }

                        Database::iud("INSERT INTO `lecturer_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                        $status->done = 'Lecturer payment rejected successfully';
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
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
