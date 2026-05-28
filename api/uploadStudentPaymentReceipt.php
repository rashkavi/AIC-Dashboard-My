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
            $method     = isset($_POST['method'])          ? (int)$_POST['method']               : 0;
            $paid_date  = isset($_POST['paid_date'])       ? trim($_POST['paid_date'])            : '';
            $note       = isset($_POST['note'])            ? trim($_POST['note'])                 : '';

            // Raw amounts exactly as user typed (could be LKR or base currency)
            $raw_amount          = isset($_POST['paid_amount'])     ? (float)$_POST['paid_amount']     : 0;
            $raw_discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
            $raw_penalty_amount  = isset($_POST['penalty_amount'])  ? (float)$_POST['penalty_amount']  : 0;
            // After your existing currency parsing block, add:
            $taxRate = isset($_POST['tax_rate']) ? (float)$_POST['tax_rate'] : 0.0;
            if ($taxRate < 0 || $taxRate > 100) $taxRate = 0.0;

            // ── Parse currency data ──────────────────────────────────────────
            $currency           = isset($_POST['currency']) ? json_decode($_POST['currency'], true) : null;
            $selectedCurrencyId = null;
            $exchangeRateId     = null;
            $exchangeRate       = 1.0;
            $currencyMode       = 'base';
            $isCustomRate       = false;
            $customRateValue    = null;

            if ($currency !== null) {
                $selectedCurrencyId = isset($currency['selectedCurrencyId']) ? (int)$currency['selectedCurrencyId']   : null;
                $exchangeRateId     = isset($currency['exchangeRateId'])     ? (int)$currency['exchangeRateId']       : null;
                $exchangeRate       = isset($currency['exchangeRate'])       ? (float)$currency['exchangeRate']       : 1.0;
                $currencyMode       = isset($currency['mode'])               ? trim($currency['mode'])                : 'base';
                $isCustomRate       = isset($currency['isCustomRate'])       ? (bool)$currency['isCustomRate']        : false;
                $customRateValue    = isset($currency['customRateValue'])    ? (float)$currency['customRateValue']    : null;

                // If custom rate → force exchangeRateId to NULL so no wrong DB row is referenced
                if ($isCustomRate) {
                    $exchangeRateId = null;
                    if ($customRateValue !== null && $customRateValue > 0) {
                        $exchangeRate = $customRateValue;
                    }
                }
            }

            // Safety guard
            if ($exchangeRate <= 0) $exchangeRate = 1.0;

            // Is user paying in LKR (converted currency)?
            $isLKRPayment = ($currencyMode === 'lkr' && $exchangeRate > 0);

            // Force LKR currency ID (1) when paying in LKR
            if ($isLKRPayment) {
                $selectedCurrencyId = 1; // LKR currency ID
            }

            // ── Convert raw input to BASE currency for DB storage ─────────────
            // If user typed LKR → divide by rate to get base (GBP/USD etc.)
            // If user typed base → use as-is
            if ($isLKRPayment) {
                $amount          = round($raw_amount          / $exchangeRate, 4);
                $discount_amount = round($raw_discount_amount / $exchangeRate, 4);
                $penalty_amount  = round($raw_penalty_amount  / $exchangeRate, 4);
            } else {
                $amount          = round($raw_amount,          4);
                $discount_amount = round($raw_discount_amount, 4);
                $penalty_amount  = round($raw_penalty_amount,  4);
            }

            // ── Fetch payment plan ───────────────────────────────────────────
            $detailQuery = "
                                                                                            SELECT
                                                                                                `cp`.`course_payments_id`,
                                                                                                `cp`.`refference`, `cp`.`amount`, `cp`.`discount`, `cp`.`due_date`,
                                                                                                `cp`.`panelty_payment_option_panelty_payment_option_id`,
                                                                                                `cp`.`discount_note`,
                                                                                                `ps`.`payment_status`, `ps`.`payment_status_id`,
                                                                                                `i`.`intake_id`, `i`.`intake`,
                                                                                                `pr`.`programme_id`, `pr`.`programme`,
                                                                                                `f`.`faculty_id`, `f`.`faculty`,
                                                                                                `ld`.`first_name`, `ld`.`last_name`,
                                                                                                `t`.`title`,
                                                                                                `s`.`lead_student_status_lead_student_status_id`,
                                                                                                `ld`.`email`,
                                                                                                `pt`.`payment_type_id`, `pt`.`payment_type`,
                                                                                                `shi`.`students_has_intakes_id`,
                                                                                                `cp`.`amount`, `cp`.`discount`, `cp`.`due_date`,
                                                                                                `pt`.`payment_type`,
                                                                                                `dt`.`discount_type`, `dt`.`discount_type_id`,
                                                                                                `c`.`code` AS `currency_code`,
                                                                                                `c`.`symbol` AS `currency_symbol`,
                                                                                                `c`.`decimal_places`,
                                                                                                `c`.`currency_id` ,
                                                                                                IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                                                IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                                                IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                                                            FROM `course_payments` AS `cp`
                                                                                            INNER JOIN `payment_type` AS `pt`     ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
                                                                                            INNER JOIN `discount_type` AS `dt`    ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                                                                                            INNER JOIN `payment_status` AS `ps`   ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                                            INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                                                                                                ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                                            INNER JOIN `payment_category` AS `pc` ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                                                            INNER JOIN `currencies` AS `c`        ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                                                            INNER JOIN `students_has_intakes` AS `shi`
                                                                                                                                ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                                            INNER JOIN `intakes` AS `i`              ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                                                                            INNER JOIN `programmes` AS `pr`         ON `i`.`programmes_programme_id` = `pr`.`programme_id`
                                                                                            INNER JOIN `faculties` AS `f`          ON `pr`.`faculties_faculty_id` = `f`.`faculty_id`
                                                                                            INNER JOIN `students` AS `s`          ON `shi`.`students_student_id` = `s`.`student_id`
                                                                                            INNER JOIN `login_details` AS `ld`    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                                                            INNER JOIN `title` AS `t`            ON `s`.`title_title_id` = `t`.`title_id`
                                                                                            LEFT JOIN (
                                                                                                SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                                                                                                FROM panelty_payments GROUP BY course_payments_course_payments_id
                                                                                            ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                                                            LEFT JOIN (
                                                                                                SELECT course_payments_course_payments_id,
                                                                                                    SUM(discount_amount) AS total_discount,
                                                                                                    SUM(panelty_amount)  AS total_sub_panelty_amount
                                                                                                FROM course_payment_has_payments
                                                                                                WHERE payment_status_payment_status_id IN (2,3)
                                                                                                GROUP BY course_payments_course_payments_id
                                                                                            ) cpp ON cp.course_payments_id = cpp.course_payments_course_payments_id
                                                                                             WHERE `cp`.`course_payments_id` = '" . $payment_id . "';
                                                                                        ";

            $paymentPlan = Database::search($detailQuery);

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
                    $currCode     = $paymentData['currency_code'];   // e.g. GBP
                    $currSymbol   = $paymentData['currency_symbol']; // e.g. £

                    // ── STEP 1: Calculate full balance in BASE currency ──────
                    $planDiscount = $paymentData["discount"];
                    if ($paymentData["discount_type_id"] == 3) {
                        $planDiscount = $paymentData["amount"] / 100 * $paymentData["discount"];
                    }

                    $totalDiscount    = $planDiscount;
                    $totalPenalty     = $paymentData['panelty_amount'];


                    // Fetch receipts for this payment
                    $receipts = Database::search("
                                                                                                    SELECT
                                                                                                        cpp.*,
                                                                                                        pm.payment_method,
                                                                                                        pm.payment_method_id,
                                                                                                        ps2.payment_status,
                                                                                                        ps2.payment_status_id,
                                                                                                        cur.code AS paid_currency_code,
                                                                                                        cur.decimal_places AS paid_decimal_places,
                                                                                                        cpp.currency_exchange_rate
                                                                                                    FROM course_payment_has_payments AS cpp
                                                                                                    INNER JOIN payment_method AS pm  ON cpp.payment_method_payment_method_id = pm.payment_method_id
                                                                                                    INNER JOIN payment_status AS ps2 ON cpp.payment_status_payment_status_id = ps2.payment_status_id
                                                                                                    LEFT JOIN currencies AS cur      ON cpp.currencies_currency_id = cur.currency_id
                                                                                                    WHERE cpp.course_payments_course_payments_id = '" . (int)$paymentData['course_payments_id'] . "'
                                                                                                    GROUP BY cpp.course_payment_has_payment_id
                                                                                                ");

                    // Compute converted paid total for this CP row
                    $convertedPaidTotal = 0;
                    $receiptRows = [];
                    $totalPaidAmount = 0;
                    if ($receipts && $receipts->num_rows > 0) {
                        while ($rec = $receipts->fetch_assoc()) {
                            $rate = resolveExchangeRate(null, $rec, $paymentData['currency_id']);
                            $rec['converted_paid']       = convertToCourseAmount($rec['paid_amount'], $rate);
                            $rec['is_foreign_currency']  = ($rate !== null);
                            $rec['currency_exchange_rate']    = $rate;
                            $convertedPaidTotal += $rec['converted_paid'];
                            $receiptRows[] = $rec;
                            if ($rec['payment_status_id'] == 3) $totalPaidAmount += $rec['converted_paid'];
                            if ($rec['payment_status_id'] == 2 || $rec['payment_status_id'] == 3) {
                                $totalDiscount += convertToCourseAmount($rec['discount_amount'], $rate);
                                $totalPenalty += convertToCourseAmount($rec['panelty_amount'], $rate);
                            };
                        }
                    }

                    $payableAmount    = $paymentData['amount'] - $totalDiscount + $totalPenalty;
                    $paidAmount = $convertedPaidTotal;

                    $balanceBeforeTax = $payableAmount - $convertedPaidTotal;
                    $taxAmount        = round($balanceBeforeTax * ($taxRate / 100), 4);
                    $balance          = $balanceBeforeTax + $taxAmount;

                    // Apply this receipt's discount to balance (base)
                    // — validate discount first
                    if ($discount_amount > 0) {
                        if (round($discount_amount, 4) > round($balance, 4)) {
                            // Show error in whichever currency user is using
                            if ($isLKRPayment) {
                                $status->discount = 'Discount (LKR ' . number_format($raw_discount_amount, 2) . ') cannot exceed balance (LKR ' . number_format($balance * $exchangeRate, 2) . ')';
                            } else {
                                $status->discount = 'Discount (' . $currCode . ' ' . number_format($discount_amount, 2) . ') cannot exceed balance (' . $currCode . ' ' . number_format($balance, 2) . ')';
                            }
                        } else {
                            $balance = round($balance - $discount_amount, 4);
                        }
                    }

                    // ── STEP 2: Convert balance to user's selected currency ──
                    // for display in validation error messages only
                    $balanceInUserCurrency = $isLKRPayment
                        ? round($balance * $exchangeRate, 2)   // base → LKR
                        : $balance;                             // already base

                    $userCurrencyLabel = $isLKRPayment ? 'LKR' : $currCode;



                    // ── STEP 3: Validate all fields ──────────────────────────
                    if ($method == 0) {
                        $status->method = 'Please select payment method';
                    }

                    if (empty($paid_date)) {
                        $status->paid_date = 'Please select paid date';
                    } elseif (CheckDateTime::isFutureDate($paid_date)) {
                        $status->paid_date = 'Paid date cannot be in the future';
                    }

                    if (empty($raw_amount) || $raw_amount <= 0) {
                        $status->amount = 'Please enter paid amount';
                    } else {
                        // Compare converted base amount against base balance
                        $epsilon = 0.001; // float safety margin
                        if (($amount - $balance) > $epsilon) {
                            $status->amount = 'Paid amount ('
                                . $userCurrencyLabel . ' ' . number_format($raw_amount, 2)
                                . ') cannot exceed balance ('
                                . $userCurrencyLabel . ' ' . number_format($balanceInUserCurrency, 2) . ')';
                        }
                    }

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

                        $status_status_id = 3;
                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id']))) {
                            $status_status_id = 2;
                        }
                        if (in_array($paymentData['lead_student_status_lead_student_status_id'], [5, 9])) {
                            $status_status_id = 2;
                        }

                        // Currency columns
                        $baseCurrencyId       = (int)$paymentData['currency_id'];
                        $insertCurrencyId     = $selectedCurrencyId ?? $baseCurrencyId;
                        $insertExchangeRateId = $exchangeRateId; // NULL when base

                        // LKR equivalents to store (NULL when paying in base currency)
                        $lkrPaidAmount     = $isLKRPayment ? round($raw_amount,          2) : null;
                        $lkrDiscountAmount = $isLKRPayment ? round($raw_discount_amount,  2) : null;
                        $lkrPenaltyAmount  = $isLKRPayment ? round($raw_penalty_amount,   2) : null;

                        // Helper to quote or NULL
                        $qn = fn($v) => $v !== null ? "'" . $v . "'" : "NULL";

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

                        $insert_val = "  '{$payment_id}',
                                '{$method}',
                                '{$paid_date}',
                                '{$_POST['paid_amount']}',
                                '{$raw_discount_amount}',
                                '{$status_status_id}',
                                '{$raw_penalty_amount}',
                                '{$insertCurrencyId}',
                                " . $qn($note !== '' ? Database::escape($note) : null) . ",
                                '{$taxRate}'";

                        if ($insertCurrencyId != $baseCurrencyId) {
                            $insert_col .= ",`currency_exchange_rate_id`,`currency_exchange_rate`";
                            $insert_val .= "," . $qn($insertExchangeRateId) . "," . $qn($exchangeRate);
                        }

                        if ($taxRate > 0) {
                            $insert_col .= ",`tax_amount`";
                            $insert_val .= "," . $qn($isLKRPayment ? round($taxAmount * $exchangeRate, 2) : round($taxAmount, 2));
                        }

                        Database::iud("
                            INSERT INTO `course_payment_has_payments`
                            ({$insert_col})
                            VALUES ({$insert_val})
                        ");

                        $paymentId = Database::$connection->insert_id;

                        // Mark fully paid if balance = 0
                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id'])) {
                            if (!in_array($paymentData['lead_student_status_lead_student_status_id'], [5, 9])) {
                                if (abs($balance - $amount) < 0.001) {
                                    Database::iud("UPDATE `course_payments` SET `payment_status_payment_status_id`='5' WHERE `course_payments_id`='{$payment_id}'");
                                }
                            }
                        }

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
                        if ($isLKRPayment) {
                            $log .= ' [Paid LKR ' . number_format($raw_amount, 2)
                                . ' = ' . $currCode . ' ' . number_format($amount, 2)
                                . ' @ rate ' . $exchangeRate . ']';
                        }

                        if ($taxRate > 0) {
                            $log .= ' [Tax ' . $taxRate . '% = ' . $currCode . ' ' . number_format($taxAmount, 2) . ']';
                        }

                        $log_cols = "`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`";
                        $log_vals = "'{$payment_id}','{$paymentId}','" . $sessionUserObject['user_id'] . "','{$now}','" . Database::escape($log) . "'";
                        if (!empty($note)) {
                            $log_cols .= ",`note`";
                            $log_vals .= ",'" . Database::escape($note) . "'";
                        }
                        Database::iud("INSERT INTO `payment_logs`({$log_cols}) VALUES({$log_vals})");

                        // Registration payment status update
                        if ($paymentData['payment_type_id'] == 18 && $paymentData['lead_student_status_lead_student_status_id'] == 4) {
                            Database::iud("UPDATE `students` SET `lead_student_status_lead_student_status_id`='5' WHERE `student_id`='{$paymentData['student_id']}'");
                            Database::iud("INSERT INTO `students_logs` (`students_student_id`,`date_updated`,`users_user_id`,`log`,`lead_student_status_lead_student_status_id`) VALUES ('{$paymentData['student_id']}','{$now}','" . $sessionUserObject['user_id'] . "','Registration Payment marked as paid','5')");
                        }

                        // Email
                        if ($status_status_id == 3) {
                            $displayAmount = $isLKRPayment
                                ? 'LKR ' . number_format($raw_amount, 2) . ' (' . $currCode . ' ' . number_format($amount, 2) . ')'
                                : $currCode . ' ' . number_format($amount, 2);

                            $mail = EmailNotificationService::build(
                                EmailTemplateContent::PAYMENT_SUCCESSFUL,
                                [
                                    'student_name'  => $paymentData['first_name'] . ' ' . $paymentData['last_name'],
                                    'programme'     => $paymentData['programme'],
                                    'payment_type'  => $paymentData['payment_type'],
                                    'paid_amount'   => $displayAmount,
                                    'payment_date'  => $paid_date,
                                    'slip_url'      => Project::getBaseUrl() . '/payment-receipt?receipt-id=/' . base64_encode($paymentId),
                                ]
                            );
                            sendMail($paymentData['email'], $paymentData['title'] . '.' . $paymentData['first_name'] . ' ' . $paymentData['last_name'], $mail['subject'], $mail['body']);
                        }

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
                                AND cp.`payment_status_payment_status_id` != '9'
                                AND pt.`payment_type` NOT LIKE '%Registration%'
                                ORDER BY cp.`due_date` ASC, cp.`course_payments_id` ASC
                                LIMIT 1
                            ");
                            if ($nextRs->num_rows > 0) {
                                $next = $nextRs->fetch_assoc();
                                if ((int)$next['course_payments_id'] === $payment_id) {
                                    Database::iud("INSERT INTO `students_logs` (`students_student_id`,`date_updated`,`users_user_id`,`log`,`lead_student_status_lead_student_status_id`) VALUES ('{$paymentData['student_id']}','{$now}','" . $sessionUserObject['user_id'] . "','First installment payment receipt uploaded','8')");
                                }
                            }
                        }

                        http_response_code(200);
                        $status->done = 'Payment receipt uploaded successfully';
                        $status->id   = $paymentData['students_has_intakes_id'];
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
function resolveExchangeRate($db, $row, $courseCurrencyId)
{
    // If payment currency is null or same as course currency → no conversion
    if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrencyId) {
        return null;
    }
    // 1) Use inline custom rate if present
    if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) {
        return (float)$row['currency_exchange_rate'];
    }
    // 2) Lookup from currency_exchange_rates table via currency_exchange_rate_id
    if (!empty($row['currency_exchange_rate_id'])) {
        $rateRow = Database::search(
            "SELECT `rate` AS `currency_exchange_rate` FROM `currency_exchange_rates`
                                                            WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1"
        );
        if ($rateRow && $rateRow->num_rows > 0) {
            $r = $rateRow->fetch_assoc();
            if (!empty($r['currency_exchange_rate']) && $r['currency_exchange_rate'] > 0) {
                return (float)$r['currency_exchange_rate'];
            }
        }
    }
    return null; // cannot convert — treat as same currency
}

function convertToCourseAmount($paidAmount, $exchangeRate)
{
    if ($exchangeRate === null || $exchangeRate <= 0) return (float)$paidAmount;
    return (float)$paidAmount / $exchangeRate;
}
