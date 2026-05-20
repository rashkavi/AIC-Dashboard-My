<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $inquiryId = isset($decoded['id']) ? $decoded['id'] : 0;
                    $inquiryStatus = isset($decoded['updateStatus']) ? $decoded['updateStatus'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';
                    $intake = isset($decoded['intake']) ? $decoded['intake'] : '';
                    $sid = isset($decoded['sid']) ? $decoded['sid'] : '';

                    if ($inquiryId == 0) {
                        $status->error = "Invalid inquiry id";
                    } else {
                        $isValidInquiryId = Database::search("SELECT * FROM `students` 
                        INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                        INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                        WHERE `student_id` = '" .  $inquiryId . "'");
                        if ($isValidInquiryId->num_rows == 0) {
                            $status->error = "Invalid inquiry id";
                        } else {
                            $studentData = $isValidInquiryId->fetch_assoc();

                            if (empty($inquiryStatus)) {
                                $status->status = "Please select status";
                            }

                            if (!empty($note) && strlen($note) > 100) {
                                $status->note = "Note is too long";
                            }

                            if ($inquiryStatus == 4) {
                                if (empty($intake)) {
                                    $status->intake = "Intake is required";
                                }

                                if (empty($sid)) {
                                    $status->sid = "Student Intake ID is required";
                                } else {
                                    $validId = Database::search("SELECT * FROM `students_has_intakes` WHERE `student_id` = '" . $sid . "'");
                                    if ($validId->num_rows > 0) {
                                        $status->sid = "Intake ID already exists";
                                    }
                                }
                            }

                            if (!property_exists($status, "status") && !property_exists($status, "note") && !property_exists($status, "intake") && !property_exists($status, "sid")) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                Database::iud("UPDATE `students` SET `lead_student_status_lead_student_status_id` = '" . $inquiryStatus . "' WHERE `student_id` = '" . $inquiryId . "'");

                                Database::iud("INSERT INTO `students_logs` 
                                (`students_student_id`, `note`, `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                                 VALUES ('" . $inquiryId . "', '" . Database::escape($note) . "', '" . $date . "', '" . $sessionUserObject['user_id'] . "','Update Lead Status','" . $inquiryStatus . "')");

                                if ($inquiryStatus == 4) {

                                    $bytes = random_bytes(75);
                                    $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                                    Database::iud("INSERT INTO `students_has_intakes` 
                                    (`students_student_id`, `intakes_intake_id`,`student_id`,`addmission_date`,`status_status_id`,`users_user_id`) 
                                    VALUES
                                     ('" . $inquiryId . "', '" . $intake . "','" . $sid . "','" . $date . "','1','" . $sessionUserObject['user_id'] . "')");

                                    Database::iud("UPDATE `login_details` SET `status_status_id` = '1',`verification` = '" . $token . "' WHERE `login_details_id` = '" . $studentData['login_details_login_details_id'] . "'");

                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::PASSWORD_SETUP,
                                        [
                                            'action_url' => Project::getBasePortalUrl() . "/setup-password?token=" . $token
                                        ]
                                    );

                                    $userName = $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'];

                                    sendMail($studentData['email'], $userName, $mail['subject'], $mail['body']);
                                }

                                $status->done = "Inquiry status updated successfully";
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
