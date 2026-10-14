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
                    $paymentPlanId = isset($decoded['paymentPlanId']) ? $decoded['paymentPlanId'] : '';
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
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $intakeId))) {
                                $status->error = 'You do not have permission to add payment plan to this intake';
                            } else {

                                if (empty($paymentPlanId) || $paymentPlanId == 0 || !is_numeric($paymentPlanId)) {
                                    $status->paymentPlan = 'Please select payment Plan';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `intake_has_payment_plans` WHERE `intake_has_payment_plan_id` = '" . $paymentPlanId . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->paymentPlan = 'Invalid payment Plan Id';
                                    }
                                }

                                if (!isset($checkedStudents) && !is_array($checkedStudents)) {
                                    $status->error = 'Please select atleast one student';
                                } else {
                                    if (count($checkedStudents) > 0) {
                                        $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` 
                                        WHERE `intake_has_payment_plan_id` = '" . $paymentPlanId . "' 
                                       
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
                                        WHERE `intake_has_payment_plan_id` = '" . $paymentPlanId . "'
                                        AND `student_payment_plan_type_student_payment_plan_type_id` = '2'
                                        AND `students_has_intakes_students_has_intakes_id` NOT IN (" . implode(',', $uncheckedStudents) . ")");
                                        $isValidIdData = $isValidId->fetch_assoc();

                                        if ($isValidIdData['count'] > 0) {
                                            $status->error = 'Some students are already added to this payment category';
                                        }
                                    }
                                }

                                if (empty(get_object_vars($status))) {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $addTime = $dateTime->format('Y-m-d H:i:s');

                                    if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0 && !$checkAll) {
                                        $status->warning = 'Please select atleast one student';
                                    } else {

                                        $paymentPlanData = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intake_has_payment_plan_id` = '" . $paymentPlanId . "'")->fetch_assoc();
                                        $paymentPlanPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments` 
                                        WHERE `intake_has_payment_plan_id` = '" . $paymentPlanId . "'");
                                        $paymentPlanPaymentsPenalities = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` IN (SELECT `intake_payments_plan_has_payment_id` FROM `intake_payments_plan_has_payments` WHERE `intake_has_payment_plan_id` = '" . $paymentPlanId . "')");

                                        $query = " SELECT `students_has_intakes_id` 
                                            FROM `students_has_intakes`
                                            INNER JOIN students ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                                            INNER JOIN login_details ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                            WHERE `intakes_intake_id` = '" . $intakeId . "' 
                                            AND `students_has_intakes`.`status_status_id` = 1
                                            AND `login_details`.`status_status_id` = 1
                                           ";

                                        if (count($checkedStudents) > 0) {
                                            $query .= " AND `students_has_intakes_id` IN (" . implode(',', $checkedStudents) . ")";
                                        }

                                        if (count($uncheckedStudents) > 0) {
                                            $query .= " AND `students_has_intakes_id` NOT IN (" . implode(',', $uncheckedStudents) . ")";
                                        }

                                        if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                            $query .= " AND `students_has_intakes_id` NOT IN (
                                                SELECT `students_has_intakes_students_has_intakes_id` 
                                                FROM `payment_plan_has_students_has_intakes` 
                                              
                                                WHERE `payment_category_payment_category_id` = '" . $paymentPlanData['payment_category_payment_category_id'] . "'
                                              
                                            )";
                                        }

                                        $result = Database::search($query);

                                        if ($result->num_rows > 0) {
                                            foreach ($result as $student) {
                                                $studentPlanPayments = $paymentPlanPayments;
                                                $studentHasIntakeId = $student['students_has_intakes_id'];

                                                $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`,
                                                  `discount_type_discount_type_id`,
                                                   `amount`, `discount`,
                                                    `date_added`,`intake_has_payment_plan_id`,
                                                    `payment_plan_payment_plan_id`,
                                                    `payment_category_payment_category_id`";
                                                $payment_plan_insert_values = "'" . $studentHasIntakeId . "',
                                                 '" . $paymentPlanData['discount_type_discount_type_id'] . "', '" . $paymentPlanData['amount'] . "',
                                                  '" . $paymentPlanData['discount'] . "', 
                                                  '" . $addTime . "', '" . $paymentPlanId . "',
                                                  '" . $paymentPlanData['payment_plan_payment_plan_id'] . "',
                                                  '" . $paymentPlanData['payment_category_payment_category_id'] . "'";

                                                if (!empty($paymentPlanData['discount_note'])) {
                                                    $payment_plan_insert_columns .= ", `discount_note`";
                                                    $payment_plan_insert_values .= ", '" . $paymentPlanData['discount_note'] . "'";
                                                }

                                                Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $payment_plan_insert_columns . ") VALUES(" . $payment_plan_insert_values . ")");
                                                $planId = Database::$connection->insert_id;


                                                if ($studentPlanPayments->num_rows > 0) {
                                                    foreach ($studentPlanPayments as $payment) {
                                                        $insert_col = "`payment_status_payment_status_id`,`payment_type_payment_type_id`,`refference`,`discount`,
                                                        `amount`,`discount_type_discount_type_id`,`discount_note`,`payment_plan_has_students_has_intakes_id`,
                                                        `panelty_payment_option_panelty_payment_option_id`,`intake_payments_plan_has_payment_id`";

                                                        $insert_val = "1,
                                                        '" . $payment['payment_type_payment_type_id'] . "',
                                                        '" . generateSecureUniqueId() . "',
                                                        '" . $payment['discount'] . "',
                                                        '" . $payment['amount'] . "',
                                                        '" . $payment['discount_type_discount_type_id'] . "',
                                                        '" . $payment['discount_note'] . "',
                                                        '" . $planId . "',
                                                        '" . $payment['panelty_payment_option_panelty_payment_option_id'] . "',
                                                        '" . $payment['intake_payments_plan_has_payment_id'] . "'";

                                                        if (!empty($payment['due_date'])) {
                                                            $insert_col .= ", `due_date`";
                                                            $insert_val .= ", '" . $payment['due_date'] . "'";
                                                        }

                                                        Database::iud("INSERT INTO `course_payments`(" . $insert_col . ") VALUES(" . $insert_val . ")");
                                                        $paymentId = Database::$connection->insert_id;

                                                        foreach ($paymentPlanPaymentsPenalities as $penality) {
                                                            if ($penality['intake_payments_plan_has_payment_id'] == $payment['intake_payments_plan_has_payment_id']) {
                                                                $insert_col = "`course_payments_course_payments_id`,`amount`,`days`,`panelty_payment_type_panelty_payment_type_id`";
                                                                $insert_val = "'" . $paymentId . "', '" . $penality['amount'] . "', '" . $penality['days'] . "', '" . $penality['panelty_payment_type_panelty_payment_type_id'] . "'";
                                                                Database::iud("INSERT INTO `course_payments_penalties`(" . $insert_col . ") VALUES(" . $insert_val . ")");
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        $status->done = 'Successfully added student to payment plan';
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
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
