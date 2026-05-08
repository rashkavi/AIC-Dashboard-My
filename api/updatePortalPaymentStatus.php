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
                    $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if ($paymentId == 0) {
                        $status->error = "Invalid payment id";
                    } else {
                        $isValidPaymentId = Database::search("SELECT * FROM `portal_payment` WHERE `portal_payment_id` = '" . $paymentId . "'");
                        if ($isValidPaymentId->num_rows == 0) {
                            $status->error = "Invalid payment id";
                        } else {
                            $paymentData = $isValidPaymentId->fetch_assoc();
                            $isValidPaymentStatus = Database::search("SELECT * FROM `portal_payment_status` WHERE `portal_payment_status_id` = '" . $paymentStatus . "'");
                            if ($isValidPaymentStatus->num_rows == 0) {
                                $status->status = "Invalid payment status";
                            } else {
                                if ($paymentData["portal_payment_status_portal_payment_status_id"] == $paymentStatus) {
                                    $status->status = "This payment status already updated";
                                }
                            }

                            if (empty($note)) {
                                $status->note = "Note is required";
                            }

                            if (!property_exists($status, "status") && !property_exists($status, "note")) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                Database::iud("UPDATE `portal_payment` SET `portal_payment_status_portal_payment_status_id` = '" . $paymentStatus . "' WHERE `portal_payment_id` = '" . $paymentId . "'");
                               
                                Database::iud("INSERT INTO `portal_payment_logs` (`portal_payment_portal_payment_id`,`users_user_id`,`date_updated`,`note`) VALUES ('" . $paymentId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . $note . "')");
                              
                                $status->done = "Payment status updated successfully!";
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
