<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) ||SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
            $status->error = 'You do not have permission to perform this action';
        } else {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                    $content = trim(file_get_contents('php://input'));
                    $decoded = json_decode($content, true);
                    if (json_last_error() == JSON_ERROR_NONE) {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $today = new DateTime("now", $timezone);

                        $lectureId = isset($decoded['lectureId']) ? $decoded['lectureId'] : '';

                        if (empty($lectureId) || $lectureId == 0) {
                            $status->error = 'Lecture is required';
                        } else {
                            $query = "SELECT 
                                `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`,
                                `lecturer_payments`.`earning_status_earning_status_id`
                            FROM `lecturer_payments_has_lectures`
                            INNER JOIN `lecturer_payments` ON `lecturer_payments`.`lecturer_payments_id` = `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`
                            WHERE `lecturer_payments_has_lectures`.`time_table_time_table_id` = '" . $lectureId . "'";

                            $paymentLinkResult = Database::search($query);

                            if ($paymentLinkResult->num_rows == 0) {
                                $status->error = 'No payment record found for this lecture';
                            } else {
                                $paymentLink = $paymentLinkResult->fetch_assoc();

                                if ($paymentLink['earning_status_earning_status_id'] != 1) {
                                    $status->error = 'Only scheduled payments can be cancelled';
                                } else {
                                    $lecturerPaymentsId = $paymentLink['lecturer_payments_lecturer_payments_id'];


                                    Database::iud("DELETE FROM `lecturer_payments_logs` WHERE `lecturer_payments_logs`.`lecturer_payments_lecturer_payments_id` = '" . $lecturerPaymentsId . "'");
                                    Database::iud("DELETE FROM `lecturer_payments_has_lectures` WHERE `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id` = '" . $lecturerPaymentsId . "'");
                                    Database::iud("DELETE FROM `lecturer_payments` WHERE `lecturer_payments`.`lecturer_payments_id` = '" . $lecturerPaymentsId . "'");

                                    $status->done = 'Schedule cancelled successfully';
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
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}
echo (json_encode($status));
