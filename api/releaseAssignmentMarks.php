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

                    $id = isset($decoded['assignmentId']) ? $decoded['assignmentId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $assignLecturer = isset($decoded['assignLecturer']) ? $decoded['assignLecturer'] : 0;

                    $assignment = Database::search("SELECT * FROM `assignments` WHERE `assignment_id` = '" . $id . "'");

                    if ($assignment->num_rows == 0) {
                        $status->error = "Invalid Assignment Id";
                    } else {
                        $assignmentData = $assignment->fetch_assoc();
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 17))) {
                            $status->error = "You are not authorized to release assignments marks.";
                        } else {

                            $query = "SELECT `students_has_assignments_id`,`lecturers_has_students_has_assignments_id` FROM `students_has_assignments` 
                                                                INNER JOIN `students` ON `students_has_assignments`.`students_student_id`=`students`.`student_id`
                                                                INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
                                                                INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                INNER JOIN `students_has_intakes` ON (`students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                                                                INNER JOIN `assignment_submission` ON `students_has_assignments`.`submission_submission_id`=`assignment_submission`.`submission_id`
                                                                INNER JOIN `lecturers_has_students_has_assignments` ON `students_has_assignments`.`students_has_assignments_id` = `lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`
                                                                WHERE `students_has_assignments`.`assignments_assignment_id`='" . $id . "'
                                                                AND `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id`  IN (1,2,3)
                                                                ";

                            if ($search_text != "") {
                                $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%')";
                            }

                            if ($filter != 0) {
                                $query .= " AND `students_has_assignments`.`submission_submission_id` = '" . $filter . "'";
                            }

                            if ($assignLecturer != 0) {
                                $query .= " AND `students_has_assignments`.`students_has_assignments_id` IN 
                    (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments` WHERE `submission_sts_submission_sts_id` = '2' AND `lecturers_lecturers_id`='" . $assignLecturer . "')";
                            } else {
                                $query .= " AND `students_has_assignments`.`students_has_assignments_id` IN 
                    (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments` WHERE `submission_sts_submission_sts_id` = '2')";
                            }

                            if ($sort != 0) {
                                if ($sort == 1) {
                                    $query .= " ORDER BY `uploaded_at` DESC";
                                } else if ($sort == 2) {
                                    $query .= " ORDER BY `uploaded_at` ASC";
                                } else if ($sort == 3) {
                                    $query .= " AND `students_has_assignments_id` NOT IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments`)";
                                } else if ($sort == 4) {
                                    $query .= " AND `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments`)";
                                } else if ($sort == 5) {
                                    $query .= " AND `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments` WHERE `submission_sts_submission_sts_id`='2')";
                                } else if ($sort == 6) {
                                    $query .= " AND `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments` WHERE `submission_sts_submission_sts_id`='1')";
                                } else if ($sort == 7) {
                                    $query .= " AND `assignment_marks_status_assignment_marks_status_id`='3'";
                                } else if ($sort == 8) {
                                    $query .= " AND `assignment_marks_status_assignment_marks_status_id`='4'";
                                }
                            } else {
                                $query .= " ORDER BY `uploaded_at` DESC";
                            }

                            $results = Database::search($query);
                            $results_count = $results->num_rows;

                            if (!($results->num_rows > 0)) {
                                $status->error = 'Please select at least one student';
                            } else {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                while ($data = $results->fetch_assoc()) {

                                    Database::iud("UPDATE `students_has_assignments` SET `assignment_marks_status_assignment_marks_status_id`='4' WHERE `students_has_assignments_id`='" . $data["students_has_assignments_id"] . "'");

                                    $log_insert_columns = "`marking_log`,`date_added`,`users_user_id`,`lecturers_has_students_has_assignments_id`";
                                    $log_insert_values  = "'Updated Lecturer','" . $date . "','" . $sessionUserObject["user_id"] . "','" . $data['lecturers_has_students_has_assignments_id'] . "'";

                                    Database::iud("INSERT INTO `assignment_marking_log` (" . $log_insert_columns . ") VALUES (" . $log_insert_values . ")");
                                }

                                $status->done = 'Assignment marks released successfully';
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
