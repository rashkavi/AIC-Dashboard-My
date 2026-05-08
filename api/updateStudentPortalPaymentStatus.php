<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole == "Developer") {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $paymentId = isset($decoded["paymentId"]) ? $decoded["paymentId"] : 0;
                    $updateStatus = isset($decoded["status"]) ? $decoded["status"] : 0;

                    if (empty($paymentId) || $paymentId == 0 || !is_numeric($paymentId)) {
                        $status->error = "Invalid Payemnt Id";
                    } else {
                        $isPaymentValid = Database::search("SELECT * FROM `portal_payment` WHERE `portal_payment_id`='" . $paymentId . "'");
                        if ($isPaymentValid->num_rows == 0) {
                            $status->error = "Invalid Payemnt Id";
                        } else {
                            $paymentData = $isPaymentValid->fetch_assoc();

                            try {
                                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                                $note = $updateStatus == 2 ? 'Accepted Paymnet' : 'Rejected';
                                Database::iud("UPDATE `portal_payment` SET `portal_payment_status_portal_payment_status_id`='" . $updateStatus . "' WHERE `portal_payment_id`='" . $paymentId . "'");

                                $paymnet_log_columns = "`portal_payment_portal_payment_id`,`users_user_id`,`date_updated`,`note`";
                                $payment_log_values = "'" . $paymentId . "','" . $sessionUserObject['user_id'] . "', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $note . "'";
                                Database::iud("INSERT INTO `portal_payment_logs` (" . $paymnet_log_columns . ") VALUES (" . $payment_log_values . ")");

                                $status->done = "Successfully updated portal payment status";
                            } catch (Exception $e) {
                                $status->error = $e->getMessage();
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
