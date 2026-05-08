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
                    $intake_id = isset($decoded['intake_id']) ? $decoded['intake_id'] : '';

                    if (empty($intake_id)) {
                        $status->error = "Invalid intake Id";
                    } else if (!is_numeric($intake_id)) {
                        $status->error = "Invalid intake Id";
                    } else {
                        $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id`='" . $intake_id . "'");
                        if ($isValidIntake->num_rows == 0) {
                            $status->error = "Invalid programme Id";
                        } else {
                            $intakeData = $isValidIntake->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('programme', 2,  $intakeData['programmes_programme_id']))) {
                                $status->error = "You don't have permission to duplicate intakes to this programme";
                            } else {

                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                $baseName = $intakeData['intake'];
                                $copyCount = 1;

                                do {
                                    $duplicateIntakeName = $baseName . " (copy " . $copyCount . ")";
                                    $check = Database::search("
                                    SELECT COUNT(*) AS count 
                                    FROM `intakes` 
                                    WHERE `intake` = '" . Database::escape($duplicateIntakeName) . "'
                                ");
                                    $exists = $check->fetch_assoc()['count'];
                                    $copyCount++;
                                } while ($exists > 0);

                                Database::iud("
                            INSERT INTO `intakes` (
                                `intake`,
                                `programmes_programme_id`,
                                `intakes_order`,
                                `intake_cover`,
                                `users_user_id`,
                                `date_added`
                            ) VALUES (
                                '" . Database::escape($duplicateIntakeName) . "',
                                '" . $intakeData['programmes_programme_id'] . "',
                                '" . ($intakeData['intakes_order'] + 1) . "',
                                '" . $intakeData['intake_cover'] . "',
                                '" . $sessionUserObject['user_id'] . "',
                                '" . $date . "'
                            )
                        ");

                                $newIntakeId = Database::$connection->insert_id;

                                $intakePermissions = Database::search("SELECT * FROM `users_has_permission_to_intakes` WHERE `intakes_intake_id`='" . $intake_id . "'");
                                if ($intakePermissions->num_rows > 0) {
                                    while ($intakePermission = $intakePermissions->fetch_assoc()) {
                                        Database::iud("
                                INSERT INTO `users_has_permission_to_intakes` 
                                (`users_user_id`, `intakes_intake_id`, `permission_to_permission_to_id`, `added_by`,`status_status_id`,`added_at`) 
                                VALUES (
                                    '" . $intakePermission['users_user_id'] . "',
                                    '" . $newIntakeId . "',
                                    '" . $intakePermission['permission_to_permission_to_id'] . "',
                                    '" . $sessionUserObject['user_id'] . "',
                                    '" . $intakePermission['status_status_id'] . "',
                                    '" . $date . "'
                                    )");
                                    }
                                }

                                $intakeLevels = Database::search("SELECT * FROM `levels` WHERE `intakes_intake_id`='" . $intake_id . "'");
                                if ($intakeLevels->num_rows > 0) {
                                    while ($intakeLevel = $intakeLevels->fetch_assoc()) {
                                        Database::iud("
                            INSERT INTO `levels` 
                            (`level`, `level_cover`,`intakes_intake_id`,`levels_order`,`date_added`,`users_user_id`) 
                            VALUES (
                                '" . Database::escape($intakeLevel['level']) . "',
                                '" . $intakeLevel['level_cover'] . "',
                                '" . $newIntakeId . "',
                                '" . $intakeLevel['levels_order'] . "',
                                '" . $date . "',
                                '" . $sessionUserObject['user_id'] . "')");

                                        $newLevelId = Database::$connection->insert_id;

                                        $levelPermissions = Database::search("SELECT * FROM `users_has_permission_to_levels` WHERE `levels_level_id`='" . $intakeLevel['level_id'] . "'");
                                        if ($levelPermissions->num_rows > 0) {
                                            while ($intakePermission = $levelPermissions->fetch_assoc()) {
                                                Database::iud("
                                        INSERT INTO `users_has_permission_to_levels` 
                                        (`users_user_id`, `levels_level_id`, `permission_to_permission_to_id`, `added_by`,`status_status_id`,`added_at`) 
                                        VALUES (
                                            '" . $intakePermission['users_user_id'] . "',
                                            '" . $newLevelId . "',
                                            '" . $intakePermission['permission_to_permission_to_id'] . "',
                                            '" . $sessionUserObject['user_id'] . "',
                                            '" . $intakePermission['status_status_id'] . "',
                                            '" . $date . "'
                                            )");
                                            }
                                        }

                                        $levelSubjects = Database::search("SELECT * FROM `subjects` WHERE `levels_level_id`='" . $intakeLevel['level_id'] . "'");
                                        if ($levelSubjects->num_rows > 0) {
                                            while ($levelSubject = $levelSubjects->fetch_assoc()) {

                                                $columns = "`subject`,
                                                `subject_cover`,
                                                `max_course_hours`,
                                                `levels_level_id`,
                                                `subjects_order`,
                                                `date_added`,
                                                `users_user_id`,
                                                `subject_type_subject_type_id`";

                                                $values = "'" . Database::escape($levelSubject['subject']) . "',
                                                '" . $levelSubject['subject_cover'] . "',
                                                '" . $levelSubject['max_course_hours'] . "',
                                                '" . $newLevelId . "',
                                                '" . $levelSubject['subjects_order'] . "',
                                                '" . $date . "',
                                                '" . $sessionUserObject['user_id'] . "',
                                                '" . $levelSubject['subject_type_subject_type_id'] . "'
                                               ";

                                                if ($levelSubject['subject_type_subject_type_id'] == 2) {
                                                    $columns .= ", `subject_group_subject_group_id`";
                                                    $values .= ", '" . $levelSubject['subject_group_subject_group_id'] . "'";
                                                }

                                                Database::iud("   INSERT INTO `subjects`  (" . $columns . ") VALUES ( " . $values . " )");

                                                $newSubjectId = Database::$connection->insert_id;

                                                $subjectPermissions = Database::search("SELECT * FROM `users_has_permission_to_subject` WHERE `subjects_subject_id`='" . $intakeLevel['level_id'] . "'");
                                                if ($subjectPermissions->num_rows > 0) {
                                                    while ($intakePermission = $subjectPermissions->fetch_assoc()) {
                                                        Database::iud("
                                                INSERT INTO `users_has_permission_to_subject` 
                                                (`users_user_id`, `subjects_subject_id`, `permission_to_permission_to_id`, `added_by`,`status_status_id`,`added_at`) 
                                                VALUES (
                                                    '" . $intakePermission['users_user_id'] . "',
                                                    '" . $newSubjectId . "',
                                                    '" . $intakePermission['permission_to_permission_to_id'] . "',
                                                    '" . $sessionUserObject['user_id'] . "',
                                                    '" . $intakePermission['status_status_id'] . "',
                                                    '" . $date . "'
                                                    )");
                                                    }
                                                }

                                                $subjectLecturers = Database::search("SELECT * FROM `lecturers` WHERE `subjects_subject_id`='" . $levelSubject['subject_id'] . "'");
                                                if ($subjectLecturers->num_rows > 0) {
                                                    while ($subjectLecturer = $subjectLecturers->fetch_assoc()) {
                                                        Database::iud("
                                                INSERT INTO `lecturers` 
                                                (`users_user_id`,
                                                 `subjects_subject_id`,
                                                 `added_by`,
                                                 `added_at`,
                                                 `status_status_id`,
                                                 `per_assignment_fee`,
                                                 `per_examination_fee`,
                                                 `hourly_rate`) 
                                                VALUES (
                                                    '" . $subjectLecturer['users_user_id'] . "',
                                                    '" . $newSubjectId . "',
                                                    '" . $sessionUserObject['user_id'] . "',
                                                    '" . $date . "',
                                                    '" . $subjectLecturer['status_status_id'] . "',
                                                    '" . $subjectLecturer['per_assignment_fee'] . "',
                                                    '" . $subjectLecturer['per_examination_fee'] . "',
                                                    '" . $subjectLecturer['hourly_rate'] . "'
                                                    )");
                                                    }
                                                }

                                                $subjectAssignments = Database::search("SELECT * FROM `assignments` WHERE `subjects_subject_id`='" . $levelSubject['subject_id'] . "'");
                                                if ($subjectAssignments->num_rows > 0) {
                                                    while ($subjectAssignment = $subjectAssignments->fetch_assoc()) {

                                                        $columns = "`assignment_code`,
                                                        `assignment`,
                                                         `attachment`,
                                                          `start_date`,
                                                          `end_date`,
                                                           `added_date`,
                                                            `users_user_id`,
                                                             `subjects_subject_id`, 
                                                              `assignments_order`,
                                                               `assignment_status_assignment_status_id`,
                                                               
                                                                  `plagiarism_status_plagiarism_status_id`";

                                                        $values = "'" . Database::escape($subjectAssignment['assignment_code']) . "',
                                                                '" . Database::escape($subjectAssignment['assignment']) . "',
                                                                '" . $subjectAssignment['attachment'] . "',
                                                                '" . $subjectAssignment['start_date'] . "',
                                                                '" . $subjectAssignment['end_date'] . "',
                                                                '" . $date . "',
                                                                '" . $sessionUserObject['user_id'] . "',
                                                                '" . $newSubjectId . "',
                                                                '" . $subjectAssignment['assignments_order'] . "',
                                                                '" . $subjectAssignment['assignment_status_assignment_status_id'] . "',
                                                              
                                                                '" . $subjectAssignment['plagiarism_status_plagiarism_status_id'] . "'";

                                                        if ($subjectAssignment['late_submission_end_date'] != null) {
                                                            $columns .= ", `late_submission_end_date`";
                                                            $values .= ", '" . $subjectAssignment['late_submission_end_date'] . "'";
                                                        }

                                                        if ($subjectAssignment['recorrection_apply_end_date'] != null) {
                                                            $columns .= ", `recorrection_apply_end_date`";
                                                            $values .= ", '" . $subjectAssignment['recorrection_apply_end_date'] . "'";
                                                        }

                                                        Database::iud("INSERT INTO `assignments`  (" . $columns . ") VALUES ( " . $values . " )");
                                                    }
                                                }

                                                $subjectExams = Database::search("SELECT * FROM `exams` WHERE `subjects_subject_id`='" . $levelSubject['subject_id'] . "'");
                                                if ($subjectExams->num_rows > 0) {
                                                    while ($subjectExam = $subjectExams->fetch_assoc()) {
                                                        Database::iud("
                                                INSERT INTO `exams` 
                                               (`exam_name`, `exam_types_exam_type_id`, `exam_date`, `subjects_subject_id`, `exams_order`, `date_added`, `users_user_id`)
                                                VALUES (
                                                '" . Database::escape($subjectExam['exam_name']) . "',
                                                '" . $subjectExam['exam_types_exam_type_id'] . "',
                                                '" . $subjectExam['exam_date'] . "',
                                                '" . $newSubjectId . "',
                                                '" . $subjectExam['exams_order'] . "',
                                                '" . $date . "',
                                                '" . $sessionUserObject['user_id'] . "'
                                                    )");
                                                    }
                                                }

                                                $subjectPhysicalExams = Database::search("SELECT * FROM `physical_exam`
                                                INNER JOIN `exams` ON `physical_exam`.`exams_exam_id` = `exams`.`exam_id` 
                                                WHERE `subjects_subject_id`='" . $levelSubject['subject_id'] . "'");
                                                if ($subjectPhysicalExams->num_rows > 0) {
                                                    while ($subjectPhysicalExam = $subjectPhysicalExams->fetch_assoc()) {
                                                        Database::iud("
                                                INSERT INTO `physical_exam` 
                                              (`start_time`,
                                               `end_time`,
                                                `duration`,
                                                 `location`,
                                                  `exams_exam_id`,
                                                   `date_added`,
                                                    `users_user_id`)
                                                VALUES (
                                                    '" . $subjectPhysicalExam['start_time'] . "',
                                                    '" . $subjectPhysicalExam['end_time'] . "',
                                                    '" . $subjectPhysicalExam['duration'] . "',
                                                    '" . $subjectPhysicalExam['location'] . "',
                                                    '" . $subjectPhysicalExam['exams_exam_id'] . "',    
                                                    '" . $date . "',
                                                    '" . $sessionUserObject['user_id'] . "'
                                                    )");
                                                    }
                                                }

                                                $subjectOnlineExams = Database::search("SELECT * FROM `online_exam` 
                                                INNER JOIN `exams` ON `online_exam`.`exams_exam_id` = `exams`.`exam_id`
                                                WHERE `subjects_subject_id`='" . $levelSubject['subject_id'] . "'");

                                                if ($subjectOnlineExams->num_rows > 0) {
                                                    while ($subjectOnlineExam = $subjectOnlineExams->fetch_assoc()) {
                                                        Database::iud("
                                                INSERT INTO `online_exam` 
                                              (`start_time`,
                                               `end_time`,
                                                `duration`,
                                                 `exams_exam_id`,
                                                  `date_added`,
                                                   `users_user_id`)
                                                VALUES (
                                                    '" . $subjectOnlineExam['start_time'] . "',
                                                    '" . $subjectOnlineExam['end_time'] . "',
                                                    '" . $subjectOnlineExam['duration'] . "',
                                                    '" . $subjectOnlineExam['exams_exam_id'] . "',
                                                    '" . $date . "',
                                                    '" . $sessionUserObject['user_id'] . "'
                                                    )");

                                                        $newOnlineExamId = Database::$connection->insert_id;

                                                        $onlineExamQuestions = Database::search("SELECT * FROM `essay_questions` 
                                                    WHERE `online_exam_online_exam_id`='" . $subjectOnlineExam['online_exam_id'] . "'");

                                                        if ($onlineExamQuestions->num_rows > 0) {
                                                            while ($onlineExamQuestion = $onlineExamQuestions->fetch_assoc()) {
                                                                Database::iud("
                                                            INSERT INTO `essay_questions` 
                                                          (`question`,
                                                           `online_exam_online_exam_id`
                                                           )
                                                            VALUES (
                                                                '" . $onlineExamQuestion['question'] . "',
                                                                '" . $newOnlineExamId . "'
                                                            )");
                                                            }
                                                        }
                                                    }
                                                }

                                                $subjectQuiz = Database::search("SELECT * FROM `quiz`
                                        INNER JOIN `exams` ON `quiz`.`exams_exam_id` = `exams`.`exam_id` 
                                        WHERE `subjects_subject_id`='" . $levelSubject['subject_id'] . "'");

                                                if ($subjectQuiz->num_rows > 0) {
                                                    while ($subjectQuizzes = $subjectQuiz->fetch_assoc()) {
                                                        Database::iud("
                                                INSERT INTO `quiz` 
                                             (
                                              `start_time`,
                                               `end_date`,
                                                `end_time`,
                                                 `duration`,
                                                  `exams_exam_id`,
                                                   `quiz_type_quiz_type_id`,
                                                    `date_added`,
                                                     `users_user_id`,
                                                      `quiz_order`,
                                                       `quiz_order_quiz_order_id`)
                                                VALUES (
                                                 '" . $subjectQuizzes['start_time'] . "',
                                                 '" . $subjectQuizzes['end_date'] . "',
                                                 '" . $subjectQuizzes['end_time'] . "',
                                                 '" . $subjectQuizzes['duration'] . "',
                                                 '" . $subjectQuizzes['exams_exam_id'] . "',
                                                 '" . $subjectQuizzes['quiz_type_quiz_type_id'] . "',
                                                 '" . $date . "',
                                                 '" . $sessionUserObject['user_id'] . "',
                                                 '" . $subjectQuizzes['quiz_order'] . "',
                                                 '" . $subjectQuizzes['quiz_order_quiz_order_id'] . "'
                                                    )");

                                                        $newQuizId = Database::$connection->insert_id;

                                                        $quizQuestions = Database::search("SELECT * FROM `questions` WHERE `quiz_quiz_id`='" . $subjectQuizzes['quiz_id'] . "'");

                                                        if ($quizQuestions->num_rows > 0) {
                                                            while ($quizQuestion = $quizQuestions->fetch_assoc()) {
                                                                Database::iud("
                                                            INSERT INTO `questions` 
                                                          (`question`,
                                                           `quiz_quiz_id`,
                                                            `correct_answer`,
                                                             `questions_order`)
                                                            VALUES (
                                                                '" . $quizQuestion['question'] . "',
                                                                '" . $newQuizId . "',
                                                                '" . $quizQuestion['correct_answer'] . "',
                                                                '" . $quizQuestion['questions_order'] . "'
                                                                )");

                                                                $newQuestionId = Database::$connection->insert_id;

                                                                $questionAnswers = Database::search("SELECT * FROM `answers` WHERE `questions_question_id`='" . $quizQuestion['question_id'] . "'");

                                                                if ($questionAnswers->num_rows > 0) {
                                                                    while ($questionAnswer = $questionAnswers->fetch_assoc()) {
                                                                        Database::iud("
                                                                    INSERT INTO `answers` 
                                                                  (`answer`,
                                                                   `questions_question_id`,
                                                                    `answer_order`)
                                                                    VALUES (
                                                                        '" . $questionAnswer['answer'] . "',
                                                                        '" . $newQuestionId . "',
                                                                        '" . $questionAnswer['answer_order'] . "'
                                                                        )");
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $status->done = 'Intake Duplicated Successfully';
                            }
                        }
                    }
                }
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
