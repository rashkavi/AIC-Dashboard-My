<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['subject']) && !empty($_GET['subject'])) {
            $subject = isset($_GET['subject']) ? $_GET['subject'] : 0;
            if (empty($subject) || $subject == 0) {
                $status->error = 'Invalid subject';
            } else {
               
                $subjectHasQuizes = Database::search("SELECT `subject_type_subject_type_id`,`subject_id` FROM `quiz` INNER JOIN `exams` ON `quiz`.`exams_exam_id`=`exams`.`exam_id`
                INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                WHERE `subjects`.`subject_id`='" . $subject . "'");

                if ($subjectHasQuizes->num_rows == 0) {
                    $labels = ["Submit Students", "Not-Submit Students", "Pending Mark Release", "Mark Released"];
                    $data = [0, 0, 0, 0];
                } else {
                    $quizesHasStudents = 0;
                    while ($subjectHasQuizesData = $subjectHasQuizes->fetch_assoc()) {
                        if ($subjectHasQuizesData['subject_type_subject_type_id'] != 1) {
                            $countQuery = Database::search("SELECT COUNT(*) AS count FROM `students_has_subjects`
                                          WHERE `subjects_subject_id` = '" . $subjectHasQuizesData['subject_id'] . "'");
                            $countResult = $countQuery->fetch_assoc();
                            $quizesHasStudents += (int)$countResult['count'];
                        } else {
                            $countQuery = Database::search("SELECT COUNT(*) AS count FROM `students_has_intakes`
                                          WHERE `intakes_intake_id` = (SELECT `intakes_intake_id` FROM `levels` 
                                          WHERE `level_id`='" . $subject . "')");
                            $countResult = $countQuery->fetch_assoc();
                            $quizesHasStudents += (int)$countResult['count'];
                        }
                    }
                    $quizesSubmitStudents = Database::search("SELECT 
                            COUNT(*) AS count,
                            SUM(CASE WHEN `exam_status_exam_status_id` = 1 THEN 1 ELSE 0 END) AS status_1_count,
                            SUM(CASE WHEN `exam_status_exam_status_id` = 3 THEN 1 ELSE 0 END) AS status_3_count
                            FROM `students_has_quiz`
                            INNER JOIN `quiz` ON `students_has_quiz`.`quiz_quiz_id`=`quiz`.`quiz_id`
                            INNER JOIN `exams` ON `quiz`.`exams_exam_id`=`exams`.`exam_id` 
                            INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                            WHERE `subjects`.`subject_id`='" . $subject . "'
                            ");
                    $quizesSubmitStudentsData = $quizesSubmitStudents->fetch_assoc();


                    $total = (int)$quizesHasStudents;
                    $submitStudents = (int)$quizesSubmitStudentsData["count"];
                    $notSubmitStudents = (int)$total - (int)$submitStudents;
                    $pendingMarkRelese = (int)$quizesSubmitStudentsData["status_1_count"];
                    $markReleased = (int)$quizesSubmitStudentsData["status_3_count"];

                    $labels = ["Submit Students", "Not-Submit Students", "Pending Mark Release", "Mark Released"];
                    $data = [$submitStudents, $notSubmitStudents, $pendingMarkRelese, $markReleased];
                }

                $status->labels = $labels;
                $status->data = $data;
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
