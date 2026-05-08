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
                    $startTime = isset($decoded['startTime']) ? $decoded['startTime'] : '';
                    $endTime = isset($decoded['endTime']) ? $decoded['endTime'] : '';

                    if (empty($lectureId) || $lectureId == 0) {
                        $status->error = 'Lecture is required';
                    } else {
                        $isValidLecture = Database::search("SELECT COUNT(*) as 'count',`date`,`end_time` FROM `time_table` 
                        WHERE `time_table_id` = '" . $lectureId . "' ");
                        $isValidLectureData = $isValidLecture->fetch_assoc();
                        if ($isValidLectureData['count'] == 0) {
                            $status->error = 'Lecture not found';
                        } else {
                            if ($isValidLectureData['date'] > $todayDate && $isValidLectureData['date'] != $todayDate) {
                                $status->error = 'Cannot confirm payment for future lecture';
                            } else if ($isValidLectureData['date'] == $todayDate) {
                                if ($isValidLectureData['end_time'] < $nowTime) {
                                    $status->error = 'Cannot confirm payment for future lecture';
                                }
                            }
                        }
                    }
                    if (empty($startTime)) {
                        $status->start = 'Start time is required';
                    }

                    if (empty($endTime)) {
                        $status->end = 'End time is required';
                    }

                    if (!property_exists($status, 'start') && !property_exists($status, 'end')) {
                        $startTimestamp = strtotime($startTime);
                        $endTimestamp = strtotime($endTime);
                        if ($endTimestamp <= $startTimestamp) {
                            $status->end = 'End time must be greater than start time';
                        }
                    }

                    $requiredProperties = [
                        'lectureId',
                        'start',
                        'end'
                    ];
                    $allPropertiesExist = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allPropertiesExist = false;
                            break;
                        }
                    }

                    if ($allPropertiesExist) {

                        $lectureData = Database::search("SELECT `lecturers`.`hourly_rate`,`lecturers_id` FROM `time_table`
                        INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id` 
                        WHERE `time_table_id` = '" . $lectureId . "' ");

                        $lectureData = $lectureData->fetch_assoc();
                        $startTimestamp = strtotime($startTime);
                        $endTimestamp = strtotime($endTime);

                        $durationInHours = ($endTimestamp - $startTimestamp) / 3600;
                        $durationInHours = round($durationInHours, 2);

                        $lectureAmount = $durationInHours * $lectureData['hourly_rate'];

                        $lecturePayment = Database::search("SELECT * FROM `lecturer_payments` WHERE `lecturers_lecturers_id`='" . $lectureData['lecturers_id'] . "' AND `time_table_time_table_id`='" . $lectureId . "' AND `earning_status_earning_status_id`!='2'");

                        if ($lecturePayment->num_rows > 0) {
                            $paymentId = $lecturePayment->fetch_assoc()['lecturer_payments_id'];
                            Database::iud("UPDATE `lecturer_payments` SET `amount`='" . $lectureAmount . "' WHERE `lecturer_payments_id`='" . $paymentId . "'");

                            $lecturer_payments_log_columns = "`lecturer_payments_lecturer_payments_id`, `note`, `date_updated`, `users_user_id`";
                            $lecturer_payments_log_values = "'" . $paymentId . "','Update Lecture Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                            Database::iud("INSERT INTO `lecturer_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                            $status->done = 'Lecturer payment updated successfully';
                        } else {
                            $lecturer_payments_columns = "`lecturers_lecturers_id`, `time_table_time_table_id`, `amount`,`earning_status_earning_status_id`";
                            $lecturer_payments_values = "'" . $lectureData['lecturers_id'] . "','" . $lectureId . "','" . $lectureAmount . "','1'";

                            Database::iud("INSERT INTO `lecturer_payments`(" . $lecturer_payments_columns . ") VALUES(" . $lecturer_payments_values . ")");
                            $paymentId = Database::$connection->insert_id;

                            $lecturer_payments_log_columns = "`lecturer_payments_lecturer_payments_id`, `note`, `date_updated`, `users_user_id`";
                            $lecturer_payments_log_values = "'" . $paymentId . "','Add Lecture Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                            Database::iud("INSERT INTO `lecturer_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                            $status->done = 'Lecturer payment added successfully';
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
