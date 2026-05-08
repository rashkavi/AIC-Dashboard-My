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
                    $student_exam_id = isset($decoded["id"]) ? $decoded["id"] : 0;
                    $mark = isset($decoded["mark"]) ? $decoded["mark"] : 0;

                    if (empty($student_exam_id) || $student_exam_id == 0) {
                        $status->error = 'Invalid student id';
                    } else {

                        $isValidId = Database::search("SELECT * FROM `students_has_online_exam`
                        INNER JOIN `online_exam` ON `students_has_online_exam`.`online_exam_online_exam_id` = `online_exam`.`online_exam_id`
                        INNER JOIN `exams` ON `online_exam`.`exams_exam_id` = `exams`.`exam_id` 
                        WHERE `students_has_online_exam_id`='" . $student_exam_id . "'");

                        if ($isValidId->num_rows == 0) {
                            $status->error = 'Invalid student id';
                        } else {
                            $data = $isValidId->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

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
                                    $data = $isValidId->fetch_assoc();
                                    try {
                                        Database::iud("UPDATE `students_has_online_exam` SET `marks`='" . $mark . "',`exam_status_exam_status_id`='2' WHERE `students_has_online_exam_id`='" . $student_exam_id . "'");

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
