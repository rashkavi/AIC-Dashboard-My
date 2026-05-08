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
                    $exam_id = isset($decoded['exam_id']) ? $decoded['exam_id'] : 0;
                    $code = isset($decoded['code']) ? $decoded['code'] : '';
                    $name = isset($decoded['name']) ? $decoded['name'] : '';
                    $date = isset($decoded['date']) ? $decoded['date'] : '';

                    if (empty($exam_id) || $exam_id == 0 || !is_numeric($exam_id)) {
                        $status->error = "Something went wrong. Please try again later.";
                    } else if (!is_numeric($exam_id)) {
                        $isValidExam = Database::search("SELECT COUNT(*) as 'count' FROM `exams` WHERE `exam_id` = '" . $exam_id . "'");
                        $row = $isValidExam->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->error = 'Something went wrong. Please try again later.';
                        } else {
                            if (empty($code)) {
                                $status->code = "Please enter code";
                            } else if (strlen($code) > 45) {
                                $status->code = "Code should be less than 45 characters";
                            }
                            if (empty($name)) {
                                $status->name = "Please enter name";
                            } else if (strlen($name) > 45) {
                                $status->name = "Name should be less than 45 characters";
                            }
                            if (!empty($date)) {
                                if (!checkIfFutureDateTime($date)) {
                                    $status->date = "Selected date is invalid";
                                }
                            }

                            $requiredProperties = [
                                'code',
                                'name',
                                'date',
                            ];
                            $allPropertiesExist = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allPropertiesExist = false;
                                    break;
                                }
                            }

                            if ($allPropertiesExist) {

                                $exams_table_update_columns = "`code` = '" . $code . "', `exam_name` = '" . $name . "', `exam_date` = '" . $date . "'";

                                Database::iud("UPDATE `exams` SET " . $exams_table_update_columns . " WHERE `exam_id` = '" . $exam_id . "'");
                                $status->success = 'Exam updated successfully';
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


function checkIfFutureDateTime($date)
{
    if ($date) {
        $startDateTime = new DateTime("$date");
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
