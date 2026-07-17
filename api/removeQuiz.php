<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $quizId = isset($decoded["quizId"]) ? $decoded["quizId"] : 0;
                    if (empty($quizId) || $quizId == 0 || !is_numeric($quizId)) {
                        $status->error = 'Invalid Quiz ID';
                    } else {
                        $isValidQuiz = Database::search("SELECT `exam_date`,`start_time`,`end_date`,`end_time`,`quiz_id`,`exam_id`,`subjects_subject_id` FROM `quiz` INNER JOIN `exams` ON `quiz`.`exams_exam_id`=`exams`.`exam_id` WHERE `quiz_id` = '" . $quizId . "'");
                        if ($isValidQuiz->num_rows == 0) {
                            $status->error = 'Invalid Quiz ID';
                        } else {
                            $data = $isValidQuiz->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 11, $data['subjects_subject_id']))) {
                                $status->error = 'You are not authorized to remove quiz.';
                            } else {
                                if (CheckDateTime::checkTimeStatus($data['exam_date'], $data['start_time'], $data['end_date'], $data['end_time']) != "Upcoming") {
                                    $status->error = "Quiz removal failed: The selected quiz cannot be removed because its scheduled date and time have already started or passed. Only upcoming quizzes can be deleted. Please verify the quiz schedule and try again.";
                                } else {
                                    Database::iud("DELETE FROM `answers` WHERE `questions_question_id` IN (SELECT `question_id` FROM `questions` WHERE `quiz_quiz_id`='" . $data['quiz_id'] . "')");
                                    Database::iud("DELETE FROM `questions` WHERE `quiz_quiz_id`='" . $data['quiz_id'] . "'");
                                    Database::iud("DELETE FROM `quiz` WHERE `quiz_id`='" . $data['quiz_id'] . "'");
                                    Database::iud("DELETE FROM `exams` WHERE `exam_id`='" . $data['exam_id'] . "'");

                                    $status->done = "Quiz removed successfully";
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
