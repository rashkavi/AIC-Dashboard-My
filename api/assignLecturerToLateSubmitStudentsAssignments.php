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
                    $checkedStudents[] = isset($decoded['checkedStudents']) ? $decoded['checkedStudents'] : [];
                    $uncheckedStudents[] = isset($decoded['uncheckedStudents']) ? $decoded['uncheckedStudents'] : [];
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
                                                                 AND `students_has_assignments`.`uploaded_at` > '" . $assignmentData["end_date"] . "'
                                                                 AND `students_has_assignments`.`uploaded_at` < '" . $assignmentData["late_submission_end_date"] . "'";

                                if ($search_text != "") {
                                    $query .= " AND `first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%'";
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

                                $lectureIsAssigned = true;

                                while ($data = $assignments->fetch_assoc()) {
                                    if (count($checkedStudents) > 0) {
                                        if (in_array($data['students_has_assignments_id'], $checkedStudents)) {
                                            $studentAssignmentAssignLecturer = Database::search("SELECT * FROM `lecturers_has_students_has_assignments` WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                            if ($studentAssignmentAssignLecturer->num_rows == 0) {
                                                $insert_columns = "`lecturers_lecturers_id`,`students_has_assignments_students_has_assignments_id`,`users_user_id`,`date_added`,`submission_sts_submission_sts_id`";
                                                $insert_values = "'" . $lecturer_id . "','" . $data['students_has_assignments_id'] . "','" . $sessionUserObject['user_id'] . "','" . $date . "','1'";
                                                Database::iud("INSERT INTO `lecturers_has_students_has_assignments` (" . $insert_columns . ") VALUES (" . $insert_values . ")");
                                            } else {
                                                $studentAssignmentAssignLecturer = $studentAssignmentAssignLecturer->fetch_assoc();
                                                if ($studentAssignmentAssignLecturer['marks'] == null) {
                                                    Database::iud("UPDATE `lecturers_has_students_has_assignments` SET `lecturers_lecturers_id`='" . $lecturer_id . "' WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                                } else {
                                                    $lectureIsAssigned = false;
                                                }
                                            }
                                        }
                                    }

                                    if (count($uncheckedStudents) > 0) {
                                        if (!in_array($data['students_has_assignments_id'], $uncheckedStudents)) {
                                            $studentAssignmentAssignLecturer = Database::search("SELECT * FROM `lecturers_has_students_has_assignments` WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                            if ($studentAssignmentAssignLecturer->num_rows == 0) {
                                                $insert_columns = "`lecturers_lecturers_id`,`students_has_assignments_students_has_assignments_id`,`users_user_id`,`date_added`,`submission_sts_submission_sts_id`";
                                                $insert_values = "'" . $lecturer_id . "','" . $data['students_has_assignments_id'] . "','" . $sessionUserObject['user_id'] . "','" . $date . "','1'";
                                                Database::iud("INSERT INTO `lecturers_has_students_has_assignments` (" . $insert_columns . ") VALUES (" . $insert_values . ")");
                                            } else {
                                                $studentAssignmentAssignLecturer = $studentAssignmentAssignLecturer->fetch_assoc();
                                                if ($studentAssignmentAssignLecturer['marks'] == null) {
                                                    Database::iud("UPDATE `lecturers_has_students_has_assignments` SET `lecturers_lecturers_id`='" . $lecturer_id . "' WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                                } else {
                                                    $lectureIsAssigned = false;
                                                }
                                            }
                                        }
                                    }

                                    if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                        $studentAssignmentAssignLecturer = Database::search("SELECT * FROM `lecturers_has_students_has_assignments` WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                        if ($studentAssignmentAssignLecturer->num_rows == 0) {
                                            $insert_columns = "`lecturers_lecturers_id`,`students_has_assignments_students_has_assignments_id`,`users_user_id`,`date_added`,`submission_sts_submission_sts_id`";
                                            $insert_values = "'" . $lecturer_id . "','" . $data['students_has_assignments_id'] . "','" . $sessionUserObject['user_id'] . "','" . $date . "','1'";
                                            Database::iud("INSERT INTO `lecturers_has_students_has_assignments` (" . $insert_columns . ") VALUES (" . $insert_values . ")");
                                        } else {
                                            $studentAssignmentAssignLecturer = $studentAssignmentAssignLecturer->fetch_assoc();
                                            if ($studentAssignmentAssignLecturer['marks'] == null) {
                                                Database::iud("UPDATE `lecturers_has_students_has_assignments` SET `lecturers_lecturers_id`='" . $lecturer_id . "' WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                            } else {
                                                $lectureIsAssigned = false;
                                            }
                                        }
                                    }
                                }

                                if ($lectureIsAssigned) {
                                    $status->done = 'Lecturer successfully assigned.';
                                } else {
                                    if (count($checkedStudents) == 1) {
                                        $status->error = 'Lecturer was already assigned to this assignment.';
                                    } else {
                                        $status->error = 'Lecturers were already assigned to these assignments.';
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
