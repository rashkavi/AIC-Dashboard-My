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

                    $attendanceId  = isset($decoded['attendanceId']) ? $decoded['attendanceId'] : 0;

                    if (empty($attendanceId) || $attendanceId == 0) {
                        $status->error = 'Invalid attendance id';
                    } else {
                        $isAttendnaceIdValid = Database::search("SELECT * FROM `attendance` WHERE `attendance_id`='" . $attendanceId . "' AND `attendance_status_attendance_status_id` != 1");
                        if ($isAttendnaceIdValid->num_rows == 0) {
                            $status->error = 'Invalid attendance id';
                        } else {
                            $attendanceData = $isAttendnaceIdValid->fetch_assoc();
                            $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                            if ($attendanceData['attendance_status_attendance_status_id'] == 2) {
                                $update = "`attendance_status_attendance_status_id` = '3'";
                            } else {
                                $update = "`attendance_status_attendance_status_id` = '2'";
                            }

                            $update .= ", `updated_at` = '" . $dateTime->format('Y-m-d H:i:s') . "',`users_user_id` = '" . $sessionUserObject['user_id'] . "'";

                            try {
                                Database::iud("UPDATE `attendance` SET " . $update . " WHERE `attendance_id` = '" . $attendanceId . "'");
                                $status->done = 'Attendance updated successfully';
                            } catch (Exception $e) {
                                $status->error = 'Failed to update attendance';
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
