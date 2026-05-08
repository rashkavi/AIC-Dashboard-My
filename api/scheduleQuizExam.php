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
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
            $duration = isset($_POST['duration']) ? $_POST['duration'] : '';
            $quiz_type = isset($_POST['quiz_type']) ? $_POST['quiz_type'] : '';
            $questions = isset($_POST['questions']) ? $_POST['questions'] : [];

            if (empty($exam_id) || $exam_id == 0 || !is_numeric($exam_id)) {
                $status->error = "Something went wrong. Please try again later.";
            } else {
                $isValidExam = Database::search("SELECT COUNT(*),`exam_types_exam_type_id`,`exam_date` FROM `exams` WHERE `exam_id` = '" . $exam_id . "'");
                $row = $isValidExam->fetch_assoc();
                if ($row['count'] == 0) {
                    $status->error = 'Something went wrong. Please try again later.';
                } else if ($row['exam_types_exam_type_id'] == 2) {
                    $status->error = 'You cannot schedule quiz exam for this exam type';
                } else {
                    if (empty($start_time)) {
                        $status->start = 'Start time is required';
                    } else {
                        if (!checkIfFutureDateTime($row['`exam_date`'], $start_time)) {
                            $status->start = 'The selected start time is in the past';
                        }
                    }
                    if (empty($end_time)) {
                        $status->end = 'End time is required';
                    } else {
                        if (!checkIfFutureDateTime($row['`exam_date`'], $end_time)) {
                            $status->end = 'The selected end time is in the past';
                        }
                    }

                    if (empty($duration)) {
                        $status->error = 'Duration is required';
                    } else {
                        if (!property_exists($status, 'start') && !property_exists($status, 'end')) {
                            if (!checkMatchDuration($start_time, $end_time, $duration)) {
                                $status->duration = 'The selected duration does not match the start and end time';
                            }
                        }
                    }

                    if (isset($_FILES["file"])) {
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

                                // Loop through each line of the CSV file
                                while (($line = fgetcsv($csvFile)) !== FALSE) {
                                    if (count(array_filter($line)) != 0) {
                                        $csvData[] = $line; // Add each line to the array
                                    }
                                }
                                fclose($csvFile);
                                if (count($csvData) == 0) {
                                    $status->file = "Please select a valid CSV file";
                                } else if (count($csvData) > 100) {
                                    $status->file = "Only 100 students can be uploaded at a time";
                                }

                                foreach ($csvData as $data) {
                                    $question = $data[0];
                                    $answer1 = $data[1];
                                    $answer2 = $data[2];
                                    $answer3 = $data[3];
                                    $answer4 = $data[4];
                                    $correct_answer = $data[5];
                                }
                            }
                        }
                    }

                    $requiredProperties = [
                        'start_time',
                        'end_time',
                        'duration',
                        'file'
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

                        $quiz_table_columns = "`exams_exam_id`,`start_time`,`end_time`,`duration`,,`quiz_type_quiz_type_id``date_added`,`users_user_id`";
                        $quiz_table_values = "'" . $exam_id . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $quiz_type . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";

                        Database::iud("INSERT INTO `quiz` (" . $quiz_table_columns . ") VALUES (" . $quiz_table_values . ")");

                        $quiz_exam = Database::search("SELECT `quiz_id` FROM `quiz` WHERE `exams_exam_id` = '" . $exam_id . "'");
                        $row = $online_exam->fetch_assoc();

                        if (isset($_FILES["file"])) {
                            foreach ($csvData as $data) {
                                $question = $data[0];
                                $answer1 = $data[1];
                                $answer2 = $data[2];
                                $answer3 = $data[3];
                                $answer4 = $data[4];
                                $correct_answer = $data[5];

                                $online_exam_question_table_columns = "`online_exam_online_exam_id`,`essay_question`";
                                $online_exam_question_table_values = "'" . $row['online_exam_id'] . "','" . $question . "'";
                                Database::iud("INSERT INTO `online_exam_question` (" . $online_exam_question_table_columns . ") VALUES (" . $online_exam_question_table_values . ")");
                            }
                        } else    if (count($questions) > 0) {
                            foreach ($questions as $question) {
                                $online_exam_question_table_columns = "`online_exam_online_exam_id`,`essay_question`";
                                $online_exam_question_table_values = "'" . $row['online_exam_id'] . "','" . $question . "'";
                                Database::iud("INSERT INTO `essay_questions` (" . $online_exam_question_table_columns . ") VALUES (" . $online_exam_question_table_values . ")");
                            }
                        }

                        $status->done = "Online exam scheduled successfully!";
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

function checkMatchDuration($start_time, $end_time, $duration)
{
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);

    $diff = $start->diff($end);

    if ($diff->format('%h') == $duration) {
        return true;
    } else {
        return false;
    }
}
