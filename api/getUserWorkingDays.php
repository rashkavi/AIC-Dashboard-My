<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $id = isset($decoded['id']) ? $decoded['id'] : '';
                    if (empty($id)) {
                        $status->error = "Invalid user Id";
                    } else if (!is_numeric($id)) {
                        $status->error = "Invalid user Id";
                    } else {
                        $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_id`='" . $id . "'");
                        $row = $isValidUser->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->error = "Invalid user Id";
                        } else {

                            $d = new DateTime();
                            $tz = new DateTimeZone("Asia/Colombo");
                            $d->setTimezone($tz);
                            $dateTime = $d->format("Y-m-d H:i:s");

                            $result = Database::search("
                                SELECT DISTINCT DATE(`students_logs`.`date_updated`) AS work_day
                                FROM `students_logs`
                                WHERE `students_logs`.`users_user_id` = '" . $id . "'
                                AND `students_logs`.`date_updated` <= '" . $dateTime . "'
                                ORDER BY work_day ASC
                            ");

                            $workingDays = [];
                            while ($row = $result->fetch_assoc()) {
                                $workingDays[] = $row['work_day'];
                            }

                            $status->workingDays = $workingDays;
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
