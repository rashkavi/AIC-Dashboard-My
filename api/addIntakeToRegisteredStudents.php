<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Limitation.php");
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

                    $studentId = isset($decoded['studentId']) ? $decoded['studentId'] : 0;
                    $intakeId = isset($decoded['intakeId']) ? $decoded['intakeId'] : 0;
                    $intakeStudentId = isset($decoded['intakeStudentId']) ? $decoded['intakeStudentId'] : 0;
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $intakeId))) {
                        $status->error = 'You do not have permission to add student to this intake';
                    } else {
                        if (!Limitation::availableStudents()) {
                            $status->error = 'You have reached the maximum number of students';
                        } else {
                            if ($studentId == 0) {
                                $status->error = 'Invalid student id';
                            } else {
                                require_once '../includes/connection.php';
                                $isValidStudent = Database::search("SELECT COUNT(*) as 'count' FROM `students` 
                        WHERE `student_id`='" . $studentId . "' AND `student_id` NOT IN (SELECT `students_student_id` FROM `students_has_intakes` WHERE `intakes_intake_id`='" . $intakeId . "')");

                                $row = $isValidStudent->fetch_assoc();
                                if ($row['count'] == 0) {
                                    $status->error = 'Invalid student id';
                                } else {
                                    $sValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id`='" . $intakeId . "'");
                                    $row = $sValidIntake->fetch_assoc();
                                    if ($row['count'] == 0) {
                                        $status->error = 'Invalid intake id';
                                    } else {

                                        if (empty($intakeStudentId) || $intakeStudentId == 0) {
                                            $status->sIId = 'Intake student id is required';
                                        } else {
                                            $isValidIntakeStudentId = Database::search("SELECT COUNT(*) as 'count' FROM `students_has_intakes` WHERE `student_id`='" . $intakeStudentId . "'");
                                            $row = $isValidIntakeStudentId->fetch_assoc();
                                            if ($row['count'] > 0) {
                                                $status->sIId = 'Intake student id already exists';
                                            } else {
                                                $timezone = new DateTimeZone("Asia/Colombo");
                                                $dateTime = new DateTime("now", $timezone);
                                                $addTime = $dateTime->format('Y-m-d H:i:s');

                                                $insert_columns = "`students_student_id`,`intakes_intake_id`,`student_id`,`addmission_date`,`status_status_id`,`users_user_id`";
                                                $insert_values = "'" . $studentId . "','" . $intakeId . "','" . $intakeStudentId . "','" . $addTime . "','1','" . $sessionUserObject['user_id'] . "'";

                                                Database::iud("INSERT INTO `students_has_intakes` (" . $insert_columns . ") VALUES (" . $insert_values . ")");

                                                $status->done = 'Student added successfully';
                                            }
                                        }
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
