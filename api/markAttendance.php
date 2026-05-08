<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $studentId = isset($decoded['studentId']) ? $decoded['studentId'] : 0;
                    $lectureId = isset($decoded['lectureId']) ? $decoded['lectureId'] : 0;
                    $attendStatus = isset($decoded['attendStatus']) ? $decoded['attendStatus'] : 0;

                    if (empty($studentId) || $studentId == 0) {
                        $status->error = 'Invalid student id';
                    } else if (empty($lectureId) || $lectureId == 0) {
                        $status->error = 'Invalid lecture id';
                    } else if (empty($attendStatus) || $attendStatus == 0 || $attendStatus == 1 || $attendStatus > 4) {
                        $status->error = 'Invalid attend status';
                    } else {
                        $isStudentValid = Database::search("SELECT * FROM `students` WHERE `student_id`='" . $studentId . "'");
                        if ($isStudentValid->num_rows == 0) {
                            $status->error = 'Invalid student id';
                        } else {
                            $isLectureValid = Database::search("SELECT * FROM `time_table` WHERE `time_table_id`='" . $lectureId . "'");
                            if ($isLectureValid->num_rows == 0) {
                                $status->error = 'Invalid lecture id';
                            } else {
                                $timeTableData = $isLectureValid->fetch_assoc();
                                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                if ($timeTableData['lecture_type_lecture_type_id'] == 2) {
                                    try {
                                        $update = "`attendance_status_attendance_status_id` = '" . $attendStatus . "',`updated_at` = '" . $dateTime->format('Y-m-d H:i:s') . "',`users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                                        Database::iud("UPDATE `attendance` SET " . $update . " WHERE `time_table_time_table_id`='" . $lectureId . "' AND `students_student_id`='" . $studentId . "'");
                                        $status->done = 'Attendance marked successfully';
                                    } catch (Exception $e) {
                                        $status->error = 'Failed to mark attendance';
                                    }
                                } else {
                                    try {
                                        $attendnace_insert_columns = "`students_student_id`,`time_table_time_table_id`,`attendance_status_attendance_status_id`,`updated_at`,`users_user_id`";
                                        $attendnace_insert_values = "'" . $studentId . "','" . $lectureId . "','" . $attendStatus . "','" . $dateTime->format('Y-m-d H:i:s') . "','" . $sessionUserObject['user_id'] . "'";

                                        Database::iud("INSERT INTO `attendance` (" . $attendnace_insert_columns . ") VALUES (" . $attendnace_insert_values . ")");
                                        $status->done = 'Attendance marked successfully';
                                    } catch (Exception $e) {
                                        $status->error = 'Failed to mark attendance';
                                    }
                                }
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
