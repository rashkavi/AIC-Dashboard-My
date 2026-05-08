<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/mail-body.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $email = $decoded['email'];
            if (empty($email)) {
                $status->email =  "Please enter your email";
            } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $status->email =  "Please enter a valid email";
            } else {
                $checkEmail = Database::search("SELECT * FROM `users` INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id` WHERE `user_email` = '" . $email . "'");
                if ($checkEmail->num_rows == 0) {
                    $status->email =  "Email does not exist";
                } else {
                    $userData = $checkEmail->fetch_assoc();
                    $userName = $userData['title'] . "." . $userData['user_fname'] . " " . $userData['user_lname'];

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $resetTime = $dateTime->format('Y-m-d H:i:s');

                    $bytes = random_bytes(75);
                    $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                    Database::iud("UPDATE `users` SET `user_reset_time`='" . $resetTime . "', `user_verification`='" . $token . "' WHERE `user_email`='" . $email . "'");

                    $url = Project::getBaseUrl() . "/forgot-password?token=" . $token;

                    sendMail($email, $userName, "Forgotten Password", renderMailBody($url, 2));

                    $status->done =  "Check your email to reset your password";
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
