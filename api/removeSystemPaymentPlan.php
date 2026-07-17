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
                    $planId = isset($decoded["plan"]) ? $decoded["plan"] : 0;

                    if (empty($planId) || $planId == 0) {
                        $status->error = 'Invalid intake payment plan';
                    } else {
                        $plan = Database::search("SELECT * FROM `system_payment_plans` 
                        WHERE `system_payment_plan_id`='" . $planId . "'");
                        if ($plan->num_rows == 0) {
                            $status->error = 'System Payment Plan not found';
                        } else {
                            $plan = $plan->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 22) || UserPermissions::hasPermission('payments', 23))) {
                                $status->error = 'You are not authorized to delete payment plan';
                            } else {

                                $planHasPayments = Database::search("SELECT * FROM `system_payment_plan_has_payments` WHERE `system_payment_plan_id`='" . $planId . "'");
                                while ($planHasPayment = $planHasPayments->fetch_assoc()) {
                                    Database::iud("DELETE FROM `system_payment_plan_has_penalties` WHERE `system_payment_plan_has_payment_id` = '" . $planHasPayment['system_payment_plan_has_payment_id'] . "'");
                                    Database::iud("DELETE FROM `system_payment_plan_has_payments` WHERE `system_payment_plan_id` = '" . $planHasPayment['system_payment_plan_id'] . "'");
                                }
                                Database::iud("DELETE FROM `system_payment_plans_log` WHERE `system_payment_plan_id`='" . $planId . "'");
                                Database::iud("DELETE FROM `system_payment_plans` WHERE `system_payment_plan_id`='" . $planId . "'");
                                $status->done = 'System Payment Plan removed successfully';
                            }
                        }
                    }
                }
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
