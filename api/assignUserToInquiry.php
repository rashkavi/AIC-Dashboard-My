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
                    $inquiry_id = isset($decoded['inquiry']) ? $decoded['inquiry'] : 0;
                    $user_id = isset($decoded['user']) ? $decoded['user'] : 0;

                    if ($inquiry_id == 0) {
                        $status->error = "Invalid inquiry id";
                    } else {
                        $isValidInquiry = Database::search("SELECT * FROM `students_has_help_desk` WHERE `students_has_help_desk_id` = '" . $inquiry_id . "'");
                        if ($isValidInquiry->num_rows == 0) {
                            $status->error = "Invalid inquiry id";
                        } else {
                            if ($user_id == 0) {
                                $status->error = "Invalid user id";
                            } else {
                                $isValidUser = Database::search("SELECT * FROM `users`
                                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id` 
                                WHERE `user_id` = '" . $user_id . "' AND `status_status_id` = '1' AND `user_roles_user_role_id` NOT IN (1,2,8)");
                                if ($isValidUser->num_rows == 0) {
                                    $status->error = "Invalid user id";
                                } else {
                                    $user = $isValidUser->fetch_assoc();
                                }
                            }
                        }
                    }

                    $requiredProperties = [
                        'level',
                        'subject',
                        'user',
                        'error'
                    ];
                    $allFieldsPresent = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allFieldsPresent = false;
                            break; // Exit loop on the first missing property
                        }
                    }

                    if ($allFieldsPresent) {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $date = $dateTime->format('Y-m-d H:i:s');

                        Database::iud("INSERT INTO `help_desk_logs` 
                        (`students_has_help_desk_students_has_help_desk_id`,`users_user_id`,`added_at`,`log`) 
                        VALUES ('" . $inquiry_id . "','" . $sessionUserObject["user_id"] . "','" . $date . "','Assign user : " . $user['user_fname'] . ' ' . $user['user_lname'] . '(' . $user['user_role'] . ')' . "' )");

                        Database::iud("UPDATE `students_has_help_desk` SET `users_user_id` = '" . $user_id . "' WHERE `students_has_help_desk_id` = '" . $inquiry_id . "'");

                        $status->done =  "User assigned successfully";
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
