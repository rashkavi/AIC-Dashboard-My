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
                        $student = Database::search("SELECT *,`students`.`student_id` AS `sId` FROM `students` 
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

                            Database::iud("UPDATE `login_details` SET `status_status_id` = '1',`verification`='" . $token . "' WHERE `login_details_id` = '" . $studentData['login_details_login_details_id'] . "'");
                            Database::iud("UPDATE `students_has_intakes` SET `status_status_id` = '1',`lead_student_status_lead_student_status_id` = '17' WHERE `students_has_intakes_id` = '" . $studentData['students_has_intakes_id'] . "'");
                            Database::iud("UPDATE `students` SET `enrolled_status` = 'ENROLLED' WHERE `student_id` = '" . $studentData['sId'] . "'");


                            Database::iud("
                            INSERT INTO `students_logs`
                            (`students_has_intakes_students_has_intakes_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`)
                            VALUES
                            (
                                '" . $studentData['students_has_intakes_id'] . "',
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
                                        'login_url' => $url,
                                        'programme_name' => $studentData['programme'],
                                    ]
                                );

                                $userName = $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'];

                                // Fetch counsellor email for CC
                                $ccEmails = [];

                                $ccQuery = Database::search("
                                        SELECT
                                            counsellorUser.user_email AS counsellor_email,
                                            counsellorUser.user_fname AS counsellor_first_name,
                                            counsellorUser.user_lname AS counsellor_last_name
                                        FROM `students`
                                        INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                                        LEFT JOIN `users` AS counsellorUser
                                            ON `students_has_intakes`.`counselor_user_id` = counsellorUser.`user_id`
                                        WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $studentData['students_has_intakes_id'] . "'
                                    ");

                                if ($ccQuery && $ccQuery->num_rows > 0) {
                                    $ccData = $ccQuery->fetch_assoc();

                                    if (!empty($ccData['counsellor_email'])) {
                                        $ccEmails[$ccData['counsellor_email']] = trim($ccData['counsellor_first_name'] . ' ' . $ccData['counsellor_last_name']);
                                    }
                                }

                                // Add the logged-in user (who confirmed) to CC
                                $ccEmails[$sessionUserObject['user_email']] = trim($sessionUserObject['user_fname'] . ' ' . $sessionUserObject['user_lname']);

                                // Send with CC
                                sendMail($studentData['email'], $userName, $mail['subject'], $mail['body'], $ccEmails);

                                // ---- Log: email sent (with CC names + emails) ----
                                $ccParts = [];
                                foreach ($ccEmails as $ccEmail => $ccName) {
                                    $ccParts[] = ($ccName != '' ? $ccName : 'Unknown') . ' (' . $ccEmail . ')';
                                }
                                $ccText = !empty($ccParts) ? implode(', ', $ccParts) : 'None';

                                $emailLog = "Welcome email sent to " . $userName . " (" . $studentData['email'] . ") | CC: " . $ccText;

                                $logTime = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');

                                Database::iud("
                                    INSERT INTO `students_logs`
                                    (`students_has_intakes_students_has_intakes_id`, `date_updated`, `users_user_id`, `log`)
                                    VALUES
                                    (
                                        '" . $studentData['students_has_intakes_id'] . "',
                                        '" . $logTime . "',
                                        '" . $sessionUserObject['user_id'] . "',
                                        '" . Database::escape($emailLog) . "'
                                        
                                    )
                                ");

                                $status->done = 'Academic department completion confirmed';
                            } catch (Exception $e) {
                                $emailLog = "Welcome email could not be sent to " . $studentData['email'] . "";

                                $logTime = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');
                                Database::iud("
                                    INSERT INTO `students_logs`
                                    (`students_has_intakes_students_has_intakes_id`, `date_updated`, `users_user_id`, `log`)
                                    VALUES
                                    (
                                        '" . $studentData['students_has_intakes_id'] . "',
                                        '" . $logTime . "',
                                        '" . $sessionUserObject['user_id'] . "',
                                        '" . Database::escape($emailLog) . "'
                                        
                                    )
                                ");
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
