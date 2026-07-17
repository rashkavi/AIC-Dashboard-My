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
                        $id = isset($decoded['id']) ? $decoded['id'] : 0;
                        $option = isset($decoded['option']) ? $decoded['option'] : 0;
                        $type = isset($decoded['type']) ? $decoded['type'] : 0;
                        $amount = isset($decoded['amount']) ? $decoded['amount'] : 0;
                        $days = isset($decoded['days']) ? $decoded['days'] : 0;
                        $currency = isset($decoded['currency']) ? $decoded['currency'] : 0;

                        if (empty($id) || $id == 0) {
                            $status->error = "Invalid panelty type id";
                        } else {
                            $isValidType = Database::search("SELECT * FROM `panelty_payment_category` WHERE `panelty_payment_category_id`='" . $id . "'");
                            if ($isValidType->num_rows == 0) {
                                $status->error = "Invalid panelty type id";
                            } else {

                                if ($option == 0) {
                                    $status->option = "Option is required";
                                } else {
                                    $isValidOption = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id`='" . $option . "'");
                                    if ($isValidOption->num_rows == 0) {
                                        $status->option = "Invalid option";
                                    }
                                }

                                if ($type == 0) {
                                    $status->type = "Type is required";
                                } else {
                                    $isValidType = Database::search("SELECT * FROM `panelty_payments_type` WHERE `panelty_payments_type_id`='" . $type . "'");
                                    if ($isValidType->num_rows == 0) {
                                        $status->type = "Invalid type";
                                    }
                                }

                                if (empty($amount)) {
                                    $status->amount = "Amount is required";
                                } else {
                                    if ($amount <= 0) {
                                        $status->amount = "Amount must be greater than 0";
                                    } else {
                                        if ($type == 2) {
                                            if ($amount > 100) {
                                                $status->amount = "Precentage must be less than 100";
                                            }
                                        }
                                    }
                                }

                                if ($option == 2) {
                                    if (empty($days)) {
                                        $status->days = "Days is required";
                                    } else {
                                        if ($days < 0) {
                                            $status->days = "Days must be greater than 0";
                                        }
                                    }
                                }

                                if ($type == 0) {
                                    if ($currency == 0) {
                                        $status->currency = "Currency is required";
                                    } else {
                                        $isVaidCurrency = Database::search("SELECT * FROM `currencies` WHERE `currency_id`='" . $currency . "' AND `is_active` = '1'");
                                        if ($isVaidCurrency->num_rows == 0) {
                                            $status->currency = "Invalid currency";
                                        }
                                    }
                                }

                                $requiredProperties = [
                                    'option',
                                    'type',
                                    'amount',
                                    'days',
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

                                    $query = "SELECT * FROM `panelty_payment_category` WHERE `panelty_payments_type_panelty_payments_type_id`='" . $type . "' 
                            AND `panelty_payment_option_panelty_payment_option_id`='" . $option . "' AND `panelty_amount`='" . $amount . "'";

                                    if ($type == 1) {
                                        $query .= " AND `currencies_currency_id`='" . $currency . "'";
                                    }

                                    if ($option == 2) {
                                        $query .= " AND `due_dates`='" . $days . "'";
                                    }

                                    $query .= " AND `panelty_payment_category_id`!='" . $id . "'";

                                    $isValidCategory = Database::search($query);

                                    if ($isValidCategory->num_rows > 0) {
                                        $status->type = "";
                                        $status->option = "";
                                        $status->amount = "";
                                        $status->days = "";
                                        $status->currency = "";
                                        $status->error = "Panelty payment category already exists";
                                    } else {

                                        $updateQuery = "`panelty_payment_option_panelty_payment_option_id`='" . $option . "'
                                        ,`panelty_payments_type_panelty_payments_type_id`='" . $type . "'
                                        ,`panelty_amount`='" . $amount . "'";

                                        if ($type == 1) {
                                            $updateQuery .= ",`currencies_currency_id`='" . $currency . "'";
                                        } else {
                                            $updateQuery .= ",`currencies_currency_id`=NULL";
                                        }

                                        if ($option == 2) {
                                            $updateQuery .= ",`due_dates`='" . $days . "'";
                                        } else {
                                            $updateQuery .= ",`due_dates`=NULL";
                                        }

                                        Database::iud("UPDATE `panelty_payment_category` SET " . $updateQuery . " WHERE `panelty_payment_category_id`='" . $id . "'");
                                        $status->done = "Panelty payment category updated successfully";
                                    }
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
