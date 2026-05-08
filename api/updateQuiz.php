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
                    $quizTitle = isset($decoded["quizTitle"]) ? $decoded["quizTitle"] : "";
                    $examDate = isset($decoded["examDate"]) ? $decoded["examDate"] : "";
                    $startTime = isset($decoded["startTime"]) ? $decoded["startTime"] : "";
                    $endDate = isset($decoded["endDate"]) ? $decoded["endDate"] : "";
                    $endTime = isset($decoded["endTime"]) ? $decoded["endTime"] : "";
                    $duration = isset($decoded["duration"]) ? $decoded["duration"] : "";
                    $quizType = isset($decoded["quizType"]) ? $decoded["quizType"] : "";
                    $quizOrder = isset($decoded["quizOrder"]) ? $decoded["quizOrder"] : "";

                    if (empty($quizId) || $quizId == 0 || !is_numeric($quizId)) {
                        $status->error = "Invalid quiz Id";
                    } else {
                        $isValidQuiz = Database::search("SELECT COUNT(*) as 'count' FROM `quiz` WHERE `quiz_id` = '" . $quizId . "'");
                        if ($isValidQuiz->num_rows == 0) {
                            $status->error = "Invalid quiz Id";
                        } else {

                            if (empty($quizTitle)) {
                                $status->title = "Please enter quiz title";
                            } else if (strlen($quizTitle) > 45) {
                                $status->title = "Title must be less than 45 characters";
                            }

                            if (empty($examDate)) {
                                $status->date = "Please enter exam date";
                            }

                            if (empty($startTime)) {
                                $status->startTime = "Please enter start time";
                            }

                            if (empty($endTime)) {
                                $status->endTime = "Please enter end time";
                            }

                            if (empty($endTime)) {
                                $status->endTime = "Please enter end time";
                            }

                            if (!property_exists($status, 'startDate') && !property_exists($status, 'startTime')) {
                                if (!checkIfFutureDateTime($examDate, $startTime)) {
                                    $status->examDate = 'The selected start date and time is in the past.';
                                }
                            }

                            if (!property_exists($status, 'endDate') && !property_exists($status, 'endTime')) {
                                if (!checkIfFutureDateTime($endDate, $endTime)) {
                                    $status->endDate = 'The selected end date and time is in the past.';
                                }
                            }

                            if (empty($duration) || $duration == 0) {
                                $status->duration = "Please enter duration";
                            }

                            $requiredProperties = [
                                'startDate',
                                'startTime',
                                'endDate',
                                'endTime',
                            ];
                            $allPropertiesExist = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allPropertiesExist = false;
                                    break;
                                }
                            }

                            if ($allPropertiesExist) {
                                if (!isDateAfter($examDate, $startTime, $endDate, $endTime)) {
                                    $status->endTime = "The selected end date is not matching";
                                }
                                if (!CheckDateTime::validateDuration($examDate, $startTime, $endDate, $endTime, $duration)) {
                                    $status->duration = "The selected duration is not matching";
                                }
                            }

                            if (empty($quizType) || $quizType == 0) {
                                $status->quizType = "Please enter quiz type";
                            }

                            if (empty($quizOrder) || $quizOrder == 0) {
                                $status->quizOrder = "Please enter quiz order";
                            }

                            $requiredProperties = [
                                'title',
                                'date',
                                'startTime',
                                'endTime',
                                'quizType',
                                'quizOrder',
                                'duration',
                                'startDate',
                                'endDate',
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

                                Database::iud("UPDATE `exams` SET `exam_name`='" . $quizTitle . "',`exam_date`='" . $examDate . "' WHERE `exam_id` = (SELECT `exams_exam_id` FROM `quiz` WHERE `quiz_id`='" . $quizId . "')");

                                Database::iud("UPDATE `quiz` SET 
                                `start_time`='" . $startTime . "',`end_date`='" . $endDate . "',`end_time`='" . $endTime . "',`duration`='" . $duration . "',`quiz_type_quiz_type_id`='" . $quizType . "',`quiz_order_quiz_order_id`='" . $quizOrder . "' WHERE `quiz_id`='" . $quizId . "'");
                                $status->done = 'Successfully updated quiz';
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

function isDateAfter($startDate, $startTime, $endDate, $endTime)
{
    try {
        $start = new DateTime("$startDate $startTime");
        $end = new DateTime("$endDate $endTime");

        return $end > $start;
    } catch (Exception $e) {
        return false;
    }
}

function validateDuration($startDate, $startTime, $endDate, $endTime, $duration)
{
    $start_datetime = $startDate . ' ' . $startTime;
    $end_datetime = $endDate . ' ' . $endTime;

    $startTimestamp = strtotime($start_datetime);
    $endTimestamp = strtotime($end_datetime);

    $gapMinutes = ($endTimestamp - $startTimestamp) / 60;

    if ($gapMinutes > $duration) {
        return false;
    } else {
        return true;
    }
}
