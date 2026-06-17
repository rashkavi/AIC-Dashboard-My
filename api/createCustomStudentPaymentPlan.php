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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── DOMPDF Configuration ─────────────────────────────────────────────────────
$dompdfPaths = [
    '../vendor/autoload.php',
    '../includes/dompdf/autoload.inc.php',
];

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
                                        s.mobile,
                                        t.title,
                                        pp.payment_plan,
                                        pc.payment_category,
                                        c.code,
                                        pphsi.amount AS total_plan_amount,
                                        pphsi.discount AS plan_discount,
                                        pphsi.discount_type_discount_type_id,
                                        prog.programme,
                                        i.intake,
                                        f.faculty

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

                                    INNER JOIN students_has_intakes shi
                                        ON shi.students_has_intakes_id = '" . $lead['students_has_intakes_id'] . "'

                                    INNER JOIN intakes i
                                        ON i.intake_id = shi.intakes_intake_id

                                    INNER JOIN programmes prog
                                        ON prog.programme_id = i.programmes_programme_id

                                    INNER JOIN faculties f
                                        ON f.faculty_id = prog.faculties_faculty_id

                                    WHERE s.student_id = '" . $leadId . "'
                                ")->fetch_assoc();

                                    // ── Fetch course payments with tax rates ───────────────────────────────
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

                                    // ── Build payment rows for PDF and email ──────────────────────────────
                                    $paymentRows = "";
                                    $paymentTableData = [];
                                    $totalAmountBeforeTax = 0;
                                    $totalDiscount = 0;
                                    $totalTax = 0;
                                    $totalPayable = 0;
                                    $count = 1;

                                    // ── Fetch latest tax rate from database ──────────────────────────────
                                    $taxRate = 2.5; // Default fallback
                                    $latestTaxRate = Database::search("
                                            SELECT * FROM tax_rates 
                                            ORDER BY date_added DESC 
                                            LIMIT 1
                                        ");
                                    if ($latestTaxRate && $latestTaxRate->num_rows > 0) {
                                        $latestTaxRate = $latestTaxRate->fetch_assoc();
                                        $taxRate = (float)$latestTaxRate['tax_rate'];
                                    }

                                    // ── Validate tax rate to prevent division by zero ──────────────────────
                                    if ($taxRate >= 100) {
                                        error_log('Invalid tax rate: ' . $taxRate . '% - Tax rate cannot be 100% or higher');
                                        $taxRate = 0; // Fallback to no tax
                                    }

                                    while ($payment = $coursePayments->fetch_assoc()) {

                                        // ── Calculate discount amount ────────────────────────────────────────
                                        $discountAmount = $payment['discount'];
                                        if ($payment['discount_type_discount_type_id'] == 3) {
                                            // Type 3 = Percentage discount
                                            $discountAmount = ($payment['amount'] / 100) * $payment['discount'];
                                        }

                                        // ── Amount after discount ───────────────────────────────────────────
                                        $amountAfterDiscount = $payment['amount'] - $discountAmount;

                                        // ═════════════════════════════════════════════════════════════════════
                                        // ── MARKUP TAX CALCULATION (CORRECTED) ──────────────────────────────
                                        // Formula: Tax = Amount × Tax% / (100 - Tax%)
                                        // 
                                        // Example: Amount=10000, Tax=2.5%
                                        // Tax = 10000 × 2.5 / (100 - 2.5)
                                        //     = 10000 × 2.5 / 97.5
                                        //     = 256.41
                                        // Payable = 10000 + 256.41 = 10256.41 ✓
                                        // ═════════════════════════════════════════════════════════════════════

                                        $taxAmount = 0;
                                        if ($taxRate > 0 && $taxRate < 100) {
                                            $taxAmount = $amountAfterDiscount * $taxRate / (100 - $taxRate);
                                        }

                                        $payableAmount = $amountAfterDiscount + $taxAmount;

                                        // ── Accumulate totals ───────────────────────────────────────────────
                                        $totalAmountBeforeTax += $payment['amount'];
                                        $totalDiscount += $discountAmount;
                                        $totalTax += $taxAmount;
                                        $totalPayable += $payableAmount;

                                        // ── Build HTML email table row ──────────────────────────────────────
                                        $paymentRows .= '
                                                <tr>
                                                    <td style="padding:10px;border:1px solid #ddd;">' . $count . '</td>

                                                    <td style="padding:10px;border:1px solid #ddd;">
                                                        ' . htmlspecialchars($payment['payment_type']) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;">
                                                        ' . htmlspecialchars($payment['due_date'] ?? 'N/A') . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . $studentData['code'] . ' ' . number_format($payment['amount'], 2) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . ($discountAmount > 0 ? $studentData['code'] . ' ' . number_format($discountAmount, 2) : '<span style="color:#999;">—</span>') . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . number_format($taxRate, 2) . '%
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;">
                                                        ' . $studentData['code'] . ' ' . number_format($taxAmount, 2) . '
                                                    </td>

                                                    <td style="padding:10px;border:1px solid #ddd;text-align:right;font-weight:bold;">
                                                        ' . $studentData['code'] . ' ' . number_format($payableAmount, 2) . '
                                                    </td>
                                                </tr>
                                            ';

                                        // ── Store data for PDF ──────────────────────────────────────────────
                                        $paymentTableData[] = [
                                            'count' => $count,
                                            'payment_type' => $payment['payment_type'],
                                            'due_date' => $payment['due_date'] ?? 'N/A',
                                            'amount' => $payment['amount'],
                                            'discount' => $discountAmount,
                                            'tax_rate' => $taxRate,
                                            'tax_amount' => $taxAmount,
                                            'payable' => $payableAmount
                                        ];

                                        $count++;
                                    }

                                    // ── Generate Payment Plan PDF ────────────────────────────────────────
                                    $pdfPath = null;
                                    if ($usePdf && class_exists('Dompdf\Dompdf')) {
                                        try {
                                            $planPdfHtml = buildPaymentPlanPdf(
                                                $studentData,
                                                $paymentTableData,
                                                $totalAmountBeforeTax,
                                                $totalDiscount,
                                                $totalTax,
                                                $totalPayable,
                                                $planId,
                                                $addTime
                                            );

                                            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
                                            $dompdf->loadHtml($planPdfHtml);
                                            $dompdf->setPaper('A4', 'portrait');
                                            $dompdf->render();

                                            $pdfOutput = $dompdf->output();
                                            $pdfPath = sys_get_temp_dir() . '/payment_plan_' . $planId . '_' . time() . '.pdf';
                                            file_put_contents($pdfPath, $pdfOutput);
                                        } catch (Exception $e) {
                                            error_log('PDF Generation Error: ' . $e->getMessage());
                                            $pdfPath = null;
                                        }
                                    }

                                    // ── Update student status ────────────────────────────────────────────
                                    Database::iud("UPDATE `students` SET `lead_student_status_lead_student_status_id` = '4' WHERE `student_id` = '" . $leadId . "'");

                                    Database::iud("INSERT INTO `students_logs` 
                                    (`students_student_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                                     VALUES ('" . $leadId . "', '" . $addTime . "', '" . $sessionUserObject['user_id'] . "','Payment Plan Created','4')");


                                    // ── Send email with PDF attachment ──────────────────────────────────────

                                    require_once '../includes/phpmailer/src/Exception.php';
                                    require_once '../includes/phpmailer/src/PHPMailer.php';
                                    require_once '../includes/phpmailer/src/SMTP.php';

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
                                                            <th style="padding:10px;border:1px solid #ddd;">Tax %</th>
                                                            <th style="padding:10px;border:1px solid #ddd;">Tax Amount</th>
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

                                    try {
                                        $mailer = new PHPMailer(true);
                                        $mailer->SMTPDebug  = 0;
                                        $mailer->isSMTP();
                                        $mailer->Host       = 'smtp.titan.email';
                                        $mailer->SMTPAuth   = true;
                                        $mailer->Username   = 'no-reply@aiccampuslms.com';
                                        $mailer->Password   = 'AicLMS#@!2026Campus';
                                        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                        $mailer->Port       = 465;

                                        $mailer->setFrom('no-reply@aiccampuslms.com', Project::getBaseName());
                                        $fullName = $studentData['title'] . ". " . $studentData['first_name'] . ' ' . $studentData['last_name'];
                                        $mailer->addAddress($studentData['email'], $fullName);

                                        // ── Attach PDF ──────────────────────────────────────────────────────
                                        if ($pdfPath && file_exists($pdfPath)) {
                                            $mailer->addAttachment(
                                                $pdfPath,
                                                'Payment-Plan-' . date('Y-m-d') . '.pdf',
                                                PHPMailer::ENCODING_BASE64,
                                                'application/pdf'
                                            );
                                        }

                                        $mailer->isHTML(true);
                                        $mailer->Subject = $mail['subject'];
                                        $mailer->Body    = $mail['body'];
                                        $mailer->AltBody = 'Payment Plan Created for ' . $fullName . '. Please see attached PDF for details.';

                                        $mailer->send();

                                        // ── Clean up temp PDF ───────────────────────────────────────────────
                                        if ($pdfPath && file_exists($pdfPath)) {
                                            @unlink($pdfPath);
                                        }

                                        // ── Log the email send ───────────────────────────────────────────────
                                        Database::iud("
                                                INSERT INTO payment_logs
                                                    (course_payments_course_payments_id, users_user_id, date_updated, log)
                                                SELECT
                                                    cp.course_payments_id,
                                                    '" . (int)$sessionUserObject['user_id'] . "',
                                                    '" . $addTime . "',
                                                    'Payment Plan email sent to " . Database::escape($studentData['email']) . " with PDF attachment (Tax Method: Markup, Rate: " . $taxRate . "%)'
                                                FROM course_payments cp
                                                WHERE cp.payment_plan_has_students_has_intakes_id = '" . $planId . "'
                                                LIMIT 1
                                            ");

                                        $status->done = 'Payment Plan Created Successfully and emailed to ' . $studentData['email'];
                                    } catch (Exception $e) {
                                        if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);
                                        $status->error = 'Failed to send email: ' . $mailer->ErrorInfo;
                                        error_log('Email Error: ' . $mailer->ErrorInfo);
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
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}


function buildPaymentPlanPdf($studentData, $paymentTableData, $totalAmount, $totalDiscount, $totalTax, $totalPayable, $planId, $createdDate)
{
    $baseUrl = Project::getBaseUrl();
    $planDate = substr($createdDate, 0, 10);
    $currency = $studentData['code'];

    // ── Build payment schedule rows ────────────────────────────────────────
    $paymentRows = '';
    foreach ($paymentTableData as $payment) {
        $bg = ($payment['count'] % 2 === 0) ? '#ffffff' : '#f8fafc';
        $C = 'padding:8px 10px;border-bottom:1px solid #f3f4f6;font-size:10px;vertical-align:top;';

        $paymentRows .= '<tr style="background:' . $bg . ';">'
            . '<td style="' . $C . 'color:#9ca3af;text-align:center;">' . $payment['count'] . '</td>'
            . '<td style="' . $C . 'font-weight:600;">' . htmlspecialchars($payment['payment_type']) . '</td>'
            . '<td style="' . $C . 'white-space:nowrap;">' . htmlspecialchars($payment['due_date']) . '</td>'
            . '<td style="' . $C . 'text-align:right;">' . $currency . ' ' . fmtN($payment['amount']) . '</td>'
            . '<td style="' . $C . 'text-align:right;">' . ($payment['discount'] > 0 ? $currency . ' ' . fmtN($payment['discount']) : '<span style="color:#d1d5db;">—</span>') . '</td>'
            . '<td style="' . $C . 'text-align:center;">' . fmtN($payment['tax_rate']) . '%</td>'
            . '<td style="' . $C . 'text-align:right;">' . $currency . ' ' . fmtN($payment['tax_amount']) . '</td>'
            . '<td style="' . $C . 'text-align:right;font-weight:700;color:#1e3a5f;">' . $currency . ' ' . fmtN($payment['payable']) . '</td>'
            . '</tr>';
    }

    if (!$paymentRows) {
        $paymentRows = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#9ca3af;font-size:10px;">No payment records found</td></tr>';
    }

    $TH = 'padding:10px;font-size:9px;font-weight:700;color:rgba(255,255,255,.95);letter-spacing:.04em;';

    $studentName = htmlspecialchars($studentData['title'] . '. ' . $studentData['first_name'] . ' ' . $studentData['last_name']);

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payment Plan - {$studentData['student_id']}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; background: #fff; color: #111827; }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { size: A4 portrait; margin: 8mm 10mm; }
  }
</style>
</head>
<body style="padding: 28px 32px;">

<!-- Header -->
<table style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
<tr>
  <td style="vertical-align: top; width: 58%;">
    <img src="https://dashboard.aiccampuslms.com/assets/imgs/common/logo.png" alt="" style="height: 44px; width: auto;">
    <div style="font-size: 10px; color: #6b7280; margin-top: 4px;">No.3</div>
    <div style="font-size: 10px; color: #6b7280;">Tel: +94 769 679778</div>
    <div style="font-size: 10px; color: #6b7280;">Colombo</div>
  </td>
  <td style="vertical-align: top; text-align: right;">
    <div style="font-size: 8px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .12em; margin-bottom: 3px;">PAYMENT PLAN SCHEDULE</div>
    <div style="font-size: 20px; font-weight: 800; color: #111827;">PAYMENT PLAN</div>
    <div style="font-size: 10px; color: #6b7280; margin-top: 2px;">Date: {$planDate}</div>
  </td>
</tr>
</table>

<div style="height: 2px; background: #1e3a5f; margin-bottom: 18px; border-radius: 1px;"></div>

<!-- Student & Programme Details -->
<table style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
<tr>
  <td style="width: 50%; vertical-align: top; padding-right: 10px;">
    <div style="border: 1px solid #e5e7eb; border-radius: 3px; padding: 12px 14px;">
      <div style="font-size: 8px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 7px;">Student Information</div>
      <div style="font-size: 13px; font-weight: 700; color: #111827;">{$studentName}</div>
      <div style="font-size: 10px; color: #6b7280; margin-top: 4px; line-height: 1.8;">
        Student ID: {$studentData['student_id']}<br>
        Email: {$studentData['email']}<br>
        Mobile: {$studentData['mobile']}
      </div>
    </div>
  </td>
  <td style="width: 50%; vertical-align: top; padding-left: 10px;">
    <div style="border: 1px solid #e5e7eb; border-radius: 3px; padding: 12px 14px;">
      <div style="font-size: 8px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 7px;">Programme & Plan Details</div>
      <div style="font-size: 11px; font-weight: 600; color: #111827;">{$studentData['faculty']}</div>
      <div style="font-size: 10px; color: #6b7280; margin-top: 3px; line-height: 1.8;">
        <strong>Programme:</strong> {$studentData['programme']}<br>
        <strong>Intake:</strong> {$studentData['intake']}<br>
        <strong>Plan:</strong> {$studentData['payment_plan']}<br>
        <strong>Category:</strong> {$studentData['payment_category']}
      </div>
    </div>
  </td>
</tr>
</table>

<!-- Payment Schedule Table -->
<div style="font-size: 8px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 7px;">Payment Schedule</div>
<div style="border: 1px solid #e5e7eb; border-radius: 3px; overflow: hidden; margin-bottom: 18px;">
<table style="width: 100%; border-collapse: collapse;">
<thead>
<tr style="background: #1e3a5f;">
  <th style="{$TH} text-align: center;">#</th>
  <th style="{$TH} text-align: left;">Payment Type</th>
  <th style="{$TH} text-align: left;">Due Date</th>
  <th style="{$TH} text-align: right;">Amount</th>
  <th style="{$TH} text-align: right;">Discount</th>
  <th style="{$TH} text-align: center;">Tax %</th>
  <th style="{$TH} text-align: right;">Tax Amount</th>
  <th style="{$TH} text-align: right;">Payable Amount</th>
</tr>
</thead>
<tbody>{$paymentRows}</tbody>
</table>
</div>

<!-- Summary Section -->
<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
<tr>
  <td style="width: 55%; vertical-align: top; padding-right: 16px;">
    <div style="font-size: 9px; color: #9ca3af; line-height: 1.8; background: #f9fafb; padding: 12px 14px; border-radius: 3px; border: 1px solid #f3f4f6;">
      <strong style="color: #374151;">Important Notes:</strong><br>
      • Payment shall be made on or before the due date specified.<br>
      • Tax rates are applicable as per the latest government regulations.<br>
      • Tax is calculated using Markup method: Amount × Tax% / (100 - Tax%).<br>
      • Discounts applied are non-refundable and non-transferable.<br>
      • Late payments may incur penalty charges as per institutional policy.<br>
      • This plan is valid subject to student's continued enrollment.
    </div>
  </td>
 
</tr>
</table>

<!-- Footer -->
<div style="
position: fixed;
bottom: 20px;
left: 32px;
right: 32px;
border-top: 1px dashed #d1d5db;
padding-top: 10px;
">
  <div style="font-size: 9px; color: #9ca3af;">This is a computer-generated payment plan document and does not require a physical signature.</div>
  <div style="font-size: 9px; color: #6b7280; text-align: right;">Plan ID: <strong>{$planId}</strong> | Generated: {$planDate}</div>

<div style="text-align: center; margin-top: 14px; padding-top: 10px; border-top: 1px solid #f3f4f6;">
  <span style="font-size: 9px; color: #c4c9d4; letter-spacing: .04em;">&copy;<?php echo date("Y"); ?> ENLIXO Uni. All Rights Reserved | Powered by
    <a href="https://enviiz.com" target="_blank" style="text-decoration:none;">
      <img src="https://dashboard.aiccampuslms.com/assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares" style="width: auto; height: 40px;">
    </a></span>
</div>
</div>

</body>
</html>
HTML;
}
