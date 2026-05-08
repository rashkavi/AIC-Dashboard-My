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

                    require_once "../includes/connection.php";

                    $intakeId = isset($decoded['intakeId']) ? $decoded['intakeId'] : 0;
                    $paymentCategoryId = isset($decoded['paymentCategoryId']) ? $decoded['paymentCategoryId'] : 0;
                    $paymentPlanId = isset($decoded['paymentPlanId']) ? $decoded['paymentPlanId'] : 0;
                    $paymentType = isset($decoded['paymentType']) ? $decoded['paymentType'] : 0;
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $discountType = isset($decoded['discountType']) ? $decoded['discountType'] : 0;
                    $discountAmount = isset($decoded['discountAmount']) ? $decoded['discountAmount'] : 0;
                    $discountNote = isset($decoded['discountNote']) ? $decoded['discountNote'] : '';
                    $paymentNote = isset($decoded['paymentNote']) ? $decoded['paymentNote'] : '';
                    $cardPayment = isset($decoded['cardPayment']) ? $decoded['cardPayment'] : false;
                    $checkedStudents = isset($decoded['checkedStudents']) ? $decoded['checkedStudents'] : [];
                    $uncheckedStudents = isset($decoded['uncheckedStudents']) ? $decoded['uncheckedStudents'] : [];
                    $checkAll = isset($decoded['checkAll']) ? $decoded['checkAll'] : false;

                    if (empty($paymentCategoryId) || $paymentCategoryId == 0 || !is_numeric($paymentCategoryId)) {
                        $status->paymentCategory = 'Invalid payment CategoryId';
                    } else {
                        $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_category` WHERE `payment_category_id` = '" . $paymentCategoryId . "'");
                        $isValidIdData = $isValidId->fetch_assoc();
                        if ($isValidIdData['count'] == 0) {
                            $status->paymentCategory = 'Invalid payment CategoryId';
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

                    if (empty($paymentType) || $paymentType == 0 || !is_numeric($paymentType)) {
                        $status->paymentType = 'Invalid payment Type';
                    } else {
                        $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_type` WHERE `payment_type_id` = '" . $paymentType . "'");
                        $isValidIdData = $isValidId->fetch_assoc();
                        if ($isValidIdData['count'] == 0) {
                            $status->paymentType = 'Invalid payment Type';
                        }
                    }

                    if (empty($paymentAmount) || $paymentAmount == 0 || !is_numeric($paymentAmount)) {
                        $status->paymentAmount = 'Invalid payment Amount';
                    }

                    if (empty($discountType) || $discountType == 0 || !is_numeric($discountType)) {
                        $status->discountType = 'Invalid discount Type';
                    } else {
                        $isValidId = Database::search("SELECT COUNT(*) as 'count',`discount_type` FROM `discount_type` WHERE `discount_type_id` = '" . $discountType . "'");
                        $isValidIdDiscountData = $isValidId->fetch_assoc();
                        if ($isValidIdDiscountData['count'] == 0) {
                            $status->discountType = 'Invalid discount Type';
                        } else {
                            if ($isValidIdDiscountData['discount_type'] == 1) {
                                if ($discountAmount > 0) {
                                    $status->discountAmount = 'Selected discount is No Discount, but a discount was added';
                                }
                            } else if ($isValidIdDiscountData['discount_type'] == 3) {
                                if ($discountAmount == 0 || $discountAmount > 100) {
                                    $status->discountAmount = 'Selected discount is Percentage-Based, but the discount amount must be between 1 and 100';
                                }
                            }
                        }
                    }

                    if (!isset($checkedStudents) && !is_array($checkedStudents)) {
                        $status->error = 'Please select atleast one student';
                    } else {
                        if (count($checkedStudents) > 0) {
                            $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` 
                            WHERE `payment_category_payment_category_id` = '" . $paymentCategoryId . "'
                            AND `payment_plan_payment_plan_id` = '" . $paymentPlanId . "' 
                            AND `payment_plan_has_students_has_intakes_id` IN (" . implode(',', $checkedStudents) . ")");
                            $isValidIdData = $isValidId->fetch_assoc();

                            if ($isValidIdData['count'] === count($checkedStudents)) {
                                $status->error = 'Something went wrong please try again later1';
                            }
                        }
                    }

                    if (!isset($uncheckedStudents) && !is_array($uncheckedStudents)) {
                        $status->error = 'Please select atleast one student';
                    } else {
                        if (count($uncheckedStudents) > 0) {

                            $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` 
                            WHERE `payment_category_payment_category_id` = '" . $paymentCategoryId . "'
                            AND `payment_plan_payment_plan_id` = '" . $paymentPlanId . "' 
                            AND `payment_plan_has_students_has_intakes_id` IN (" . implode(',', $uncheckedStudents) . ")");
                            $isValidIdData = $isValidId->fetch_assoc();

                            if ($isValidIdData['count'] === count($uncheckedStudents)) {
                                $status->error = 'Something went wrong please try again later';
                            }
                        }
                    }

                    $requiredProperties = [
                        'error',
                        'paymentCategory',
                        'paymentPlan',
                        'paymentType',
                        'paymentAmount',
                        'discountType',
                        'discountAmount',
                        'paymentNote',
                        'cardPayment',
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
                        $date = $dateTime->format('Y-m-d H:i:s');

                        $paymentMethod = $cardPayment == true ? '2' : '1';

                        if (empty($paymentNote)) {
                            $paymentNote = 'Add Payment';
                        }

                        if (count($checkedStudents) > 0) {
                            foreach ($checkedStudents as $studentHasIntakeId) {
                                $payment_insert_columns = "`payment_status_payment_status_id`, `payment_type_payment_type_id`, `payment_method_payment_method_id`, `discount_type_discount_type_id`, `amount`, `discount_note`,`payment_plan_has_students_has_intakes_id`,`refference`";
                                $payment_insert_values = "'1', '" . $paymentType . "', '" . $paymentMethod . "', '" . $discountType . "', '" . $paymentAmount . "', '" . $discountNote . "', '" . $studentHasIntakeId . "','" . generateSecureUniqueId() . "'";

                                if (empty($discountAmount)) {
                                    $payment_insert_columns .= ", `discount`";
                                    $payment_insert_values .= ", '0'";
                                } else {
                                    $payment_insert_columns .= ", `discount`";
                                    $payment_insert_values .= ", '" . $discountAmount . "'";
                                }

                                Database::iud("INSERT INTO `course_payments`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");

                                $cpId = Database::$connection->insert_id;

                                $payment_log_columns = "`course_payments_course_payments_id`,`users_user_id`,`date_updated`,`note`";
                                $payment_log_values = "'" . $cpId . "', '" . $sessionUserObject['user_id'] . "', '" . $date . "', '" . $paymentNote . "'";

                                Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");
                            }
                            $status->done = 'Successfully added student to payment';
                        }

                        if (count($uncheckedStudents) > 0) {
                            $students = Database::search("SELECT `payment_plan_has_students_has_intakes_id` FROM `payment_plan_has_students_has_intakes` 
                            WHERE `payment_plan_has_students_has_intakes_id` NOT IN (" . implode(',', $uncheckedStudents) . ")");

                            if ($students->num_rows > 0) {
                                foreach ($students as $student) {
                                    $payment_insert_columns = "`payment_status_payment_status_id`, `payment_type_payment_type_id`, `payment_method_payment_method_id`, `discount_type_discount_type_id`, `amount`, `discount_note`,`payment_plan_has_students_has_intakes_id`,`refference`";
                                    $payment_insert_values = "'1', '" . $paymentType . "', '" . $paymentMethod . "', '" . $discountType . "', '" . $paymentAmount . "', '" . $discountNote . "', '" . $student['payment_plan_has_students_has_intakes_id'] . "','" . generateSecureUniqueId() . "'";

                                    if (empty($discountAmount)) {
                                        $payment_insert_columns .= ", `discount`";
                                        $payment_insert_values .= ", '0'";
                                    } else {
                                        $payment_insert_columns .= ", `discount`";
                                        $payment_insert_values .= ", '" . $discountAmount . "'";
                                    }

                                    Database::iud("INSERT INTO `course_payments`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");

                                    $cpId = Database::$connection->insert_id;

                                    $payment_log_columns = "`course_payments_course_payments_id`,`users_user_id`,`date_updated`,`note`";
                                    $payment_log_values = "'" . $cpId . "', '" . $sessionUserObject['user_id'] . "', '" . $date . "', '" . $paymentNote . "'";

                                    Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");
                                }
                            }
                            $status->done = 'Successfully added student to payment plan';
                        }

                        if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                            if ($checkAll) {
                                $studentCount = Database::search("
                                SELECT `payment_plan_has_students_has_intakes_id` 
                                FROM `payment_plan_has_students_has_intakes`
                                WHERE `payment_category_payment_category_id` = '" . $paymentCategoryId . "'
                                AND `payment_plan_payment_plan_id` = '" . $paymentPlanId . "' 
                                AND `students_has_intakes_students_has_intakes_id` IN (
                                    SELECT `students_has_intakes_id` 
                                    FROM `students_has_intakes` 
                                    WHERE `intakes_intake_id` = '" . $intakeId . "'
                                )
                            ");

                                if ($studentCount->num_rows > 0) {
                                    foreach ($studentCount as $student) {
                                        $payment_insert_columns = "`payment_status_payment_status_id`, `payment_type_payment_type_id`, `payment_method_payment_method_id`, `discount_type_discount_type_id`, `amount`, `discount_note`,`payment_plan_has_students_has_intakes_id`,`refference`";
                                        $payment_insert_values = "'1', '" . $paymentType . "', '" . $paymentMethod . "', '" . $discountType . "', '" . $paymentAmount . "', '" . $discountNote . "', '" . $student['payment_plan_has_students_has_intakes_id'] . "','" . generateSecureUniqueId() . "'";

                                        if (empty($discountAmount)) {
                                            $payment_insert_columns .= ", `discount`";
                                            $payment_insert_values .= ", '0'";
                                        } else {
                                            $payment_insert_columns .= ", `discount`";
                                            $payment_insert_values .= ", '" . $discountAmount . "'";
                                        }

                                        Database::iud("INSERT INTO `course_payments`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");

                                        $cpId = Database::$connection->insert_id;

                                        $payment_log_columns = "`course_payments_course_payments_id`,`users_user_id`,`date_updated`,`note`";
                                        $payment_log_values = "'" . $cpId . "', '" . $sessionUserObject['user_id'] . "', '" . $date . "', '" . $paymentNote . "'";

                                        Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");
                                    }
                                }
                                $status->done = 'Successfully added student to payment plan';
                            } else {
                                $status->warning = 'Please select atleast one student';
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

function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
