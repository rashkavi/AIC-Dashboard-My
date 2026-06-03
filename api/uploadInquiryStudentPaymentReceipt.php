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
    $sessionUserRole   = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $payment_id = isset($_POST['payment_id'])      ? (int)$_POST['payment_id']          : 0;
            $note       = isset($_POST['note'])            ? trim($_POST['note'])                 : '';

            $awaitingQuery = "SELECT
                                                    `cp`.`course_payments_id`,
                                                    `ps`.`payment_status`,
                                                    `ps`.`payment_status_id`,
                                                    `cp`.`due_date`,
                                                    `cp`.`amount`,
                                                    `cp`.`discount`,
                                                    `cp`.`discount_type_discount_type_id`,
                                                    `shi`.`student_id`,
                                                    `ld`.`first_name`,
                                                    `ld`.`last_name`,
                                                    `t`.`title`,
                                                    `s`.`profile_img`,
                                                    `c`.`code`,
                                                    `c`.`decimal_places`,
                                                    `c`.`currency_id` AS `course_currency_id`,
                                                    `pt`.`payment_type`,
                                                    `pc`.`payment_category`,
                                                    IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                    `lead_student_status_lead_student_status_id`
                                                   
                                                FROM `course_payments` AS `cp`
                                                INNER JOIN `payment_status` AS `ps`      ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                                                        ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                INNER JOIN `payment_category` AS `pc`    ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                INNER JOIN `currencies` AS `c`           ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                INNER JOIN `payment_type` AS `pt`        ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
                                                INNER JOIN `students_has_intakes` AS `shi` ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                INNER JOIN `students` AS `s`             ON `shi`.`students_student_id` = `s`.`student_id`
                                                INNER JOIN `login_details` AS `ld`       ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                INNER JOIN `title` AS `t`               ON `s`.`title_title_id` = `t`.`title_id`
                                                LEFT JOIN (
                                                    SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                                                    FROM panelty_payments GROUP BY course_payments_course_payments_id
                                                ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                                               
                                                WHERE `cp`.`course_payments_id` = '" . $payment_id . "'
                                                AND `cp`.`payment_status_payment_status_id` = '1'
                                                GROUP BY `cp`.`course_payments_id`
                                                ORDER BY `cp`.`course_payments_id` DESC";

            $paymentPlan = Database::search($awaitingQuery);

            if ($paymentPlan->num_rows == 0) {
                $status->error = 'Invalid request';
            } else {
                $paymentData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(
                    SessionUser::ifUserIsPro($sessionUserRole) ||
                    UserPermissions::validPermisisonType('leads') ||
                    UserPermissions::validPermisisonType('payments') ||
                    UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id'])
                )) {
                    $status->error = 'You are not authorized to this action.';
                } else {

                    $paymentSlips = [];

                    // ── STEP 4: File validation ──────────────────────────────
                    if (isset($_FILES["slips"])) {
                        $allowed = ["application/pdf", "image/jpeg", "image/png", "image/jpg"];
                        $ext_map = ["application/pdf" => ".pdf", "image/jpeg" => ".jpg", "image/png" => ".png", "image/jpg" => ".jpg"];

                        foreach ($_FILES["slips"]["name"] as $key => $slip_name) {
                            $slip_ex = $_FILES["slips"]["type"][$key];
                            if (!in_array($slip_ex, $allowed)) {
                                $status->slip[] = "File '$slip_name' is not a valid type. Only PDF, JPEG, PNG and JPG files are allowed.";
                            } elseif ($_FILES["slips"]["error"][$key] !== 0) {
                                $status->slip[] = "There was an error uploading '$slip_name'.";
                            } elseif ($_FILES["slips"]["size"][$key] > 10 * 1024 * 1024) {
                                $status->slip[] = "File '$slip_name' exceeds the maximum allowed size of 10MB.";
                            } else {
                                $slipHash    = hash_file('sha256', $_FILES["slips"]["tmp_name"][$key]);
                                $isDuplicate = Database::search("SELECT `course_payment_slip_id` FROM `course_payment_slips` WHERE `slip_hash` = '" . $slipHash . "'");
                                if ($isDuplicate->num_rows > 0) {
                                    $status->slip[] = "$slip_name — Duplicate receipt detected. This slip was already used.";
                                } else {
                                    $paymentSlips[] = 'R' . $payment_id . '_' . uniqid() . $ext_map[$slip_ex];
                                }
                            }
                        }
                    } else {
                        $status->slip[] = "At least one payment slip is required.";
                    }

                    if (!empty($note) && strlen($note) > 150) {
                        $status->note = 'Note must be less than 150 characters';
                    }

                    // ── STEP 5: All valid → INSERT ───────────────────────────
                    $requiredProperties = ['error', 'amount', 'slip', 'paid_date', 'method', 'discount', 'panelty', 'note'];
                    $allValid = true;
                    foreach ($requiredProperties as $prop) {
                        if (property_exists($status, $prop)) {
                            $allValid = false;
                            break;
                        }
                    }

                    if ($allValid) {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');

                        $taxRate = 2.5;

                        $latestTaxRate = Database::search("SELECT * FROM `tax_rates` ORDER BY `tax_rate_id` LIMIT 1");
                        if ($latestTaxRate->num_rows > 0) {
                            $taxRateData = $latestTaxRate->fetch_assoc();
                            $taxRate = (float)$taxRateData['tax_rate'];
                        }

                        $taxAmount = ($paymentData['amount'] * $taxRate) / 100;


                        $insert_col = "  `course_payments_course_payments_id`,
                                `payment_method_payment_method_id`,
                                `date_paid`,
                                `paid_amount`,
                                `discount_amount`,
                                `payment_status_payment_status_id`,
                                `panelty_amount`,
                                `currencies_currency_id`,
                                `note`,
                                `tax_rate`";

                        $escapedNote = !empty($note)
                            ? "'" . Database::escape($note) . "'"
                            : "NULL";

                        $insert_val = " '{$payment_id}',
                '1',
                '{$now}',
                '{$paymentData['amount']}',
                '0',
                '2',
                '0',
                '{$paymentData['course_currency_id']}',
                {$escapedNote},
                '{$taxRate}'";

                        if ($taxRate > 0) {
                            $insert_col .= ",`tax_amount`";
                            $insert_val .= ",'" . $taxAmount . "'";
                        }

                        Database::iud("
                            INSERT INTO `course_payment_has_payments`
                            ({$insert_col})
                            VALUES ({$insert_val})
                        ");

                        $paymentId = Database::$connection->insert_id;


                        // Save slips
                        if (!empty($paymentSlips)) {
                            foreach ($_FILES["slips"]["name"] as $i => $slip_name) {
                                $slipHash  = hash_file('sha256', $_FILES["slips"]["tmp_name"][$i]);
                                $reference = strtoupper(substr($slipHash, 0, 10));
                                Database::iud("INSERT INTO `course_payment_slips` (`course_payment_has_payment_id`,`slip_name`,`reference_number`,`slip_hash`) VALUES ('{$paymentId}','{$paymentSlips[$i]}','{$reference}','{$slipHash}')");
                                move_uploaded_file($_FILES["slips"]["tmp_name"][$i], "../assets/uploads/payment-slips/" . $paymentSlips[$i]);
                            }
                        }

                        // Log
                        $log = 'Upload Direct Payment Receipt and Confirm Payment';

                        $log_cols = "`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`";
                        $log_vals = "'{$payment_id}','{$paymentId}','" . $sessionUserObject['user_id'] . "','{$now}','" . Database::escape($log) . "'";
                        if (!empty($note)) {
                            $log_cols .= ",`note`";
                            $log_vals .= ",'" . Database::escape($note) . "'";
                        }
                        Database::iud("INSERT INTO `payment_logs`({$log_cols}) VALUES({$log_vals})");

                        // Second stage log
                        if (in_array($paymentData['lead_student_status_lead_student_status_id'], [5, 9])) {
                            $nextRs = Database::search("
                                SELECT `cp`.`course_payments_id`
                                FROM `course_payments` cp
                                INNER JOIN `payment_type` pt ON cp.`payment_type_payment_type_id` = pt.`payment_type_id`
                                INNER JOIN `payment_plan_has_students_has_intakes` pshi ON cp.`payment_plan_has_students_has_intakes_id` = pshi.`payment_plan_has_students_has_intakes_id`
                                WHERE cp.`payment_plan_has_students_has_intakes_id` = (
                                    SELECT `payment_plan_has_students_has_intakes_id` FROM `course_payments` WHERE `course_payments_id`='{$payment_id}' LIMIT 1
                                )
                                AND pshi.`students_has_intakes_students_has_intakes_id`='{$paymentData['students_has_intakes_id']}'
                                AND cp.`payment_status_payment_status_id` != '5'
                                AND pt.`payment_type` NOT LIKE '%Registration%'
                                ORDER BY cp.`due_date` ASC, cp.`course_payments_id` ASC
                                LIMIT 1
                            ");
                            if ($nextRs->num_rows > 0) {
                                $next = $nextRs->fetch_assoc();
                                if ((int)$next['course_payments_id'] === $payment_id) {
                                    Database::iud("INSERT INTO `students_logs` (`students_student_id`,`date_updated`,`users_user_id`,`log`,`lead_student_status_lead_student_status_id`) 
                                    VALUES ('{$paymentData['student_id']}','{$now}','" . $sessionUserObject['user_id'] . "','First installment payment receipt uploaded','8')");
                                }
                            }
                        }

                        http_response_code(200);
                        $status->done = 'Payment receipt uploaded successfully';
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

echo json_encode($status);
exit();
