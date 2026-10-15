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

                    $paymentId = isset($decoded['paymentId']) ? $decoded['paymentId'] : '';

                    if (empty($paymentId) || $paymentId == 0) {
                        $status->error = 'Payment id is required';
                    } else {
                        $query = "SELECT 
                                                        tt.time_table_id,
                                                        tt.date,
                                                        tt.start_time,
                                                        tt.end_time,
                                                        tt.lecturers_lecturers_id,
                                                        title.title,
                                                        users.user_fname,
                                                        users.user_lname,
                                                        users.profile_img,
                                                        lecture_type.lecture_type,
                                                        lecture_type.lecture_type_id,
                                                        tt.lecture_title,
                                                        tt.duration,
                                                        COUNT(tt.subjects_subject_id) AS batch_count,
                                                        `lecturer_payments`.`earning_status_earning_status_id`,
                                                        `earning_status`.`earning_status`,
                                                        `lecturer_payments`.`amount`,
                                                        `lecturer_payments`.`lecturer_payments_id`,
                                                        `lecturer_payments`.`duration` AS payment_duration,
                                                        `time_table_status_time_table_status_id`
                                                        FROM time_table AS tt
                                                        INNER JOIN `lecturers`  ON `lecturers`.`lecturers_id` = `tt`.`lecturers_lecturers_id`
                                                        INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
                                                        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
                                                        INNER JOIN `lecture_type` ON `lecture_type`.`lecture_type_id` = `tt`.`lecture_type_lecture_type_id`
                                                        INNER JOIN `lecturer_payments_has_lectures` ON `lecturer_payments_has_lectures`.`time_table_time_table_id` = `tt`.`time_table_id`
                                                        INNER JOIN `lecturer_payments` ON `lecturer_payments`.`lecturer_payments_id` = `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`
                                                        INNER JOIN `earning_status` ON `earning_status`.`earning_status_id` = `lecturer_payments`.`earning_status_earning_status_id`
                                                        WHERE tt.time_table_status_time_table_status_id = '1'
                                                        AND tt.lecture_type_lecture_type_id != 3
                                                        AND CONCAT(tt.date , ' ', tt.start_time) <= '" . date('Y-m-d H:i:s') . "'
                                                        AND lecturer_payments.lecturer_payments_id = '" . $paymentId . "'
                                                        AND `earning_status`.`earning_status_id`='1'
                                                        GROUP BY 
                                                        tt.date,
                                                        tt.start_time,
                                                        tt.end_time,
                                                        users.user_id
                                                        ORDER BY tt.date DESC";

                        $isValidLecture = Database::search($query);
                        if ($isValidLecture->num_rows == 0) {
                            $status->error = 'Payment not found';
                        } else {
                            $lectureData = $isValidLecture->fetch_assoc();

                            Database::iud("UPDATE `lecturer_payments` SET `earning_status_earning_status_id` = '3' WHERE `lecturer_payments_id` = '" . $paymentId . "'");

                            $lecturer_payments_log_columns = "`lecturer_payments_lecturer_payments_id`, `log`, `date_updated`, `users_user_id`";
                            $lecturer_payments_log_values = "'" . $paymentId . "','Rejected Scheduled Lecture Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                            Database::iud("INSERT INTO `lecturer_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                            $status->done = 'Lecturer payment rejected successfully';
                        }
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
