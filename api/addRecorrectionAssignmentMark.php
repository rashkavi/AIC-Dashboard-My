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
                    $student_has_asi_id = isset($decoded["id"]) ? $decoded["id"] : 0;
                    $mark = isset($decoded["mark"]) ? $decoded["mark"] : 0;

                    if (empty($student_has_asi_id) || $student_has_asi_id == 0) {
                        $status->error = 'Invalid student id';
                    } else {
                        $isValidId = Database::search("SELECT `students_has_assignments_id`,`recorrection_id`,`subjects_subject_id` FROM `students_has_assignments`
                        INNER JOIN `assignment_recorrection` ON `students_has_assignments`.`students_has_assignments_id` = `assignment_recorrection`.`students_has_assignments_students_has_assignments_id` 
                        INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
                        WHERE `students_has_assignments_id`='" . $student_has_asi_id . "'");
                        if ($isValidId->num_rows == 0) {
                            $status->error = 'Invalid student id';
                        } else {
                            $data = $isValidId->fetch_assoc();
                            $validLecturer = false;
                            $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $data['subjects_subject_id'] . "'");
                            if ($lecturerSubject->num_rows > 0) {
                                $validLecturer = true;
                            }
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || $validLecturer)) {
                                $status->error = 'You are not authorized to add mark';
                            } else {
                                if (empty($mark) || $mark == 0) {
                                    $status->mark = 'Please enter mark';
                                } else if ($mark < 0 || $mark > 100) {
                                    $status->mark = 'Invalid mark';
                                }

                                if (!property_exists($status, 'mark')) {
                                    try {
                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $date = $dateTime->format('Y-m-d H:i:s');

                                        Database::iud("UPDATE `assignment_recorrection` SET `marks`='" . $mark . "',`submission_sts_submission_sts_id`='2' WHERE `students_has_assignments_students_has_assignments_id`='" . $student_has_asi_id . "'");
                                        Database::iud("UPDATE `students_has_assignments` SET `assignment_marks_status_assignment_marks_status_id`='3' WHERE `students_has_assignments_id`='" . $student_has_asi_id . "'");

                                        $log_insert_columns = "`recorrection_log`,`date_added`,`users_user_id`,`recorrection_recorrection_id`";
                                        $log_insert_values  = "'Update Assignment Mark','" . $date . "','" . $sessionUserObject["user_id"] . "','" . $data['recorrection_id'] . "'";
                                        Database::iud("INSERT INTO `assignment_recorrection_log` (" . $log_insert_columns . ") VALUES (" . $log_insert_values . ")");
                                        $status->done = 'Mark updated successfully';
                                    } catch (Exception $e) {
                                        $status->error = "Failed to update mark ";
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
