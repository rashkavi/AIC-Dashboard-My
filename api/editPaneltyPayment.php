<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $paneltyId = isset($decoded['paneltyId']) ? $decoded['paneltyId'] : 0;
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $paymentNote = isset($decoded['paymentNote']) ? $decoded['paymentNote'] : '';
                    $cardPayment = isset($decoded['cardPayment']) ? $decoded['cardPayment'] : false;

                    if (empty($paneltyId) || $paneltyId == 0 || !is_numeric($paneltyId)) {
                        $status->error = 'Invalid Panelty Payment Id';
                    } else {

                        $isValidPaneltyPayment = Database::search("SELECT * FROM `panelty_payments` 
                        WHERE `panelty_payment_id`='" . $paneltyId . "'
                        AND `payment_status_payment_status_id`='1'");

                        if ($isValidPaneltyPayment->num_rows == 0) {
                            $status->error = "Panelty Payment not found";
                        } else {

                            if (empty($paymentAmount) || !is_numeric($paymentAmount)) {
                                $status->paymentAmount = 'Invalid Payment Amount';
                            }

                            if (empty($paymentNote)) {
                                $status->paymentNote = 'Invalid Payment Note';
                            }

                            $requiredProperties = [
                                'paymentAmount',
                                'paymentNote',
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
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                $paymentMethod = ($cardPayment == true ? '2' : '1');

                                $update_query = "`amount`='" . $paymentAmount . "',`note`='" . $paymentNote . "',`payment_method_payment_method_id`='" . $paymentMethod . "'";

                                Database::iud("UPDATE `panelty_payments` SET " . $update_query . " WHERE `panelty_payment_id`='" . $paneltyId . "'");

                                $log_insert_col = "`panelty_payments_panelty_payment_id`,`users_user_id`,`date_updated`,`note`";
                                $log_insert_val = "'" . $paneltyId . "','" . $sessionUserObject['user_id'] . "','" . $date . "','" . $paymentNote . "'";

                                Database::iud("INSERT INTO `panelty_payments_logs`(" . $log_insert_col . ") VALUES(" . $log_insert_val . ")");

                                $status->done = 'Successfully updated panelty payment';
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
