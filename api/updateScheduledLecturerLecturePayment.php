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
                    $duration = isset($decoded['duration']) ? $decoded['duration'] : '';
                    $amount = isset($decoded['amount']) ? $decoded['amount'] : '';
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if (empty($paymentId) || $paymentId == 0) {
                        $status->error = 'Payment ID is required';
                    } else {
                        $query = "SELECT  * FROM `lecturer_payments` 
                        WHERE `lecturer_payments`.`lecturer_payments_id` = '" . $paymentId . "'";

                        $isValidPayment = Database::search($query);
                        if ($isValidPayment->num_rows == 0) {
                            $status->error = 'Payment not found';
                        } else {
                            $paymentData = $isValidPayment->fetch_assoc();

                            if ($paymentData['earning_status_earning_status_id'] == 2) {
                                $status->error = 'Payment already completed';
                            } else {

                                if (empty($duration) || $duration == 0) {
                                    $status->duration = 'Duration is required';
                                }

                                if (empty($amount)) {
                                    $status->amount = 'Amount is required';
                                }


                                $requiredProperties = [
                                    'amount',
                                    'error',
                                    'duration'
                                ];
                                $allPropertiesExist = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allPropertiesExist = false;
                                        break;
                                    }
                                }

                                if ($allPropertiesExist) {

                                    $update_val = "`amount` = '" . $amount . "', `duration` = '" . $duration . "'";

                                    if (!empty($note)) {
                                        $update_val .= ", `note` = '" . Database::escape($note) . "'";
                                    }

                                    Database::iud("UPDATE `lecturer_payments` SET
                                    " . $update_val . " WHERE `lecturer_payments`.`lecturer_payments_id` = '" . $paymentId . "'");

                                    $lecturer_payments_log_columns = "`lecturer_payments_lecturer_payments_id`, `log`, `date_updated`, `users_user_id`";
                                    $lecturer_payments_log_values = "'" . $paymentId . "','Update Scheduled Lecture Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                                    if (!empty($note)) {
                                        $lecturer_payments_log_columns .= ", `note`";
                                        $lecturer_payments_log_values .= ", '" . Database::escape($note) . "'";
                                    }

                                    Database::iud("INSERT INTO `lecturer_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                                    $status->done = 'Lecturer payment updated successfully';
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
