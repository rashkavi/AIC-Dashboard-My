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

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 2))) {
                        $status->error = 'You are not authorized to add payment category';
                    } else {
                        $planId = isset($decoded['planId']) ? $decoded['planId'] : '';
                        $name = isset($decoded['name']) ? $decoded['name'] : '';

                        if (empty($name)) {
                            $status->name = "Type name is required";
                        } else {
                            $isValidName = Database::search("SELECT * FROM `payment_type` WHERE `payment_type`='" . $name . "'");
                            if ($isValidName->num_rows > 0) {
                                $status->name = "This type is already added";
                            }
                        }

                        $requiredProperties = [
                            'name',
                        ];
                        $allGood = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allGood = false;
                                break;
                            }
                        }

                        if ($allGood) {

                            $col = "`payment_type`";
                            $val = "'" . $name . "'";

                            if (!empty($planId)) {
                                $col .= ", `payment_plan_payment_plan_id`";
                                $val .= ", '" . $planId . "'";
                            }

                            Database::iud("INSERT INTO `payment_type` (" . $col . ") VALUES(" . $val . ")");
                            $status->done = "Payment type added successfully";
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

echo json_encode($status);
