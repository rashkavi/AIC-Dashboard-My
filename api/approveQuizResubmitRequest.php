<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();

    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);

                $requestId = isset($decoded['request_id']) ? $decoded['request_id'] : '';

                if (empty($requestId)) {
                    $status->error = 'Invalid request Id';
                } else {
                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `students_has_quiz_reset_request` WHERE `students_has_quiz_reset_request_id` = '" . $requestId . "'");
                    $isValidIdData = $isValidId->fetch_assoc();
                    if ($isValidIdData['count'] == 0) {
                        $status->error = 'Invalid request Id2';
                    } else {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $date = $dateTime->format('Y-m-d H:i:s');

                        $query = "UPDATE `students_has_quiz_reset_request` SET `quiz_reset_status_quiz_reset_status_id` = '2',`users_user_id`='" . $sessionUserObject["user_id"] . "',`update_date_tme`='" . $date . "' WHERE `students_has_quiz_reset_request_id` = '" . $requestId . "'";
                        Database::iud($query);
                        $status->done = 'Request approved successfully';
                    }
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid content-type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication failed';
    }
} else {
    $status->error = 'Authentication failed';
}

echo json_encode($status);
