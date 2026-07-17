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

                    $intakeId = isset($decoded['id']) ? $decoded['id'] : 0;
                    $category = isset($decoded['category']) ? $decoded['category'] : 0;
                    $plan = isset($decoded['plan']) ? $decoded['plan'] : 0;
                    $payments = isset($decoded['payments']) ? $decoded['payments'] : [];
                    $checkedStudents = isset($decoded['checkedStudents']) ? $decoded['checkedStudents'] : [];
                    $uncheckedStudents = isset($decoded['uncheckedStudents']) ? $decoded['uncheckedStudents'] : [];
                    $checkAll = isset($decoded['checkAll']) ? $decoded['checkAll'] : false;

                    if (empty($intakeId)) {
                        http_response_code(400);
                        $status->error = 'Intake is required';
                    } else {
                        $intake = Database::search("SELECT * FROM `intakes` WHERE `intake_id`='" . $intakeId . "'");
                        if ($intake->num_rows == 0) {
                            http_response_code(400);
                            $status->error = 'Intake not found';
                        } else {
                            $intake = $intake->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $intakeId))) {
                                http_response_code(400);
                                $status->error = 'You are not authorized to add payments';
                            } else {

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

                                    if (!empty($dueDate) && !CheckDateTime::isFutureDate($dueDate)) {
                                        $installmentErrors['dueDate'] = 'Due date must be a future date';
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

                                if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0 && !$checkAll) {
                                    $status->error = 'Please select at least one student';
                                }

                                $requiredProperties = [
                                    'error',
                                    'installments'
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

                                    $query = "SELECT `payment_plan_has_students_has_intakes_id` FROM `payment_plan_has_students_has_intakes`
                                    INNER JOIN `students_has_intakes` ON `payment_plan_has_students_has_intakes`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                                    WHERE `intakes_intake_id` = '" . $intakeId . "' AND `payment_category_payment_category_id` = '" . $category . "' AND `payment_plan_payment_plan_id` = '" . $plan . "'";

                                    if (count($checkedStudents) > 0) {
                                        $query .= " AND `payment_plan_has_students_has_intakes_id` IN (" . implode(',', $checkedStudents) . ")";
                                    }

                                    if (count($uncheckedStudents) > 0) {
                                        $query .= " AND `payment_plan_has_students_has_intakes_id` NOT IN (" . implode(',', $uncheckedStudents) . ")";
                                    }

                                    $result = Database::search($query);

                                    if ($result->num_rows == 0) {
                                        $status->error = 'No students found';
                                    } else {
                                        $paymentNote = "Schedule Payment";
                                        while ($student = $result->fetch_assoc()) {
                                            $pphisid = $student['payment_plan_has_students_has_intakes_id'];
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

                                                $payment_insert_columns = "`payment_status_payment_status_id`,
                                                 `payment_type_payment_type_id`,
                                                
                                                   `discount_type_discount_type_id`,
                                                    `amount`, 
                                                    `payment_plan_has_students_has_intakes_id`,`refference`,
                                                    `panelty_payment_option_panelty_payment_option_id`";

                                                $payment_insert_values = "'1', '" . $paymentType . "',
                                                 '" . $discountType . "',
                                                  '" . $amount . "',
                                                   '" . $pphisid . "','" . generateSecureUniqueId() . "',
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

                                                Database::iud("INSERT INTO `course_payments`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");

                                                $cpId = Database::$connection->insert_id;

                                                $payment_log_columns = "`course_payments_course_payments_id`,`users_user_id`,`date_updated`,`note`";
                                                $payment_log_values = "'" . $cpId . "', '" . $sessionUserObject['user_id'] . "', '" . $date . "', '" . $paymentNote . "'";

                                                Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");

                                                if ($penaltyEnabled) {
                                                    $mode   = $penalty['mode'] ?? null;
                                                    $type   = isset($penalty['type']) && $penalty['type'] !== '' ? (int)$penalty['type'] : null;
                                                    $amount = isset($penalty['amount']) && $penalty['amount'] !== '' ? (float)$penalty['amount'] : null;
                                                    $slots  = $penalty['slots'] ?? [];

                                                    if ($mode == 4) {
                                                        foreach ($slots as $sIndex => $slot) {
                                                            $columns = "`course_payments_course_payments_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`,`days`";
                                                            $values  = "'" . $cpId . "','" . $slot['type'] . "','" . $slot['amount'] . "','" . $slot['days'] . "'";

                                                            Database::iud("INSERT INTO `course_payments_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                                        }
                                                    } else {
                                                        $columns = "`course_payments_course_payments_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`";
                                                        $values  = "'" . $cpId . "','" . $type . "','" . $amount . "'";

                                                        Database::iud("INSERT INTO `course_payments_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                                    }
                                                }
                                            }
                                        }
                                        $status->done = 'Successfully added students to payments';
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
