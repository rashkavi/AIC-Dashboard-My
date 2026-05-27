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

                    $leadId = isset($decoded['leadId']) ? $decoded['leadId'] : '';
                    $intakePaymentPlanId = isset($decoded['intakePaymentPlanId']) ? $decoded['intakePaymentPlanId'] : '';
                    $markAsPaidPayments = isset($decoded['paidPayments']) ? $decoded['paidPayments'] : [];

                    if (empty($leadId) || $leadId == 0 || !is_numeric($leadId)) {
                        $status->error = 'Invalid Lead Id';
                    } else {
                        $isValidLead = Database::search("SELECT * FROM `students` 
                        INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                        INNER JOIN `title` ON `title`.`title_id` = `students`.`title_title_id`
                        INNER JOIN `students_has_intakes` ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                        INNER JOIN `intakes` ON `intakes`.`intake_id` = `students_has_intakes`.`intakes_intake_id`
                        INNER JOIN `programmes` ON `programmes`.`programme_id` = `intakes`.`programmes_programme_id`
                        WHERE `students`.`student_id` = '" . $leadId . "'");
                        if ($isValidLead->num_rows == 0) {
                            $status->error = 'Invalid Lead Id';
                        } else {
                            $leadData = $isValidLead->fetch_assoc();

                            if (empty($intakePaymentPlanId)) {
                                $status->error = 'Invalid Intake Payment Plan Id';
                            } else {
                                $isValidPaymentPlan = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intake_has_payment_plan_id` = '" . $intakePaymentPlanId . "'");
                                if ($isValidPaymentPlan->num_rows == 0) {
                                    $status->error = 'Invalid Intake Payment Plan Id';
                                } else {

                                    $paymentPlanData = $isValidPaymentPlan->fetch_assoc();

                                    if (count($markAsPaidPayments) > 0) {
                                        foreach ($markAsPaidPayments as $paidPayment) {

                                            $paymentTypeId = (int)$paidPayment["payment_type_id"];

                                            if ($paymentTypeId !== 18) {
                                                continue;
                                            }

                                            $paidAmount = (float)$paidPayment["paid_amount"];
                                            $paidDate = $paidPayment["paid_date"];
                                            $paymentMethodId = (int)$paidPayment["payment_method_id"];
                                            $note = trim($paidPayment["note"] ?? "");

                                            if ($paidAmount <= 0) {
                                                $status->paid_amount = "Paid amount is required";
                                            }

                                            if (empty($paidDate)) {
                                                $status->paid_date = "Paid date is required";
                                            }

                                            if ($paymentMethodId <= 0) {
                                                $status->paid_date = "Payment method is required";
                                            }
                                        }
                                    }


                                    if (empty(get_object_vars($status))) {
                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $addTime = $dateTime->format('Y-m-d H:i:s');

                                        $studentHasIntakeId = $leadData['students_has_intakes_id'];

                                        $paymentPlanPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments` 
                                    WHERE `intake_has_payment_plan_id` = '" . $intakePaymentPlanId . "'");
                                        $paymentPlanPaymentsPenalities = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` IN (SELECT `intake_payments_plan_has_payment_id` 
                                    FROM `intake_payments_plan_has_payments` WHERE `intake_has_payment_plan_id` = '" . $intakePaymentPlanId . "')");

                                        $studentPlanPayments = $paymentPlanPayments;

                                        $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`,
                                    `discount_type_discount_type_id`,
                                    `amount`, `discount`,
                                        `date_added`,`intake_has_payment_plan_id`,
                                        `payment_plan_payment_plan_id`,
                                        `payment_category_payment_category_id`";
                                        $payment_plan_insert_values = "'" . $studentHasIntakeId . "',
                                    '" . $paymentPlanData['discount_type_discount_type_id'] . "', '" . $paymentPlanData['amount'] . "',
                                    '" . $paymentPlanData['discount'] . "', 
                                    '" . $addTime . "', '" . $intakePaymentPlanId . "',
                                    '" . $paymentPlanData['payment_plan_payment_plan_id'] . "',
                                    '" . $paymentPlanData['payment_category_payment_category_id'] . "'";

                                        if (!empty($paymentPlanData['discount_note'])) {
                                            $payment_plan_insert_columns .= ", `discount_note`";
                                            $payment_plan_insert_values .= ", '" . $paymentPlanData['discount_note'] . "'";
                                        }

                                        Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $payment_plan_insert_columns . ") VALUES(" . $payment_plan_insert_values . ")");
                                        $planId = Database::$connection->insert_id;

                                        if ($studentPlanPayments->num_rows > 0) {
                                            while ($payment = $studentPlanPayments->fetch_assoc()) {
                                                $insert_col = "`payment_status_payment_status_id`,`payment_type_payment_type_id`,`refference`,`discount`,
                                                `amount`,`discount_type_discount_type_id`,`discount_note`,`payment_plan_has_students_has_intakes_id`,
                                                `panelty_payment_option_panelty_payment_option_id`,`intake_payments_plan_has_payment_id`";

                                                $insert_val = "1,
                                                '" . $payment['payment_type_payment_type_id'] . "',
                                                '" . generateSecureUniqueId() . "',
                                                '" . $payment['discount'] . "',
                                                '" . $payment['amount'] . "',
                                                '" . $payment['discount_type_discount_type_id'] . "',
                                                '" . $payment['discount_note'] . "',
                                                '" . $planId . "',
                                                '" . $payment['panelty_payment_option_panelty_payment_option_id'] . "',
                                                '" . $payment['intake_payments_plan_has_payment_id'] . "'";

                                                if (!empty($payment['due_date'])) {
                                                    $insert_col .= ", `due_date`";
                                                    $insert_val .= ", '" . $payment['due_date'] . "'";
                                                }

                                                Database::iud("INSERT INTO `course_payments`(" . $insert_col . ") VALUES(" . $insert_val . ")");
                                                $paymentId = Database::$connection->insert_id;

                                                while ($penality = $paymentPlanPaymentsPenalities->fetch_assoc()) {
                                                    if ($penality['intake_payments_plan_has_payment_id'] == $payment['intake_payments_plan_has_payment_id']) {
                                                        $insert_col = "`course_payments_course_payments_id`,`amount`,`days`,`panelty_payment_type_panelty_payment_type_id`";
                                                        $insert_val = "'" . $paymentId . "', '" . $penality['amount'] . "', '" . $penality['days'] . "', '" . $penality['panelty_payment_type_panelty_payment_type_id'] . "'";
                                                        Database::iud("INSERT INTO `course_payments_penalties`(" . $insert_col . ") VALUES(" . $insert_val . ")");
                                                    }
                                                }

                                                if (count($markAsPaidPayments) > 0) {
                                                    foreach ($markAsPaidPayments as $markAsPaidPayment) {
                                                        if ($markAsPaidPayment['installment_id'] == $payment['intake_payments_plan_has_payment_id']) {
                                                            $currencyResult = Database::search("SELECT `currencies_currency_id` AS `currency_id` FROM `payment_category` WHERE `payment_category_id` = '" . $paymentPlanData['payment_category_payment_category_id'] . "' LIMIT 1");
                                                            $paymentCurrencyId = 1;
                                                            if ($currencyResult->num_rows > 0) {
                                                                $paymentCurrencyId = (int)$currencyResult->fetch_assoc()['currency_id'];
                                                            }

                                                            $columns = "`course_payments_course_payments_id`,`payment_method_payment_method_id`
                                                            ,`date_paid`,`paid_amount`,`discount_amount`,`payment_status_payment_status_id`,`panelty_amount`,`bank_bank_id`,`currencies_currency_id`,`currency_exchange_rate_id`,`currency_exchange_rate`";

                                                            $values = "'" . $paymentId . "','" . $markAsPaidPayment['payment_method_id'] . "','" . $markAsPaidPayment['paid_date'] . "','" . $markAsPaidPayment['paid_amount'] . "','" . $markAsPaidPayment['discount'] . "','3','0',NULL,'" . $paymentCurrencyId . "',NULL,'1.0000'";

                                                            Database::iud("INSERT INTO `course_payment_has_payments` (" . $columns . ") VALUES (" . $values . ")");
                                                            $cphp_id = Database::$connection->insert_id;

                                                            $discount = $payment['discount'];
                                                            if ($payment['discount_type_discount_type_id'] == 3) {
                                                                $discount = $payment['amount'] / 100 * $payment['discount'];
                                                            }

                                                            $payableAmount = $payment['amount'] - $discount;
                                                            $paidAmount = $markAsPaidPayment['paid_amount'] - $markAsPaidPayment['discount'];

                                                            if ($payableAmount == $paidAmount) {
                                                                Database::iud("UPDATE `course_payments` SET `payment_status_payment_status_id` = 5 WHERE `course_payments_id` = '" . $paymentId . "'");
                                                            }

                                                            $payment_log_columns = "`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`";
                                                            $payment_log_values = "'" . $paymentId . "','" . $cphp_id . "','" . $sessionUserObject['user_id'] . "', '" . $addTime . "', 'Registration Fee Marked as Paid'";

                                                            if (!empty($markAsPaidPayment['note'])) {
                                                                $payment_log_columns .= ",`note`";
                                                                $payment_log_values .= ",'" . Database::escape($markAsPaidPayment['note']) . "'";
                                                            }
                                                            Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");

                                                            Database::iud("INSERT INTO `students_logs` 
                                                        (`students_student_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`,`note`)
                                                         VALUES ('" . $leadId . "', '" . $addTime . "', '" . $sessionUserObject['user_id'] . "','Registration Fee Approved','3','" . Database::escape($markAsPaidPayment['note']) . "')");

                                                            $encodedPaymentId = base64_encode($paymentId);

                                                            $slipUrl = Project::getBaseUrl() . '/payment-receipt?receipt-id=/' . $encodedPaymentId;

                                                            $mail = EmailNotificationService::build(
                                                                EmailTemplateContent::PAYMENT_SUCCESSFUL,
                                                                [
                                                                    'student_name' => $leadData['first_name'] . ' ' . $leadData['last_name'],
                                                                    'programme' => $leadData['programme'],
                                                                    'payment_type' => "Registration Fee",
                                                                    'paid_amount' => $markAsPaidPayment['paid_amount'],
                                                                    'payment_date' => $markAsPaidPayment['paid_date'],
                                                                    'slip_url' => $slipUrl,
                                                                ]
                                                            );

                                                            $fullName = $leadData['title'] . "." . $leadData['first_name'] . ' ' . $leadData['last_name'];
                                                            sendMail($leadData['email'], $fullName, $mail['subject'], $mail['body']);
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $studentData = Database::search("
                                    SELECT 
                                        s.student_id,
                                        ld.first_name,
                                        ld.last_name,
                                        ld.email,
                                        t.title,
                                        pp.payment_plan,
                                        pc.payment_category,
                                        c.code

                                    FROM students s

                                    INNER JOIN login_details ld
                                        ON s.login_details_login_details_id = ld.login_details_id
                                    INNER JOIN title t
                                        ON s.title_title_id = t.title_id

                                    INNER JOIN payment_plan_has_students_has_intakes pphsi
                                        ON pphsi.students_has_intakes_students_has_intakes_id = '" . $studentHasIntakeId . "'

                                    INNER JOIN payment_plan pp
                                        ON pphsi.payment_plan_payment_plan_id = pp.payment_plan_id

                                    INNER JOIN payment_category pc
                                        ON pphsi.payment_category_payment_category_id = pc.payment_category_id

                                    INNER JOIN currencies c
                                        ON pc.currencies_currency_id = c.currency_id

                                    WHERE s.student_id = '" . $leadId . "'
                                ")->fetch_assoc();

                                        $paymentRows = "";

                                        $coursePayments = Database::search("
                                                    SELECT 
                                                        cp.*,
                                                        pt.payment_type,
                                                        dt.discount_type

                                                    FROM course_payments cp

                                                    INNER JOIN payment_type pt
                                                        ON cp.payment_type_payment_type_id = pt.payment_type_id

                                                    INNER JOIN discount_type dt
                                                        ON cp.discount_type_discount_type_id = dt.discount_type_id

                                                    WHERE cp.payment_plan_has_students_has_intakes_id = '" . $planId . "'

                                                    ORDER BY cp.due_date ASC
                                                ");

                                        $count = 1;

                                        while ($payment = $coursePayments->fetch_assoc()) {

                                            $discountAmount = $payment['discount'];

                                            if ($payment['discount_type_discount_type_id'] == 3) {
                                                $discountAmount = ($payment['amount'] / 100) * $payment['discount'];
                                            }

                                            $finalAmount = $payment['amount'] - $discountAmount;

                                            $paymentRows .= '
                                                <tr>
                                                    <td style="padding:10px;border:1px solid #ddd;">' . $count . '</td>

                                                    <td style="padding:10px;border:1px solid #ddd;">
                                                        ' . htmlspecialchars($payment['payment_type']) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;">
                                                        ' . htmlspecialchars($payment['due_date']) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . $studentData['code'] . ' ' . number_format($payment['amount'], 2) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . $studentData['code'] . ' ' . number_format($discountAmount, 2) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;font-weight:bold;">
                                                        ' . $studentData['code'] . ' ' . number_format($finalAmount, 2) . '
                                                    </td>
                                                </tr>
                                            ';

                                            $count++;
                                        }

                                        Database::iud("UPDATE `students` SET `lead_student_status_lead_student_status_id` = '4' WHERE `student_id` = '" . $leadId . "'");

                                        Database::iud("INSERT INTO `students_logs` 
                                    (`students_student_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                                     VALUES ('" . $leadId . "', '" . $addTime . "', '" . $sessionUserObject['user_id'] . "','Payment Plan Created','4')");


                                        $mail = EmailNotificationService::build(
                                            EmailTemplateContent::PAYMENT_PLAN_CREATED,
                                            [


                                                'student_name' => $studentData['first_name'] . ' ' . $studentData['last_name'],

                                                'payment_plan' => $studentData['payment_plan'],

                                                'payment_category' => $studentData['payment_category'],

                                                'payment_table' => '
                                                <table width="100%" cellspacing="0" cellpadding="0"
                                                       style="border-collapse:collapse;margin-top:15px;font-size:14px;">
                                    
                                                    <thead>
                                                        <tr style="background:#f5f5f5;">
                                                            <th style="padding:10px;border:1px solid #ddd;">#</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Payment Type</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Due Date</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Amount</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Discount</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Payable</th>
                                                        </tr>
                                                    </thead>
                                    
                                                    <tbody>
                                                        ' . $paymentRows . '
                                                    </tbody>
                                    
                                                </table>
                                            '

                                            ]
                                        );

                                        $fullName = $studentData['title'] . "." . $studentData['first_name'] . ' ' . $studentData['last_name'];
                                        sendMail($studentData['email'], $fullName, $mail['subject'], $mail['body']);


                                        $status->done = 'Payment Plan Created Successfully';
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
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
