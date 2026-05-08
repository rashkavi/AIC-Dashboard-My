<?php

use function PHPSTORM_META\type;

$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $subject_id = isset($_POST['subject_id']) ? $_POST['subject_id'] : 0;
            $code = isset($_POST['code']) ? $_POST['code'] : '';
            $topic = isset($_POST['topic']) ? $_POST['topic'] : '';
            $date = isset($_POST['date']) ? $_POST['date'] : '';
            $type = isset($_POST['type']) ? $_POST['type'] : 0;

            if (empty($subject_id)) {
                $status->error = 'Subject ID is required';
            } else if ($subject_id == 0) {
                $status->error = 'Invalid Subject ID';
            } else {
                $isValidSubject = Database::search("SELECT COUNT(*) as 'count' FROM `subjects` WHERE `subject_id` = '" . $subject_id . "'");
                $row = $isValidSubject->fetch_assoc();
                if ($row['count'] == 0) {
                    $status->error = 'Invalid Subject ID';
                } else {

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 2, $subject_id))) {
                        $status->error = 'You are not authorized to add examination.';
                    } else {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $addTime = $dateTime->format('Y-m-d H:i:s');
                        $today = $dateTime->format('Y-m-d');
                        $now = $dateTime->format('Y-m-d H:i');
                        $nowTime = $dateTime->format('H:i');

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

                                            $csvData = [];
                                            while (($line = fgetcsv($csvFile)) !== false) {
                                                if (!empty($line) && count(array_filter($line)) > 0) {
                                                    $csvData[] = $line;
                                                }
                                            }

                                            fclose($csvFile);
                                            if (count($csvData) == 0) {
                                                $status->file = "Please select a valid CSV file";
                                            } else if (count($csvData) > 100) {
                                                $status->file = "Only 100 students can be uploaded at a time";
                                            }

                                            if (!property_exists($status, 'file') && !property_exists($status, 'error')) {
                                                if ($type == 3) {
                                                    $rowNumber = 1;

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
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Question is required";
                                                        }

                                                        if (empty($option1)) {
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Option 1 is required";
                                                        }

                                                        if (empty($option2)) {
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Option 2 is required";
                                                        }

                                                        if (empty($option3)) {
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Option 3 is required";
                                                        }

                                                        if (empty($option4)) {
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Option 4 is required";
                                                        }

                                                        if (empty($correct)) {
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Correct Answer is required";
                                                        } else if (is_int($correct)) {
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Correct Answer is not matching";
                                                        } else if ((int)$correct < 1 || (int)$correct > 4) {
                                                            $lineErrors[] =  "Row " . $rowNumber . ":Correct Answer must be between 1 and 4";
                                                        }

                                                        $rowNumber++;
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

                            $subject = Database::search("SELECT * FROM `subjects` WHERE `subject_id` = '" . $subject_id . "'");
                            $subjectData = $subject->fetch_assoc();

                            $alreadyHasExam = Database::search("SELECT * FROM `exams` INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id` WHERE `levels_level_id` = '" . $subjectData['levels_level_id'] . "' AND `exam_date` = '" . $date . "'");

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
                                $addTime = $dateTime->format('Y-m-d H:i:s');

                                $exam_insert_columns = "`subjects_subject_id`,`exam_name`,`exam_types_exam_type_id`,`exam_date`,`date_added`,`users_user_id`,`exams_order`";
                                $exam_insert_values = "'" . $subject_id . "','" . $topic . "','" . $type . "','" . $date . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "','0'";

                                try {
                                    Database::iud("INSERT INTO `exams` (" . $exam_insert_columns . ") VALUES (" . $exam_insert_values . ")");

                                    $examId = Database::$connection->insert_id;

                                    if ($type == 1) {
                                        $physicalExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`location`,`date_added`,`users_user_id`";
                                        $physicalExam_insert_values = "'" . $examId . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $location . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";

                                        Database::iud("INSERT INTO `physical_exam` (" . $physicalExam_insert_columns . ") VALUES (" . $physicalExam_insert_values . ")");
                                        $physicalExamId = Database::$connection->insert_id;

                                        $students  = Database::search("SELECT `students`.`student_id` FROM `students` INNER JOIN  
                                        `students_has_intakes` ON `students`.`student_id`=`students_has_intakes`.`students_student_id`
                                        WHERE `intakes_intake_id` = (SELECT `intakes_intake_id` FROM `levels` 
                                        WHERE `level_id` IN (SELECT `levels_level_id` FROM `subjects` WHERE `subject_id`='" . $subject_id . "'))");

                                        if ($students->num_rows > 0) {
                                            while ($data = $students->fetch_assoc()) {
                                                $admissionId = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

                                                $student_has_exam_insert_columns = "`students_student_id`,`physical_exam_physical_exam_id`,`admission`,`exam_status_exam_status_id`";
                                                $student_has_exam_insert_values = "'" . $data['student_id'] . "','" . $physicalExamId . "','" . $admissionId . "','1'";

                                                Database::iud("INSERT INTO `students_has_physical_exam` (" . $student_has_exam_insert_columns . ") VALUES (" . $student_has_exam_insert_values . ")");
                                            }
                                        }
                                    } else if ($type == 2) {
                                        $onlineExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`date_added`,`users_user_id`";
                                        $onlineExam_insert_values = "'" . $examId . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";

                                        Database::iud("INSERT INTO `online_exam` (" . $onlineExam_insert_columns . ") VALUES (" . $onlineExam_insert_values . ")");

                                        $onlineExamId = Database::$connection->insert_id;

                                        foreach ($csvData as $data) {
                                            $question = $data[0];
                                            $question_insert_columns = "`online_exam_online_exam_id`,`essay_question`";
                                            $question_insert_values = "'" . $onlineExamId . "','" . $question . "'";

                                            Database::iud("INSERT INTO `essay_questions` (" . $question_insert_columns . ") VALUES (" . $question_insert_values . ")");
                                        }
                                    } else if ($type == 3) {

                                        $quizExam_insert_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,`date_added`,`quiz_type_quiz_type_id`,`users_user_id`";
                                        $quizExam_insert_values = "'" . $examId . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $addTime . "','" . $quiz_type . "','" . $sessionUserObject['user_id'] . "'";

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

                                    $status->done = "The examination has been successfully added";
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
