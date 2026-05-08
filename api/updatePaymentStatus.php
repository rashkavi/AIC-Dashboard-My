<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
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
                    $receiptOption = isset($decoded['receiptOption']) ? $decoded['receiptOption'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

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

                            if (empty($note)) {
                                $status->note = "Note is required";
                            }

                            if ($paymentStatus == 6 && $receiptOption == 0) {
                                $status->receiptOption = "Please select receipt option";
                            }


                            if (!property_exists($status, "status") && !property_exists($status, "note")) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                $paymentStatusData = $isValidPaymentStatus->fetch_assoc();

                                if ($paymentStatus == 3) {
                                    Database::iud("UPDATE `course_payment_has_payments` SET `payment_status_payment_status_id` = '5' WHERE `course_payment_has_payment_id` = '" . $paymentId . "'");
                                } else if ($paymentStatus == 6) {
                                    Database::iud("UPDATE `course_payment_has_payments` SET `payment_status_payment_status_id` = '8' WHERE `course_payment_has_payment_id` = '" . $paymentId . "'");
                                } else {
                                    Database::iud("UPDATE `course_payment_has_payments` SET `payment_status_payment_status_id` = '" . $paymentStatus . "' WHERE `course_payment_has_payment_id` = '" . $paymentId . "'");
                                }

                                Database::iud("INSERT INTO `payment_logs` (`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`note`,`log`) 
                                VALUES ('" . $coursePaymentData["course_payments_course_payments_id"] . "','" . $paymentId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . Database::escape($note) . "','Update payment status to " . $paymentStatusData["payment_status"] . "')");

                                if ($paymentStatus == 6) {
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

                                    $discountAmount = $paymentData["discount"];
                                    if ($paymentData["discount_type_id"] == 3) {
                                        $discountAmount = $paymentData["amount"] / 100 * $paymentData["discount"];
                                    }
                                    $payableAmount = $paymentData["amount"] - $discountAmount + $paymentData["panelty_amount"] + $paymentData["total_sub_panelty_amount"];
                                    $balance = $payableAmount - $paymentData["paid_amount"] - $paymentData["total_discount"];

                                    if ($balance == 0) {
                                        $payment_update_query = "`payment_status_payment_status_id`='9'";
                                        Database::iud("UPDATE `course_payments` SET " . $payment_update_query . " WHERE `course_payments_id`='" . $coursePaymentData["course_payments_course_payments_id"] . "'");
                                    }


                                    if ($receiptOption == 1) {
                                        $receptData = [
                                            "reference_no" => $coursePaymentData["course_payment_has_payment_id"],
                                            "student" => $paymentData['first_name'] . " " . $paymentData['last_name'],
                                            "payment_method" => $coursePaymentData["payment_method"],
                                            "date_on" => $dateTime->format('Y-m-d'),
                                            "printed_on" => $dateTime->format('H:i A'),
                                            "bill_title" => $paymentData['payment_category'] . "-" . $paymentData['payment_type'],
                                            "remarks" => $coursePaymentData['date_paid'],
                                            "net_total" => $paymentData['code'] . " " . number_format($coursePaymentData['paid_amount'], $paymentData['decimal_places']),
                                        ];

                                        $status->receiptData = $receptData;
                                        $status->done = "Payment status updated successfully!";
                                    } else if ($receiptOption == 2) {
                                        $guardian_details = Database::search("SELECT * FROM `guardian_details` WHERE `students_student_id` = '" . $paymentData['student_id'] . "'");
                                        if ($guardian_details->num_rows > 0) {
                                            $guardian_data = $guardian_details->fetch_assoc();
                                            if ($guardian_data['whatsapp'] != null) {
                                                $formattedWhatsapp = normalizePhoneNumber($guardian_data['whatsapp']);
                                                $curl = curl_init();
                                                curl_setopt_array($curl, array(
                                                    CURLOPT_URL => 'https://graph.facebook.com/v22.0/1046956908493510/messages',
                                                    CURLOPT_RETURNTRANSFER => true,
                                                    CURLOPT_POST => true,
                                                    CURLOPT_HTTPHEADER => array(
                                                        "Authorization: Bearer EAAWijtYusPgBRMOqgf1ZChSWZBhrXDDKn2XZBl3mavZCkZC1mKfvDaZAfrOKTeaOJce5bMB6fJmK3qsvG545CkUJclqxBJc8aDIc2mFI4pGb1shsAW0z35XLCvugiuZCtyWhUerzoMg5lWI1Ky5hbNQs0iZC3NuoTaavKFJ8iLazqvMVPELLZAKG6FgA8kdDDgJG7zwZDZD",
                                                        "Content-Type: application/json"
                                                    ),
                                                    CURLOPT_POSTFIELDS => json_encode([
                                                        "messaging_product" => "whatsapp",
                                                        "recipient_type" => "individual",
                                                        "to" => $formattedWhatsapp,
                                                        "type" => "template",
                                                        "template" => [
                                                            "name" => "payment_massage",
                                                            "language" => [
                                                                "code" => "en_US"
                                                            ],
                                                            "components" => [
                                                                [
                                                                    "type" => "body",
                                                                    "parameters" => [
                                                                        [
                                                                            "type" => "text",
                                                                            "text" => $paymentData['first_name'] . " " . $paymentData['last_name']
                                                                        ],
                                                                        [
                                                                            "type" => "text",
                                                                            "text" => $coursePaymentData['paid_amount']
                                                                        ],
                                                                        [
                                                                            "type" => "text",
                                                                            "text" => $paymentData['programme']
                                                                        ],
                                                                        [
                                                                            "type" => "text",
                                                                            "text" => $paymentData['intake']
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ])
                                                ));
                                                $response = curl_exec($curl);
                                                curl_close($curl);

                                                $curlData = json_decode($response, true);
                                                if (isset($curlData['error'])) {
                                                    $status->warning =  "Payment status updated successfully. But failed to send whatsapp message";
                                                } else {
                                                    $status->done =  "Payment status updated successfully";
                                                }
                                            } else {
                                                $status->warning =  "Payment status updated successfully. But failed to send whatsapp message as whatsapp number not found";
                                            }
                                        } else {
                                            $status->warning =  "Payment status updated successfully. But failed to send whatsapp message as guardian details not found";
                                        }
                                    } else {
                                        $guardian_details = Database::search("SELECT * FROM `guardian_details` WHERE `students_student_id` = '" . $paymentData['student_id'] . "'");
                                        if ($guardian_details->num_rows > 0) {
                                            $guardian_data = $guardian_details->fetch_assoc();
                                            if ($guardian_data['mobile'] != null) {
                                                require_once("../includes/notify-php-master/autoload.php");

                                                $studentname = $paymentData['first_name'] . " " . $paymentData['last_name'];

                                                $message = "Times Academy\n"
                                                    . "Payment Confirmation\n\n"
                                                    . "{$studentname}\n"
                                                    . "Batch: {$paymentData['intake']}\n"
                                                    . "Programme: {$paymentData['programme']}\n"
                                                    . "Amount: {$paymentData['code']} {$coursePaymentData['paid_amount']}\n\n"
                                                    . "We appreciate your prompt payment.";

                                                $api_instance = new NotifyLk\Api\SmsApi();
                                                $user_id = "15265"; // string | API User ID - Can be found in your settings page.
                                                $api_key = "1RjO7jsSUqztg5oMAon8"; // string | API Key - Can be found in your settings page.
                                                $message = $message; // string | Text of the message. 320 chars max.
                                                $to = preg_replace('/^(?:\+94|0)/', '94', preg_replace('/[\s\-]/', '', $paymentData["mobile"])); // string | Number to send the SMS. Better to use 9471XXXXXXX format.
                                                $sender_id = "TIMES Info"; // string | This is the from name recipient will see as the sender of the SMS. Use \\\"NotifyDemo\\\" if you have not ordered your own sender ID yet.
                                                $contact_fname = ""; // string | Contact First Name - This will be used while saving the phone number in your Notify contacts (optional).
                                                $contact_lname = ""; // string | Contact Last Name - This will be used while saving the phone number in your Notify contacts (optional).
                                                $contact_email = ""; // string | Contact Email Address - This will be used while saving the phone number in your Notify contacts (optional).
                                                $contact_address = ""; // string | Contact Physical Address - This will be used while saving the phone number in your Notify contacts (optional).
                                                $contact_group = 0; // int | A group ID to associate the saving contact with (optional).
                                                $type =  "unicode"; // string | Message type. Provide as unicode to support unicode (optional).

                                                try {
                                                    $api_instance->sendSMS($user_id, $api_key, $message, $to, $sender_id, $contact_fname, $contact_lname, $contact_email, $contact_address, $contact_group, $type);
                                                    $status->done =  "Payment status updated successfully";
                                                } catch (Exception $e) {
                                                    $status->warning =  "Payment status updated successfully. But failed to send SMS";
                                                }
                                            } else {
                                                $status->done = "Payment status updated successfully. But failed to send SMS as guardian mobile not found";
                                            }
                                        } else {
                                            $status->done = "Payment status updated successfully. But failed to send SMS as guardian details not found";
                                        }
                                    }
                                } else {
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
