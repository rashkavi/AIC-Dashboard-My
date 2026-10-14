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

                    $student_id = isset($decoded['student']) ? $decoded['student'] : '';


                    if ($student_id == '') {
                        http_response_code(400);
                        $status->error = 'Please select a student';
                    } else {
                        $student = Database::search("SELECT * FROM `students` 
                        INNER JOIN `login_details` ON `login_details_login_details_id` = `login_details_id`
                        INNER JOIN `title` ON `title_title_id` = `title_id`
                        INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                        INNER JOIN `intakes` ON `intakes`.`intake_id` = `students_has_intakes`.`intakes_intake_id`
                        INNER JOIN `programmes` ON `programmes`.`programme_id` = `intakes`.`programmes_programme_id`
                        WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $student_id . "'");
                        if ($student->num_rows == 0) {
                            http_response_code(400);
                            $status->error = 'Invalid student';
                        } else {

                            $studentData = $student->fetch_assoc();

                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $now = $dateTime->format('Y-m-d H:i:s');


                            $bytes = random_bytes(75);
                            $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                            $url = Project::getBasePortalUrl() . "/setup-password?token=" . $token;

                            Database::iud("
                            INSERT INTO `students_logs`
                            (`students_has_intakes_students_has_intakes_id`, `date_updated`, `users_user_id`, `log`)
                            VALUES
                            (
                                '" . $studentData['students_has_intakes_id'] . "',
                                '" . $now . "',
                                '" . $sessionUserObject['user_id'] . "',
                                'Resend Welcome Email'
                                                            
                            )
                                ");

                            try {
                                $mail = EmailNotificationService::build(
                                    EmailTemplateContent::STUDENT_WELCOME_EMAIL,
                                    [
                                        'student_name' => $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'],
                                        'email' => $studentData['email'],
                                        'login_url' => $url,
                                        'programme_name' => $studentData['programme'],
                                    ]
                                );

                                $userName = $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'];

                                // Fetch added-by user email + counsellor email for CC
                                $ccEmails = [];

                                $ccQuery = Database::search("
                                        SELECT
                                          
                                            counsellorUser.user_email AS counsellor_email,
                                            counsellorUser.user_fname AS counsellor_first_name,
                                            counsellorUser.user_lname AS counsellor_last_name
                                        FROM `students`
                                        LEFT JOIN `users` AS counsellorUser
                                            ON `students`.`users_user_id` = counsellorUser.`user_id`
                                        
                                        WHERE `students`.`student_id` = '" . $studentData['students_student_id'] . "'
                                    ");

                                if ($ccQuery && $ccQuery->num_rows > 0) {
                                    $ccData = $ccQuery->fetch_assoc();

                                    if (!empty($ccData['counsellor_email'])) {
                                        $ccEmails[$ccData['counsellor_email']] = trim($ccData['counsellor_first_name'] . ' ' . $ccData['counsellor_last_name']);
                                    }
                                }

                                $ccEmails[$sessionUserObject['user_email']] = trim($sessionUserObject['user_fname'] . ' ' . $sessionUserObject['user_lname']);

                                // Send with CC
                                sendMail($studentData['email'], $userName, $mail['subject'], $mail['body'], $ccEmails);
                                $status->done = 'Welcome email resent to ' . $studentData['email'];
                            } catch (Exception $e) {
                                $status->error = "Welcome email could not be sent to " . $studentData['email'];
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
