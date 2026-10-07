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
                        INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                        WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $student_id . "'");
                        if ($student->num_rows == 0) {
                            http_response_code(400);
                            $status->error = 'Invalid student';
                        } else {

                            $studentData = $student->fetch_assoc();

                            $nextPaymentRs = Database::search("
                                SELECT 
                                
                                    `cp`.`course_payments_id`,
                                    `pt`.`payment_type`
                                FROM `course_payments` cp
                                INNER JOIN `payment_type` pt
                                    ON cp.`payment_type_payment_type_id` = pt.`payment_type_id`
                                INNER JOIN `payment_plan_has_students_has_intakes` pshi
                                    ON cp.`payment_plan_has_students_has_intakes_id` = pshi.`payment_plan_has_students_has_intakes_id`
                                WHERE 
                                 pshi.`students_has_intakes_students_has_intakes_id` = '" . $studentData['students_has_intakes_id'] . "'
                                AND pt.`payment_type_id` != 18
                                AND pt.`payment_type` NOT LIKE '%Registration%'
                                ORDER BY cp.`due_date` ASC, cp.`course_payments_id` ASC
                                LIMIT 1
                            ");

                            if ($nextPaymentRs->num_rows == 0) {
                                http_response_code(200);
                                $status->error = 'Something went wrong.Please try again';
                            } else {
                                $payment = $nextPaymentRs->fetch_assoc();

                                $isPaid = Database::search("SELECT * FROM `course_payment_has_payments` 
                              WHERE `course_payments_course_payments_id` = '" . $payment['course_payments_id'] . "'
                              ORDER BY `course_payment_has_payment_id` DESC LIMIT 1");

                                if ($isPaid->num_rows == 0) {
                                    http_response_code(200);
                                    $status->message = 'The initial payment process has not been completed yet.';
                                } else {
                                    $paidData = $isPaid->fetch_assoc();

                                    if ($paidData['payment_status_payment_status_id'] == 4) {
                                        http_response_code(200);
                                        $status->message = 'The initial payment verification was rejected. Please review the payment details and try again.';
                                    } else {

                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $date = $dateTime->format('Y-m-d H:i:s');

                                        if ($paidData['payment_status_payment_status_id'] == 2) {
                                            Database::iud("UPDATE `students_has_intakes` SET `lead_student_status_lead_student_status_id` = '8' WHERE `students_has_intakes_id` = '" . $student_id . "'");

                                            Database::iud("INSERT INTO `students_logs` 
                                        (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                                         VALUES ('" . $student_id . "',  '" . $date . "', '" . $sessionUserObject['user_id'] . "','Confirm Accounts','8')");
                                        } else {
                                            Database::iud("UPDATE `students_has_intakes` SET `lead_student_status_lead_student_status_id` = '11' WHERE `students_has_intakes_id` = '" . $student_id . "'");

                                            Database::iud("INSERT INTO `students_logs` 
                                            (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                                             VALUES ('" . $student_id . "',  '" . $date . "', '" . $sessionUserObject['user_id'] . "','Confirm Accounts','11')");
                                        }

                                        http_response_code(200);
                                        $status->done = 'Academic & Initial Payment Completion confirmed successfully';
                                    }
                                }
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
