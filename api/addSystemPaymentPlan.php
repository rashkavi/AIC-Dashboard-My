<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $date = $dateTime->format('Y-m-d H:i:s');

                    $category = isset($decoded['category']) ? $decoded['category'] : 0;
                    $planName = isset($decoded['planName']) ? $decoded['planName'] : '';
                    $plan = isset($decoded['plan']) ? $decoded['plan'] : 0;
                    $isDefault = isset($decoded['isDefault']) ? $decoded['isDefault'] : false;
                    $payments = isset($decoded['payments']) ? $decoded['payments'] : [];
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $paymentdiscountType = isset($decoded['discountType']) ? $decoded['discountType'] : 0;
                    $discountAmount = isset($decoded['discountAmount']) ? $decoded['discountAmount'] : 0;
                    $discountNote = isset($decoded['discountNote']) ? $decoded['discountNote'] : '';

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 22) || UserPermissions::hasPermission('payments', 23))) {
                        $status->error = 'You are not authorized to add payments';
                    } else {

                        if (empty($planName)) {
                            $status->name = 'Plan name is required';
                        } else if (strlen($planName) > 50) {
                            $status->name = 'Plan name cannot exceed 50 characters';
                        }

                        if (empty($category)) {
                            $status->category = 'Please select a category';
                        }

                        if (empty($plan)) {
                            $status->plan = 'Plan is required';
                        }

                        if (empty($paymentAmount) || $paymentAmount == 0 || !is_numeric($paymentAmount)) {
                            $status->paymentAmount = 'Invalid payment Amount';
                        }

                        if (empty($paymentdiscountType) || $paymentdiscountType == 0 || !is_numeric($paymentdiscountType)) {
                            $status->discountType = 'Please select a valid discount type.';
                        } else {
                            $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `discount_type` WHERE `discount_type_id` = '" . $paymentdiscountType . "'");
                            $isValidIdData = $isValidId->fetch_assoc();
                            if ($isValidIdData['count'] == 0) {
                                $status->discountType = 'Selected discount type does not exist.';
                            }
                        }

                        if (!is_numeric($discountAmount)) {
                            $discountAmount = 0;
                        } else if (!property_exists($status, 'discountType')) {

                            if ($paymentdiscountType == 1 && $discountAmount > 0) {
                                $status->discountAmount = 'No discount selected. Discount amount must be 0.';
                            }

                            if ($paymentdiscountType == 2 && $discountAmount <= 0) {
                                $status->discountAmount = 'Fixed discount amount must be greater than 0.';
                            }

                            if ($paymentdiscountType == 3 && ($discountAmount <= 0 || $discountAmount > 100)) {
                                $status->discountAmount = 'Percentage discount must be between 1 and 100.';
                            }
                        }

                        if ($paymentdiscountType == 2 || $paymentdiscountType == 3) {
                            if (empty($planDiscountNote)) {
                                $status->discountNote = 'Discount note is required';
                            }
                        }

                        if (!empty($plan)) {
                            $errors = [];
                            $validatedPayments = [];

                            foreach ($payments as $index => $payment) {
                                $installmentErrors = [];

                                $installmentId = $payment['installmentId'] ?? null;
                                $paymentType = (int)($payment['paymentType'] ?? 0);
                                $amount        = $payment['amount'] ?? null;
                                $dueDate       = trim($payment['dueDate'] ?? '');
                                $discountType  = (int)($payment['discountType'] ?? 0);
                                $discount      = $payment['discount'] ?? null;
                                $discountNote  = trim($payment['discountNote'] ?? '');
                                $penalty = $payment['penalty'] ?? [];
                                $penaltyEnabled = $payment['penaltyEnabled'] ?? false;
                                $installmentName = '';

                                $installmentName = "Payment - " . $installmentId;

                                if (empty($paymentType)) {
                                    $installmentErrors['paymentType'] = 'Installment ID is required';
                                } else {
                                    $isValid = Database::search("SELECT * FROM `payment_type` WHERE `payment_type_id`='" . $paymentType . "' AND (`payment_plan_payment_plan_id`='" . $plan . "' OR `payment_plan_payment_plan_id` IS NULL)");
                                    if ($isValid->num_rows == 0) {
                                        $installmentErrors['paymentType'] = 'Invalid payment type ID';
                                    }
                                }

                                if ($amount === '' || !is_numeric($amount) || $amount <= 0) {
                                    $installmentErrors['amount'] = 'Valid installment amount is required';
                                }

                                if ($discountType != 0 && $discountType != 1) {
                                    if ($discount === '' || !is_numeric($discount) || $discount < 0) {
                                        $installmentErrors['discount'] = 'Valid discount is required';
                                    }

                                    if ($discount == 3 && $discount > 100) {
                                        $installmentErrors['discount'] = 'Discount cannot exceed 100%';
                                    }

                                    if (is_numeric($discount) && is_numeric($amount) && $discount > $amount) {
                                        $installmentErrors['discount'] = 'Discount cannot exceed installment amount';
                                    }
                                }

                                if ($penaltyEnabled) {
                                    if (empty($penalty)) {
                                        $installmentErrors['panelty'] = 'Please select panelty option';
                                    } else {

                                        $penaltyErrors = [];
                                        $mode          = $penalty['mode'] ?? null;
                                        $slots         = $penalty['slots'] ?? [];
                                        if ($mode == 4) {

                                            if (!is_array($slots) || empty($slots)) {
                                                $penaltyErrors['slots'] = 'At least one penalty slot is required';
                                            } else {
                                                foreach ($slots as $sIndex => $slot) {
                                                    if (empty($slot['type'])) {
                                                        $penaltyErrors["slots.$sIndex.type"] = 'Penalty type is required';
                                                    }

                                                    if (
                                                        empty($slot['amount']) ||
                                                        !is_numeric($slot['amount']) ||
                                                        $slot['amount'] <= 0
                                                    ) {
                                                        $penaltyErrors["slots.$sIndex.amount"] = 'Valid penalty amount is required';
                                                    }

                                                    if ($slot['type'] == 3 && $slot['amount'] > 100) {
                                                        $installmentErrors["slots.$sIndex.amount"] = 'Panelty amount cannot exceed 100%';
                                                    }

                                                    if (
                                                        empty($slot['days']) ||
                                                        !is_numeric($slot['days']) ||
                                                        $slot['days'] <= 0
                                                    ) {
                                                        $penaltyErrors["slots.$sIndex.days"] = 'Valid penalty days is required';
                                                    }
                                                }
                                            }
                                        } else {
                                            if (empty($penalty['type'])) {
                                                $penaltyErrors["slots.0.type"] = 'Penalty type is required';
                                            }
                                            if (
                                                empty($penalty['amount']) ||
                                                !is_numeric($penalty['amount']) ||
                                                $penalty['amount'] <= 0
                                            ) {
                                                $penaltyErrors["slots.0.amount"] = 'Valid penalty amount is required';
                                            }

                                            if ($penalty['type'] == 3 && $penalty['type'] > 100) {
                                                $penaltyErrors["slots.0.amount"] = 'Panelty amount cannot exceed 100%';
                                            }
                                        }
                                        if (!empty($penaltyErrors)) {
                                            $installmentErrors['panelty'] = $penaltyErrors;
                                        }
                                    }
                                }

                                if (!empty($installmentErrors)) {
                                    $errors[$index] = [
                                        'installmentId' => $installmentName,
                                        'errors' => $installmentErrors
                                    ];

                                    $status->installments = $errors;
                                }
                            }
                        }

                        $requiredProperties = [
                            'error',
                            'installments',
                            'name',
                            'category',
                            'plan',
                            'amount',
                            'discount',
                            'discountType',
                            'discountNote',
                            'discountAmount',
                            'status'
                        ];
                        $allGood = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allGood = false;
                                break;
                            }
                        }

                        if ($allGood) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');
                            $addTime = $dateTime->format('Y-m-d H:i:s');

                            if ($isDefault) {
                                Database::iud("UPDATE `system_payment_plans` SET `is_default_plan` = '0'");
                            }

                            $isDefaultValue = ($isDefault) ? 1 : 0;

                            $intake_plan_insert_col = "`payment_plan_name`,
                                    `date_added`,
                                    `users_user_id`,
                                    `is_default_plan`,
                                    `payment_category_payment_category_id`,
                                    `payment_plan_payment_plan_id`,
                                    `discount_type_discount_type_id`,
                                    `amount`,
                                    `discount`";

                            $intake_plan_insert_val = "'" . $planName . "',
                                    '" . $date . "',
                                    '" . $sessionUserObject['user_id'] . "',
                                    '" . $isDefaultValue . "',
                                    '" . $category . "',
                                    '" . $plan . "',
                                    '" . $paymentdiscountType . "',
                                    '" . $paymentAmount . "',
                                    '" . $discountAmount . "'";

                            if (!empty($planDiscountNote)) {
                                $intake_plan_insert_col .= ", `discount_note`";
                                $intake_plan_insert_val .= ", '" . $planDiscountNote . "'";
                            }

                            Database::iud("INSERT INTO `system_payment_plans` (" . $intake_plan_insert_col . ") VALUES (" . $intake_plan_insert_val . ")");
                            $planId = Database::$connection->insert_id;

                            foreach ($payments as $index => $payment) {
                                $paymentType   = (int)($payment['paymentType'] ?? 0);
                                $amount        = $payment['amount'] ?? null;
                                $dueDate       = trim($payment['dueDate'] ?? '');
                                $discountType  = (int)($payment['discountType'] ?? 0);
                                $discount      = is_numeric($payment['discount']) ? (float)$payment['discount'] : 0;
                                $discountNote  = trim($payment['discountNote'] ?? '');
                                $penalty = $payment['penalty'] ?? [];
                                $penaltyEnabled = $payment['penaltyEnabled'] ?? false;

                                $penaltyId = $penaltyEnabled ? $penalty['mode'] : 1;

                                $payment_insert_columns = "
                                                `system_payment_plan_id`,
                                                 `payment_type_payment_type_id`,
                                                 
                                                   `discount_type_discount_type_id`,
                                                    `amount`, 
                                                   
                                                    `panelty_payment_option_panelty_payment_option_id`";

                                $payment_insert_values = "
                                                '" . $planId . "',
                                        '" . $paymentType . "',
                                                 '" . $discountType . "',
                                                  '" . $amount . "',
                                                   
                                                    '" . $penaltyId . "'";

                                if (empty($discount)) {
                                    $payment_insert_columns .= ", `discount`";
                                    $payment_insert_values .= ", '0'";
                                } else {
                                    $payment_insert_columns .= ", `discount`";
                                    $payment_insert_values .= ", '" . $discount . "'";
                                }

                                if (!empty($discountNote)) {
                                    $payment_insert_columns .= ", `discount_note`";
                                    $payment_insert_values .= ", '" . $discountNote . "'";
                                }

                                if (!empty($dueDate)) {
                                    $payment_insert_columns .= ", `due_date`";
                                    $payment_insert_values .= ", '" . $dueDate . "'";
                                }

                                Database::iud("INSERT INTO `system_payment_plan_has_payments`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");

                                $ipphp = Database::$connection->insert_id;

                                if ($penaltyEnabled) {
                                    $mode   = $penalty['mode'] ?? null;
                                    $type   = isset($penalty['type']) && $penalty['type'] !== '' ? (int)$penalty['type'] : null;
                                    $amount = isset($penalty['amount']) && $penalty['amount'] !== '' ? (float)$penalty['amount'] : null;
                                    $slots  = $penalty['slots'] ?? [];

                                    if ($mode == 4) {
                                        foreach ($slots as $sIndex => $slot) {
                                            $columns = "`system_payment_plan_has_payment_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`,`days`";
                                            $values  = "'" . $ipphp . "','" . $slot['type'] . "','" . $slot['amount'] . "','" . $slot['days'] . "'";

                                            Database::iud("INSERT INTO `system_payment_plan_has_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                        }
                                    } else {
                                        $columns = "`system_payment_plan_has_payment_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`";
                                        $values  = "'" . $ipphp . "','" . $type . "','" . $amount . "'";

                                        Database::iud("INSERT INTO `system_payment_plan_has_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                    }
                                }
                            }

                            $log_insert_col = "`system_payment_plan_id`,`users_user_id`,`date_updated`,`log`";
                            $log_insert_val = "'" . $planId . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "','Added system payment plan - " . $planName . "'";

                            Database::iud("INSERT INTO `system_payment_plans_log`(" . $log_insert_col . ") VALUES(" . $log_insert_val . ")");

                            $status->done = 'System payment plan added successfully!';
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
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
