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
                        $categoryId = isset($decoded['categoryId']) ? $decoded['categoryId'] : 0;
                        $name = isset($decoded['name']) ? $decoded['name'] : '';
                        $currency = isset($decoded['currency']) ? $decoded['currency'] : 0;

                        if (empty($categoryId) || $categoryId == 0) {
                            $status->error = "Invalid category id";
                        } else {
                            $isValidId = Database::search("SELECT * FROM `payment_category` WHERE `payment_category_id`='" . $categoryId . "'");
                            if ($isValidId->num_rows == 0) {
                                $status->error = "Invalid category id";
                            } else {
                                if (empty($name)) {
                                    $status->name = "Category name is required";
                                } else {
                                    $isValidName = Database::search("SELECT * FROM `payment_category` WHERE `payment_category`='" . $name . "' AND `payment_category_id` != '" . $categoryId . "'");
                                    if ($isValidName->num_rows > 0) {
                                        $status->name = "This category name is already added";
                                    }
                                }

                                if ($currency == 0) {
                                    $status->currency = "Currency is required";
                                } else {
                                    $isVaidCurrency = Database::search("SELECT * FROM `currencies` WHERE `currency_id`='" . $currency . "' AND `is_active` = '1'");
                                    if ($isVaidCurrency->num_rows == 0) {
                                        $status->currency = "Invalid currency";
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
                                    Database::iud("UPDATE `payment_category` SET `payment_category` ='" . $name . "', `currencies_currency_id`='" . $currency . "' WHERE `payment_category_id`='" . $categoryId . "'");
                                    $status->done = "Payment category updated successfully";
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
