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

                            $paymentPlanCreate = Database::search("SELECT * FROM `payment_plan_has_students_has_intakes` 
                            INNER JOIN `students_has_intakes` ON `payment_plan_has_students_has_intakes`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $student_id . "'");
                            if ($paymentPlanCreate->num_rows == 0) {
                                http_response_code(400);
                                $status->error = 'Payment plan not created for this student';
                            } else {

                                $paymentPlanCreateData = $paymentPlanCreate->fetch_assoc();

                                $paymentType = [18, 19];
                                if ($paymentPlanCreateData['payment_category_payment_category_id'] == 4) {
                                    $paymentType[] = 20;
                                }

                                // Build a safe IN clause (integers only)
                                $paymentTypeIn = implode(',', array_map('intval', $paymentType));
                                $studentIdSafe = (int) $student_id;

                                $registrationPaymentApprove = Database::search("SELECT course_payment_has_payments.*
                                        FROM course_payment_has_payments
                                        INNER JOIN course_payments
                                            ON course_payment_has_payments.course_payments_course_payments_id = course_payments.course_payments_id
                                        INNER JOIN payment_plan_has_students_has_intakes
                                            ON course_payments.payment_plan_has_students_has_intakes_id = payment_plan_has_students_has_intakes.payment_plan_has_students_has_intakes_id
                                        INNER JOIN students_has_intakes
                                            ON payment_plan_has_students_has_intakes.students_has_intakes_students_has_intakes_id = students_has_intakes.students_has_intakes_id
                                        WHERE `payment_type_payment_type_id` IN ($paymentTypeIn)
                                        AND `course_payment_has_payments`.`payment_status_payment_status_id` = 3
                                        AND `students_has_intakes`.`students_has_intakes_id` = $studentIdSafe
                                        LIMIT 1");

                                if ($registrationPaymentApprove->num_rows == 0) {
                                    http_response_code(400);
                                    $status->error = ($paymentPlanCreateData['payment_category_payment_category_id'] == 4)
                                        ? 'Registration payment or 1st installment not approved yet'
                                        : 'Registration payment not approved yet';
                                } else {

                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $date = $dateTime->format('Y-m-d H:i:s');

                                    Database::iud("UPDATE `students_has_intakes` SET `lead_student_status_lead_student_status_id` = '5' WHERE `students_has_intakes_id` = '" . $student_id . "'");

                                    Database::iud("INSERT INTO `students_logs` 
                                    (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                                     VALUES ('" . $student_id . "',  '" . $date . "', '" . $sessionUserObject['user_id'] . "','Confirm Accounts','5')");

                                    http_response_code(200);
                                    $status->done = 'Accounts confirmed successfully';
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
