<?php
error_reporting(1);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $intake_id = isset($_POST['intake_id']) ? $_POST['intake_id'] : '';
            $programme_id = isset($_POST['programme_id']) ? $_POST['programme_id'] : '';

            if (empty($intake_id)) {
                $status->error = "Please select an intake to duplicate";
            } else {
                $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id`='" . $intake_id . "'");
                if ($isValidIntake->num_rows == 0) {
                    $status->error = "Selected intake is not valid";
                } else {
                    $intakeData = $isValidIntake->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('programme', 2,  $intakeData['programmes_programme_id']))) {
                        $status->error = "You don't have permission to duplicate intakes to this programme";
                    } else {

                        $intake_name = isset($_POST['intake_name']) ? trim($_POST['intake_name']) : '';

                        $intake_cover = null;
                        if (empty($intake_name)) {
                            $status->name =  "Please enter intake Name";
                        } else if (strlen($intake_name) > 100) {
                            $status->name =  "Intake name should be less than 100 characters";
                        } else {

                            $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake`='" . $intake_name . "' AND `intake_id` != '" . $intake_id . "' AND `programmes_programme_id` = '" . $intakeData['programmes_programme_id'] . "'");
                            $row = $isValidIntake->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->name =  "Intake name already exists in this intake";
                            }
                        }



                        if (isset($_FILES["intake_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["intake_cover"]["type"];
                            if (!in_array($file_ex, $allowed_image_extentions)) {
                                $status->cover = "Only jpg,jpeg,png and svg allowed";
                            } else {
                                $new_file_extention;
                                if ($file_ex == "image/jpg") {
                                    $new_file_extention = ".jpg";
                                } else if ($file_ex == "image/jpeg") {
                                    $new_file_extention = ".jpeg";
                                } else if ($file_ex == "image/png") {
                                    $new_file_extention = ".png";
                                } else if ($file_ex == "image/svg+xml") {
                                    $new_file_extention = ".svg";
                                }
                                if ($_FILES["intake_cover"]["error"] === 0) {
                                    if ($_FILES["intake_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["intake_cover"]["tmp_name"])) {
                                            $intake_cover = uniqid() . $new_file_extention;
                                        } else {
                                            $status->cover = "Invalid image file!";
                                        }
                                    } else {
                                        $status->cover = "Maximum allowed file size is 2MB";
                                    }
                                } else {
                                    $status->cover = "There is an error with the selected image";
                                }
                            }
                        }

                        $requiredProperties = [
                            'name',
                            'start',
                            'end',
                            'cover'
                        ];
                        $allGood = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allGood = false;
                                break;
                            }
                        }

                        if ($allGood) {

                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');

                            Database::iud("
                                                INSERT INTO `intakes` (
                                                    `intake`,
                                                    `programmes_programme_id`,
                                                    `intakes_order`,
                                                    `intake_cover`,
                                                    `users_user_id`,
                                                    `date_added`
                                                ) VALUES (
                                                    '" . Database::escape($intake_name) . "',
                                                    '" . $programme_id . "',
                                                    '0',
                                                    '" .  $intake_cover . "',
                                                    '" . $sessionUserObject['user_id'] . "',
                                                    '" . $date . "'
                                                )
                                            ");
                            if (isset($_FILES["intake_cover"])) {
                                $cover_path = "../assets/imgs/intakes-covers/";
                                $qimg_path = $cover_path . $intake_cover;
                                move_uploaded_file($_FILES["intake_cover"]["tmp_name"], $qimg_path);
                            }

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

                                            $subjectAttachment = Database::search("SELECT * FROM `subject_has_attachments` WHERE `subjects_subject_id`='" . $levelSubject['subject_id'] . "'");
                                            if ($subjectAttachment->num_rows > 0) {
                                                while ($attachment = $subjectAttachment->fetch_assoc()) {
                                                    Database::iud("
                                                INSERT INTO `subject_has_attachments` 
                                                (`attachment`,
                                                `attachment_name`,
                                                 `subjects_subject_id`,
                                                 `date_added`,
                                                 `users_user_id`,
                                                 `attachments_order`) 
                                                VALUES (
                                                    '" . $attachment['attachment'] . "',
                                                    '" . $attachment['attachment_name'] . "',
                                                    '" . $newSubjectId . "',
                                                    '" . $date . "',
                                                    '" . $sessionUserObject['user_id'] . "',
                                                    '0'
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
                                                         
                                                           `added_date`,
                                                            `users_user_id`,
                                                             `subjects_subject_id`, 
                                                              `assignments_order`,
                                                               `assignment_status_assignment_status_id`,
                                                               
                                                                  `plagiarism_status_plagiarism_status_id`";

                                                    $values = "'" . Database::escape($subjectAssignment['assignment_code']) . "',
                                                                '" . Database::escape($subjectAssignment['assignment']) . "',
                                                                '" . $subjectAssignment['attachment'] . "',
                                                             
                                                                '" . $date . "',
                                                                '" . $sessionUserObject['user_id'] . "',
                                                                '" . $newSubjectId . "',
                                                                '" . $subjectAssignment['assignments_order'] . "',
                                                                '" . $subjectAssignment['assignment_status_assignment_status_id'] . "',
                                                              
                                                                '" . $subjectAssignment['plagiarism_status_plagiarism_status_id'] . "'";

                                                    if ($subjectAssignment['start_date'] != null) {
                                                        $columns .= ", `start_date`";
                                                        $values .= ", '" . $subjectAssignment['start_date'] . "'";
                                                    }

                                                    if ($subjectAssignment['end_date'] != null) {
                                                        $columns .= ", `end_date`";
                                                        $values .= ", '" . $subjectAssignment['end_date'] . "'";
                                                    }

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


                            $systemPaymentPlans = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intakes_intake_id`='" . $intake_id . "'");
                            if ($systemPaymentPlans->num_rows > 0) {
                                while ($systemPaymentPlanData = $systemPaymentPlans->fetch_assoc()) {
                                    Database::iud("INSERT INTO `intake_has_payment_plans` 
                                    (`intakes_intake_id`,
                                    `payment_plan_name`,
                                    `date_added`,
                                    `users_user_id`,
                                    `is_default_plan`,
                                    `payment_category_payment_category_id`,
                                    `payment_plan_payment_plan_id`,
                                    `amount`,
                                    `discount`,
                                    `discount_type_discount_type_id`,
                                    `discount_note`) 
                                    VALUES 
                                    (
                                        '" . $newIntakeId . "',
                                        '" . $systemPaymentPlanData['payment_plan_name'] . "',
                                        '" . $date . "',
                                        '" . $sessionUserObject['user_id'] . "',
                                        '" . $systemPaymentPlanData['is_default_plan'] . "',
                                        '" . $systemPaymentPlanData['payment_category_payment_category_id'] . "',
                                        '" . $systemPaymentPlanData['payment_plan_payment_plan_id'] . "',
                                        '" . $systemPaymentPlanData['amount'] . "',
                                        '" . $systemPaymentPlanData['discount'] . "',
                                        '" . $systemPaymentPlanData['discount_type_discount_type_id'] . "',
                                        '" . $systemPaymentPlanData['discount_note'] . "'
                                    )
                                   ");

                                    $planId = Database::$connection->insert_id;

                                    $planHasPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments` WHERE `intake_has_payment_plan_id`='" . $systemPaymentPlanData['intake_has_payment_plan_id'] . "'");
                                    if ($planHasPayments->num_rows > 0) {
                                        while ($planHasPaymentData = $planHasPayments->fetch_assoc()) {
                                            $dueDate = empty($planHasPaymentData['due_date'])
                                                ? "NULL"
                                                : "'" . $planHasPaymentData['due_date'] . "'";

                                            Database::iud("
                                                        INSERT INTO `intake_payments_plan_has_payments` 
                                                        (
                                                            `intake_has_payment_plan_id`,
                                                            `payment_type_payment_type_id`,
                                                            `amount`,
                                                            `discount`,
                                                            `discount_type_discount_type_id`,
                                                            `discount_note`,
                                                            `panelty_payment_option_panelty_payment_option_id`,
                                                            `due_date`
                                                        )
                                                        VALUES
                                                        (
                                                            '" . $planId . "',
                                                            '" . $planHasPaymentData['payment_type_payment_type_id'] . "',
                                                            '" . $planHasPaymentData['amount'] . "',
                                                            '" . $planHasPaymentData['discount'] . "',
                                                            '" . $planHasPaymentData['discount_type_discount_type_id'] . "',
                                                            '" . $planHasPaymentData['discount_note'] . "',
                                                            '" . $planHasPaymentData['panelty_payment_option_panelty_payment_option_id'] . "',
                                                            " . $dueDate . "
                                                        )
                                                        ");

                                            $planHasPaymentId = Database::$connection->insert_id;

                                            $paymentHasPenalties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` 
                                           WHERE `intake_payments_plan_has_payment_id`='" . $planHasPaymentData['intake_payments_plan_has_payment_id'] . "'");
                                            if ($paymentHasPenalties->num_rows > 0) {
                                                while ($paymentHasPenaltyData = $paymentHasPenalties->fetch_assoc()) {
                                                    Database::iud("INSERT INTO `intake_has_payments_has_penalties` 
                                                     (`intake_payments_plan_has_payment_id`,
                                                     `amount`,
                                                     `days`,
                                                     `panelty_payment_type_panelty_payment_type_id`)
                                                     VALUES
                                                     (
                                                        '" . $planHasPaymentId . "',
                                                        '" . $paymentHasPenaltyData['amount'] . "',
                                                        '" . $paymentHasPenaltyData['days'] . "',
                                                        '" . $paymentHasPenaltyData['panelty_payment_type_panelty_payment_type_id'] . "'
                                                     ) 
                                                     
                                                    ");
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
