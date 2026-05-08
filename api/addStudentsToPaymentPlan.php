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

                    $intakeId = isset($decoded['intakeId']) ? $decoded['intakeId'] : '';
                    $paymentCategoryId = isset($decoded['paymentCategoryId']) ? $decoded['paymentCategoryId'] : '';
                    $paymentPlanId = isset($decoded['paymentPlanId']) ? $decoded['paymentPlanId'] : '';
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : '';
                    $discountType = isset($decoded['discountType']) ? $decoded['discountType'] : '';
                    $discountAmount = isset($decoded['discountAmount']) ? $decoded['discountAmount'] : '';
                    $discountNote = isset($decoded['discountNote']) ? $decoded['discountNote'] : '';
                    $checkedStudents = isset($decoded['checkedStudents']) ? $decoded['checkedStudents'] : [];
                    $uncheckedStudents = isset($decoded['uncheckedStudents']) ? $decoded['uncheckedStudents'] : [];
                    $checkAll = isset($decoded['checkAll']) ? $decoded['checkAll'] : false;

                    if (empty($intakeId) || $intakeId == 0 || !is_numeric($intakeId)) {
                        $status->intake = 'Invalid Intake Id';
                    } else {
                        $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id` = '" . $intakeId . "'");
                        $isValidIdData = $isValidId->fetch_assoc();
                        if ($isValidIdData['count'] == 0) {
                            $status->intake = 'Invalid Intake Id';
                        } else {
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $intakeId))) {
                                $status->error = 'You do not have permission to add payment plan to this intake';
                            } else {
                                if (empty($paymentCategoryId) || $paymentCategoryId == 0 || !is_numeric($paymentCategoryId)) {
                                    $status->paymentCategory = 'Please select payment Category';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_category` WHERE `payment_category_id` = '" . $paymentCategoryId . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->paymentCategory = 'Invalid payment Category Id';
                                    }
                                }

                                if (empty($paymentPlanId) || $paymentPlanId == 0 || !is_numeric($paymentPlanId)) {
                                    $status->paymentPlan = 'Please select payment Plan';
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

                                if ($discountType == 2 || $discountType == 3) {
                                    if (empty($discountAmount) || $discountAmount == 0 || !is_numeric($discountAmount)) {
                                        $status->discountAmount = 'Invalid discount Amount';
                                    }
                                } else {
                                    $discountAmount = 0;
                                }

                                if (!isset($checkedStudents) && !is_array($checkedStudents)) {
                                    $status->error = 'Please select atleast one student';
                                } else {
                                    if (count($checkedStudents) > 0) {
                                        $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` 
                                        WHERE `payment_category_payment_category_id` = '" . $paymentCategoryId . "' 
                                        AND `students_has_intakes_students_has_intakes_id` IN (" . implode(',', $checkedStudents) . ")");
                                        $isValidIdData = $isValidId->fetch_assoc();

                                        if ($isValidIdData['count'] > 0) {
                                            $status->error = 'Some students are already added to this payment category';
                                        }
                                    }
                                }

                                if (!isset($uncheckedStudents) && !is_array($uncheckedStudents)) {
                                    $status->error = 'Please select atleast one student';
                                } else {
                                    if (count($uncheckedStudents) > 0) {

                                        $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` 
                                        WHERE `payment_category_payment_category_id` = '" . $paymentCategoryId . "' 
                                        AND `students_has_intakes_students_has_intakes_id` NOT IN (" . implode(',', $uncheckedStudents) . ")");
                                        $isValidIdData = $isValidId->fetch_assoc();

                                        if ($isValidIdData['count'] > 0) {
                                            $status->error = 'Some students are already added to this payment category';
                                        }
                                    }
                                }


                                $requiredProperties = [
                                    'error',
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
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $addTime = $dateTime->format('Y-m-d H:i:s');

                                    if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0 && !$checkAll) {
                                        $status->warning = 'Please select atleast one student';
                                    } else {

                                        if (count($checkedStudents) > 0) {

                                            foreach ($checkedStudents as $studentHasIntakeId) {
                                                $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`, `payment_category_payment_category_id`, `payment_plan_payment_plan_id`, `discount_type_discount_type_id`, `amount`, `discount`, `date_added`";
                                                $payment_plan_insert_values = "'" . $studentHasIntakeId . "', '" . $paymentCategoryId . "', '" . $paymentPlanId . "', '" . $discountType . "', '" . $paymentAmount . "', '" . $discountAmount . "', '" . $addTime . "'";

                                                if (!empty($discountNote)) {
                                                    $payment_plan_insert_columns .= ", `discount_note`";
                                                    $payment_plan_insert_values .= ", '" . $discountNote . "'";
                                                }

                                                Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $payment_plan_insert_columns . ") VALUES(" . $payment_plan_insert_values . ")");
                                            }
                                            $status->done = 'Successfully added student to payment plan';
                                        }

                                        if (count($uncheckedStudents) > 0) {
                                            $students = Database::search("SELECT `students_has_intakes_id` FROM `students_has_intakes` 
                                            WHERE `intakes_intakes_id` = '" . $intakeId . "' AND `students_has_intakes_id` NOT IN (" . implode(',', $uncheckedStudents) . ")");

                                            if ($students->num_rows > 0) {
                                                foreach ($students as $student) {
                                                    $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`, `payment_category_payment_category_id`, `payment_plan_payment_plan_id`, `discount_type_discount_type_id`, `amount`, `discount`, `date_added`";
                                                    $payment_plan_insert_values = "'" . $student['students_has_intakes_id'] . "', '" . $paymentCategoryId . "', '" . $paymentPlanId . "', '" . $discountType . "', '" . $paymentAmount . "', '" . $discountAmount . "','" . $addTime . "'";

                                                    if (!empty($discountNote)) {
                                                        $payment_plan_insert_columns .= ", `discount_note`";
                                                        $payment_plan_insert_values .= ", '" . $discountNote . "'";
                                                    }

                                                    Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $payment_plan_insert_columns . ") VALUES(" . $payment_plan_insert_values . ")");
                                                }
                                            }
                                            $status->done = 'Successfully added student to payment plan';
                                        }

                                        if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                            if ($checkAll) {
                                                $studentCount = Database::search("
                                            SELECT `students_has_intakes_id` 
                                            FROM `students_has_intakes`
                                            WHERE `intakes_intake_id` = '" . $intakeId . "' 
                                            AND `students_has_intakes_id` NOT IN (
                                                SELECT `students_has_intakes_students_has_intakes_id` 
                                                FROM `payment_plan_has_students_has_intakes` 
                                                WHERE `payment_category_payment_category_id` = '" . $paymentCategoryId . "'
                                            )
                                        ");

                                                if ($studentCount->num_rows > 0) {
                                                    foreach ($studentCount as $student) {
                                                        $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`, `payment_category_payment_category_id`, `payment_plan_payment_plan_id`, `discount_type_discount_type_id`, `amount`, `discount`,`date_added`";
                                                        $payment_plan_insert_values = "'" . $student['students_has_intakes_id'] . "', '" . $paymentCategoryId . "', '" . $paymentPlanId . "', '" . $discountType . "', '" . $paymentAmount . "', '" . $discountAmount . "', '" . $addTime . "'";

                                                        if (!empty($discountNote)) {
                                                            $payment_plan_insert_columns .= ", `discount_note`";
                                                            $payment_plan_insert_values .= ", '" . $discountNote . "'";
                                                        }

                                                        Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $payment_plan_insert_columns . ") VALUES(" . $payment_plan_insert_values . ")");
                                                    }
                                                }
                                                $status->done = 'Successfully added student to payment plan';
                                            }
                                        }
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

echo (json_encode($status));
