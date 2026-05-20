<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/DateTimeClass.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/mail-process.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : 0;
            $discount_amount = isset($_POST['discount_amount']) ? $_POST['discount_amount'] : 0;
            $penalty_amount = isset($_POST['penalty_amount']) ? $_POST['penalty_amount'] : 0;
            $method = isset($_POST['method']) ? $_POST['method'] : 0;
            $paid_date = isset($_POST['paid_date']) ? $_POST['paid_date'] : 0;
            $amount = isset($_POST['paid_amount']) ? $_POST['paid_amount'] : 0;
            $note = isset($_POST['note']) ? $_POST['note'] : '';
            $payment_slip = '';

            $paymentPlan = Database::search("SELECT
            `cp`.`course_payments_id`, 
            `ps`.`payment_status`,
            `ps`.`payment_status_id`,
            `pgp`.`payment_type`,
             `pgp`.`payment_type_id`,
            `cp`.`amount`,
            `cp`.`discount`,
            `cp`.`panelty_payment_option_panelty_payment_option_id`,
            `dt`.`discount_type`,
            `dt`.`discount_type_id`, 
          
            `cp`.`due_date`,
          
            `cp`.`refference`,
            `s`.`lead_student_status_lead_student_status_id`,
            `i`.`intake_id`,
            `p`.`programme_id`,
            `f`.`faculty_id`,
            `i`.`intake`,
            `p`.`programme`,
            `f`.`faculty`,
            `t`.`title`,
            `ld`.`first_name`,
            `ld`.`last_name`,
            `ld`.`email`,
            `s`.`student_id`,
            `shi`.`students_has_intakes_id`,
            `ppo`.`panelty_payment_option`,
            IFNULL(pp.panelty_amount, 0) AS panelty_amount,
            IFNULL(cpp.paid_amount, 0) AS paid_amount,
            IFNULL(cpp.total_discount, 0) AS total_discount,
            IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
        FROM `course_payments` AS `cp`
        INNER JOIN `payment_status` AS `ps` 
            ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
        INNER JOIN `panelty_payment_option` AS `ppo` 
            ON `cp`.`panelty_payment_option_panelty_payment_option_id` = `ppo`.`panelty_payment_option_id`
       
        INNER JOIN `discount_type` AS `dt` 
            ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
       INNER JOIN `payment_type` AS `pgp`
        ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
       INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi` 
                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                INNER JOIN `payment_category` AS `pc` 
                    ON `pphsi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                INNER JOIN `currencies` AS `c`
                    ON `pc`.`currencies_currency_id` = `c`.`currency_id`    
                
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
                INNER JOIN `faculties` AS `f` 
                    ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
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
                WHERE payment_status_payment_status_id IN (2,3)
                GROUP BY course_payments_course_payments_id
            ) cpp
                ON cp.course_payments_id = cpp.course_payments_course_payments_id
        WHERE `cp`.`course_payments_id` = '" . $payment_id . "'
        GROUP BY `cp`.`course_payments_id`
        ");

            if ($paymentPlan->num_rows == 0) {
                $status->error = 'Invalid request method';
            } else {
                $paymentData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('leads') || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id']))) {
                    $status->error = 'You are not authorized to this action.';
                } else {

                    $paymentSlips = array();

                    $discountAmount = $paymentData["discount"];
                    if ($paymentData["discount_type_id"] == 3) {
                        $discountAmount = $paymentData["amount"] / 100 * $paymentData["discount"];
                    }
                    $payableAmount = $paymentData["amount"] - $discountAmount + $paymentData["panelty_amount"] + $paymentData["total_sub_panelty_amount"];
                    $balance = $payableAmount - $paymentData["paid_amount"] - $paymentData["total_discount"];

                    if (!empty($penalty_amount)) {
                        $balance = $balance + $penalty_amount;
                    }

                    if (!empty($discount_amount) && $discount_amount > $balance) {
                        $status->discount = 'Discount amount cannot be greater than payable amount';
                    } else {
                        $balance = $balance - $discount_amount;
                    }


                    if ($method == 0) {
                        $status->method = 'Please select payment method';
                    }

                    if (empty($paid_date)) {
                        $status->paid_date = 'Please select paid date';
                    } else if (CheckDateTime::isFutureDate($paid_date)) {
                        $status->paid_date = 'Paid date cannot be in the future';
                    }


                    if (empty($amount)) {
                        $status->amount = 'Please enter paid amount';
                    } else if ($amount > $balance) {
                        $status->amount = 'Paid amount cannot be greater than balance amount';
                    }

                    if (isset($_FILES["slips"])) {
                        $allowed_slip_extensions = array(
                            "application/pdf",
                            "image/jpeg",
                            "image/png",
                            "image/jpg"
                        );

                        foreach ($_FILES["slips"]["name"] as $key => $slip_name) {
                            $slip_ex = $_FILES["slips"]["type"][$key];

                            if (!in_array($slip_ex, $allowed_slip_extensions)) {
                                $status->slip[] = "File '$slip_name' is not a valid type. Only PDF, JPEG, PNG and JPG files are allowed.";
                            } else {
                                $new_slip_extension = '';

                                switch ($slip_ex) {
                                    case "application/pdf":
                                        $new_slip_extension = ".pdf";
                                        break;
                                    case "image/jpeg":
                                        $new_slip_extension = ".jpg";
                                        break;
                                    case "image/png":
                                        $new_slip_extension = ".png";
                                        break;
                                    case "image/jpg":
                                        $new_slip_extension = ".jpg";
                                        break;
                                }

                                if ($_FILES["slips"]["error"][$key] === 0) {
                                    if ($_FILES["slips"]["size"][$key] <= 10 * 1024 * 1024) {
                                        $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$key]);
                                        $isValidSlip = Database::search("SELECT * FROM `course_payment_slips` WHERE `slip_hash` = '" . $slipHash . "'");
                                        if ($isValidSlip->num_rows > 0) {
                                            $status->slip[] = $slip_name . " - Duplicate receipt detected. This payment slip was already used.";
                                        } else {
                                            $slip = 'R' . $payment_id . '_' . uniqid() . $new_slip_extension;
                                            array_push($paymentSlips, $slip);
                                        }
                                    } else {
                                        $status->slip[] = "File '$slip_name' exceeds the maximum allowed size of 10MB.";
                                    }
                                } else {
                                    $status->slip[] = "There was an error uploading the file '$slip_name'.";
                                }
                            }
                        }
                    }

                    if (!empty($note) && strlen($note) > 150) {
                        $status->note = 'Note must be less than 150 characters';
                    }

                    $requiredProperties = [
                        'error',
                        'amount',
                        'slip',
                        'paid_date',
                        'method',
                        'discount',
                        'panelty',
                        'note'
                    ];
                    $allPropertiesExist = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allPropertiesExist = false;
                            break;
                        }
                    }
                    if ($allPropertiesExist) {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $now = $dateTime->format('Y-m-d H:i:s');

                        $status_status_id = 3;

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id']))) {
                            $status_status_id = 2;
                        }
                        if ($paymentData['lead_student_status_lead_student_status_id'] == 5 || $paymentData['lead_student_status_lead_student_status_id'] == 9) {
                            $status_status_id = 2;
                        }

                        $columns = "`course_payments_course_payments_id`,`payment_method_payment_method_id`
                        ,`date_paid`,`paid_amount`,`discount_amount`,`payment_status_payment_status_id`";

                        $values = "'" . $payment_id . "','" . $method . "','" . $paid_date . "','" . $amount . "','" . $discount_amount . "','" . $status_status_id . "'";

                        if (!empty($penalty_amount)) {
                            $columns .= ",`panelty_amount`";
                            $values .= ",'" . $penalty_amount . "'";
                        } else {
                            $columns .= ",`panelty_amount`";
                            $values .= ",'0'";
                        }

                        Database::iud("INSERT INTO `course_payment_has_payments` (" . $columns . ") VALUES (" . $values . ")");

                        $paymentId = Database::$connection->insert_id;

                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id'])) {
                            if ($paymentData['lead_student_status_lead_student_status_id'] != 5 && $paymentData['lead_student_status_lead_student_status_id'] != 9) {
                                if ($balance - $amount == 0) {
                                    $payment_update_query = "`payment_status_payment_status_id`='5'";
                                    Database::iud("UPDATE `course_payments` SET " . $payment_update_query . " WHERE `course_payments_id`='" . $payment_id . "'");
                                }
                            }
                        }

                        if (isset($_FILES["slips"])) {
                            $payment_slip_insert_columns = "`course_payment_has_payment_id`,`slip_name`,`reference_number`,`slip_hash`";

                            for ($i = 0; $i < count($_FILES["slips"]["name"]); $i++) {
                                $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$i]);
                                $reference = strtoupper(substr($slipHash, 0, 10));

                                Database::iud("INSERT INTO `course_payment_slips` (" . $payment_slip_insert_columns . ") VALUES ('" . $paymentId . "','" . $paymentSlips[$i] . "','" . $reference . "','" . $slipHash . "')");
                                $payment_slip_path = "../assets/uploads/payment-slips/" . $paymentSlips[$i];
                                move_uploaded_file($_FILES["slips"]["tmp_name"][$i], $payment_slip_path);
                            }
                        }

                        $log = 'Upload Direct Payment Receipt and Confirm Payment';

                        $payment_log_columns = "`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`";
                        $payment_log_values = "'" . $payment_id . "','" . $paymentId . "','" . $sessionUserObject['user_id'] . "', '" . $now . "', '" . Database::escape($log) . "'";

                        if (!empty($note)) {
                            $payment_log_columns .= ",`note`";
                            $payment_log_values .= ",'" . Database::escape($note) . "'";
                        }
                        Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");

                        if ($paymentData['payment_type_id'] == 18 && $paymentData['lead_student_status_lead_student_status_id'] == 4) {
                            Database::iud("UPDATE `students` SET `lead_student_status_lead_student_status_id` = '5' WHERE `student_id` = '" . $paymentData['student_id'] . "'");

                            Database::iud("INSERT INTO `students_logs` 
                            (`students_student_id`, `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                             VALUES ('" . $paymentData['student_id'] . "','" . $now . "', '" . $sessionUserObject['user_id'] . "','Registration Payment marked as paid','5')");
                        }

                        if ($status_status_id == 3) {
                            $encodedPaymentId = base64_encode($paymentId);

                            $slipUrl = Project::getBaseUrl() . '/payment-receipt?receipt-id=/' . $encodedPaymentId;

                            $mail = EmailNotificationService::build(
                                EmailTemplateContent::PAYMENT_SUCCESSFUL,
                                [
                                    'student_name' => $paymentData['first_name'] . ' ' . $paymentData['last_name'],
                                    'programme' => $paymentData['programme'],
                                    'payment_type' => $paymentData['payment_type'],
                                    'paid_amount' => $amount,
                                    'payment_date' => $paid_date,
                                    'slip_url' => $slipUrl,
                                ]
                            );

                            $fullName = $paymentData['title'] . "." . $paymentData['first_name'] . ' ' . $paymentData['last_name'];
                            sendMail($paymentData['email'], $fullName, $mail['subject'], $mail['body']);
                        }

                        if ($paymentData['lead_student_status_lead_student_status_id'] == 5 || $paymentData['lead_student_status_lead_student_status_id'] == 9) {
                            $isSecondStagePayment = false;

                            $nextPaymentRs = Database::search("
                            SELECT 
                                `cp`.`course_payments_id`,
                                `pt`.`payment_type`
                            FROM `course_payments` cp
                            INNER JOIN `payment_type` pt
                                ON cp.`payment_type_payment_type_id` = pt.`payment_type_id`
                            INNER JOIN `payment_plan_has_students_has_intakes` pshi
                                ON cp.`payment_plan_has_students_has_intakes_id` = pshi.`payment_plan_has_students_has_intakes_id`
                            WHERE cp.`payment_plan_has_students_has_intakes_id` = (
                                SELECT `payment_plan_has_students_has_intakes_id`
                                FROM `course_payments`
                                WHERE `course_payments_id` = '" . $payment_id . "'
                                LIMIT 1
                            )
                                AND pshi.`students_has_intakes_students_has_intakes_id` = '" . $paymentData['students_has_intakes_id'] . "'
                            AND cp.`payment_status_payment_status_id` != '9'
                            AND pt.`payment_type` NOT LIKE '%Registration%'
                            ORDER BY cp.`due_date` ASC, cp.`course_payments_id` ASC
                            LIMIT 1
                        ");

                            if ($nextPaymentRs->num_rows > 0) {
                                $nextPayment = $nextPaymentRs->fetch_assoc();

                                if ((int)$nextPayment['course_payments_id'] === (int)$payment_id) {
                                    $isSecondStagePayment = true;
                                }
                            }

                            if (
                                $isSecondStagePayment
                            ) {
                                Database::iud("
                                    INSERT INTO `students_logs`
                                    (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`)
                                    VALUES
                                    (
                                        '" . $paymentData['student_id'] . "',
                                        '" . $now . "',
                                        '" . $sessionUserObject['user_id'] . "',
                                        'First installment payment receipt uploaded',
                                        '8'
                                    )
                                ");
                            }
                        }

                        http_response_code(200);
                        $status->done = 'Payment receipt uploaded successfully';
                        $status->id = $paymentData['students_has_intakes_id'];
                    }
                }
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
