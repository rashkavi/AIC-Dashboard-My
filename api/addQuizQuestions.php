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

                    $quizId = isset($decoded["quizId"]) ? $decoded["quizId"] : 0;
                    $quizzes = isset($decoded["quizzes"]) ? $decoded["quizzes"] : [];

                    if (empty($quizId) || $quizId == 0 || !is_numeric($quizId)) {
                        $status->error = 'Invalid Quiz ID';
                    } else {
                        $isValidQuiz = Database::search("SELECT * FROM `quiz` INNER JOIN `exams` ON `quiz`.`exams_exam_id`=`exams`.`exam_id` WHERE `quiz_id` = '" . $quizId . "'");
                        if ($isValidQuiz->num_rows == 0) {
                            $status->error = 'Invalid Quiz ID';
                        } else {
                            $quizData = $isValidQuiz->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 2, $quizData['subjects_subject_id']))) {
                                $status->error = 'You are not authorized to add quiz questions.';
                            } else {
                                if (count($quizzes) == 0) {
                                    $status->error = 'Please enter at least one question';
                                } else {
                                    $errors = [];
                                    $invalid_chars = '/[\'\";\-]/';

                                    foreach ($quizzes as $index => $quiz) {
                                        $question = isset($quiz["question"]) ? $quiz["question"] : '';
                                        $answers = isset($quiz["answers"]) ? $quiz["answers"] : [];

                                        $quizErrors = [];

                                        if (empty($question)) {
                                            $quizErrors[] = "Quiz " . ($index + 1) . ": Question is required.";
                                        } else if (preg_match($invalid_chars, $question)) {
                                            $quizErrors[] = "Quiz " . ($index + 1) . ": Question contains invalid characters.";
                                        }

                                        if (empty($answers)) {
                                            $quizErrors[] = "Quiz " . ($index + 1) . ": Answers are required.";
                                        } elseif (count($answers) < 4) {
                                            $quizErrors[] = "Quiz " . ($index + 1) . ": At least 4 answers are required.";
                                        } else {
                                            $hasCorrectAnswer = false;
                                            foreach ($answers as $answerIndex => $answer) {
                                                if (!isset($answer['option']) || empty($answer['option'])) {
                                                    $quizErrors[] = "Quiz " . ($index + 1) . ": Answer " . ($answerIndex + 1) . " is missing the 'option' field.";
                                                }
                                                if (isset($answer['isCorrect']) && $answer['isCorrect'] === true) {
                                                    $hasCorrectAnswer = true;
                                                }
                                            }

                                            if (!$hasCorrectAnswer) {
                                                $quizErrors[] = "Quiz " . ($index + 1) . ": At least one answer must be marked as correct.";
                                            }
                                        }

                                        if (!empty($quizErrors)) {
                                            $errors = array_merge($errors, $quizErrors);
                                        }
                                    }

                                    if (!empty($errors)) {
                                        $status->errors = $errors;
                                    }

                                    if (!property_exists($status, 'errors')) {
                                        foreach ($quizzes as $index => $quiz) {
                                            $question = isset($quiz["question"]) ? $quiz["question"] : '';
                                            $answers = isset($quiz["answers"]) ? $quiz["answers"] : [];
                                            $correctAnswer = isset($quiz["correctAnswer"]) ? $quiz["correctAnswer"] : '';

                                            Database::iud("INSERT INTO `questions` (`question`,`correct_answer`,`quiz_quiz_id`,`questions_order`) VALUES ('" . $question . "','" . $correctAnswer . "','" . $quizId . "','0')");

                                            $quizQuestionId = Database::$connection->insert_id;

                                            foreach ($answers as $answerIndex => $answer) {
                                                $option = isset($answer['option']) ? $answer['option'] : '';
                                                $isCorrect = isset($answer['isCorrect']) ? $answer['isCorrect'] : false;

                                                Database::iud("INSERT INTO `answers` (`questions_question_id`,`answer`,`answer_order`) VALUES ('" . $quizQuestionId . "','" . $option . "','" . $answerIndex + 1 . "')");
                                            }

                                            $status->done = 'Questions added successfully!';
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
