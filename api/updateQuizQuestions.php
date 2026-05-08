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
                    $removedQuizes = isset($decoded["removeQuizes"]) ? $decoded["removeQuizes"] : [];
                    $quizzes = isset($decoded["quizzes"]) ? $decoded["quizzes"] : [];

                    if (empty($quizId) || $quizId == 0 || !is_numeric($quizId)) {
                        $status->error = 'Invalid Quiz ID';
                    } else {
                        $isValidQuiz = Database::search("SELECT * FROM `quiz` WHERE `quiz_id` = '" . $quizId . "'");
                        if ($isValidQuiz->num_rows == 0) {
                            $status->error = 'Invalid Quiz ID';
                        } else {
                            if (count($quizzes) == 0) {
                                $status->error = 'Please enter at least one question';
                            } else {
                                $errors = [];
                                $invalid_chars = '/[\'\";\-]/';

                                $quizQuestions = Database::search("SELECT `question_id` FROM `questions` WHERE `quiz_quiz_id`='" . $quizId . "'");
                                $questionAnswers = Database::search("SELECT `answer_id` FROM `answers` WHERE `questions_question_id` IN (SELECT `question_id` FROM `questions` WHERE `quiz_quiz_id`='" . $quizId . "')");

                                $quizQuestionsArray = [];
                                $questionAnswerArray = [];
                                while ($quizQuestion = $quizQuestions->fetch_assoc()) {
                                    array_push($quizQuestionsArray, $quizQuestion['question_id']);
                                }

                                while ($questionAnswer = $questionAnswers->fetch_assoc()) {
                                    array_push($questionAnswerArray, $questionAnswer['answer_id']);
                                }

                                foreach ($quizzes as $index => $quiz) {
                                    $questionId = isset($quiz["quizId"]) ? $quiz["quizId"] : '';
                                    $question = isset($quiz["question"]) ? $quiz["question"] : '';
                                    $answers = isset($quiz["answers"]) ? $quiz["answers"] : [];

                                    $quizErrors = [];

                                    if (!in_array($questionId, $quizQuestionsArray)) {
                                        $quizErrors[] = "Question ID $questionId is not valid.";
                                    }

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

                                            if (!in_array($answer['answerId'], $questionAnswerArray)) {
                                                $quizErrors[] = "Quiz " . ($index + 1) . ": Answer " . ($answerIndex + 1) . " is not valid.";
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
                                        $questionId = $quiz["quizId"];
                                        $question = $quiz["question"];
                                        $answers = $quiz["answers"];
                                        $correctAnswer = $quiz["correctAnswer"];

                                        Database::iud("UPDATE `questions` SET `question`='" . $question . "',`correct_answer`='" . $correctAnswer . "' WHERE `question_id`='" . $questionId . "'");

                                        foreach ($answers as $answerIndex => $answer) {
                                            $option = $answer['option'];
                                            $id = $answer['answerId'];
                                            $isCorrect = $answer['isCorrect'];

                                            Database::iud("UPDATE `answers` SET `answer`='" . $option . "' WHERE `answer_id`='" . $id . "'");
                                        }
                                    }

                                    if (count($removedQuizes) > 0) {
                                        Database::iud("DELETE FROM `answers` WHERE `questions_question_id` IN (" . implode(",", $removedQuizes) . ")");
                                        Database::iud("DELETE FROM `questions` WHERE `question_id` IN (" . implode(",", $removedQuizes) . ")");
                                    }

                                    $status->done = 'Questions update successfully!';
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
