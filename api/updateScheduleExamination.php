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

            $exam_id = isset($_POST['exam_id']) ? $_POST['exam_id'] : 0;
            $code = isset($_POST['code']) ? $_POST['code'] : '';
            $topic = isset($_POST['topic']) ? $_POST['topic'] : '';
            $date = isset($_POST['date']) ? $_POST['date'] : '';
            $type = isset($_POST['type']) ? $_POST['type'] : 0;

            if (empty($exam_id)) {
                $status->error = 'Exam ID is required';
            } else if ($exam_id == 0) {
                $status->error = 'Invalid exam ID';
            } else {
                $isValidExam = Database::search("SELECT * FROM `exams` WHERE `exam_id` = '" . $exam_id . "'");
                if ($isValidExam->num_rows == 0) {
                    $status->error = 'Invalid Exam ID';
                } else {
                    $examData = $isValidExam->fetch_assoc();
                    if ($sessionUserRole == 'academic') {
                        $isValidAddUser = Database::search("SELECT COUNT(*) AS student_count FROM `users_has_intakes` 
                        INNER JOIN `levels` ON `users_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id` 
                        INNER JOIN `subjects` ON `levels`.`level_id` = `subjects`.`levels_level_id`
                        WHERE `users_has_intakes`.`users_user_id` = '" . $sessionUserObject['user_id'] . "' AND `subjects`.`subject_id`='" . $subject_id . "'")->fetch_assoc();
                        if ($isValidAddUser['count'] == 0) {
                            $status->error = 'You do not have access to this subject';
                        }
                    }
                    if (!property_exists($status, 'error')) {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $addTime = $dateTime->format('Y-m-d H:i:s');
                        $today = $dateTime->format('Y-m-d');
                        $now = $dateTime->format('Y-m-d H:i');
                        $nowTime = $dateTime->format('H:i');

                        // if (empty($code)) {
                        //     $status->code = 'Assignment code is required';
                        // } else if (strlen($code) > 45) {
                        //     $status->code = 'Assignment code should be less than 45 characters';
                        // } else {
                        //     $isValidCode = Database::search("SELECT COUNT(*) as 'count' FROM `assignments` WHERE `assignment_code` = '" . $code . "'");
                        //     $examData = $isValidCode->fetch_assoc();
                        //     if ($examData['count'] > 0) {
                        //         $status->code = 'Assignment code already exists';
                        //     }
                        // }

                        if (empty($topic)) {
                            $status->topic = 'Examination name is required';
                        }

                        if (empty($date)) {
                            $status->date = 'Examination date is required';
                        } else if (!checkIfFutureDateTime($date, $nowTime)) {
                            $status->date = 'The selected date is in the past';
                        }

                        if (empty($type) || $type == 0) {
                            $status->type = 'Examination type is required';
                        } else if (!is_numeric($type)) {
                            $status->type = 'Examination type is invalid';
                        } else if ($type < 0 || $type > 3) {
                            $status->type = 'Examination type is invalid';
                        } else {

                            if ($type == 1) {
                                $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
                                $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
                                $duration = isset($_POST['duration']) ? $_POST['duration'] : '';
                                $location = isset($_POST['location']) ? $_POST['location'] : '';

                                if (empty($start_time)) {
                                    $status->physical_start_time = 'Start time is required';
                                } else if (!checkIfFutureDateTime($date, $start_time)) {
                                    $status->physical_start_time = 'The selected start time is in the past';
                                }

                                if (empty($end_time)) {
                                    $status->physical_end_time = 'End time is required';
                                }

                                if (empty($location)) {
                                    $status->location = 'Location is required';
                                }

                                if (empty($duration)) {
                                    $status->physical_duration = 'Duration is required';
                                }
                            } else {
                                $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
                                $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
                                $duration = isset($_POST['duration']) ? $_POST['duration'] : '';

                                if (empty($start_time)) {
                                    $status->online_start_time = 'Start time is required';
                                } else if (!checkIfFutureDateTime($date, $start_time)) {
                                    $status->online_start_time = 'The selected start time is in the past';
                                }

                                if (empty($end_time)) {
                                    $status->online_end_time = 'End time is required';
                                }

                                if (empty($duration)) {
                                    $status->online_duration = 'Duration is required';
                                }

                                if ($type == 3) {
                                    $quiz_type = isset($_POST['quiz_type']) ? $_POST['quiz_type'] : '';

                                    if (empty($quiz_type) || $quiz_type == 0) {
                                        $status->quiz_type = 'Quiz type is required';
                                    }
                                }
                                $csvData = [];
                                if ($examData["exam_types_exam_type_id"] != $type) {
                                    if (!isset($_FILES["file"])) {
                                        $status->file = 'File is required';
                                    } else {
                                        $csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');
                                        if (empty($_FILES['file']['name']) && !in_array($_FILES['file']['type'], $csvMimes)) {
                                            $status->file = "Please select a valid CSV file";
                                        } else {
                                            if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
                                                $status->file = "Please select a valid CSV file";
                                            } else {
                                                $csvFile = fopen($_FILES['file']['tmp_name'], 'r');
                                                fgetcsv($csvFile);

                                                while (($line = fgetcsv($csvFile)) !== false) {
                                                    if (!empty($line) && count(array_filter($line)) > 0) {
                                                        $csvData[] = $line;
                                                    }
                                                }

                                                fclose($csvFile);
                                                if (count($csvData) == 0) {
                                                    $status->file = "Please select a valid CSV file";
                                                } else if (count($csvData) > 100) {
                                                    $status->file = "Only 100 questions can be uploaded at a time";
                                                }

                                                if (!property_exists($status, 'file') && !property_exists($status, 'error')) {
                                                    if ($type == 3) {
                                                        $examDataNumber = 1;

                                                        $linesErrors = [];
                                                        $qidArray = [];

                                                        foreach ($csvData as $data) {
                                                            $question = $data[0];
                                                            $option1 = $data[1];
                                                            $option2 = $data[2];
                                                            $option3 = $data[3];
                                                            $option4 = $data[4];
                                                            $correct = $data[5];

                                                            $lineErrors = [];

                                                            if (empty($question)) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Question is required";
                                                            }

                                                            if (empty($option1)) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Option 1 is required";
                                                            }

                                                            if (empty($option2)) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Option 2 is required";
                                                            }

                                                            if (empty($option3)) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Option 3 is required";
                                                            }

                                                            if (empty($option4)) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Option 4 is required";
                                                            }

                                                            if (empty($correct)) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Correct Answer is required";
                                                            } else if (is_int($correct)) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Correct Answer is not matching";
                                                            } else if ((int)$correct < 1 || (int)$correct > 4) {
                                                                $lineErrors[] =  "Row " . $examDataNumber . ":Correct Answer must be between 1 and 4";
                                                            }

                                                            $examDataNumber++;
                                                            if (!empty($lineErrors)) {
                                                                $linesErrors[] = $lineErrors;
                                                            }
                                                        }

                                                        if (!empty($linesErrors)) {
                                                            $status->quizErrors = $linesErrors;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $requiredProperties = [
                            'code',
                            'topic',
                            'date',
                            'type',
                            'physical_start_time',
                            'physical_end_time',
                            'physical_duration',
                            'physical_location',
                            'online_start_time',
                            'online_end_time',
                            'online_duration',
                            'quiz_type',
                            'file',
                            'quizErrors'
                        ];
                        $allPropertiesExist = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allPropertiesExist = false;
                                break;
                            }
                        }

                        if ($allPropertiesExist) {

                            $start_time_format = date("H:i:s", strtotime($start_time));
                            $end_time_format = date("H:i:s", strtotime($end_time));

                            $subject = Database::search("SELECT * FROM `subjects` WHERE `subject_id` = '" . $examData["subjects_subject_id"] . "'");
                            $subjectData = $subject->fetch_assoc();

                            $alreadyHasExam = Database::search("SELECT * FROM `exams` INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id` WHERE `exam_id` != '" . $exam_id . "' AND `levels_level_id` = '" . $subjectData['levels_level_id'] . "' AND `exam_date` = '" . $date . "'");

                            if ($alreadyHasExam->num_rows > 0) {
                                while ($alreadyHasExamData = $alreadyHasExam->fetch_assoc()) {
                                    if ($alreadyHasExamData['exam_types_exam_type_id'] == 1) {
                                        if (($subjectData['subject_type_subject_type_id'] == $alreadyHasExamData['subject_type_subject_type_id']) && ($subjectData['subject_type_subject_type_id'] == 2)) {
                                            if ($subjectData['subject_group_subject_group_id'] != $alreadyHasExamData['subject_group_subject_group_id']) {
                                                $physicalExam = Database::search("SELECT COUNT(*) as 'count' FROM `physical_exam` WHERE `exams_exam_id` = '" . $alreadyHasExamData['exam_id'] . "' AND ((`start_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "') OR (`end_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "'))");
                                                if ($physicalExam->fetch_assoc()['count'] > 0) {
                                                    $status->date = "The seleted date already has a physical exam in this Intake";
                                                    $status->physical_start_time = "The seleted start time already has a physical exam in this Intake";
                                                    $status->physical_end_time = "The seleted end time already has a physical exam in this Intake";
                                                }
                                            }
                                        } else {
                                            $physicalExam = Database::search("SELECT COUNT(*) as 'count' FROM `physical_exam` WHERE `exams_exam_id` = '" . $alreadyHasExamData['exam_id'] . "' AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "') OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))");
                                            if ($physicalExam->fetch_assoc()['count'] > 0) {
                                                $status->date = "The seleted date already has a physical exam in this Intake";
                                                $status->physical_start_time = "The seleted start time already has a physical exam in this Intake";
                                                $status->physical_end_time = "The seleted end time already has a physical exam in this Intake";
                                            }
                                        }
                                    } else if ($alreadyHasExamData['exam_types_exam_type_id'] == 2) {
                                        if (($subjectData['subject_type_subject_type_id'] == $alreadyHasExamData['subject_type_subject_type_id']) && ($subjectData['subject_type_subject_type_id'] == 2)) {
                                            if ($subjectData['subject_group_subject_group_id'] != $alreadyHasExamData['subject_group_subject_group_id']) {
                                                $onlineExam = Database::search("SELECT COUNT(*) as 'count' FROM `online_exam` WHERE `exams_exam_id` = '" . $alreadyHasExamData['exam_id'] . "' AND ((`start_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "') OR (`end_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "'))");
                                                if ($onlineExam->fetch_assoc()['count'] > 0) {
                                                    $status->date = "The seleted date already has a online exam in this Intake";
                                                    $status->online_start_time = "The seleted start time already has a online exam in this Intake";
                                                    $status->online_end_time = "The seleted end time already has a online exam in this Intake";
                                                }
                                            }
                                        } else {
                                            $onlineExam = Database::search("SELECT COUNT(*) as 'count' FROM `online_exam` WHERE `exams_exam_id` = '" . $alreadyHasExamData['exam_id'] . "' AND ((`start_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "') OR (`end_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "'))");
                                            if ($onlineExam->fetch_assoc()['count'] > 0) {
                                                $status->date = "The seleted date already has a online exam in this Intake";
                                                $status->online_start_time = "The seleted start time already has a online exam in this Intake";
                                                $status->online_end_time = "The seleted end time already has a online exam in this Intake";
                                            }
                                        }
                                    } else if ($alreadyHasExamData['exam_types_exam_type_id'] == 3) {
                                        if (($subjectData['subject_type_subject_type_id'] == $alreadyHasExamData['subject_type_subject_type_id']) && ($subjectData['subject_type_subject_type_id'] == 2)) {
                                            if ($subjectData['subject_group_subject_group_id'] != $alreadyHasExamData['subject_group_subject_group_id']) {
                                                $quizExam = Database::search("SELECT COUNT(*) as 'count' FROM `quiz` WHERE `exams_exam_id` = '" . $alreadyHasExamData['exam_id'] . "' AND ((`start_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "') OR (`end_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "'))");
                                                if ($quizExam->fetch_assoc()['count'] > 0) {
                                                    $status->date = "The seleted date already has a quiz exam in this Intake";
                                                    $status->online_start_time = "The seleted start time already has a quiz exam in this Intake";
                                                    $status->online_end_time = "The seleted end time already has a quiz exam in this Intake";
                                                }
                                            }
                                        } else {
                                            $quizExam = Database::search("SELECT COUNT(*) as 'count' FROM `quiz` WHERE `exams_exam_id` = '" . $alreadyHasExamData['exam_id'] . "' AND ((`start_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "') OR (`end_time` BETWEEN '" . $start_time_format . "' AND '" . $end_time_format . "'))");
                                            if ($quizExam->fetch_assoc()['count'] > 0) {
                                                $status->date = "The seleted date already has a quiz exam in this Intake";
                                                $status->online_start_time = "The seleted start time already has a quiz exam in this Intake";
                                                $status->online_end_time = "The seleted end time already has a quiz exam in this Intake";
                                            }
                                        }
                                    }
                                }
                            }

                            $requiredProperties = [
                                'error',
                                'date',
                                'quiz_start_time',
                                'quiz_end_time',
                                'physical_start_time',
                                'physical_end_time',
                                'online_start_time',
                                'online_end_time'
                            ];
                            $allPropertiesExist = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allPropertiesExist = false;
                                    break;
                                }
                            }

                            if ($allPropertiesExist) {

                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);

                                $exam_update_query = "`exam_name` = '" . $topic . "', `exam_types_exam_type_id` = '" . $type . "', `exam_date` = '" . $date . "'";

                                try {
                                    if ($examData['exam_types_exam_type_id'] == 1 && $type != 1) {
                                        Database::iud("DELETE FROM `physical_exam` WHERE `exams_exam_id` = '" . $exam_id . "'");
                                    } else if ($examData['exam_types_exam_type_id'] == 2 && $type != 2) {
                                        Database::iud("DELETE FROM `essay_questions` INNER JOIN `online_exam` ON `essay_questions`.`online_exam_online_exam_id` = `online_exam`.`online_exam_id` WHERE `exams_exam_id` = '" . $exam_id . "'");
                                        Database::iud("DELETE FROM `online_exam` WHERE `exams_exam_id` = '" . $exam_id . "'");
                                    } else if ($examData['exam_types_exam_type_id'] == 3 && $type != 3) {
                                        Database::iud("DELETE FROM `answers` WHERE `questions_question_id` IN (SELECT `question_id` FROM `questions` WHERE `quiz_quiz_id`=(SELECT `quiz_id` FROM `quiz` WHERE `exams_exam_id`='" . $exam_id . "'))");
                                        Database::iud("DELETE FROM `questions` WHERE `quiz_quiz_id` = (SELECT `quiz_id` FROM `quiz` WHERE `exams_exam_id`='" . $exam_id . "')");
                                        Database::iud("DELETE FROM `quiz` WHERE `exams_exam_id` = '" . $exam_id . "'");
                                    }

                                    Database::iud("UPDATE `exams` SET " . $exam_update_query . " WHERE `exam_id` = '" . $exam_id . "'");

                                    if ($type == 1) {
                                        if ($examData['exam_types_exam_type_id'] == 1) {
                                            $physicalExam_rs = Database::search("SELECT `physical_exam_id` FROM `physical_exam` WHERE `exams_exam_id` = '" . $exam_id . "'");
                                            if ($physicalExam_rs->num_rows > 0) {
                                                $physicalExam_update_query = "`start_time` = '" . $start_time . "', `end_time` = '" . $end_time . "', `duration` = '" . $duration . "', `location` = '" . $location . "'";
                                                Database::iud("UPDATE `physical_exam` SET " . $physicalExam_update_query . " WHERE `exams_exam_id` = '" . $exam_id . "'");
                                            } else {
                                                $physicalExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`location`,`date_added`,`users_user_id`";
                                                $physicalExam_insert_values = "'" . $exam_id . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $location . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";
                                                Database::iud("INSERT INTO `physical_exam` (" . $physicalExam_insert_columns . ") VALUES (" . $physicalExam_insert_values . ")");
                                            }
                                        } else {
                                            $physicalExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`location`,`date_added`,`users_user_id`";
                                            $physicalExam_insert_values = "'" . $exam_id . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $location . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";
                                            Database::iud("INSERT INTO `physical_exam` (" . $physicalExam_insert_columns . ") VALUES (" . $physicalExam_insert_values . ")");
                                        }
                                    } else if ($type == 2) {
                                        if ($examData['exam_types_exam_type_id'] == 2) {
                                            $onlineExam_rs = Database::search("SELECT `online_exam_id` FROM `online_exam` WHERE `exams_exam_id` = '" . $exam_id . "'");
                                            if ($onlineExam_rs->num_rows > 0) {
                                                $onlineExamId = $onlineExam_rs->fetch_assoc()['online_exam_id'];
                                                $onlineExam_update_query = "`start_time` = '" . $start_time . "', `end_time` = '" . $end_time . "', `duration` = '" . $duration . "'";
                                                Database::iud("UPDATE `online_exam` SET " . $onlineExam_update_query . " WHERE `online_exam_id` = '" . $onlineExamId . "'");

                                                if (count($csvData) > 0) {
                                                    Database::iud("DELETE FROM `essay_questions` WHERE `online_exam_online_exam_id` = '" . $onlineExamId . "'");
                                                    foreach ($csvData as $data) {
                                                        $question = $data[0];
                                                        $question_insert_columns = "`online_exam_online_exam_id`,`essay_question`";
                                                        $question_insert_values = "'" . $onlineExamId . "','" . $question . "'";
                                                        Database::iud("INSERT INTO `essay_questions` (" . $question_insert_columns . ") VALUES (" . $question_insert_values . ")");
                                                    }
                                                }
                                            } else {
                                                $onlineExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`date_added`,`users_user_id`";
                                                $onlineExam_insert_values = "'" . $exam_id . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";
                                                Database::iud("INSERT INTO `online_exam` (" . $onlineExam_insert_columns . ") VALUES (" . $onlineExam_insert_values . ")");
                                                $onlineExamId = Database::$connection->insert_id;
                                                foreach ($csvData as $data) {
                                                    $question = $data[0];
                                                    $question_insert_columns = "`online_exam_online_exam_id`,`essay_question`";
                                                    $question_insert_values = "'" . $onlineExamId . "','" . $question . "'";
                                                    Database::iud("INSERT INTO `essay_questions` (" . $question_insert_columns . ") VALUES (" . $question_insert_values . ")");
                                                }
                                            }
                                        } else {
                                            $onlineExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`date_added`,`users_user_id`";
                                            $onlineExam_insert_values = "'" . $exam_id . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";
                                            Database::iud("INSERT INTO `online_exam` (" . $onlineExam_insert_columns . ") VALUES (" . $onlineExam_insert_values . ")");
                                            $onlineExamId = Database::$connection->insert_id;
                                            foreach ($csvData as $data) {
                                                $question = $data[0];
                                                $question_insert_columns = "`online_exam_online_exam_id`,`essay_question`";
                                                $question_insert_values = "'" . $onlineExamId . "','" . $question . "'";
                                                Database::iud("INSERT INTO `essay_questions` (" . $question_insert_columns . ") VALUES (" . $question_insert_values . ")");
                                            }
                                        }
                                    } else if ($type == 3) {
                                        if ($examData['exam_types_exam_type_id'] == 3) {
                                            $quiz_rs = Database::search("SELECT `quiz_id` FROM `quiz` WHERE `exams_exam_id` = '" . $exam_id . "'");
                                            if ($quiz_rs->num_rows > 0) {
                                                $quiz_id = $quiz_rs->fetch_assoc()['quiz_id'];
                                                $quiz_update_query = "`start_time` = '" . $start_time . "', `end_time` = '" . $end_time . "', `duration` = '" . $duration . "'";
                                                Database::iud("UPDATE `quiz` SET " . $quiz_update_query . " WHERE `quiz_id` = '" . $quiz_id . "'");

                                                if (count($csvData) > 0) {
                                                    Database::iud("DELETE FROM `answers` WHERE `questions_question_id` IN (SELECT `question_id` FROM `questions` WHERE `quiz_quiz_id`='" . $quiz_id . "')");
                                                    Database::iud("DELETE FROM `questions` WHERE `quiz_quiz_id` = '" . $quiz_id . "'");
                                                    foreach ($csvData as $data) {
                                                        $question = $data[0];
                                                        $option1 = $data[1];
                                                        $option2 = $data[2];
                                                        $option3 = $data[3];
                                                        $option4 = $data[4];
                                                        $correct = $data[5];
                                                        $option_array = [$option1, $option2, $option3, $option4];
                                                        $question_insert_columns = "`quiz_quiz_id`,`question`,`correct_answer`";
                                                        $question_insert_values = "'" . $quiz_id . "','" . $question . "','" . $correct . "'";

                                                        Database::iud("INSERT INTO `questions` (" . $question_insert_columns . ") VALUES (" . $question_insert_values . ")");

                                                        foreach ($option_array as $option) {
                                                            $option_insert_columns = "`questions_question_id`,`option`";
                                                            $option_insert_values = "'" . Database::$connection->insert_id . "','" . $option . "'";

                                                            Database::iud("INSERT INTO `options` (" . $option_insert_columns . ") VALUES (" . $option_insert_values . ")");
                                                        }
                                                    }
                                                }
                                            } else {
                                                $quizExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`date_added`,`quiz_type_quiz_type_id`,`users_user_id`";
                                                $quizExam_insert_values = "'" . $exam_id . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $addTime . "','" . $quiz_type . "','" . $sessionUserObject['user_id'] . "'";

                                                Database::iud("INSERT INTO `quiz` (" . $quizExam_insert_columns . ") VALUES (" . $quizExam_insert_values . ")");

                                                $quiz_id = Database::$connection->insert_id;

                                                foreach ($csvData as $data) {
                                                    $question = $data[0];
                                                    $option1 = $data[1];
                                                    $option2 = $data[2];
                                                    $option3 = $data[3];
                                                    $option4 = $data[4];
                                                    $correct = $data[5];

                                                    $option_array = [$option1, $option2, $option3, $option4];

                                                    $question_insert_columns = "`question`,`correct_answer`,`quiz_quiz_id`";
                                                    $question_insert_values = "'" . $question . "','" . $correct . "','" . $quiz_id . "'";

                                                    Database::iud("INSERT INTO `questions` (" . $question_insert_columns . ") VALUES (" . $question_insert_values . ")");

                                                    $quiz_question_id = Database::$connection->insert_id;

                                                    foreach ($option_array as $option) {
                                                        $option_insert_columns = "`questions_question_id`,`answer`";
                                                        $option_insert_values = "'" . $quiz_question_id . "','" . $option . "'";

                                                        Database::iud("INSERT INTO `answers` (" . $option_insert_columns . ") VALUES (" . $option_insert_values . ")");
                                                    }
                                                }
                                            }
                                        } else {
                                            $quizExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`date_added`,`quiz_type_quiz_type_id`,`users_user_id`";
                                            $quizExam_insert_values = "'" . $exam_id . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $addTime . "','" . $quiz_type . "','" . $sessionUserObject['user_id'] . "'";

                                            Database::iud("INSERT INTO `quiz` (" . $quizExam_insert_columns . ") VALUES (" . $quizExam_insert_values . ")");

                                            $quiz_id = Database::$connection->insert_id;

                                            foreach ($csvData as $data) {
                                                $question = $data[0];
                                                $option1 = $data[1];
                                                $option2 = $data[2];
                                                $option3 = $data[3];
                                                $option4 = $data[4];
                                                $correct = $data[5];

                                                $option_array = [$option1, $option2, $option3, $option4];

                                                $question_insert_columns = "`question`,`correct_answer`,`quiz_quiz_id`";
                                                $question_insert_values = "'" . $question . "','" . $correct . "','" . $quiz_id . "'";

                                                Database::iud("INSERT INTO `questions` (" . $question_insert_columns . ") VALUES (" . $question_insert_values . ")");

                                                $quiz_question_id = Database::$connection->insert_id;

                                                foreach ($option_array as $option) {
                                                    $option_insert_columns = "`questions_question_id`,`answer`";
                                                    $option_insert_values = "'" . $quiz_question_id . "','" . $option . "'";

                                                    Database::iud("INSERT INTO `answers` (" . $option_insert_columns . ") VALUES (" . $option_insert_values . ")");
                                                }
                                            }
                                        }
                                    }

                                    $status->done = "The examination has been successfully updated";
                                } catch (Exception $e) {
                                    $status->error = $e->getMessage();
                                }
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

function checkIfFutureDateTime($start_date, $start_time)
{
    if ($start_date && $start_time) {
        $startDateTime = new DateTime("$start_date $start_time");
        $now = new DateTime();
        $isToday = $startDateTime->format('Y-m-d') === $now->format('Y-m-d');
        if (($isToday && $startDateTime > $now) || $startDateTime > $now) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
