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

                    $planId = isset($decoded['planId']) ? $decoded['planId'] : 0;
                    $paymentCategoryId = isset($decoded['paymentCategoryId']) ? $decoded['paymentCategoryId'] : 0;
                    $paymentPlanId = isset($decoded['paymentPlanId']) ? $decoded['paymentPlanId'] : 0;
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $discountType = isset($decoded['discountType']) ? $decoded['discountType'] : 0;
                    $discountAmount = isset($decoded['discountAmount']) ? $decoded['discountAmount'] : 0;
                    $discountNote = isset($decoded['discountNote']) ? $decoded['discountNote'] : '';

                    if (empty($discountAmount)) {
                        $discountAmount = 0;
                    }

                    if (empty($planId) || $planId == 0 || !is_numeric($planId)) {
                        $status->error = 'Invalid planId';
                    } else {
                        $isValidId = Database::search("SELECT * FROM `payment_plan_has_students_has_intakes`
                        INNER JOIN `students_has_intakes` ON `payment_plan_has_students_has_intakes`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                        INNER JOIN `students` ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                        WHERE `payment_plan_has_students_has_intakes_id` = '" . $planId . "'
                         AND (`students_has_intakes`.`status_status_id` = 1 OR `students_has_intakes`.`status_status_id` IS NULL)
                        AND (`login_details`.`status_status_id` = 1 OR `login_details`.`status_status_id` IS NULL)");
                        if ($isValidId->num_rows == 0) {
                            $status->error = 'Invalid planId';
                        } else {
                            $studentIntakeData = $isValidId->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $studentIntakeData['intakes_intake_id']))) {
                                $status->error = 'You do not have permission to add payment plan to this intake';
                            } else {

                                if (empty($paymentCategoryId) || $paymentCategoryId == 0 || !is_numeric($paymentCategoryId)) {
                                    $status->paymentCategory = 'Invalid payment CategoryId';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_category` WHERE `payment_category_id` = '" . $paymentCategoryId . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->paymentCategory = 'Invalid payment CategoryId';
                                    } else {
                                        $checkAlreadyAdded = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` WHERE 
                                        `payment_category_payment_category_id` = '" . $paymentCategoryId . "' AND `students_has_intakes_students_has_intakes_id` = '" . $studentIntakeData['students_has_intakes_students_has_intakes_id'] . "' AND `payment_plan_has_students_has_intakes_id` != '" . $planId . "'");
                                        $checkAlreadyAddedData = $checkAlreadyAdded->fetch_assoc();
                                        if ($checkAlreadyAddedData['count'] > 0) {
                                            $status->paymentCategory = 'Already added payment category';
                                        }
                                    }
                                }

                                if (empty($paymentPlanId) || $paymentPlanId == 0 || !is_numeric($paymentPlanId)) {
                                    $status->paymentPlan = 'Invalid payment Plan Id';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan` WHERE `payment_plan_id` = '" . $paymentPlanId . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->paymentPlan = 'Invalid payment Plan Id';
                                    }
                                }

                                if (empty($paymentAmount) || $paymentAmount == 0 || !is_numeric($paymentAmount)) {
                                    $status->paymentAmount = 'Invalid payment Amount';
                                }

                                if (empty($discountType) || $discountType == 0 || !is_numeric($discountType)) {
                                    $status->discountType = 'Invalid discount Type';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `discount_type` WHERE `discount_type_id` = '" . $discountType . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->discountType = 'Invalid discount Type';
                                    }
                                }

                                if (!is_numeric($discountAmount)) {
                                    $status->discountAmount = 'Invalid discount Amount';
                                } else if (!property_exists($status, 'discountType')) {
                                    if ($discountType == 1) {
                                        if ($discountAmount > 0) {
                                            $status->discountAmount = 'Selected discount is No Discount, but a discount was added';
                                        }
                                    } else if ($discountType == 2) {
                                        if ($discountAmount == 0) {
                                            $status->discountAmount = 'Selected discount is Fixed-Based, but the discount amount must be higher than 0';
                                        }
                                    } else if ($discountType == 3) {
                                        if ($discountAmount == 0 || $discountAmount > 100) {
                                            $status->discountAmount = 'Selected discount is Percentage-Based, but the discount amount must be between 1 and 100';
                                        }
                                    }
                                }

                                $requiredProperties = [
                                    'paymentCategory',
                                    'paymentPlan',
                                    'paymentAmount',
                                    'discountType',
                                    'discountAmount'
                                ];
                                $allPropertiesExist = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allPropertiesExist = false;
                                        break;
                                    }
                                }

                                if ($allPropertiesExist) {

                                    Database::iud("UPDATE `payment_plan_has_students_has_intakes` SET `payment_category_payment_category_id`='" . $paymentCategoryId . "',`payment_plan_payment_plan_id`='" . $paymentPlanId . "'
                                    , `discount_type_discount_type_id`='" . $discountType . "', `amount`='" . $paymentAmount . "', `discount`='" . $discountAmount . "', `discount_note`='" . $discountNote . "' WHERE `payment_plan_has_students_has_intakes_id`='" . $planId . "'");

                                    $status->done = 'Successfully updated student payment plan';
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
