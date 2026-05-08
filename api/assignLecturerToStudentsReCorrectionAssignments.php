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

                    $assignment_id = isset($decoded['assignment_id']) ? $decoded['assignment_id'] : 0;
                    $search_text = isset($decoded['search_text']) ? $decoded['search_text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $lecturer_id = isset($decoded['lecturer_id']) ? $decoded['lecturer_id'] : 0;
                    $checkedStudents = isset($decoded['checkedStudents']) ? $decoded['checkedStudents'] : [];
                    $uncheckedStudents = isset($decoded['uncheckedStudents']) ? $decoded['uncheckedStudents'] : [];
                    $pageno = isset($decoded['currentPage']) ? $decoded['currentPage'] : 1;

                    if (empty($assignment_id) || $assignment_id == 0) {
                        $status->error = 'Invalid assignment id';
                    } else {
                        $isValidAssi = Database::search("SELECT * FROM `assignments` WHERE `assignment_id` = '" . $assignment_id . "'");

                        if ($isValidAssi->num_rows == 0) {
                            $status->error = 'Invalid assignment ID';
                        } else {
                            $isValidLectId = Database::search("SELECT COUNT(*) as 'count' FROM `lecturers` WHERE `lecturers_id` = '" . $lecturer_id . "'");
                            $row = $isValidLectId->fetch_assoc();
                            if ($row['count'] == 0) {
                                $status->error = 'Invalid lecturer ID';
                            } else {

                                $assignmentData = $isValidAssi->fetch_assoc();

                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                $query = "SELECT `students_has_assignments_id`,`title`,`first_name`,`last_name`,`profile_img`,`students_has_intakes`.`student_id`,`uploaded_at`,`upload` FROM `students_has_assignments` 
                                                                 INNER JOIN `students` ON `students_has_assignments`.`students_student_id`=`students`.`student_id`
                                                                 INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                 INNER JOIN `students_has_intakes` ON `students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                 WHERE `students_has_assignments`.`assignments_assignment_id`='" . $assignment_id . "'
                                                                 AND `students_has_intakes`.`intakes_intake_id` = (SELECT `intakes_intake_id` FROM `levels` WHERE `levels`.`level_id` = (SELECT `levels_level_id` FROM `subjects` WHERE `subjects`.`subject_id` = '" . $assignmentData['subjects_subject_id'] . "'))
                                                                 AND (`assignment_marks_status_assignment_marks_status_id` = '5' OR `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `assignment_recorrection`))
                                                                 ";

                                if (count($checkedStudents) > 0) {
                                    $query .= " AND `students_has_assignments`.`students_has_assignments_id` IN (" . implode(",", $checkedStudents) . ")";
                                }

                                if (count($uncheckedStudents) > 0) {
                                    $query .= " AND `students_has_assignments`.`students_has_assignments_id` NOT IN (" . implode(",", $uncheckedStudents) . ")";
                                }

                                if ($search_text != "") {
                                    $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%')";
                                }

                                if ($sort != 0) {
                                    if ($sort == 1) {
                                        $query .= " ORDER BY `uploaded_at` DESC";
                                    } else if ($sort == 2) {
                                        $query .= " ORDER BY `uploaded_at` ASC";
                                    }
                                } else {
                                    $query .= " ORDER BY `uploaded_at` DESC";
                                }

                                $results = Database::search($query);
                                $results_count = $results->num_rows;
                                $result_per_page = (int)$resultPerPage;

                                if ($results_count > $result_per_page) {
                                    $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                } else {
                                    $number_of_pages = 1;
                                }

                                $page_results = ($pageno - 1) * $result_per_page;
                                $assignments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                if ($assignments->num_rows > 0) {
                                    while ($data = $assignments->fetch_assoc()) {

                                        $studentAssignmentAssignLecturer = Database::search("SELECT * FROM `assignment_recorrection` WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                        if ($studentAssignmentAssignLecturer->num_rows > 0) {
                                            $studentAssignmentAssignLecturerData = $studentAssignmentAssignLecturer->fetch_assoc();
                                            if ($studentAssignmentAssignLecturerData['submission_sts_submission_sts_id'] == 1) {

                                                Database::iud("UPDATE `assignment_recorrection` SET `lecturers_lecturers_id`='" . $lecturer_id . "' WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");

                                                $log_insert_columns = "`recorrection_log`,`date_added`,`users_user_id`,`recorrection_recorrection_id`";
                                                $log_insert_values  = "'Updated Lecturer','" . $date . "','" . $sessionUserObject["user_id"] . "','" . $studentAssignmentAssignLecturerData['recorrection_id'] . "'";

                                                Database::iud("INSERT INTO `assignment_recorrection_log` (" . $log_insert_columns . ") VALUES (" . $log_insert_values . ")");
                                            }
                                        } else {
                                            $insert_columns = "`lecturers_lecturers_id`,`students_has_assignments_students_has_assignments_id`,`submission_sts_submission_sts_id`";
                                            $insert_values = "'" . $lecturer_id . "','" . $data['students_has_assignments_id'] . "','1'";
                                            Database::iud("INSERT INTO `assignment_recorrection` (" . $insert_columns . ") VALUES (" . $insert_values . ")");

                                            $assignmentRecorrectionId = Database::$connection->insert_id;

                                            $log_insert_columns = "`recorrection_log`,`date_added`,`users_user_id`,`recorrection_recorrection_id`";
                                            $log_insert_values  = "'Assigned Lecturer','" . $date . "','" . $sessionUserObject["user_id"] . "','" . $assignmentRecorrectionId . "'";

                                            Database::iud("INSERT INTO `assignment_recorrection_log` (" . $log_insert_columns . ") VALUES (" . $log_insert_values . ")");
                                        }
                                    }
                                    if (count($checkedStudents) == 1) {
                                        $status->done = 'Lecturer successfully assigned.';
                                    } else {
                                        $status->done = 'Lecturers successfully assigned.';
                                    }
                                } else {
                                    $status->error = 'All assignments are already assigned to this lecturer';
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
