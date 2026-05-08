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
                    $subjectId = isset($decoded['subjectId']) ? $decoded['subjectId'] : 0;
                    $checkedStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : 0;
                    $uncheckedStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : 0;
                    $checkAll = isset($decoded['checkAll']) ? $decoded['checkAll'] : false;
                    if (empty($subjectId) || $subjectId == 0) {
                        $status->error = 'Invalid subject id';
                    } else {
                        $isValidSubject = Database::search("SELECT `subject_id`,`intake_id`,`subject` FROM `subjects`
                        INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
                        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
                        WHERE `subject_id` = '" . $subjectId . "'");
                        if ($isValidSubject->num_rows == 0) {
                            $status->error = 'Invalid subject id';
                        } else {
                            $subjectData = $isValidSubject->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_students', 2, $subjectId))) {
                                $status->error = 'You do not have permission to add student to this subject';
                            } else {
                                if (!isset($checkedStudents) && !is_array($checkedStudents)) {
                                    $status->error = 'Please select atleast one student';
                                }
                                if (!isset($uncheckedStudents) && !is_array($uncheckedStudents)) {
                                    $status->error = 'Please select atleast one student';
                                }
                                if (!property_exists($status, 'error')) {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $date = $dateTime->format('Y-m-d H:i:s');

                                    $addStudentsCount = 0;

                                    if (count($checkedStudents) > 0) {
                                        foreach ($checkedStudents as $studentId) {
                                            $student_has_subject_insert_columns = "`subjects_subject_id`,`students_student_id`,`added_at`,`users_user_id`,`status_status_id`";
                                            $student_has_subject_insert_values = "'" . $subjectId . "','" . $studentId . "','" . $date . "','" . $sessionUserObject['user_id'] . "','1'";
                                            Database::iud("INSERT INTO `students_has_subjects` (" . $student_has_subject_insert_columns . ") VALUES (" . $student_has_subject_insert_values . ")");
                                            $addStudentsCount++;
                                        }
                                    }
                                    if (count($uncheckedStudents) > 0) {
                                        $students = Database::search("SELECT `students_student_id` FROM `students_has_intakes` 
                                    WHERE `intakes_intake_id`='" . $subjectData['intake_id'] . "' AND `students_student_id` NOT IN (SELECT `students_student_id` FROM `students_has_subjects` 
                                    WHERE `subjects_subject_id`='" . $subjectId . "') AND `students_student_id` NOT IN (" . implode(',', $uncheckedStudents) . ")");
                                        if ($students->num_rows > 0) {
                                            foreach ($students as $student) {
                                                $student_has_subject_insert_columns = "`subjects_subject_id`,`students_student_id`,`added_at`,`users_user_id`,`status_status_id`";
                                                $student_has_subject_insert_values = "'" . $subjectId . "','" . $student['student_id'] . "','" . $date . "','" . $sessionUserObject['user_id'] . "','1'";
                                                Database::iud("INSERT INTO `students_has_subjects` (" . $student_has_subject_insert_columns . ") VALUES (" . $student_has_subject_insert_values . ")");
                                                $addStudentsCount++;
                                            }
                                        }
                                    }
                                    if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                        if ($checkAll) {
                                            $students = Database::search("SELECT `students_student_id` FROM `students_has_intakes` 
                                    WHERE `intakes_intake_id`='" . $subjectData['intake_id'] . "' AND `students_student_id` NOT IN (SELECT `students_student_id` FROM `students_has_subjects` 
                                    WHERE `subjects_subject_id`='" . $subjectId . "') ");
                                            if ($students->num_rows > 0) {
                                                foreach ($students as $student) {
                                                    $student_has_subject_insert_columns = "`subjects_subject_id`,`students_student_id`,`added_at`,`users_user_id`,`status_status_id`";
                                                    $student_has_subject_insert_values = "'" . $subjectId . "','" . $student['students_student_id'] . "','" . $date . "','" . $sessionUserObject['user_id'] . "','1'";
                                                    Database::iud("INSERT INTO `students_has_subjects` (" . $student_has_subject_insert_columns . ") VALUES (" . $student_has_subject_insert_values . ")");
                                                    $addStudentsCount++;
                                                }
                                            }
                                        } else {
                                            $status->warning = 'Please select atleast one student';
                                        }
                                    }
                                    if (!property_exists($status, 'warning')) {
                                        if ($addStudentsCount == 1) {
                                            $status->done = 'Selected Student added successfully';
                                        } else {
                                            $status->done = 'Selected Students added successfully';
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
