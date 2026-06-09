<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/mail-process.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $student_id = isset($decoded['student_id']) ? $decoded['student_id'] : '';


                    if ($student_id == '') {
                        http_response_code(400);
                        $status->error = 'Please select a student';
                    } else {
                        $student = Database::search("SELECT * FROM `students` 
                        INNER JOIN `login_details` ON `login_details_login_details_id` = `login_details_id`
                        INNER JOIN `title` ON `title_title_id` = `title_id`
                        INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                        WHERE `students`.`student_id` = '" . $student_id . "'");
                        if ($student->num_rows == 0) {
                            http_response_code(400);
                            $status->error = 'Invalid student';
                        } else {

                            $studentData = $student->fetch_assoc();

                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $now = $dateTime->format('Y-m-d H:i:s');

                            Database::iud("
                            UPDATE `students`
                            SET `lead_student_status_lead_student_status_id` = '17'
                            WHERE `student_id` = '" . $studentData['students_student_id'] . "'
                        ");

                            Database::iud("
                            INSERT INTO `students_logs`
                            (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`)
                            VALUES
                            (
                                '" . $studentData['students_student_id'] . "',
                                '" . $now . "',
                                '" . $sessionUserObject['user_id'] . "',
                                'Academic department completion confirmed',
                                '17'
                            
                            )
                                ");

                            try {
                                $mail = EmailNotificationService::build(
                                    EmailTemplateContent::STUDENT_WELCOME_EMAIL,
                                    [
                                        'student_name' => $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'],
                                        'email' => $studentData['email'],
                                        'login_url' => Project::getBasePortalUrl(),
                                    ]
                                );

                                $userName = $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'];

                                sendMail($studentData['email'], $userName, $mail['subject'], $mail['body']);

                                $status->done = 'Academic department completion confirmed';
                            } catch (Exception $e) {
                                $status->error = "Academic department completion confirmed but mail not sent";
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
