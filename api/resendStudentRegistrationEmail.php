<?php

$status = new stdClass();
require_once '../includes/UserClass.php';
require_once '../includes/Project.php';
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Limitation.php");
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

                    require_once "../includes/connection.php";
                    require_once "../includes/UserPermissionsClass.php";

                    $student = $decoded['student'];

                    if (empty($student) || $student == 0 || !is_numeric($student)) {
                        $status->error = 'Invalid Student Id';
                    } else {
                        $isValidStudent = Database::search("SELECT * FROM `students`
                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
                        WHERE `student_id` = '" . $student . "'");
                        if ($isValidStudent->num_rows == 0) {
                            $status->error = 'Invalid Student Id';
                        } else {
                            $studentData = $isValidStudent->fetch_assoc();

                            $fullname = $studentData['first_name'] . " " . $studentData['last_name'];
                            $email = $studentData['email'];

                            $bytes = random_bytes(75);
                            $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);


                            $url = "setup-password?token=" . $token;

                            try {
                                if (empty($studentData['reset_time'])) {
                                    Database::iud("UPDATE `login_details` SET `verification` = '" . $token . "' WHERE `login_details_id` = '" . $studentData['login_details_login_details_id'] . "'");
                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::PASSWORD_SETUP,
                                        [
                                            'action_url' => Project::getBasePortalUrl() . "/setup-password?token=" . $token
                                        ]
                                    );
                                    sendMail($email, $fullname, $mail['subject'], $mail['body']);
                                } else {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $resetTime = $dateTime->format('Y-m-d H:i:s');

                                    Database::iud("UPDATE `login_details` SET `verification` = '" . $token . "', `reset_time` = '" . $resetTime . "' WHERE `login_details_id` = '" . $studentData['login_details_login_details_id'] . "'");
                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::PASSWORD_SETUP,
                                        [
                                            'action_url' => Project::getBasePortalUrl() . "/forgot-password?token=" . $token
                                        ]
                                    );
                                    sendMail($email, $fullname, $mail['subject'], $mail['body']);
                                }
                                $status->done =  "Email resend successfully!";
                            } catch (Exception $e) {
                                $status->error = 'Email sending failed.';
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


function normalizePhoneNumber($number)
{
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        if ($number[0] !== '+') {
            return '+' . $number;
        }
        return $number;
    }

    return false;
}
