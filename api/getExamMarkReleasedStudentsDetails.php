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
                 
                    $id = isset($decoded['examId']) ? $decoded['examId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $exam = Database::search("SELECT * FROM `exams` WHERE `exam_id` = '" . $id . "'");
                    $examinationData = $exam->fetch_assoc();
                    $examType = $examinationData["exam_types_exam_type_id"];
   

                    if ($examType == 1) {
                        $query = "SELECT `students_has_physical_exam_id` AS `student_exam_id`,`exam_name`,`marks`,`admission`,`exam_status`,`exam_status_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`title`,`profile_img` FROM `students_has_physical_exam`
                        INNER JOIN `students` ON `students_has_physical_exam`.`students_student_id`=`students`.`student_id`
                        INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `physical_exam` ON `students_has_physical_exam`.`physical_exam_physical_exam_id` = `physical_exam`.`physical_exam_id`
                        INNER JOIN `exams` ON `physical_exam`.`exams_exam_id`=`exams`.`exam_id`
                        INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                        INNER JOIN `students_has_intakes` ON (`students_has_physical_exam`.`students_student_id` = `students_has_intakes`.`students_student_id`
                        AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                        INNER JOIN `exam_status` ON `students_has_physical_exam`.`exam_status_exam_status_id` = `exam_status`.`exam_status_id`
                        WHERE `exams`.`exam_id`='" . $id . "' AND `exam_status`.`exam_status_id`='2'
                        ";
                    } elseif ($examType == 2) {
                        $query = "SELECT `students_has_online_exam_id` AS `student_exam_id`,`exam_name`,`marks`,`exam_status`,`exam_status_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`title`,`profile_img` FROM `students_has_online_exam`
                        INNER JOIN `students` ON `students_has_online_exam`.`students_student_id`=`students`.`student_id`
                        INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `online_exam` ON `students_has_online_exam`.`online_exam_online_exam_id` = `online_exam`.`online_exam_id`
                        INNER JOIN `exams` ON `online_exam`.`exams_exam_id`=`exams`.`exam_id`
                        INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                        INNER JOIN `students_has_intakes` ON (`students_has_online_exam`.`students_student_id` = `students_has_intakes`.`students_student_id`
                        AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                        INNER JOIN `exam_status` ON `students_has_online_exam`.`exam_status_exam_status_id` = `exam_status`.`exam_status_id`
                        WHERE `exams`.`exam_id`='" . $id . "' AND `exam_status`.`exam_status_id`='2'
                        ";
                    }

                    if ($search_text != "") {
                        $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%')";
                    }

                    if ($filter != 0) {
                        $query .= " AND `exam_status`.`exam_status_id`='" . $filter . "'";
                    }
                 
                    $results = Database::search($query);
                    $results_count = $results->num_rows;

                    if (!($results->num_rows > 0)) {
                        $status->error = 'Marking complete — all assignment marks are released.';
                    } else {
                        $status->modal = array(
                            'title' => 'Release Assignment Marks',
                            'body' => '
                            <div class="text-center">
                                <p>There are <strong>' . $results_count . '</strong> students selected.</p>
                                <div class="alert alert-warning">
                                    <h5>Mark Release Guidelines:</h5>
                                    <ul class="text-left">
                                       
                                        <li>Students will be notified immediately</li>
                                        <li>Ensure all marks are finalized before releasing</li>
                                    </ul>
                                </div>
                                <p>Are you sure you want to release the marks for this examination?</p>
                            </div>
                        ',
                            'footer' => '
                            <button type="button" class="btn btn-secondary br-0" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary br-0" id="confirmReleaseMarks" onclick="releaseExamMarks(' . $id . ')">Confirm Release</button>
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
