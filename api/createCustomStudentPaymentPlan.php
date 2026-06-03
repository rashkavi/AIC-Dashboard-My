<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/mail-process.php");
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
                    $date = $dateTime->format('Y-m-d H:i:s');

                    $leadId = isset($decoded['id']) ? $decoded['id'] : 0;
                    $category = isset($decoded['category']) ? $decoded['category'] : 0;
                    $planName = isset($decoded['planName']) ? $decoded['planName'] : '';
                    $plan = isset($decoded['plan']) ? $decoded['plan'] : 0;
                    $isDefault = isset($decoded['isDefault']) ? $decoded['isDefault'] : false;
                    $payments = isset($decoded['payments']) ? $decoded['payments'] : [];
                    $paymentAmount = isset($decoded['paymentAmount']) ? $decoded['paymentAmount'] : 0;
                    $paymentdiscountType = isset($decoded['discountType']) ? $decoded['discountType'] : 0;
                    $discountAmount = isset($decoded['discountAmount']) ? $decoded['discountAmount'] : 0;
                    $discountNote = isset($decoded['discountNote']) ? $decoded['discountNote'] : '';

                    if (empty($leadId)) {
                        http_response_code(400);
                        $status->error = 'Lead is required';
                    } else {
                        $lead = Database::search("SELECT * FROM `students_has_intakes` WHERE `students_student_id`='" . $leadId . "'");
                        if ($lead->num_rows == 0) {
                            $status->error = 'Lead not found';
                        } else {
                            $lead = $lead->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 2))) {
                                $status->error = 'You are not authorized to add payments';
                            } else {

                                if (empty($category)) {
                                    $status->category = 'Please select a category';
                                }

                                if (empty($plan)) {
                                    $status->plan = 'Plan is required';
                                }

                                if (empty($paymentAmount) || $paymentAmount == 0 || !is_numeric($paymentAmount)) {
                                    $status->paymentAmount = 'Invalid payment Amount';
                                }

                                if (empty($paymentdiscountType) || $paymentdiscountType == 0 || !is_numeric($paymentdiscountType)) {
                                    $status->discountType = 'Please select a valid discount type.';
                                } else {
                                    $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `discount_type` WHERE `discount_type_id` = '" . $paymentdiscountType . "'");
                                    $isValidIdData = $isValidId->fetch_assoc();
                                    if ($isValidIdData['count'] == 0) {
                                        $status->discountType = 'Selected discount type does not exist.';
                                    }
                                }

                                if (!is_numeric($discountAmount)) {
                                    $discountAmount = 0;
                                } else if (!property_exists($status, 'discountType')) {

                                    if ($paymentdiscountType == 1 && $discountAmount > 0) {
                                        $status->discountAmount = 'No discount selected. Discount amount must be 0.';
                                    }

                                    if ($paymentdiscountType == 2 && $discountAmount <= 0) {
                                        $status->discountAmount = 'Fixed discount amount must be greater than 0.';
                                    }

                                    if ($paymentdiscountType == 3 && ($discountAmount <= 0 || $discountAmount > 100)) {
                                        $status->discountAmount = 'Percentage discount must be between 1 and 100.';
                                    }
                                }

                                if ($paymentdiscountType == 2 || $paymentdiscountType == 3) {
                                    if (empty($planDiscountNote)) {
                                        $status->discountNote = 'Discount note is required';
                                    }
                                }

                                if (!empty($plan)) {
                                    $errors = [];
                                    $validatedPayments = [];

                                    foreach ($payments as $index => $payment) {
                                        $installmentErrors = [];

                                        $installmentId = $payment['installmentId'] ?? null;
                                        $paymentType = (int)($payment['paymentType'] ?? 0);
                                        $amount        = $payment['amount'] ?? null;
                                        $dueDate       = trim($payment['dueDate'] ?? '');
                                        $discountType  = (int)($payment['discountType'] ?? 0);
                                        $discount      = $payment['discount'] ?? null;
                                        $discountNote  = trim($payment['discountNote'] ?? '');
                                        $penalty = $payment['penalty'] ?? [];
                                        $penaltyEnabled = $payment['penaltyEnabled'] ?? false;
                                        $installmentName = '';

                                        $installmentName = "Payment - " . $installmentId;

                                        if (empty($paymentType)) {
                                            $installmentErrors['paymentType'] = 'Payment type is required';
                                        } else {
                                            $isValid = Database::search("SELECT * FROM `payment_type` WHERE `payment_type_id`='" . $paymentType . "' AND (`payment_plan_payment_plan_id`='" . $plan . "' OR `payment_plan_payment_plan_id` IS NULL)");
                                            if ($isValid->num_rows == 0) {
                                                $installmentErrors['paymentType'] = 'Invalid payment type ID';
                                            }
                                        }

                                        if ($amount === '' || !is_numeric($amount) || $amount <= 0) {
                                            $installmentErrors['amount'] = 'Valid installment amount is required';
                                        }

                                        if ($discountType != 0 && $discountType != 1) {
                                            if ($discount === '' || !is_numeric($discount) || $discount < 0) {
                                                $installmentErrors['discount'] = 'Valid discount is required';
                                            }

                                            if ($discount == 3 && $discount > 100) {
                                                $installmentErrors['discount'] = 'Discount cannot exceed 100%';
                                            }

                                            if (is_numeric($discount) && is_numeric($amount) && $discount > $amount) {
                                                $installmentErrors['discount'] = 'Discount cannot exceed installment amount';
                                            }
                                        }

                                        if ($penaltyEnabled) {
                                            if (empty($penalty)) {
                                                $installmentErrors['panelty'] = 'Please select panelty option';
                                            } else {

                                                $penaltyErrors = [];
                                                $mode          = $penalty['mode'] ?? null;
                                                $slots         = $penalty['slots'] ?? [];
                                                if ($mode == 4) {

                                                    if (!is_array($slots) || empty($slots)) {
                                                        $penaltyErrors['slots'] = 'At least one penalty slot is required';
                                                    } else {
                                                        foreach ($slots as $sIndex => $slot) {
                                                            if (empty($slot['type'])) {
                                                                $penaltyErrors["slots.$sIndex.type"] = 'Penalty type is required';
                                                            }

                                                            if (
                                                                empty($slot['amount']) ||
                                                                !is_numeric($slot['amount']) ||
                                                                $slot['amount'] <= 0
                                                            ) {
                                                                $penaltyErrors["slots.$sIndex.amount"] = 'Valid penalty amount is required';
                                                            }

                                                            if ($slot['type'] == 3 && $slot['amount'] > 100) {
                                                                $installmentErrors["slots.$sIndex.amount"] = 'Panelty amount cannot exceed 100%';
                                                            }

                                                            if (
                                                                empty($slot['days']) ||
                                                                !is_numeric($slot['days']) ||
                                                                $slot['days'] <= 0
                                                            ) {
                                                                $penaltyErrors["slots.$sIndex.days"] = 'Valid penalty days is required';
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    if (empty($penalty['type'])) {
                                                        $penaltyErrors["slots.0.type"] = 'Penalty type is required';
                                                    }
                                                    if (
                                                        empty($penalty['amount']) ||
                                                        !is_numeric($penalty['amount']) ||
                                                        $penalty['amount'] <= 0
                                                    ) {
                                                        $penaltyErrors["slots.0.amount"] = 'Valid penalty amount is required';
                                                    }

                                                    if ($penalty['type'] == 3 && $penalty['type'] > 100) {
                                                        $penaltyErrors["slots.0.amount"] = 'Panelty amount cannot exceed 100%';
                                                    }
                                                }
                                                if (!empty($penaltyErrors)) {
                                                    $installmentErrors['panelty'] = $penaltyErrors;
                                                }
                                            }
                                        }

                                        if (!empty($installmentErrors)) {
                                            $errors[$index] = [
                                                'installmentId' => $installmentName,
                                                'errors' => $installmentErrors
                                            ];

                                            $status->installments = $errors;
                                        }
                                    }
                                }

                                $requiredProperties = [
                                    'error',
                                    'installments',
                                    'name',
                                    'category',
                                    'plan',
                                    'amount',
                                    'discount',
                                    'discountType',
                                    'discountNote',
                                    'discountAmount',
                                    'status'
                                ];
                                $allGood = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allGood = false;
                                        break;
                                    }
                                }

                                if ($allGood) {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $date = $dateTime->format('Y-m-d H:i:s');
                                    $addTime = $dateTime->format('Y-m-d H:i:s');

                                    $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`,
                                    `discount_type_discount_type_id`,
                                    `amount`, `discount`,
                                        `date_added`,
                                        `payment_plan_payment_plan_id`,
                                        `payment_category_payment_category_id`";

                                    $payment_plan_insert_values = "'" . $lead['students_has_intakes_id'] . "',
                                    '" . $paymentdiscountType . "',
                                    '" . $paymentAmount . "',
                                    '" . $discountAmount . "',
                                    '" . $addTime . "',
                                    '" . $plan . "',
                                   
                                    '" . $category . "'";

                                    if (!empty($planDiscountNote)) {
                                        $payment_plan_insert_columns .= ", `discount_note`";
                                        $payment_plan_insert_values .= ", '" . $planDiscountNote . "'";
                                    }

                                    Database::iud("INSERT INTO `payment_plan_has_students_has_intakes` (" . $payment_plan_insert_columns . ") VALUES (" . $payment_plan_insert_values . ")");
                                    $planId = Database::$connection->insert_id;

                                    foreach ($payments as $index => $payment) {
                                        $paymentType   = (int)($payment['paymentType'] ?? 0);
                                        $amount        = $payment['amount'] ?? null;
                                        $dueDate       = trim($payment['dueDate'] ?? '');
                                        $discountType  = (int)($payment['discountType'] ?? 0);
                                        $discount      = is_numeric($payment['discount']) ? (float)$payment['discount'] : 0;
                                        $discountNote  = trim($payment['discountNote'] ?? '');
                                        $penalty = $payment['penalty'] ?? [];
                                        $penaltyEnabled = $payment['penaltyEnabled'] ?? false;

                                        $penaltyId = $penaltyEnabled ? $penalty['mode'] : 1;

                                        $payment_insert_columns = "
                                                `payment_plan_has_students_has_intakes_id`,
                                                 `payment_type_payment_type_id`,
                                                 `payment_status_payment_status_id`,
                                                   `discount_type_discount_type_id`,
                                                `amount`, 
                                                 
                                                   `refference`,
                                                  
                                                    `panelty_payment_option_panelty_payment_option_id`";

                                        $payment_insert_values = "
                                                '" . $planId . "',
                                                '" . $paymentType . "',
                                                '1',
                                                 '" . $discountType . "',
                                                  '" . $amount . "',
                                                 
                                                   '" . generateSecureUniqueId() . "',
                                                  
                                                   '" . $penaltyId . "'";

                                        if (empty($discount)) {
                                            $payment_insert_columns .= ", `discount`";
                                            $payment_insert_values .= ", '0'";
                                        } else {
                                            $payment_insert_columns .= ", `discount`";
                                            $payment_insert_values .= ", '" . $discount . "'";
                                        }

                                        if (!empty($discountNote)) {
                                            $payment_insert_columns .= ", `discount_note`";
                                            $payment_insert_values .= ", '" . $discountNote . "'";
                                        }

                                        if (!empty($dueDate)) {
                                            $payment_insert_columns .= ", `due_date`";
                                            $payment_insert_values .= ", '" . $dueDate . "'";
                                        }

                                        Database::iud("INSERT INTO `course_payments`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");

                                        $ipphp = Database::$connection->insert_id;

                                        if ($penaltyEnabled) {
                                            $mode   = $penalty['mode'] ?? null;
                                            $type   = isset($penalty['type']) && $penalty['type'] !== '' ? (int)$penalty['type'] : null;
                                            $amount = isset($penalty['amount']) && $penalty['amount'] !== '' ? (float)$penalty['amount'] : null;
                                            $slots  = $penalty['slots'] ?? [];

                                            if ($mode == 4) {
                                                foreach ($slots as $sIndex => $slot) {
                                                    $columns = "`course_payments_course_payments_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`,`days`";
                                                    $values  = "'" . $ipphp . "','" . $slot['type'] . "','" . $slot['amount'] . "','" . $slot['days'] . "'";

                                                    Database::iud("INSERT INTO `course_payments_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                                }
                                            } else {
                                                $columns = "`course_payments_course_payments_id`,`panelty_payment_type_panelty_payment_type_id`,`amount`";
                                                $values  = "'" . $ipphp . "','" . $type . "','" . $amount . "'";

                                                Database::iud("INSERT INTO `course_payments_penalties` (" . $columns . ") VALUES (" . $values . ")");
                                            }
                                        }
                                    }

                                    $studentData = Database::search("
                                    SELECT 
                                        s.student_id,
                                        ld.first_name,
                                        ld.last_name,
                                        ld.email,
                                        t.title,
                                        pp.payment_plan,
                                        pc.payment_category,
                                        c.code

                                    FROM students s

                                    INNER JOIN login_details ld
                                        ON s.login_details_login_details_id = ld.login_details_id
                                    INNER JOIN title t
                                        ON s.title_title_id = t.title_id

                                    INNER JOIN payment_plan_has_students_has_intakes pphsi
                                        ON pphsi.students_has_intakes_students_has_intakes_id = '" . $lead['students_has_intakes_id'] . "'

                                    INNER JOIN payment_plan pp
                                        ON pphsi.payment_plan_payment_plan_id = pp.payment_plan_id

                                    INNER JOIN payment_category pc
                                        ON pphsi.payment_category_payment_category_id = pc.payment_category_id

                                    INNER JOIN currencies c
                                        ON pc.currencies_currency_id = c.currency_id

                                    WHERE s.student_id = '" . $leadId . "'
                                ")->fetch_assoc();

                                    $paymentRows = "";

                                    $coursePayments = Database::search("
                                                    SELECT 
                                                        cp.*,
                                                        pt.payment_type,
                                                        dt.discount_type

                                                    FROM course_payments cp

                                                    INNER JOIN payment_type pt
                                                        ON cp.payment_type_payment_type_id = pt.payment_type_id

                                                    INNER JOIN discount_type dt
                                                        ON cp.discount_type_discount_type_id = dt.discount_type_id

                                                    WHERE cp.payment_plan_has_students_has_intakes_id = '" . $planId . "'

                                                    ORDER BY cp.due_date ASC
                                                ");

                                    $count = 1;

                                    while ($payment = $coursePayments->fetch_assoc()) {

                                        $discountAmount = $payment['discount'];

                                        if ($payment['discount_type_discount_type_id'] == 3) {
                                            $discountAmount = ($payment['amount'] / 100) * $payment['discount'];
                                        }

                                        $finalAmount = $payment['amount'] - $discountAmount;

                                        $paymentRows .= '
                                                <tr>
                                                    <td style="padding:10px;border:1px solid #ddd;">' . $count . '</td>

                                                    <td style="padding:10px;border:1px solid #ddd;">
                                                        ' . htmlspecialchars($payment['payment_type']) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;">
                                                        ' . htmlspecialchars($payment['due_date']) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . $studentData['code'] . ' ' . number_format($payment['amount'], 2) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . $studentData['code'] . ' ' . number_format($discountAmount, 2) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;font-weight:bold;">
                                                        ' . $studentData['code'] . ' ' . number_format($finalAmount, 2) . '
                                                    </td>
                                                </tr>
                                            ';

                                        $count++;
                                    }

                                    Database::iud("UPDATE `students` SET `lead_student_status_lead_student_status_id` = '4' WHERE `student_id` = '" . $leadId . "'");

                                    Database::iud("INSERT INTO `students_logs` 
                                    (`students_student_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                                     VALUES ('" . $leadId . "', '" . $addTime . "', '" . $sessionUserObject['user_id'] . "','Payment Plan Created','4')");


                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::PAYMENT_PLAN_CREATED,
                                        [


                                            'student_name' => $studentData['first_name'] . ' ' . $studentData['last_name'],

                                            'payment_plan' => $studentData['payment_plan'],

                                            'payment_category' => $studentData['payment_category'],

                                            'payment_table' => '
                                                <table width="100%" cellspacing="0" cellpadding="0"
                                                       style="border-collapse:collapse;margin-top:15px;font-size:14px;">
                                    
                                                    <thead>
                                                        <tr style="background:#f5f5f5;">
                                                            <th style="padding:10px;border:1px solid #ddd;">#</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Payment Type</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Due Date</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Amount</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Discount</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Payable</th>
                                                        </tr>
                                                    </thead>
                                    
                                                    <tbody>
                                                        ' . $paymentRows . '
                                                    </tbody>
                                    
                                                </table>
                                            '

                                        ]
                                    );

                                    $fullName = $studentData['title'] . "." . $studentData['first_name'] . ' ' . $studentData['last_name'];
                                    sendMail($studentData['email'], $fullName, $mail['subject'], $mail['body']);



                                    $status->done = 'Payment plan created successfully!';
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
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
