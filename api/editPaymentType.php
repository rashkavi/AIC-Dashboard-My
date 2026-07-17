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
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 10))) {
                        $status->error = 'You are not authorized to edit payment category';
                    } else {
                        $id = isset($decoded['id']) ? $decoded['id'] : 0;
                        $name = isset($decoded['name']) ? $decoded['name'] : '';

                        if (empty($id) || $id == 0) {
                            $status->error = "Invalid type id";
                        } else {
                            $isValidId = Database::search("SELECT * FROM `payment_type` WHERE `payment_type_id`='" . $id . "'");
                            if ($isValidId->num_rows == 0) {
                                $status->error = "Invalid type id";
                            } else {
                                if (empty($name)) {
                                    $status->name = "Type name is required";
                                } else {
                                    $isValidName = Database::search("SELECT * FROM `payment_type` WHERE `payment_type`='" . $name . "' AND `payment_type_id` != '" . $id . "'");
                                    if ($isValidName->num_rows > 0) {
                                        $status->name = "This type name is already added";
                                    }
                                }

                                $requiredProperties = [
                                    'name',
                                    'currency'
                                ];
                                $allGood = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allGood = false;
                                        break;
                                    }
                                }

                                if ($allGood) {
                                    Database::iud("UPDATE `payment_type` SET `payment_type` ='" . $name . "' WHERE `payment_type_id`='" . $id . "'");
                                    $status->done = "Payment type updated successfully";
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

echo json_encode($status);
