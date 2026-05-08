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

                    $lecturerPayemntId = isset($decoded['lecturerPayemntId']) ? $decoded['lecturerPayemntId'] : '';
                    $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : '';

                    if (empty($lecturerPayemntId) || $lecturerPayemntId == 0) {
                        $status->error = 'Lecturer assignemnt payment id is required';
                    } else {
                        $isValidLecture = Database::search("SELECT COUNT(*) as 'count' FROM `assignment_payments` WHERE `assignment_payments_id` = '" . $lecturerPayemntId . "'");

                        $isValidLectureData = $isValidLecture->fetch_assoc();
                        if ($isValidLectureData['count'] == 0) {
                            $status->error = 'Lecture assignment payment not found';
                        } else {
                            if (empty($paymentStatus) || $paymentStatus == 0 || ($paymentStatus != 2 && $paymentStatus != 3)) {
                                $status->error = 'Invalid payment status';
                            } else {

                                Database::iud("UPDATE `assignment_payments` SET `earning_status_earning_status_id`='" . $paymentStatus . "' WHERE `assignment_payments_id`='" . $lecturerPayemntId . "'");

                                if ($paymentStatus == 2) {
                                    $note = 'payment accepted';
                                } else {
                                    $note = 'payment rejected';
                                }

                                $lecturer_payments_log_columns = "`assignment_payments_assignment_payments_id`, `note`, `date_updated`, `users_user_id`";
                                $lecturer_payments_log_values = "'" . $lecturerPayemntId . "','" . $note . "', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                                Database::iud("INSERT INTO `assignment_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                                $status->done = 'Lecturer payment updated successfully';
                            }
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
