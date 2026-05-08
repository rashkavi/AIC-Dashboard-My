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

            $online_exam_id = isset($_POST['online_exam_id']) ? $_POST['online_exam_id'] : 0;
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
            $duration = isset($_POST['duration']) ? $_POST['duration'] : '';
            $questions = isset($_POST['questions']) ? $_POST['questions'] : [];

            if (empty($online_exam_id) || $online_exam_id == 0 || !is_numeric($online_exam_id)) {
                $status->error = "Something went wrong. Please try again later.";
            } else {
                $isValidExam = Database::search("SELECT COUNT(*),`exam_date` FROM `online_exam` INNER JOIN `exams` ON `online_exam`.`exams_exam_id` = `exams`.`exam_id` WHERE `exam_id` = '" . $exam_id . "'");
                $row = $isValidExam->fetch_assoc();
                if ($row['count'] == 0) {
                    $status->error = 'Something went wrong. Please try again later.';
                } else if ($row['exam_types_exam_type_id'] == 1) {
                    $status->error = 'You cannot schedule online exam for this exam type';
                } else {
                    if (empty($start_time)) {
                        $status->start = 'Start time is required';
                    } else {
                        if (!checkIfFutureDateTime($row['exam_date'], $start_time)) {
                            $status->start = 'The selected start time is in the past';
                        }
                    }
                    if (empty($end_time)) {
                        $status->end = 'End time is required';
                    } else {
                        if (!checkIfFutureDateTime($row['exam_date'], $end_time)) {
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

                        $online_exam_update_query = "`start_time`='" . $start_time . "',`end_time`='" . $end_time . "',`duration`='" . $duration . "'";
                        Database::iud("UPDATE `online_exam` SET " . $online_exam_update_query . " WHERE `online_exam_id` = '" . $online_exam_id . "'");

                        if (isset($_FILES["file"])) {
                            foreach ($csvData as $data) {
                                $question = $data[0];

                                $online_exam_question_table_columns = "`online_exam_online_exam_id`,`essay_question`";
                                $online_exam_question_table_values = "'" . $online_exam_id . "','" . $question . "'";
                                Database::iud("INSERT INTO `online_exam_question` (" . $online_exam_question_table_columns . ") VALUES (" . $online_exam_question_table_values . ")");
                            }
                        } else  if (!isset($_FILES["file"]) && count($questions) > 0) {
                            foreach ($questions as $question) {
                                $online_exam_question_table_columns = "`online_exam_online_exam_id`,`essay_question`";
                                $online_exam_question_table_values = "'" . $online_exam_id . "','" . $question . "'";
                                Database::iud("INSERT INTO `essay_questions` (" . $online_exam_question_table_columns . ") VALUES (" . $online_exam_question_table_values . ")");
                            }
                        }

                        $status->done = "Online exam updated successfully!";
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
