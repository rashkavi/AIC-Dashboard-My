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

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $date = $dateTime->format('Y-m-d');

                    $group              = isset($decoded['group']) ? $decoded['group'] : "";
                    $category           = isset($decoded['category']) ? trim($decoded['category']) : "";
                    $type               = isset($decoded['type']) ? trim($decoded['type']) : "";
                    $plan               = isset($decoded['plan']) ? trim($decoded['plan']) : "";
                    $amount             = isset($decoded['amount']) ? floatval($decoded['amount']) : 0;
                    $discountType      = isset($decoded['discountType']) ? trim($decoded['discountType']) : "";
                    $discount           = isset($decoded['discount']) ? floatval($decoded['discount']) : 0;
                    $discountNote       = isset($decoded['discountNote']) ? trim($decoded['discountNote']) : "";
                    $dueDate             = isset($decoded['dueDate']) ? $decoded['dueDate'] : null;
                    $isEnablePanelty     = isset($decoded['isEnablePanelty']) ? (bool)$decoded['isEnablePanelty'] : false;
                    $paneltyOption       = isset($decoded['paneltyOption']) ? trim($decoded['paneltyOption']) : "";
                    $paneltyCategories   = isset($decoded['paneltyCategories']) && is_array($decoded['paneltyCategories'])
                        ? $decoded['paneltyCategories']
                        : [];


                    if (empty($group)) {
                        http_response_code(400);
                        $status->error = 'Group is required';
                    } else {
                        $isValidGroup = Database::search("SELECT * FROM `intakes_has_payment_groups` WHERE `intakes_has_payment_group_id`='" . $group . "'");
                        if ($isValidGroup->num_rows == 0) {
                            http_response_code(400);
                            $status->error = 'Invalid group';
                        } else {
                            $groupData = $isValidGroup->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $intake_id))) {
                                $status->error = 'You are not authorized to add payment group';
                            } else {

                                if (empty($category)) {
                                    $status->category = 'Payment category is required';
                                } else {
                                    $isValidCategory = Database::search("SELECT * FROM `payment_category` WHERE `payment_category_id`='" . $category . "'");
                                    if ($isValidCategory->num_rows == 0) {
                                        $status->category = 'Invalid payment category';
                                    }
                                }

                                if (empty($type)) {
                                    $status->type = 'Payment type is required';
                                } else {
                                    $isValidType = Database::search("SELECT * FROM `payment_type` WHERE `payment_type_id`='" . $type . "'");
                                    if ($isValidType->num_rows == 0) {
                                        $status->type = 'Invalid payment type';
                                    }
                                }

                                if (empty($plan)) {
                                    $status->plan = 'Payment plan is required';
                                } else {
                                    $isValidPlan = Database::search("SELECT * FROM `payment_plan` WHERE `payment_plan_id`='" . $plan . "'");
                                    if ($isValidPlan->num_rows == 0) {
                                        $status->plan = 'Invalid payment plan';
                                    }
                                }

                                if (empty($amount)) {
                                    $status->amount = 'Amount is required';
                                }

                                if (empty($discountType)) {
                                    $status->discountType = 'Discount type is required';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `discount_type` WHERE `discount_type_id` = '" . $discountType . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->discountType = 'Invalid discount Type';
                                    }
                                }

                                if ($discountType == 2 || $discountType == 3) {
                                    if (empty($discountAmount) || $discountAmount == 0 || !is_numeric($discountAmount)) {
                                        $status->discountAmount = 'Invalid discount Amount';
                                    }

                                    if (empty($discountNote)) {
                                        $status->discountNote = 'Discount note is required';
                                    }
                                }

                                if (empty($dueDate)) {
                                    $status->dueDate = 'Due date is required';
                                } else if ($dueDate < $date || $dueDate == $date) {
                                    $status->dueDate = 'Due date must be a future date';
                                }

                                if ($isEnablePanelty) {
                                    if (empty($paneltyOption)) {
                                        $status->paneltyOption = 'Panelty option is required';
                                    } else {
                                        $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `panelty_payment_option` WHERE `panelty_payment_option_id` = '" . $paneltyOption . "'");
                                        $isValidIdData = $isValidId->fetch_assoc();
                                        if ($isValidIdData['count'] == 0) {
                                            $status->paneltyOption = 'Invalid panelty option';
                                        }
                                    }

                                    if (empty($paneltyCategories)) {
                                        $status->paneltyCategories = 'Panelty categories is required';
                                    }
                                }

                                $requiredProperties = [
                                    'error',
                                    'category',
                                    'type',
                                    'plan',
                                    'amount',
                                    'discountType',
                                    'discountAmount',
                                    'dueDate',
                                    'paneltyOption',
                                    'paneltyCategories',
                                    'discountNote'
                                ];
                                $allGood = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allGood = false;
                                        break;
                                    }
                                }

                                if ($allGood) {

                                    $columns = "`intakes_has_payment_group_id`,
                                    `payment_category_payment_category_id`,
                                    `payment_type_payment_type_id`,
                                    `payment_plan_payment_plan_id`,
                                    `amount`,
                                    `discount_type_discount_type_id`,
                                    `discount`,
                                    `due_date`,
                                    `is_enable_panelty_payment`
                                    ";

                                    $values = "'" . $group . "',
                                    '" . $category . "',
                                    '" . $type . "',
                                    '" . $plan . "',
                                    '" . $amount . "',
                                    '" . $discountType . "',
                                    '" . $discount . "',
                                    '" . $dueDate . "',
                                    '" . ($isEnablePanelty ? 1 : 0) . "'";

                                    if (!empty($discountNote)) {
                                        $columns .= ", `discount_note`";
                                        $values .= ", '" . $discountNote . "'";
                                    }

                                    if ($isEnablePanelty) {
                                        $columns .= ",`panelty_payment_option_panelty_payment_option_id`";
                                        $values .= ",'" . $paneltyOption . "'";
                                    }

                                    Database::iud("INSERT INTO `payment_groups_payments`(" . $columns . ") VALUES(" . $values . ")");

                                    $paymentId = Database::$connection->insert_id;

                                    if ($isEnablePanelty) {
                                        foreach ($paneltyCategories as $paneltyCategory) {
                                            $columns = "`payment_groups_payment_id`, `panelty_payment_category_id`";
                                            $values = "'" . $paymentId . "','" . $paneltyCategory . "'";

                                            Database::iud("INSERT INTO `payment_groups_panelty_payments`(" . $columns . ") VALUES(" . $values . ")");
                                        }
                                    }

                                    $status->done = 'Payment added successfully';
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
