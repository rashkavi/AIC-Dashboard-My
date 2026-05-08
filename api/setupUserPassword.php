<?php
$status = new stdClass();
require_once("../includes/connection.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $token = $decoded['token'];
            $setupStatus = $decoded['status'];
            $password = $decoded['password'];
            if (empty($token)) {
                $status->code =  "The link is invalid or expired. Please check your email and try again.";
            }
            if (empty($password)) {
                $status->password =  "Please enter your password";
            }

            if (empty($setupStatus) || !($setupStatus > 0 && $setupStatus < 3)) {
                $status->error =  "Something went wrong. Please try again.";
            }

            if (empty($status->code) && empty($status->password) && empty($status->error)) {
                $isValidCode = Database::search("SELECT * FROM `users` WHERE `user_verification`='" . $token . "'");
                if ($isValidCode->num_rows > 0) {
                    $data = $isValidCode->fetch_assoc();
                    date_default_timezone_set("Asia/Colombo");

                    if ($setupStatus == 1) {
                        $hashPassword = password_hash($password, PASSWORD_BCRYPT);
                        Database::iud("UPDATE `users` SET `user_password`='" . $hashPassword . "',`user_verification`='',`user_reset_time`='' WHERE `user_verification`='" . $token . "'");
                        $status->done = "Password updated successfully";
                    } else if ($setupStatus == 2) {
                        if ($data["user_reset_time"] > strtotime("-15 minutes")) {
                            $hashPassword = password_hash($password, PASSWORD_BCRYPT);
                            Database::iud("UPDATE `users` SET `user_password`='" . $hashPassword . "',`user_verification`='',`user_reset_time`='' WHERE `user_verification`='" . $token . "'");
                            $status->done = "Password updated successfully";
                        } else {
                            $status->code = "The link is invalid or expired. Please check your email and try again.";
                        }
                    }
                } else {
                    $status->code = "The link is invalid. Please check your email and try again.";
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

echo (json_encode($status));
