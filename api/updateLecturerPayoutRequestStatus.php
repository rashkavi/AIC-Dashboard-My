<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if (SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole == 'Manager' || $sessionUserRole == 'Accountant') {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                    $content = trim(file_get_contents('php://input'));
                    $decoded = json_decode($content, true);
                    if (json_last_error() == JSON_ERROR_NONE) {
                      
                        $payoutId = isset($decoded['payoutId']) ? $decoded['payoutId'] : 0;
                        $payoutStatus = isset($decoded['payoutStatus']) ? $decoded['payoutStatus'] : 0;
                        $note = isset($decoded['note']) ? $decoded['note'] : '';

                        if ($payoutId == 0) {
                            $status->error = "Invalid payout request id";
                        } else {
                            $isValidPayoutId = Database::search("SELECT * FROM `lecturer_payouts` WHERE `lecturer_payouts_id` = '" . $payoutId . "'");
                            if ($isValidPayoutId->num_rows == 0) {
                                $status->error = "Invalid payout request id";
                            } else {

                                $isValidPayoutStatus = Database::search("SELECT * FROM `payout_status` WHERE `payout_status_id` = '" . $payoutStatus . "'");
                                if ($isValidPayoutStatus->num_rows == 0) {
                                    $status->status = "Invalid payout status";
                                } else {
                                    $paymentCurrentStatus = Database::search("SELECT `payout_status_payout_status_id` FROM `lecturer_payouts` WHERE `lecturer_payouts_id` = '" . $payoutId . "'");
                                    $row = $paymentCurrentStatus->fetch_assoc();
                                    if ($row["payout_status_payout_status_id"] == $payoutStatus) {
                                        $status->status = "This payout status already updated";
                                    } else {
                                        if ($sessionUserRole == 'Manager') {
                                            if (!($payoutStatus == 2 || $payoutStatus == 3)) {
                                                $status->error = "Invalid payout status";
                                            }
                                        } else if ($sessionUserRole == 'Accountant') {
                                            if (!($payoutStatus == 5 || $payoutStatus == 6 || $payoutStatus == 7)) {
                                                $status->error = "Invalid payout status";
                                            }
                                        }
                                    }
                                }

                                if (empty($note)) {
                                    $status->note = "Note is required";
                                }

                                if (!property_exists($status, "status") && !property_exists($status, "note") && !property_exists($status, "error")) {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $date = $dateTime->format('Y-m-d H:i:s');

                                    try {
                                        Database::iud("INSERT INTO `payout_logs` (`lecturer_payouts_lecturer_payouts_id`,`users_user_id`,`date_updated`,`note`) VALUES ('" . $payoutId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . $note . "')");

                                        if ($payoutStatus == 2) {
                                            Database::iud("UPDATE `lecturer_payouts` SET `payout_status_payout_status_id` = '4' WHERE `lecturer_payouts_id` = '" . $payoutId . "'");
                                        } else {
                                            Database::iud("UPDATE `lecturer_payouts` SET `payout_status_payout_status_id` = '" . $payoutStatus . "' WHERE `lecturer_payouts_id` = '" . $payoutId . "'");
                                        }
                                        $status->done = "Payout request status updated successfully!";
                                    } catch (Exception $e) {
                                        $status->error = $e->getMessage();
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
            $status->error = "You don't have permission to perform this action";
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
