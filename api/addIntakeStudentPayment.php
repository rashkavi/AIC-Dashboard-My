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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $date = $dateTime->format('Y-m-d H:i:s');

                    $paymentPlanId = isset($decoded['paymentPlanId']) ? $decoded['paymentPlanId'] : 0;
                    $payments = isset($decoded['data']) ? $decoded['data'] : [];

                    if (empty($paymentPlanId)) {
                        http_response_code(400);
                        $status->error = 'Student is required';
                    } else {
                        $isValidStudent = Database::search("SELECT *
                           
                        FROM `payment_plan_has_students_has_intakes` AS `pshi`
                          
                        WHERE   `pshi`.`payment_plan_has_students_has_intakes_id` = '" . $paymentPlanId . "'
                     ");
                        if ($isValidStudent->num_rows == 0) {
                            http_response_code(400);
                            $status->error = 'Invalid payment plan id';
                        } else {
                            if (!isset($payments['paymentType']) || $payments['paymentType'] == 0) {
                                $status->paymentType = 'Payment type is required';
                            }

                            if (!isset($payments['amount']) || !is_numeric($payments['amount']) || $payments['amount'] <= 0) {
                                $status->amount = 'Valid amount is required';
                            }

                            if (isset($payments['discountType']) && $payments['discountType'] != 1) {
                                if (!isset($payments['discount']) || !is_numeric($payments['discount']) || $payments['discount'] < 0) {
                                    $status->discount = 'Valid discount is required';
                                }
                            } else {
                                $payments['discount'] = 0;
                                $payments['discountType'] = 1;
                            }

                            if (!empty($payments['dueDate']) && !CheckDateTime::isFutureDate($payments['dueDate'])) {
                                $status->dueDate = 'Due date must be a future date';
                            }

                            if ($payments['penaltyEnabled']) {
                                $penalty = $payments['penalty'];
                                if (empty($penalty)) {
                                    $status->penalty = 'Please select panelty option';
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
                                        $status->penalty_data = $penaltyErrors;
                                    }
                                }
                            }

                            if (!empty($payments['note']) && strlen($payments['note']) > 150) {
                                $status->note = 'Note cannot exceed 150 characters';
                            }

                            $requiredProperties = [
                                'error',
                                'installments',
                                'penalty_data',
                                'paymentType',
                                'amount',
                                'discount',
                                'note',
                                'dueDate',
                            ];
                            $allGood = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allGood = false;
                                    break;
                                }
                            }

                            if ($allGood) {

                                $col = "`payment_plan_has_students_has_intakes_id`
                                ,`refference`,
                                `amount`,
                                `discount_type_discount_type_id`,
                                `discount`,
                                `payment_status_payment_status_id`
                                ,`panelty_payment_option_panelty_payment_option_id`,
                                `payment_type_payment_type_id`";

                                $penaltyType = $payments['penaltyEnabled'] ? $payments['penalty']['mode'] : 1;

                                $values = "'" . $paymentPlanId . "',
                                '" . generateSecureUniqueId() . "',
                                '" . $payments['amount'] . "',
                                '" . $payments['discountType'] . "',
                                '" . $payments['discount'] . "',
                                '1',
                              
                                '" . $penaltyType . "',
                                '" . $payments['paymentType'] . "'";

                                if ($payments['dueDate'] != null) {
                                    $col .= ",`due_date`";
                                    $values .= ",'" . $payments['dueDate'] . "'";
                                }

                                if ($payments['discountNote'] != null) {
                                    $col .= ",`discount_note`";
                                    $values .= ",'" . Database::escape($payments['discountNote']) . "'";
                                }

                                Database::iud("INSERT INTO `course_payments` (" . $col . ") VALUES (" . $values . ")");

                                $paymentId = Database::$connection->insert_id;

                                if ($payments['penaltyEnabled']) {
                                    $penalty = $payments['penalty'];

                                    $mode   = $penalty['mode'] ?? null;
                                    $type   = isset($penalty['type']) && $penalty['type'] !== '' ? (int)$penalty['type'] : null;
                                    $amount = isset($penalty['amount']) && $penalty['amount'] !== '' ? (float)$penalty['amount'] : null;
                                    $slots  = $penalty['slots'] ?? [];

                                    if ($mode == 4) {
                                        foreach ($slots as $sIndex => $slot) {
                                            $columns = "`course_payments_course_payments_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`,`days`";
                                            $values  = "'" . $paymentId . "','" . $slot['type'] . "','" . $slot['amount'] . "','" . $slot['days'] . "'";

                                            Database::iud("INSERT INTO `course_payments_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                        }
                                    } else {
                                        $columns = "`course_payments_course_payments_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`";
                                        $values  = "'" . $paymentId . "','" . $type . "','" . $amount . "'";

                                        Database::iud("INSERT INTO `course_payments_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                    }
                                }

                                $payment_log_columns = "course_payments_course_payments_id,users_user_id,date_updated,log";
                                $payment_log_values = "'" . $paymentId . "', '" . $sessionUserObject['user_id'] . "', '" . $date . "', 'Schedule payment'";

                                if (!empty($paymentNote)) {
                                    $payment_log_columns .= ",note";
                                    $payment_log_values .= ", '" . Database::escape($paymentNote) . "'";
                                }

                                Database::iud("INSERT INTO payment_logs(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");

                                $status->done = 'Payment added successfully';
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
