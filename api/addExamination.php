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

                        if (empty($code)) {
                            $status->code = 'Examination code is required';
                        }

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
                        }

                        $requiredProperties = [
                            'code',
                            'topic',
                            'date',
                        ];
                        $allPropertiesExist = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allPropertiesExist = false;
                                break; // Exit loop on the first missing property
                            }
                        }

                        if ($allPropertiesExist) {

                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $addTime = $dateTime->format('Y-m-d H:i:s');

                            $exam_insert_columns = "`subjects_subject_id`,`exam_name`,`exam_types_exam_type_id`,`exam_date`,`date_added`,`users_user_id`,`exams_order`";
                            $exam_insert_values = "'" . $subject_id . "','" . $topic . "','" . $type . "','" . $date . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "','0'";

                            Database::iud("INSERT INTO `exams` (" . $exam_insert_columns . ") VALUES (" . $exam_insert_values . ")");

                            $status->done = "Examination added successfully!";
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
