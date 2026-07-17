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

                    $id = isset($decoded['examId']) ? $decoded['examId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $quiz = Database::search("SELECT * FROM `quiz` INNER JOIN `exams` ON `quiz`.`exams_exam_id` = `exams`.`exam_id` WHERE `quiz_id` = '" . $id . "'");
                    if ($quiz->num_rows == 0) {
                        $status->error = "Invalid quiz Id";
                    } else {
                        $quizData = $quiz->fetch_assoc();
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 17,$quizData['subjects_subject_id']))) {
                            $status->error = 'You are not authorized to released quiz marks.';
                        } else {

                            $query = "SELECT * FROM `students_has_quiz` 
                            INNER JOIN `students` ON `students_has_quiz`.`students_student_id`=`students`.`student_id`
                            INNER JOIN `quiz` ON `students_has_quiz`.`quiz_quiz_id`=`quiz`.`quiz_id`
                            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                            INNER JOIN `exam_status` ON `students_has_quiz`.`exam_status_exam_status_id`=`exam_status`.`exam_status_id`
                            WHERE `quiz`.`quiz_id`='" . $id . "'";

                            if ($search_text != "") {
                                $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%')";
                            }

                            if ($filter != 0) {
                                $query .= " AND `exam_status`.`exam_status_id` = '" . $filter . "'";
                            }

                            if ($sort != 0) {
                                if ($sort == 1) {
                                    $query .= " ORDER BY `marks` DESC";
                                } else if ($sort == 2) {
                                    $query .= " ORDER BY `marks` ASC";
                                } else if ($sort == 3) {
                                    $query .= " ORDER BY `submitted_at` DESC";
                                } else if ($sort == 4) {
                                    $query .= " ORDER BY `submitted_at` ASC";
                                }
                            } else {
                                $query .= " ORDER BY `submitted_at` ASC";
                            }

                            $results = Database::search($query);
                            $results_count = $results->num_rows;

                            if (!($results->num_rows > 0)) {
                                $status->error = 'Marking complete — all assignment marks are released.';
                            } else {
                                $status->modal = array(
                                    'title' => 'Release Quiz Marks',
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
                                <p>Are you sure you want to release the marks for this quiz?</p>
                            </div>
                        ',
                                    'footer' => '
                            <button type="button" class="btn btn-secondary br-0" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary br-0" id="confirmReleaseMarks" onclick="releaseQuizMarks(' . $id . ')">Confirm Release</button>
                        '
                                );
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
