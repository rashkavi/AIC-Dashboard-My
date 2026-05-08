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

                    $studentId = isset($decoded["studentId"]) ? $decoded["studentId"] : 0;

                    if (empty($studentId) || $studentId == 0 || !is_numeric($studentId)) {
                        $status->error = "Invalid student Id";
                    } else {
                        $isStudentValid = Database::search("SELECT * FROM `students` WHERE `student_id`='" . $studentId . "'");
                        if ($isStudentValid->num_rows == 0) {
                            $status->error = "Invalid student Id";
                        } else {
                            $studentData = $isStudentValid->fetch_assoc();

                            $lastPortalPayment = Database::search("SELECT * FROM `portal_payment` 
                            WHERE `students_student_id`='" . $studentId . "'                            
                             ORDER BY `portal_payment_id` DESC LIMIT 1");

                            try {
                                if ($lastPortalPayment->num_rows == 0) {
                                    $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                    $newLastAccessTime = clone $dateTime;
                                    $newLastAccessTime->modify('+30 day');

                                    $payment_insert_columns = "`students_student_id`,`portal_payment_plan_portal_payment_plan_id`,`amount`,`discount`,`date_paid`, `from`, `to`, `portal_payment_status_portal_payment_status_id`,`payment_method_payment_method_id`,`discount_type_discount_type_id`";
                                    $payment_insert_values = "'" . $studentId . "','2', '200.00', '0', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $newLastAccessTime->format("Y-m-d H:i:s") . "', '2', '1','1'";

                                    Database::iud("INSERT INTO `portal_payment`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");

                                    $status->done = "Successfully added portal payment";
                                } else {
                                    $lastPortalPaymentData = $lastPortalPayment->fetch_assoc();
                                    if ($lastPortalPaymentData["portal_payment_status_portal_payment_status_id"] == 1) {
                                        $status->warning = "Last portal payment not completed";
                                    } else {
                                        $lastAccessTime = new DateTime($lastPortalPaymentData["to"]);
                                        $lastAccessTime->modify('+1 second');
                                        $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                        $newLastAccessTime = clone $lastAccessTime;
                                        $newLastAccessTime->modify('+30 day');

                                        if ($lastPortalPaymentData["portal_payment_status_portal_payment_status_id"] == 3) {
                                            $lastAccessTime = new DateTime($lastPortalPaymentData["from"]);
                                            $newLastAccessTime = new DateTime($lastPortalPaymentData["to"]);
                                        }

                                        try {
                                            $payment_insert_columns = "`students_student_id`,`portal_payment_plan_portal_payment_plan_id`,`amount`,`discount`,`date_paid`, `from`, `to`, `portal_payment_status_portal_payment_status_id`,`payment_method_payment_method_id`,`discount_type_discount_type_id`";
                                            $payment_insert_values = "'" . $studentId . "','2', '200.00', '0', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $lastAccessTime->format("Y-m-d H:i:s") . "', '" . $newLastAccessTime->format("Y-m-d H:i:s") . "', '2', '1','1'";

                                            Database::iud("INSERT INTO `portal_payment`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");
                                            $paymentId = Database::$connection->insert_id;

                                            $paymnet_log_columns = "`portal_payment_portal_payment_id`,`users_user_id`,`date_updated`,`note`";
                                            $payment_log_values = "'" . $paymentId . "','" . $sessionUserObject['user_id'] . "', '" . $dateTime->format("Y-m-d H:i:s") . "', 'Add  Portal Payment'";
                                            Database::iud("INSERT INTO `portal_payment_logs` (" . $paymnet_log_columns . ") VALUES (" . $payment_log_values . ")");

                                            $status->done = "Successfully added portal payment";
                                        } catch (Exception $e) {
                                            $status->error = $e->getMessage();
                                        }
                                    }
                                }
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
