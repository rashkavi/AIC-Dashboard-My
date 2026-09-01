<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $intake = isset($decoded['intake']) ? $decoded['intake'] : '';

                    $query = "
                SELECT 
                    i.*,
            
                    COUNT(s.intakes_intake_id) AS total_students,
            
                    SUM(CASE 
                            WHEN s.status_status_id = '1' THEN 1 
                            ELSE 0 
                        END) AS active_students
            
                FROM intakes i
            
                LEFT JOIN students_has_intakes s 
                    ON s.intakes_intake_id = i.intake_id
            
               WHERE i.intake_id = '" . $intake . "'
            
                GROUP BY i.intake_id
                ORDER BY i.intake_id DESC
            ";

                    $intakes = Database::search($query);

                    if ($intakes->num_rows > 0) {
                        $intakeData = $intakes->fetch_assoc();
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('course_overview', 0))) {
                            $status->error = 'You are not authorized to this action.';
                        } else {
                            if ($intakeData['total_students'] == 0) {

                                try {
                                    $levels = Database::search("SELECT * FROM `levels` WHERE `intakes_intake_id`='" . $intake . "'");
                                    if ($levels->num_rows > 0) {
                                        while ($level = $levels->fetch_assoc()) {
                                            $subjects = Database::search("SELECT * FROM `subjects` WHERE `levels_level_id`='" . $level['level_id'] . "'");
                                            if ($subjects->num_rows > 0) {
                                                while ($subject = $subjects->fetch_assoc()) {
                                                    $assignments = Database::search("SELECT * FROM `assignments` WHERE `subjects_subject_id`='" . $subject['subject_id'] . "'");
                                                    if ($assignments->num_rows > 0) {
                                                        while ($assignment = $assignments->fetch_assoc()) {
                                                            Database::iud("DELETE FROM `assignments` WHERE `assignment_id` = '" . $assignment['assignment_id'] . "'");

                                                            if (!empty($assignment['attachment'])) {
                                                                $isUsedAnother = Database::search("SELECT * FROM `assignments` WHERE `assignment_id` != '" . $assignment['assignment_id'] . "' AND `attachment` = '" . $assignment['attachment'] . "'");
                                                                if ($isUsedAnother->num_rows == 0) {
                                                                    if (file_exists("../assets/uploads/assignments-attachments/" . $assignment['attachment'])) {
                                                                        unlink("../assets/uploads/assignments-attachments/" . $assignment['attachment']);
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }

                                                    $exams = Database::search("SELECT * FROM `exams` WHERE `subjects_subject_id`='" . $subject['subject_id'] . "'");
                                                    if ($exams->num_rows > 0) {
                                                        while ($exam = $exams->fetch_assoc()) {
                                                            if ($exam['exam_types_exam_type_id'] == 1) {
                                                                Database::iud("DELETE FROM `physical_exam` WHERE `exams_exam_id` = '" . $exam['exam_id'] . "'");
                                                            }

                                                            if ($exam['exam_types_exam_type_id'] == 2) {
                                                                Database::iud("DELETE FROM `online_exam` WHERE `exams_exam_id` = '" . $exam['exam_id'] . "'");
                                                            }

                                                            if ($exam['exam_types_exam_type_id'] == 3) {
                                                                $quiz = Database::search("SELECT * FROM `quiz` WHERE `exams_exam_id`='" . $exam['exam_id'] . "'");
                                                                if ($quiz->num_rows > 0) {
                                                                    $quizData = $quiz->fetch_assoc();
                                                                    $questions = Database::search("SELECT * FROM `questions` WHERE `quiz_quiz_id`='" . $quizData['quiz_id'] . "'");
                                                                    if ($questions->num_rows > 0) {
                                                                        while ($question = $questions->fetch_assoc()) {
                                                                            Database::iud("DELETE FROM `answers` WHERE `questions_question_id` = '" . $question['question_id'] . "'");
                                                                            Database::iud("DELETE FROM `questions` WHERE `question_id` = '" . $question['question_id'] . "'");
                                                                        }
                                                                    }
                                                                    Database::iud("DELETE FROM `quiz` WHERE `quiz_id` = '" . $quizData['quiz_id'] . "'");
                                                                }
                                                                Database::iud("DELETE FROM `quiz` WHERE `exams_exam_id` = '" . $exam['exam_id'] . "'");
                                                            }
                                                            Database::iud("DELETE FROM `exams` WHERE `exam_id` = '" . $exam['exam_id'] . "'");
                                                        }
                                                    }

                                                    $timeTable = Database::search("SELECT * FROM `time_table` WHERE `subjects_subject_id`='" . $subject['subject_id'] . "'");
                                                    if ($timeTable->num_rows > 0) {
                                                        while ($timeTableData = $timeTable->fetch_assoc()) {
                                                            $attachments = Database::search("SELECT * FROM `attachments` WHERE `time_table_time_table_id`='" . $timeTableData['time_table_id'] . "'");
                                                            if ($attachments->num_rows > 0) {
                                                                while ($attachmentData = $attachments->fetch_assoc()) {
                                                                    $isUsedAnother = Database::search("SELECT * FROM `attachments` WHERE `time_table_time_table_id` != '" . $timeTableData['time_table_id'] . "' AND `attachment` = '" . $attachmentData["attachment"] . "'");
                                                                    if ($isUsedAnother->num_rows == 0) {
                                                                        $attachment_path = "../assets/uploads/lectures-attachments/" . $attachmentData["attachment"];
                                                                        if (file_exists($attachment_path)) {
                                                                            unlink($attachment_path);
                                                                        }
                                                                    }
                                                                    Database::iud("DELETE FROM `attachments` WHERE `attachment_id`='" . $attachmentData["attachment_id"] . "'");
                                                                }
                                                            }
                                                            Database::iud("DELETE FROM `time_table` WHERE `time_table_id` = '" . $timeTableData['time_table_id'] . "'");
                                                        }
                                                    }

                                                    $lecturers = Database::search("SELECT * FROM `lecturers` WHERE `subjects_subject_id`='" . $subject['subject_id'] . "'");
                                                    if ($lecturers->num_rows > 0) {
                                                        while ($lecturer = $lecturers->fetch_assoc()) {
                                                            Database::iud("DELETE FROM `lecturers` WHERE `lecturers_id` = '" . $lecturer['lecturers_id'] . "'");
                                                        }
                                                    }

                                                    if (!empty($subject['subject_cover'])) {
                                                        $isUsedAnother = Database::search("SELECT * FROM `subjects` WHERE `subject_id` != '" . $subject['subject_id'] . "' AND `subject_cover` = '" . $subject['subject_cover'] . "'");
                                                        if ($isUsedAnother->num_rows == 0) {
                                                            $subject_cover_path = "../assets/uploads/subjects-covers/" . $subject['subject_cover'];
                                                            if (file_exists($subject_cover_path)) {
                                                                unlink($subject_cover_path);
                                                            }
                                                        }
                                                    }
                                                    Database::iud("DELETE FROM `subjects` WHERE `subject_id` = '" . $subject['subject_id'] . "'");
                                                }
                                            }

                                            if (!empty($level['level_cover'])) {
                                                $isUsedAnother = Database::search("SELECT * FROM `levels` WHERE `level_id` != '" . $level['level_id'] . "' AND `level_cover` = '" . $level['level_cover'] . "'");
                                                if ($isUsedAnother->num_rows == 0) {
                                                    $level_cover_path = "../assets/uploads/levels-covers/" . $level['level_cover'];
                                                    if (file_exists($level_cover_path)) {
                                                        unlink($level_cover_path);
                                                    }
                                                }
                                            }
                                            Database::iud("DELETE FROM `levels` WHERE `level_id` = '" . $level['level_id'] . "'");
                                        }
                                    }

                                    $groupHasIntakes = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intakes_intake_id` = '" . $intake . "'");
                                    if ($groupHasIntakes->num_rows > 0) {
                                        while ($group = $groupHasIntakes->fetch_assoc()) {
                                            $intakeGroupPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments` WHERE `intake_has_payment_plan_id`='" . $group['intake_has_payment_plan_id'] . "'");
                                            if ($intakeGroupPayments->num_rows > 0) {
                                                while ($payment = $intakeGroupPayments->fetch_assoc()) {
                                                    $paymentPanelties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id`='" . $payment['intake_payments_plan_has_payment_id'] . "'");
                                                    if ($paymentPanelties->num_rows > 0) {
                                                        while ($panelty = $paymentPanelties->fetch_assoc()) {
                                                            Database::iud("DELETE FROM `intake_has_payments_has_penalties` WHERE `intake_has_payments_has_penalty_id`='" . $panelty['intake_has_payments_has_penalty_id'] . "'");
                                                        }
                                                    }
                                                    Database::iud("DELETE FROM `intake_payments_plan_has_payments` WHERE `intake_payments_plan_has_payment_id`='" . $payment['intake_payments_plan_has_payment_id'] . "'");
                                                }
                                            }
                                            Database::iud("DELETE FROM `intake_has_payment_plans_log` WHERE `intake_has_payment_plan_id`='" . $group['intake_has_payment_plan_id'] . "'");
                                            Database::iud("DELETE FROM `intake_has_payment_plans` WHERE `intake_has_payment_plan_id`='" . $group['intake_has_payment_plan_id'] . "'");
                                        }
                                    }

                                    if (!empty($intakeData['intake_cover'])) {
                                        $isUsedAnother = Database::search("SELECT * FROM `intakes` WHERE `intake_id` != '" . $intake . "' AND `intake_cover` = '" . $intakeData['intake_cover'] . "'");
                                        if ($isUsedAnother->num_rows == 0) {
                                            $intake_cover_path = "../assets/uploads/intakes-covers/" . $intakeData['intake_cover'];
                                            if (file_exists($intake_cover_path)) {
                                                unlink($intake_cover_path);
                                            }
                                        }
                                    }
                                    Database::iud("DELETE FROM `intakes` WHERE `intake_id` = '" . $intake . "'");

                                    $status->done = 'Intake removed successfully';
                                } catch (Exception $e) {
                                    $status->error = "Something went wrong ".$e->getMessage();
                                }
                            } else {
                                $status->error = 'Intake has active students';
                            }
                        }
                    } else {
                        $status->error = 'Invalid Intake ID';
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
