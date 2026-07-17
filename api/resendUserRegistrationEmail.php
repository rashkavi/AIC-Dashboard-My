<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once '../includes/connection.php';

require_once '../includes/UserClass.php';
require_once '../includes/Project.php';
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $sessionUserObject = SessionUser::getCurrentUserObject();
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                 
                    $user = $decoded['user'];

                    if (empty($user) || $user == 0 || !is_numeric($user)) {
                        $status->error = 'Invalid user Id';
                    } else {
                        $isValidUser = Database::search("SELECT * FROM `users` WHERE `user_id` = '" . $user . "'");
                        if ($isValidUser->num_rows == 0) {
                            $status->error = 'Invalid Student Id';
                        } else {
                            $userData = $isValidUser->fetch_assoc();

                            $fullname = $userData['user_fname'] . " " . $userData['user_lname'];
                            $email = $userData['user_email'];

                            $bytes = random_bytes(75);
                            $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);


                            try {
                                if (empty($studentData['reset_time'])) {
                                    Database::iud("UPDATE `users` SET `user_verification` = '" . $token . "' WHERE `user_id` = '" . $user . "'");

                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::PASSWORD_SETUP,
                                        [
                                            'action_url' => Project::getBaseUrl() . "/setup-password?token=" . $token
                                        ]
                                    );
                                    sendMail($email, $fullname, $mail['subject'], $mail['body']);
                                } else {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $resetTime = $dateTime->format('Y-m-d H:i:s');

                                    Database::iud("UPDATE `users` SET `user_verification` = '" . $token . "', `user_reset_time` = '" . $resetTime . "' WHERE `user_id` = '" . $user . "'");
                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::PASSWORD_SETUP,
                                        [
                                            'action_url' => Project::getBaseUrl() . "/forgot-password?token=" . $token
                                        ]
                                    );
                                    sendMail($email, $fullname, $mail['subject'], $mail['body']);
                                }
                                $status->done =  "Email sent successfully!";
                            } catch (Exception $e) {
                                $status->error = 'Email sending failed';
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
