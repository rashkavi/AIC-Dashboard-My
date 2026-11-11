<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d');
                    $nowTime = $today->format('H:i:s');

                    $paymentId = isset($decoded['paymentId']) ? $decoded['paymentId'] : '';
                    $amount = isset($decoded['amount']) ? $decoded['amount'] : '';
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if (empty($paymentId) || $paymentId == 0) {
                        $status->error = 'Payment ID is required';
                    } else {
                        $query = "SELECT  * FROM `assignment_payments` 
                        WHERE `assignment_payments`.`assignment_payments_id` = '" . $paymentId . "'";

                        $isValidPayment = Database::search($query);
                        if ($isValidPayment->num_rows == 0) {
                            $status->error = 'Payment not found';
                        } else {
                            $paymentData = $isValidPayment->fetch_assoc();

                            if ($paymentData['earning_status_earning_status_id'] != 1) {
                                $status->error = 'Only scheduled payments can be updated';
                            } else {

                                if ($amount === '' || !is_numeric($amount) || $amount < 0) {
                                    $status->amount = 'Valid amount is required';
                                }

                                $requiredProperties = [
                                    'amount',
                                    'error'
                                ];
                                $allPropertiesExist = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allPropertiesExist = false;
                                        break;
                                    }
                                }

                                if ($allPropertiesExist) {

                                    $update_val = "`amount` = '" . (float) $amount . "', `note` = '" . Database::escape($note) . "'";

                                    Database::iud("UPDATE `assignment_payments` SET
                                    " . $update_val . " WHERE `assignment_payments`.`assignment_payments_id` = '" . $paymentId . "'");

                                    $assignment_payments_log_columns = "`assignment_payments_assignment_payments_id`, `note`, `date_updated`, `users_user_id`";
                                    $assignment_payments_log_values = "'" . $paymentId . "','Update Scheduled Assignment Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                                    Database::iud("INSERT INTO `assignment_payments_logs`(" . $assignment_payments_log_columns . ") VALUES(" . $assignment_payments_log_values . ")");
                                    $status->done = 'Lecturer assignment payment updated successfully';
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
