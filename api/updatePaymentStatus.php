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
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $paymentId = isset($decoded['paymentId']) ? $decoded['paymentId'] : 0;
                    $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';
                    $paymentMethod = isset($decoded['method']) ? $decoded['method'] : 0;
                    $paymentAmount = isset($decoded['amount']) ? $decoded['amount'] : 0;
                    $paymentDate = isset($decoded['date']) ? $decoded['date'] : 0;

                    if ($paymentId == 0) {
                        $status->error = "Invalid payment id";
                    } else {
                        $isValidPaymentId = Database::search("SELECT * FROM `course_payment_has_payments` 
                        INNER JOIN `payment_method` ON `course_payment_has_payments`.`payment_method_payment_method_id` = `payment_method`.`payment_method_id`
                        
                        WHERE `course_payment_has_payment_id` = '" . $paymentId . "'");
                        if ($isValidPaymentId->num_rows == 0) {
                            $status->error = "Invalid payment id";
                        } else {
                            $coursePaymentData = $isValidPaymentId->fetch_assoc();
                            $isValidPaymentStatus = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` = '" . $paymentStatus . "'");
                            if ($isValidPaymentStatus->num_rows == 0) {
                                $status->status = "Please select payment status";
                            } else if ($coursePaymentData['payment_status_payment_status_id'] == $paymentStatus) {
                                $status->status = "This payment status already updated";
                            }

                            if (!property_exists($status, "status") && !property_exists($status, "note")) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                $paymentStatusData = $isValidPaymentStatus->fetch_assoc();

                                $update_query = "`payment_status_payment_status_id` = '" . $paymentStatus . "'";

                                if (!empty($paymentAmount)) {
                                    $update_query .= ", `paid_amount` = '" . $paymentAmount . "'";
                                }

                                if (!empty($paymentMethod) && $paymentMethod != 0) {
                                    $update_query .= ", `payment_method_payment_method_id` = '" . $paymentMethod . "'";
                                }

                                if (!empty($paymentDate)) {
                                    $update_query .= ", `date_paid` = '" . $paymentDate . "'";
                                }


                                Database::iud("UPDATE `course_payment_has_payments` SET " . $update_query . " WHERE `course_payment_has_payment_id` = '" . $paymentId . "'");

                                Database::iud("INSERT INTO `payment_logs` (`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`note`,`log`) 
                                VALUES ('" . $coursePaymentData["course_payments_course_payments_id"] . "','" . $paymentId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . Database::escape($note) . "','Update payment status to " . $paymentStatusData["payment_status"] . "')");

                                if ($paymentStatus == 3) {
                                    $paymentPlan = Database::search("SELECT
                                        `cp`.`course_payments_id`, 
                                        `ps`.`payment_status`,
                                        `ps`.`payment_status_id`,
                                        `pgp`.`payment_type`,
                                        `cp`.`amount`,
                                        `cp`.`discount`,
                                        `cp`.`panelty_payment_option_panelty_payment_option_id`,
                                        `dt`.`discount_type`,
                                        `dt`.`discount_type_id`, 
                                    
                                        `cp`.`due_date`,
                                    
                                        `cp`.`refference`,
                                                                                                              
                                        `ppo`.`panelty_payment_option`,
                                        `ld`.`first_name`,
                                        `ld`.`last_name`,
                                        `ld`.`whatsapp`,
                                        `s`.`mobile`,
                                        `p`.`programme`,
                                        `i`.`intake`,
                                        `pgp`.`payment_type`,
                                        `pc`.`payment_category`,
                                        `c`.`code`,
                                         `c`.`decimal_places`,
                                         `s`.`student_id`,
                                         `ld`.`first_name`,
                                         `ld`.`last_name`,
                                         `ld`.`email`,
                                         `t`.`title`,
                                         `s`.`lead_student_status_lead_student_status_id`,
                                        IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                        IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                        IFNULL(cpp.total_discount, 0) AS total_discount,
                                        IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                    FROM `course_payments` AS `cp`
                                    INNER JOIN `payment_status` AS `ps` 
                                        ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                    INNER JOIN `panelty_payment_option` AS `ppo` 
                                        ON `cp`.`panelty_payment_option_panelty_payment_option_id` = `ppo`.`panelty_payment_option_id`
                                    INNER JOIN `payment_type` AS `pgp`
                                    ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
                                    INNER JOIN `discount_type` AS `dt` 
                                        ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                                    
                                   INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi` 
                                        ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                    INNER JOIN `students_has_intakes` AS `shi` 
                                        ON `pphsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                    INNER JOIN `students` AS `s` 
                                        ON `shi`.`students_student_id` = `s`.`student_id`
                                    INNER JOIN `login_details` AS `ld`
                                        ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                    INNER JOIN `title` AS `t` 
                                        ON `s`.`title_title_id` = `t`.`title_id`
                                    INNER JOIN `intakes` AS `i` 
                                        ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                    INNER JOIN `programmes` AS `p` 
                                        ON `i`.`programmes_programme_id` = `p`.`programme_id`
                                    INNER JOIN `payment_category` AS `pc` 
                                        ON `pphsi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                    INNER JOIN `currencies` AS `c` 
                                        ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                    LEFT JOIN (
                                            SELECT
                                                course_payments_course_payments_id,
                                                SUM(amount) AS panelty_amount
                                            FROM panelty_payments

                                            GROUP BY course_payments_course_payments_id
                                        ) pp
                                            ON cp.course_payments_id = pp.course_payments_course_payments_id

                                        LEFT JOIN (
                                            SELECT
                                                course_payments_course_payments_id,
                                                SUM(paid_amount) AS paid_amount,
                                                SUM(discount_amount) AS total_discount,
                                                SUM(panelty_amount) AS total_sub_panelty_amount
                                            FROM course_payment_has_payments
                                            WHERE payment_status_payment_status_id = 3
                                            GROUP BY course_payments_course_payments_id
                                        ) cpp
                                            ON cp.course_payments_id = cpp.course_payments_course_payments_id
                                    WHERE `cp`.`course_payments_id` = '" . $coursePaymentData["course_payments_course_payments_id"] . "'
                                    GROUP BY `cp`.`course_payments_id`
                                    ");

                                    $paymentData = $paymentPlan->fetch_assoc();

                                    $discountAmount = $paymentData["discount"];
                                    if ($paymentData["discount_type_id"] == 3) {
                                        $discountAmount = $paymentData["amount"] / 100 * $paymentData["discount"];
                                    }
                                    $payableAmount = $paymentData["amount"] - $discountAmount + $paymentData["panelty_amount"] + $paymentData["total_sub_panelty_amount"];
                                    $balance = $payableAmount - $paymentData["paid_amount"] - $paymentData["total_discount"];

                                    if ($balance == 0) {
                                        $payment_update_query = "`payment_status_payment_status_id`='5'";
                                        Database::iud("UPDATE `course_payments` SET " . $payment_update_query . " WHERE `course_payments_id`='" . $coursePaymentData["course_payments_course_payments_id"] . "'");
                                    }

                                    if ($paymentData['lead_student_status_lead_student_status_id'] == 8) {
                                        Database::iud("
                                        UPDATE `students`
                                        SET `lead_student_status_lead_student_status_id` = '11'
                                        WHERE `student_id` = '" . $paymentData['student_id'] . "'
                                    ");

                                        Database::iud("
                                        INSERT INTO `students_logs`
                                        (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`,`note`)
                                        VALUES
                                        (
                                            '" . $paymentData['student_id'] . "',
                                            '" . $date . "',
                                            '" . $sessionUserObject['user_id'] . "',
                                            'First installment payment approved',
                                            '10',
                                            '" . Database::escape($note) . "'
                                        )
                                    ");
                                    } else if ($paymentData['lead_student_status_lead_student_status_id'] == 5) {
                                        Database::iud("
                                        INSERT INTO `students_logs`
                                        (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`,`note`)
                                        VALUES
                                        (
                                            '" . $paymentData['student_id'] . "',
                                            '" . $date . "',
                                            '" . $sessionUserObject['user_id'] . "',
                                            'First installment payment approved',
                                            '10',
                                            '" . Database::escape($note) . "'
                                        )
                                    ");
                                    }

                                    $encodedPaymentId = base64_encode($paymentId);

                                    $slipUrl = Project::getBaseUrl() . '/payment-receipt?receipt-id=/' . $encodedPaymentId;

                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::PAYMENT_SUCCESSFUL,
                                        [
                                            'student_name' => $paymentData['first_name'] . ' ' . $paymentData['last_name'],
                                            'programme' => $paymentData['programme'],
                                            'payment_type' => $paymentData['payment_type'],
                                            'paid_amount' => $coursePaymentData['paid_amount'],
                                            'payment_date' => $coursePaymentData['date_paid'],
                                            'slip_url' => $slipUrl,
                                        ]
                                    );

                                    $fullName = $paymentData['title'] . "." . $paymentData['first_name'] . ' ' . $paymentData['last_name'];
                                    sendMail($paymentData['email'], $fullName, $mail['subject'], $mail['body']);

                                    $status->done = "Payment status updated successfully!";
                                } else {

                                    $paymentPlan = Database::search("SELECT
                                    `cp`.`course_payments_id`, 
                                    `ps`.`payment_status`,
                                    `ps`.`payment_status_id`,
                                    `pgp`.`payment_type`,
                                    `cp`.`amount`,
                                    `cp`.`discount`,
                                    `cp`.`panelty_payment_option_panelty_payment_option_id`,
                                    `dt`.`discount_type`,
                                    `dt`.`discount_type_id`, 
                                
                                    `cp`.`due_date`,
                                
                                    `cp`.`refference`,
                                                                                                          
                                    `ppo`.`panelty_payment_option`,
                                    `ld`.`first_name`,
                                    `ld`.`last_name`,
                                    `ld`.`whatsapp`,
                                    `s`.`mobile`,
                                    `p`.`programme`,
                                    `i`.`intake`,
                                    `pgp`.`payment_type`,
                                    `pc`.`payment_category`,
                                    `c`.`code`,
                                     `c`.`decimal_places`,
                                     `s`.`student_id`,
                                     `ld`.`first_name`,
                                     `ld`.`last_name`,
                                     `ld`.`email`,
                                     `t`.`title`,
                                     `s`.`lead_student_status_lead_student_status_id`,
                                    IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                    IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                    IFNULL(cpp.total_discount, 0) AS total_discount,
                                    IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                FROM `course_payments` AS `cp`
                                INNER JOIN `payment_status` AS `ps` 
                                    ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                INNER JOIN `panelty_payment_option` AS `ppo` 
                                    ON `cp`.`panelty_payment_option_panelty_payment_option_id` = `ppo`.`panelty_payment_option_id`
                                INNER JOIN `payment_type` AS `pgp`
                                ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
                                INNER JOIN `discount_type` AS `dt` 
                                    ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                                
                               INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi` 
                                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                INNER JOIN `students_has_intakes` AS `shi` 
                                    ON `pphsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                INNER JOIN `students` AS `s` 
                                    ON `shi`.`students_student_id` = `s`.`student_id`
                                INNER JOIN `login_details` AS `ld`
                                    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                INNER JOIN `title` AS `t` 
                                    ON `s`.`title_title_id` = `t`.`title_id`
                                INNER JOIN `intakes` AS `i` 
                                    ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                INNER JOIN `programmes` AS `p` 
                                    ON `i`.`programmes_programme_id` = `p`.`programme_id`
                                INNER JOIN `payment_category` AS `pc` 
                                    ON `pphsi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                INNER JOIN `currencies` AS `c` 
                                    ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                LEFT JOIN (
                                        SELECT
                                            course_payments_course_payments_id,
                                            SUM(amount) AS panelty_amount
                                        FROM panelty_payments
                                        GROUP BY course_payments_course_payments_id
                                    ) pp
                                        ON cp.course_payments_id = pp.course_payments_course_payments_id

                                    LEFT JOIN (
                                        SELECT
                                            course_payments_course_payments_id,
                                            SUM(paid_amount) AS paid_amount,
                                            SUM(discount_amount) AS total_discount,
                                            SUM(panelty_amount) AS total_sub_panelty_amount
                                        FROM course_payment_has_payments
                                        GROUP BY course_payments_course_payments_id
                                    ) cpp
                                        ON cp.course_payments_id = cpp.course_payments_course_payments_id
                                WHERE `cp`.`course_payments_id` = '" . $coursePaymentData["course_payments_course_payments_id"] . "'
                                GROUP BY `cp`.`course_payments_id`
                                ");

                                    $paymentData = $paymentPlan->fetch_assoc();

                                    if ($paymentData['lead_student_status_lead_student_status_id'] == 8) {
                                        Database::iud("
                                UPDATE `students`
                                SET `lead_student_status_lead_student_status_id` = '9'
                                WHERE `student_id` = '" . $paymentData['student_id'] . "'
                            ");

                                        Database::iud("
                                INSERT INTO `students_logs`
                                (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`,`note`)
                                VALUES
                                (
                                    '" . $paymentData['student_id'] . "',
                                    '" . $date . "',
                                    '" . $sessionUserObject['user_id'] . "',
                                    'First installment payment rejected',
                                    '9',
                                    '" . Database::escape($note) . "'
                                )
                            ");
                                    } else if ($paymentData['lead_student_status_lead_student_status_id'] == 5) {
                                        Database::iud("
                                        INSERT INTO `students_logs`
                                        (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`,`note`)
                                        VALUES
                                        (
                                            '" . $paymentData['student_id'] . "',
                                            '" . $date . "',
                                            '" . $sessionUserObject['user_id'] . "',
                                            'First installment payment rejected',
                                            '9',
                                            '" . Database::escape($note) . "'
                                        )
                                    ");
                                    }
                                    $status->done = "Payment status updated successfully!";
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
