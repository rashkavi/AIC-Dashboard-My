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

                    $paymentId = isset($decoded['paymentId']) ? $decoded['paymentId'] : 0;
                    $paymentType = isset($decoded['paymentType']) ? $decoded['paymentType'] : 0;
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $discountType = isset($decoded['discountType']) ? $decoded['discountType'] : 0;
                    $discountAmount = isset($decoded['discountAmount']) ? $decoded['discountAmount'] : 0;
                    $discountNote = isset($decoded['discountNote']) ? $decoded['discountNote'] : '';
                    $paymentNote = isset($decoded['paymentNote']) ? $decoded['paymentNote'] : '';
                    $dueDate = isset($decoded['dueDate']) ? $decoded['dueDate'] : '';
                    $ifHasPenalty = isset($decoded['enablePenalty']) ? $decoded['enablePenalty'] : false;
                    $penalty = isset($decoded['penalty']) ? $decoded['penalty'] : [];

                    if (empty($discountAmount)) {
                        $discountAmount = 0;
                    }

                    if (empty($paymentId) || $paymentId == 0 || !is_numeric($paymentId)) {
                        $status->error = "Invalid Payment Id";
                    } else {
                        $isValidPayment = Database::search("SELECT * FROM `course_payments`
                        INNER JOIN `payment_type` ON `course_payments`.`payment_type_payment_type_id`=`payment_type`.`payment_type_id`
                        
                        WHERE `course_payments_id`='" . $paymentId . "'");

                        if ($isValidPayment->num_rows == 0) {
                            $status->error = "Invalid Payment Id";
                        } else {
                            $isValidPaymentData = $isValidPayment->fetch_assoc();
                            if (!($isValidPaymentData['payment_status_payment_status_id'] == 1)) {
                                $status->error = 'Cannot update payment because the student has already paid.';
                            } else {

                                if (empty($paymentType) || $paymentType == 0 || !is_numeric($paymentType)) {
                                    $status->paymentType = 'Invalid payment Type';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_type` WHERE `payment_type_id` = '" . $paymentType . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->paymentType = 'Invalid payment Type';
                                    }
                                }

                                if (empty($paymentAmount) || $paymentAmount == 0 || !is_numeric($paymentAmount)) {
                                    $status->paymentAmount = 'Invalid payment Amount';
                                }

                                if (empty($discountType) || $discountType == 0 || !is_numeric($discountType)) {
                                    $status->discountType = 'Invalid discount Type';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count',`discount_type` FROM `discount_type` WHERE `discount_type_id` = '" . $discountType . "'");
                                    $isValidIdDiscountData = $isValidId->fetch_assoc();
                                    if ($isValidIdDiscountData['count'] == 0) {
                                        $status->discountType = 'Invalid discount Type';
                                    }
                                }

                                if (!is_numeric($discountAmount)) {
                                    $status->discountAmount = 'Invalid discount Amount';
                                } else if (!property_exists($status, 'discountType')) {
                                    if ($discountType == 1) {
                                        if ($discountAmount > 0) {
                                            $status->discountAmount = 'Selected discount is No Discount, but a discount was added';
                                        }
                                    } else if ($discountType == 2) {
                                        if ($discountAmount == 0) {
                                            $status->discountAmount = 'Selected discount is Fixed-Based, but the discount amount must be higher than 0';
                                        }
                                    } else if ($discountType == 3) {
                                        if ($discountAmount == 0 || $discountAmount > 100) {
                                            $status->discountAmount = 'Selected discount is Percentage-Based, but the discount amount must be between 1 and 100';
                                        }
                                    }
                                }

                                if ($ifHasPenalty) {
                                    if (empty($penalty)) {
                                        $status->penalty_option = 'Please select panelty option';
                                    } else {

                                        $penaltyErrors = [];
                                        $mode          = $penalty['mode'] ?? null;
                                        $slots         = $penalty['slots'] ?? [];
                                        if ($mode == 4) {

                                            if (!is_array($slots) || empty($slots)) {
                                                $status->slots = 'At least one penalty slot is required';
                                            } else {
                                                foreach ($slots as $sIndex => $slot) {
                                                    if (empty($slot['type'])) {
                                                        $status->penalty_type = 'Penalty type is required';
                                                    }

                                                    if (
                                                        empty($slot['amount']) ||
                                                        $slot['amount'] <= 0
                                                    ) {
                                                        $status->penalty_amount = 'Valid penalty amount is required';
                                                    }

                                                    if ($slot['type'] == 3 && $slot['amount'] > 100) {
                                                        $status->penalty_amount = 'Panelty amount cannot exceed 100%';
                                                    }

                                                    if (
                                                        empty($slot['days']) ||
                                                        !is_numeric($slot['days']) ||
                                                        $slot['days'] <= 0
                                                    ) {
                                                        $status->penalty_days = 'Valid penalty days is required';
                                                    }
                                                }
                                            }
                                        } else {
                                            if (empty($penalty['type'])) {
                                                $status->penalty_type = 'Penalty type is required';
                                            }
                                            if (
                                                empty($penalty['amount']) ||
                                                $penalty['amount'] <= 0
                                            ) {
                                                $status->penalty_amount = 'Valid penalty amount is required';
                                            }

                                            if ($penalty['type'] == 3 && $penalty['type'] > 100) {
                                                $status->penalty_amount = 'Panelty amount cannot exceed 100%';
                                            }
                                        }
                                    }
                                }

                                $requiredProperties = [
                                    'paymentCategory',
                                    'paymentPlan',
                                    'paymentType',
                                    'paymentAmount',
                                    'discountType',
                                    'discountAmount',
                                    'paymentNote',
                                    'error',
                                    'dueDate',
                                    'paymentAmount',
                                    'penalty_option',
                                    'penalty_amount',
                                    'penalty_type',
                                    'penalty_days'
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
                                    $date = $dateTime->format('Y-m-d H:i:s');

                                    $payment_update = "`payment_type_payment_type_id`='" . $paymentType . "',
                                        `discount_type_discount_type_id`='" . $discountType . "',
                                        `amount`='" . $paymentAmount . "',`discount`='" . $discountAmount . "',
                                        `discount_note`='" . $discountNote . "'";

                                    if (!empty($dueDate)) {
                                        $payment_update .= ",`due_date`='" . $dueDate . "'";
                                    }

                                    if ($ifHasPenalty) {
                                        $payment_update .= ",`panelty_payment_option_panelty_payment_option_id`='" . $penalty['mode'] . "'";
                                    } else {
                                        $payment_update .= ",`panelty_payment_option_panelty_payment_option_id`='1'";
                                    }

                                    Database::iud("UPDATE `course_payments` SET " . $payment_update . " WHERE `course_payments_id`='" . $paymentId . "'");

                                    if ($ifHasPenalty) {
                                        Database::iud("DELETE FROM `course_payments_penalties` WHERE `course_payments_course_payments_id`='" . $paymentId . "'");

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

                                    if (empty($paymentNote)) {
                                        $paymentNote = "Update student payment";
                                    }

                                    $payment_log_columns = "`course_payments_course_payments_id`,`users_user_id`,`date_updated`,`log`";
                                    $payment_log_values = "'" . $paymentId . "', '" . $sessionUserObject['user_id'] . "', '" . $date . "', 'Updated student payment'";

                                    if (!empty($paymentNote)) {
                                        $payment_log_columns .= ",`note`";
                                        $payment_log_values .= ", '" . Database::escape($paymentNote) . "'";
                                    }

                                    Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");

                                    $status->done = 'Payment updated successfully';
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
