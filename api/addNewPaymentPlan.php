<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole   = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();

    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);

                if (json_last_error() == JSON_ERROR_NONE) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $date     = $dateTime->format('Y-m-d H:i:s');

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) ||
                        SessionUser::ifUserIsAccounts($sessionUserRole) ||
                        UserPermissions::hasPermission('payments', 1) ||
                        UserPermissions::hasPermission('payments', 22) ||
                        UserPermissions::hasPermission('payments', 23))) {
                        $status->error = 'You are not authorized to add payment plans';
                    } else {

                        // ── Inputs ──────────────────────────────────────────
                        $intakeIds           = isset($decoded['intakes']) ? (array)$decoded['intakes'] : [];
                        $category            = isset($decoded['category'])       ? $decoded['category']       : 0;
                        $planName            = isset($decoded['planName'])        ? trim($decoded['planName']) : '';
                        $plan                = isset($decoded['plan'])            ? $decoded['plan']           : 0;
                        $isDefault           = isset($decoded['isDefault'])       ? (bool)$decoded['isDefault'] : false;
                        $payments            = isset($decoded['payments'])        ? $decoded['payments']       : [];
                        $paymentAmount       = isset($decoded['paymentAmount'])   ? $decoded['paymentAmount']  : 0;
                        $paymentdiscountType = isset($decoded['discountType'])    ? $decoded['discountType']   : 0;
                        $discountAmount      = isset($decoded['discountAmount'])  ? $decoded['discountAmount'] : 0;
                        $discountNote        = isset($decoded['discountNote'])    ? $decoded['discountNote']   : '';
                        $confirmation        = isset($decoded['confirmation'])    ? $decoded['confirmation']   : null;

                        // ── Validate intakes array ──────────────────────────
                        $intakeIds = array_filter(array_map('intval', $intakeIds));
                        if (empty($intakeIds)) {
                            $status->error = 'Please select at least one intake.';
                            echo json_encode($status);
                            exit;
                        }

                        // ── Validate shared fields ──────────────────────────
                        if (empty($planName)) {
                            $status->name = 'Plan name is required';
                        } elseif (strlen($planName) > 50) {
                            $status->name = 'Plan name cannot exceed 50 characters';
                        }

                        if (empty($category))      $status->category      = 'Please select a category';
                        if (empty($plan))           $status->plan          = 'Plan is required';
                        if (!$paymentAmount || !is_numeric($paymentAmount) || $paymentAmount == 0)
                            $status->paymentAmount = 'Invalid payment amount';

                        if (empty($paymentdiscountType) || !is_numeric($paymentdiscountType)) {
                            $status->discountType = 'Please select a valid discount type.';
                        }

                        if (!is_numeric($discountAmount)) $discountAmount = 0;

                        // ── Validate installments ───────────────────────────
                        if (!empty($plan)) {
                            $errors = [];
                            foreach ($payments as $index => $payment) {
                                $installmentErrors = [];
                                $installmentId     = $payment['installmentId'] ?? null;
                                $paymentType       = (int)($payment['paymentType'] ?? 0);
                                $amount            = $payment['amount'] ?? null;
                                $pDiscountType     = (int)($payment['discountType'] ?? 0);
                                $discount          = $payment['discount'] ?? null;
                                $penaltyEnabled    = $payment['penaltyEnabled'] ?? false;
                                $penalty           = $payment['penalty'] ?? [];

                                if (empty($paymentType)) {
                                    $installmentErrors['paymentType'] = 'Payment type is required';
                                } else {
                                    $isValid = Database::search("SELECT * FROM `payment_type`
                                        WHERE `payment_type_id`='$paymentType'
                                        AND (`payment_plan_payment_plan_id`='$plan' OR `payment_plan_payment_plan_id` IS NULL)");
                                    if ($isValid->num_rows == 0)
                                        $installmentErrors['paymentType'] = 'Invalid payment type';
                                }

                                if ($amount === '' || !is_numeric($amount) || $amount <= 0)
                                    $installmentErrors['amount'] = 'Valid installment amount is required';

                                if ($penaltyEnabled && empty($penalty))
                                    $installmentErrors['panelty'] = 'Please configure penalty';

                                if (!empty($installmentErrors)) {
                                    $errors[$index] = [
                                        'installmentId' => "Payment - $installmentId",
                                        'errors'        => $installmentErrors
                                    ];
                                }
                            }
                            if (!empty($errors)) $status->installments = $errors;
                        }

                        // ── Bail if any shared validation failed ────────────
                        $hasError = false;
                        foreach (['error', 'installments', 'name', 'category', 'plan', 'paymentAmount', 'discountType', 'discountAmount'] as $prop) {
                            if (property_exists($status, $prop)) {
                                $hasError = true;
                                break;
                            }
                        }
                        if ($hasError) {
                            echo json_encode($status);
                            exit;
                        }

                        // ════════════════════════════════════════════════════
                        // LOOP THROUGH EACH INTAKE — create plan for each
                        // ════════════════════════════════════════════════════
                        $successCount  = 0;
                        $intakeErrors  = [];

                        foreach ($intakeIds as $intakeId) {

                            // Get intake name for error reporting
                            $intakeRs   = Database::search("SELECT `intake` FROM `intakes` WHERE `intake_id`='$intakeId' LIMIT 1");
                            $intakeName = $intakeRs->num_rows > 0 ? $intakeRs->fetch_assoc()['intake'] : "Intake #$intakeId";

                            // Check plan name uniqueness per intake
                            $nameCheck = Database::search("SELECT COUNT(*) AS cnt FROM `intake_has_payment_plans`
                                WHERE `payment_plan_name`='" . addslashes($planName) . "'
                                AND `intakes_intake_id`='$intakeId'");
                            if ($nameCheck->fetch_assoc()['cnt'] > 0) {
                                $intakeErrors[] = ['intakeName' => $intakeName, 'error' => "Plan name '$planName' already exists"];
                                continue;
                            }

                            // Confirm required check (only first intake that needs it)
                            if ($isDefault && $confirmation == null) {
                                $existingDefault = Database::search("SELECT * FROM `intake_has_payment_plans`
                                    WHERE `intakes_intake_id`='$intakeId' AND `is_default_plan`='1'");
                                if ($existingDefault->num_rows == 0) {
                                    $studentsRs = Database::search("SELECT * FROM `students_has_intakes`
                                        WHERE `intakes_intake_id`='$intakeId'
                                        AND `student_intake_status_student_intake_status_id`='1'");
                                    if ($studentsRs->num_rows > 0) {
                                        $status->confirm_required = true;
                                        echo json_encode($status);
                                        exit();
                                    }
                                }
                            }

                            // Set this intake's plan as default if needed
                            if ($isDefault) {
                                Database::iud("UPDATE `intake_has_payment_plans`
                                    SET `is_default_plan`='0' WHERE `intakes_intake_id`='$intakeId'");
                            }

                            $isDefaultValue = $isDefault ? 1 : 0;

                            // Insert intake_has_payment_plans
                            $col = "`intakes_intake_id`,`payment_plan_name`,`date_added`,`users_user_id`,
                                    `is_default_plan`,`payment_category_payment_category_id`,
                                    `payment_plan_payment_plan_id`,`discount_type_discount_type_id`,
                                    `amount`,`discount`";
                            $val = "'$intakeId','" . addslashes($planName) . "','$date',
                                    '" . $sessionUserObject['user_id'] . "','$isDefaultValue',
                                    '$category','$plan','$paymentdiscountType',
                                    '$paymentAmount','$discountAmount'";

                            if (!empty($discountNote)) {
                                $col .= ", `discount_note`";
                                $val .= ", '" . addslashes($discountNote) . "'";
                            }

                            Database::iud("INSERT INTO `intake_has_payment_plans` ($col) VALUES ($val)");
                            $planId = Database::$connection->insert_id;

                            // Insert payments + penalties (same for all intakes)
                            foreach ($payments as $payment) {
                                $pType         = (int)($payment['paymentType'] ?? 0);
                                $pAmount       = $payment['amount'] ?? 0;
                                $pDueDate      = trim($payment['dueDate'] ?? '');
                                $pDiscType     = (int)($payment['discountType'] ?? 0);
                                $pDiscount     = is_numeric($payment['discount']) ? (float)$payment['discount'] : 0;
                                $pDiscNote     = trim($payment['discountNote'] ?? '');
                                $penaltyData   = $payment['penalty'] ?? [];
                                $penaltyOn     = $payment['penaltyEnabled'] ?? false;
                                $penaltyId     = $penaltyOn ? ($penaltyData['mode'] ?? 1) : 1;

                                $pcol = "`intake_has_payment_plan_id`,`payment_type_payment_type_id`,
                                         `discount_type_discount_type_id`,`amount`,
                                         `panelty_payment_option_panelty_payment_option_id`,`discount`";
                                $pval = "'$planId','$pType','$pDiscType','$pAmount','$penaltyId','$pDiscount'";

                                if (!empty($pDiscNote)) {
                                    $pcol .= ",`discount_note`";
                                    $pval .= ",'" . addslashes($pDiscNote) . "'";
                                }
                                if (!empty($pDueDate)) {
                                    $pcol .= ",`due_date`";
                                    $pval .= ",'$pDueDate'";
                                }

                                Database::iud("INSERT INTO `intake_payments_plan_has_payments`($pcol) VALUES($pval)");
                                $ipphp = Database::$connection->insert_id;

                                if ($penaltyOn) {
                                    $mode  = $penaltyData['mode']   ?? null;
                                    $type  = isset($penaltyData['type'])   && $penaltyData['type']   !== '' ? (int)$penaltyData['type']   : null;
                                    $pAmt  = isset($penaltyData['amount']) && $penaltyData['amount'] !== '' ? (float)$penaltyData['amount'] : null;
                                    $slots = $penaltyData['slots'] ?? [];

                                    if ($mode == 4) {
                                        foreach ($slots as $slot) {
                                            Database::iud("INSERT INTO `intake_has_payments_has_penalties`
                                                (`intake_payments_plan_has_payment_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`,`days`)
                                                VALUES ('$ipphp','" . $slot['type'] . "','" . $slot['amount'] . "','" . $slot['days'] . "')");
                                        }
                                    } else {
                                        Database::iud("INSERT INTO `intake_has_payments_has_penalties`
                                            (`intake_payments_plan_has_payment_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`)
                                            VALUES ('$ipphp','$type','$pAmt')");
                                    }
                                }
                            }

                            // Confirmation: assign default plan to existing students
                            if ($confirmation && $isDefault) {
                                $intakeStudents = Database::search("SELECT * FROM `students_has_intakes`
                                    WHERE `intakes_intake_id`='$intakeId'
                                    AND `student_intake_status_student_intake_status_id`='1'
                                    AND `students_has_intakes_id` NOT IN (
                                        SELECT `students_has_intakes_students_has_intakes_id`
                                        FROM `payment_plan_has_students_has_intakes`
                                        INNER JOIN `intake_has_payment_plans`
                                            ON `payment_plan_has_students_has_intakes`.`intake_has_payment_plan_id`=`intake_has_payment_plans`.`intake_has_payment_plan_id`
                                        WHERE `payment_category_payment_category_id`='$category')");

                                if ($intakeStudents->num_rows > 0) {
                                    $defaultPlanRs  = Database::search("SELECT * FROM `intake_has_payment_plans`
                                        WHERE `intakes_intake_id`='$intakeId' AND `is_default_plan`='1'");
                                    $defaultPlan    = $defaultPlanRs->fetch_assoc();
                                    $defaultPlanId  = $defaultPlan['intake_has_payment_plan_id'];

                                    $planPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments`
                                        INNER JOIN `payment_type`
                                            ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id`=`payment_type`.`payment_type_id`
                                        WHERE `intake_has_payment_plan_id`='$defaultPlanId'
                                        AND `payment_type`.`payment_plan_payment_plan_id` IS NULL");

                                    $planPenalties = Database::search("SELECT * FROM `intake_has_payments_has_penalties`
                                        WHERE `intake_payments_plan_has_payment_id` IN (
                                            SELECT `intake_payments_plan_has_payment_id`
                                            FROM `intake_payments_plan_has_payments`
                                            WHERE `intake_has_payment_plan_id`='$defaultPlanId')");

                                    while ($student = $intakeStudents->fetch_assoc()) {
                                        $shiId = $student['students_has_intakes_id'];

                                        $spcol = "`students_has_intakes_students_has_intakes_id`,`discount_type_discount_type_id`,
                                                  `amount`,`discount`,`date_added`,`intake_has_payment_plan_id`,
                                                  `student_payment_plan_type_student_payment_plan_type_id`";
                                        $spval = "'$shiId','{$defaultPlan['discount_type_discount_type_id']}',
                                                  '{$defaultPlan['amount']}','{$defaultPlan['discount']}',
                                                  '$date','$defaultPlanId','1'";

                                        Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`($spcol) VALUES($spval)");
                                        $studentPlanId = Database::$connection->insert_id;

                                        while ($pp = $planPayments->fetch_assoc()) {
                                            $cpcol = "`payment_status_payment_status_id`,`payment_type_payment_type_id`,
                                                      `refference`,`discount`,`amount`,`discount_type_discount_type_id`,
                                                      `discount_note`,`payment_plan_has_students_has_intakes_id`,
                                                      `panelty_payment_option_panelty_payment_option_id`,
                                                      `intake_payments_plan_has_payment_id`";
                                            $cpval = "1,'{$pp['payment_type_payment_type_id']}',
                                                      '" . generateSecureUniqueId() . "',
                                                      '{$pp['discount']}','{$pp['amount']}',
                                                      '{$pp['discount_type_discount_type_id']}',
                                                      '{$pp['discount_note']}','$studentPlanId',
                                                      '{$pp['panelty_payment_option_panelty_payment_option_id']}',
                                                      '{$pp['intake_payments_plan_has_payment_id']}'";
                                            if (!empty($pp['due_date'])) {
                                                $cpcol .= ",`due_date`";
                                                $cpval .= ",'{$pp['due_date']}'";
                                            }

                                            Database::iud("INSERT INTO `course_payments`($cpcol) VALUES($cpval)");
                                            $cpId = Database::$connection->insert_id;

                                            while ($pen = $planPenalties->fetch_assoc()) {
                                                if ($pen['intake_payments_plan_has_payment_id'] == $pp['intake_payments_plan_has_payment_id']) {
                                                    Database::iud("INSERT INTO `course_payments_penalties`
                                                        (`course_payments_course_payments_id`,`amount`,`days`,`panelty_payment_type_panelty_payment_type_id`)
                                                        VALUES ('$cpId','{$pen['amount']}','{$pen['days']}','{$pen['panelty_payment_type_panelty_payment_type_id']}')");
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            // Log
                            Database::iud("INSERT INTO `intake_has_payment_plans_log`
                                (`intake_has_payment_plan_id`,`users_user_id`,`date_updated`,`log`)
                                VALUES ('$planId','" . $sessionUserObject['user_id'] . "','$date','Intake payment plan added')");

                            $successCount++;
                        } // end foreach intake

                        // ── Response ────────────────────────────────────────
                        $total = count($intakeIds);

                        if ($successCount === $total) {
                            $status->done = "Payment plan created successfully for $successCount intake(s).";
                        } elseif ($successCount > 0) {
                            $status->partial      = "$successCount of $total intakes saved. Some failed.";
                            $status->intake_errors = $intakeErrors;
                        } else {
                            $status->error        = "Failed to save payment plan for all selected intakes.";
                            $status->intake_errors = $intakeErrors;
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

echo json_encode($status);

function generateSecureUniqueId()
{
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $out   = '';
    for ($i = 0; $i < 8; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
    return $out;
}
