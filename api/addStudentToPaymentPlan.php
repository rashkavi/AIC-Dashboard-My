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

                    $studentHasIntakeId = isset($decoded['studentHasIntakeId']) ? $decoded['studentHasIntakeId'] : 0;
                    $paymentCategoryId = isset($decoded['paymentCategoryId']) ? $decoded['paymentCategoryId'] : 0;
                    $paymentPlanId = isset($decoded['paymentPlanId']) ? $decoded['paymentPlanId'] : 0;
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $discountType = isset($decoded['discountType']) ? $decoded['discountType'] : 0;
                    $discountAmount = isset($decoded['discountAmount']) ? $decoded['discountAmount'] : 0;
                    $discountNote = isset($decoded['discountNote']) ? $decoded['discountNote'] : '';

                    if (empty($discountAmount)) {
                        $discountAmount = 0;
                    }

                    if (empty($studentHasIntakeId) || $studentHasIntakeId == 0 || !is_numeric($studentHasIntakeId)) {
                        $status->error = 'Invalid studentHasIntakeId';
                    } else {
                        $isValidId = Database::search("SELECT * FROM `students_has_intakes` WHERE `students_has_intakes_id` = '" . $studentHasIntakeId . "'");
                        if ($isValidId->num_rows == 0) {
                            $status->error = 'Invalid studentHasIntakeId';
                        } else {
                            $studentIntakeData = $isValidId->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $studentIntakeData['intakes_intake_id']))) {
                                $status->error = 'You do not have permission to add payment plan to this intake';
                            } else {

                                if (empty($paymentCategoryId) || $paymentCategoryId == 0 || !is_numeric($paymentCategoryId)) {
                                    $status->paymentCategory = 'Please select a valid payment category.';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_category` WHERE `payment_category_id` = '" . $paymentCategoryId . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->paymentCategory = 'Selected payment category does not exist.';
                                    } else {
                                        $checkAlreadyAdded = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` WHERE `payment_category_payment_category_id` = '" . $paymentCategoryId . "' AND `students_has_intakes_students_has_intakes_id` = '" . $studentHasIntakeId . "'");
                                        $checkAlreadyAddedData = $checkAlreadyAdded->fetch_assoc();
                                        if ($checkAlreadyAddedData['count'] > 0) {
                                            $status->paymentCategory = 'This payment category has already been added.';
                                        }
                                    }
                                }

                                if (empty($paymentPlanId) || $paymentPlanId == 0 || !is_numeric($paymentPlanId)) {
                                    $status->paymentPlan = 'Please select a valid payment plan.';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan` WHERE `payment_plan_id` = '" . $paymentPlanId . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->paymentPlan = 'Selected payment plan does not exist.';
                                    }
                                }

                                if (empty($paymentAmount) || $paymentAmount == 0 || !is_numeric($paymentAmount)) {
                                    $status->paymentAmount = 'Invalid payment Amount';
                                }

                                if (empty($discountType) || $discountType == 0 || !is_numeric($discountType)) {
                                    $status->discountType = 'Please select a valid discount type.';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `discount_type` WHERE `discount_type_id` = '" . $discountType . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->discountType = 'Selected discount type does not exist.';
                                    }
                                }

                                if (!is_numeric($discountAmount)) {
                                    $status->discountAmount = 'Discount amount must be a valid number.';
                                } else if (!property_exists($status, 'discountType')) {

                                    if ($discountType == 1 && $discountAmount > 0) {
                                        $status->discountAmount = 'No discount selected. Discount amount must be 0.';
                                    }

                                    if ($discountType == 2 && $discountAmount <= 0) {
                                        $status->discountAmount = 'Fixed discount amount must be greater than 0.';
                                    }

                                    if ($discountType == 3 && ($discountAmount <= 0 || $discountAmount > 100)) {
                                        $status->discountAmount = 'Percentage discount must be between 1 and 100.';
                                    }
                                }

                                if ($discountType == 2 || $discountType == 3) {
                                    if (empty($discountNote)) {
                                        $status->discountNote = 'Discount note is required';
                                    }
                                }


                                $requiredProperties = [
                                    'paymentCategory',
                                    'paymentPlan',
                                    'paymentAmount',
                                    'discountType',
                                    'discountAmount',
                                    'discountNote'
                                ];
                                $allPropertiesExist = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allPropertiesExist = false;
                                        break;
                                    }
                                }

                                if ($allPropertiesExist) {

                                    $columns = "`students_has_intakes_students_has_intakes_id`, `payment_category_payment_category_id`, `payment_plan_payment_plan_id`, `discount_type_discount_type_id`, `amount`, `discount`, `discount_note`";
                                    $values = "'" . $studentHasIntakeId . "', '" . $paymentCategoryId . "', '" . $paymentPlanId . "', '" . $discountType . "', '" . $paymentAmount . "', '" . $discountAmount . "', '" . $discountNote . "'";

                                    Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $columns . ") VALUES(" . $values . ")");

                                    $status->done = 'Successfully added student to payment plan';
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
