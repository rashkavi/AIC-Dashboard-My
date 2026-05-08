<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    if (!isset($decoded['policy']) && $decoded["policy"] != 0) {
                        http_response_code(400);
                        $status->error = 'Something went wrong';
                    } else {
                        $policy = $decoded['policy'];
                        $isHasPolicyContent = Database::search("SELECT COUNT(*) as 'count' FROM `student_policy` ");
                        $row = $isHasPolicyContent->fetch_assoc();

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $updateTime = $dateTime->format('Y-m-d H:i:s');

                        $sanitized_policy = mysqli_real_escape_string(Database::$connection, $policy);

                        if ($row['count'] == 0) {
                            Database::iud("INSERT INTO `student_policy`(`student_policy`,`date_updated`,`users_user_id`) VALUES ('" . $sanitized_policy . "','" . $updateTime . "','" . $sessionUserObject["user_id"] . "')");
                        } else {
                            Database::iud("UPDATE `student_policy` SET `student_policy` = '" . $sanitized_policy . "', `date_updated` = '" . $updateTime . "'");
                        }
                        $status->done =  "The policy has been successfully added";
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
