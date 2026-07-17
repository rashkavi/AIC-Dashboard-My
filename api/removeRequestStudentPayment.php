<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $sessionUserObject = SessionUser::getCurrentUserObject();
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $id = isset($decoded['paymentId']) ? $decoded['paymentId'] : 0;
                    if (empty($id) || $id == 0 || !is_numeric($id)) {
                        $status->error = 'Invalid Payment Id';
                    } else {
                        $paymentPlan = Database::search("SELECT *
                                FROM `course_payments` AS `cp`
                                INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi` 
                                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                INNER JOIN `students_has_intakes` AS `shi` 
                                    ON `pphsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                WHERE `cp`.`course_payments_id` = '" . $id . "';
                                ");

                        if ($paymentPlan->num_rows == 0) {
                            $status->error = 'Invalid Payment Id';
                        } else {
                            $paymentPlanData = $paymentPlan->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 11, $paymentPlan['intakes_intake_id']))) {
                                $status->error = 'You do not have permission to delete payment';
                            } else {
                                if (!($paymentPlanData['payment_status_payment_status_id'] == 1)) {
                                    $status->error = 'Cannot delete payment because the student has already paid.';
                                } else {
                                    Database::iud("DELETE FROM `payment_logs` WHERE `course_payments_course_payments_id`='" . $id . "'");
                                    Database::iud("DELETE FROM `course_payments_penalties` WHERE `course_payments_course_payments_id`='" . $id . "'");
                                    Database::iud("DELETE FROM `panelty_payments` WHERE `course_payments_course_payments_id`='" . $id . "'");
                                    Database::iud("DELETE FROM `course_payments` WHERE `course_payments_id`='" . $id . "'");
                                    $status->done = "Payment removed successfully.";
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
