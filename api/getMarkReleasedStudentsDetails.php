<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
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
                    $assignmentData = $assignment->fetch_assoc();

                    $query = "SELECT `students_has_assignments_id`,`title`,`first_name`,`last_name`,`profile_img`,`students_has_intakes`.`student_id`,`uploaded_at`,`upload`,`submission_id`,`submission` FROM `students_has_assignments` 
                                                                INNER JOIN `students` ON `students_has_assignments`.`students_student_id`=`students`.`student_id`
                                                                INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
                                                                INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                INNER JOIN `students_has_intakes` ON (`students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                                                                INNER JOIN `assignment_submission` ON `students_has_assignments`.`submission_submission_id`=`assignment_submission`.`submission_id`
                                                                INNER JOIN `lecturers_has_students_has_assignments` ON `students_has_assignments`.`students_has_assignments_id` = `lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`
                                                                WHERE `students_has_assignments`.`assignments_assignment_id`='" . $id . "'
                                                                AND `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = '3'
                                                                ";

                    if ($search_text != "") {
                        $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%')";
                    }

                    if ($filter != 0) {
                        $query .= " AND `students_has_assignments`.`submission_submission_id` = '" . $filter . "'";
                    }

                    if ($assignLecturer != 0) {
                        $query .= " AND `lecturers_has_students_has_assignments`.`lecturers_lecturers_id` = '" . $assignLecturer . "' ";
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
                        $status->error = 'No assignments are currently available.';
                    } else {
                        $status->modal = array(
                            'title' => 'Release Assignment Marks',
                            'body' => '
                            <div class="text-center">
                                <p>There are <strong>' . $results_count . '</strong> students selected.</p>
                                <div class="alert alert-warning">
                                    <h5>Mark Release Guidelines:</h5>
                                    <ul class="text-left">
                                        <li>Once released, marks cannot be modified</li>
                                        <li>Students will be notified immediately</li>
                                        <li>Ensure all marks are finalized before releasing</li>
                                    </ul>
                                </div>
                                <p>Are you sure you want to release the marks for this assignment?</p>
                            </div>
                        ',
                            'footer' => '
                            <button type="button" class="custom-btn" style="width:150px" id="confirmReleaseMarks" onclick="releaseAssignmentMarks(this,' . $id . ')">Confirm Release</button>
                        '
                        );
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
