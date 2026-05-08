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

                    $paymentId = isset($decoded['paymentId']) ? $decoded['paymentId'] : 0;
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $paymentNote = isset($decoded['paymentNote']) ? $decoded['paymentNote'] : '';
                    $cardPayment = isset($decoded['cardPayment']) ? $decoded['cardPayment'] : false;

                    if (empty($paymentId) || $paymentId == 0 || !is_numeric($paymentId)) {
                        $status->error = 'Invalid Payment Id';
                    } else {
                        date_default_timezone_set('Asia/Colombo');
                        $isValidPaymentId = Database::search("SELECT * FROM `course_payments`
                        LEFT JOIN `payment_logs` ON  `course_payments`.`course_payments_id`=`payment_logs`.`course_payments_course_payments_id`
                        WHERE `course_payments_id` = '" . $paymentId . "'
                        AND `payment_status_payment_status_id` = '1'
                        AND NOW() > DATE_ADD(`payment_logs`.`date_updated`, INTERVAL 30 DAY)
                        GROUP BY `course_payments_id`");
                        if ($isValidPaymentId->num_rows == 0) {
                            $status->error = 'Invalid Payment Id';
                        } else {

                            $isValidPaneltyPayment = Database::search("SELECT * FROM `panelty_payments` WHERE `course_payments_course_payments_id`='" . $paymentId . "'");
                            if ($isValidPaneltyPayment->num_rows > 0) {
                                $status->error = "Panelty Payment Already Exists";
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

                                    $insert_col = "`course_payments_course_payments_id`,`amount`,`note`,`payment_method_payment_method_id`,`payment_status_payment_status_id`,`refference`";
                                    $insert_val = "'" . $paymentId . "','" . $paymentAmount . "','" . $paymentNote . "','" . $paymentMethod . "','2','" . generateSecureUniqueId() . "'";

                                    Database::iud("INSERT INTO `panelty_payments`(" . $insert_col . ") VALUES(" . $insert_val . ")");

                                    $paneltyPaymentId = Database::$connection->insert_id;

                                    $log_insert_col = "`panelty_payments_panelty_payment_id`,`users_user_id`,`date_updated`,`note`";
                                    $log_insert_val = "'" . $paneltyPaymentId . "','" . $sessionUserObject['user_id'] . "','" . $date . "','" . $paymentNote . "'";

                                    Database::iud("INSERT INTO `panelty_payments_logs`(" . $log_insert_col . ") VALUES(" . $log_insert_val . ")");

                                    $status->done = 'Successfully added panelty payment';
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
