<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
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

$usePdf = false;
foreach ($dompdfPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $usePdf = true;
        break;
    }
}

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $leadId = isset($decoded['leadId']) ? $decoded['leadId'] : '';
                    $intakePaymentPlanId = isset($decoded['intakePaymentPlanId']) ? $decoded['intakePaymentPlanId'] : '';
                    $markAsPaidPayments = isset($decoded['paidPayments']) ? $decoded['paidPayments'] : [];

                    if (empty($leadId) || $leadId == 0 || !is_numeric($leadId)) {
                        $status->error = 'Invalid Lead Id';
                    } else {
                        $isValidLead = Database::search("SELECT * FROM `students` 
                        INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                        INNER JOIN `title` ON `title`.`title_id` = `students`.`title_title_id`
                        INNER JOIN `students_has_intakes` ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                        INNER JOIN `intakes` ON `intakes`.`intake_id` = `students_has_intakes`.`intakes_intake_id`
                        INNER JOIN `programmes` ON `programmes`.`programme_id` = `intakes`.`programmes_programme_id`
                        INNER JOIN `faculties` ON `faculties`.`faculty_id` = `programmes`.`faculties_faculty_id`
                        INNER JOIN `branches` ON `branches`.`branch_id` = `faculties`.`branches_branch_id`
                        WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $leadId . "'");
                        if ($isValidLead->num_rows == 0) {
                            $status->error = 'Invalid Lead Id';
                        } else {
                            $leadData = $isValidLead->fetch_assoc();

                            if (empty($intakePaymentPlanId)) {
                                $status->error = 'Invalid Intake Payment Plan Id';
                            } else {
                                $isValidPaymentPlan = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intake_has_payment_plan_id` = '" . $intakePaymentPlanId . "'");
                                if ($isValidPaymentPlan->num_rows == 0) {
                                    $status->error = 'Invalid Intake Payment Plan Id';
                                } else {

                                    $paymentPlanData = $isValidPaymentPlan->fetch_assoc();

                                    if (count($markAsPaidPayments) > 0) {
                                        foreach ($markAsPaidPayments as $paidPayment) {

                                            $paymentTypeId = (int)$paidPayment["payment_type_id"];

                                            if ($paymentTypeId !== 18) {
                                                continue;
                                            }

                                            $paidAmount = (float)$paidPayment["paid_amount"];
                                            $paidDate = $paidPayment["paid_date"];
                                            $paymentMethodId = (int)$paidPayment["payment_method_id"];
                                            $note = trim($paidPayment["note"] ?? "");

                                            if ($paidAmount <= 0) {
                                                $status->paid_amount = "Paid amount is required";
                                            }

                                            if (empty($paidDate)) {
                                                $status->paid_date = "Paid date is required";
                                            }

                                            if ($paymentMethodId <= 0) {
                                                $status->paid_date = "Payment method is required";
                                            }
                                        }
                                    }


                                    if (empty(get_object_vars($status))) {
                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $addTime = $dateTime->format('Y-m-d H:i:s');

                                        $studentHasIntakeId = $leadData['students_has_intakes_id'];

                                        $paymentPlanPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments` 
                                    WHERE `intake_has_payment_plan_id` = '" . $intakePaymentPlanId . "'");
                                        $paymentPlanPaymentsPenalities = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` IN (SELECT `intake_payments_plan_has_payment_id` 
                                    FROM `intake_payments_plan_has_payments` WHERE `intake_has_payment_plan_id` = '" . $intakePaymentPlanId . "')");

                                        $studentPlanPayments = $paymentPlanPayments;

                                        $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`,
                                        `discount_type_discount_type_id`,
                                        `amount`, `discount`,
                                        `date_added`,`intake_has_payment_plan_id`,
                                        `payment_plan_payment_plan_id`,
                                        `payment_category_payment_category_id`";
                                        $payment_plan_insert_values = "'" . $studentHasIntakeId . "',
                                    '" . $paymentPlanData['discount_type_discount_type_id'] . "', '" . $paymentPlanData['amount'] . "',
                                    '" . $paymentPlanData['discount'] . "', 
                                    '" . $addTime . "', '" . $intakePaymentPlanId . "',
                                    '" . $paymentPlanData['payment_plan_payment_plan_id'] . "',
                                    '" . $paymentPlanData['payment_category_payment_category_id'] . "'";

                                        if (!empty($paymentPlanData['discount_note'])) {
                                            $payment_plan_insert_columns .= ", `discount_note`";
                                            $payment_plan_insert_values .= ", '" . $paymentPlanData['discount_note'] . "'";
                                        }

                                        Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $payment_plan_insert_columns . ") VALUES(" . $payment_plan_insert_values . ")");
                                        $planId = Database::$connection->insert_id;

                                        if ($studentPlanPayments->num_rows > 0) {
                                            while ($payment = $studentPlanPayments->fetch_assoc()) {
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

                                                while ($penality = $paymentPlanPaymentsPenalities->fetch_assoc()) {
                                                    if ($penality['intake_payments_plan_has_payment_id'] == $payment['intake_payments_plan_has_payment_id']) {
                                                        $insert_col = "`course_payments_course_payments_id`,`amount`,`days`,`panelty_payment_type_panelty_payment_type_id`";
                                                        $insert_val = "'" . $paymentId . "', '" . $penality['amount'] . "', '" . $penality['days'] . "', '" . $penality['panelty_payment_type_panelty_payment_type_id'] . "'";
                                                        Database::iud("INSERT INTO `course_payments_penalties`(" . $insert_col . ") VALUES(" . $insert_val . ")");
                                                    }
                                                }

                                                if (count($markAsPaidPayments) > 0) {
                                                    foreach ($markAsPaidPayments as $markAsPaidPayment) {
                                                        if ($markAsPaidPayment['installment_id'] == $payment['intake_payments_plan_has_payment_id']) {
                                                            $currencyResult = Database::search("SELECT `currencies_currency_id` AS `currency_id` FROM `payment_category` WHERE `payment_category_id` = '" . $paymentPlanData['payment_category_payment_category_id'] . "' LIMIT 1");
                                                            $paymentCurrencyId = 1;
                                                            if ($currencyResult->num_rows > 0) {
                                                                $paymentCurrencyId = (int)$currencyResult->fetch_assoc()['currency_id'];
                                                            }

                                                            $columns = "`course_payments_course_payments_id`,`payment_method_payment_method_id`
                                                            ,`date_paid`,`paid_amount`,`discount_amount`,`payment_status_payment_status_id`,`panelty_amount`,`bank_bank_id`,`currencies_currency_id`,`currency_exchange_rate_id`,`currency_exchange_rate`";

                                                            $values = "'" . $paymentId . "','" . $markAsPaidPayment['payment_method_id'] . "','" . $markAsPaidPayment['paid_date'] . "','" . $markAsPaidPayment['paid_amount'] . "','" . $markAsPaidPayment['discount'] . "','3','0',NULL,'" . $paymentCurrencyId . "',NULL,'1.0000'";

                                                            $branchName   = $leadData['branch'] ?? 'XX';
                                                            $branchPrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $branchName), 0, 2));
                                                            if (strlen($branchPrefix) < 2) $branchPrefix = str_pad($branchPrefix, 2, 'X');
                                                            $year = date('Y');

                                                            $seqQ  = Database::search("SELECT COUNT(*) AS cnt FROM course_payment_receipts WHERE receipt_number LIKE '" . addslashes($branchPrefix . $year) . "-%'");
                                                            $seq   = 1 + (int)(($seqQ && $seqQ->num_rows > 0) ? $seqQ->fetch_assoc()['cnt'] : 0);
                                                            $receiptNumber = $branchPrefix . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);

                                                            Database::iud("
    INSERT INTO course_payment_receipts
        (receipt_number, users_user_id, date_added, students_has_intakes_students_has_intakes_id)
    VALUES
        ('" . addslashes($receiptNumber) . "', '" . $sessionUserObject['user_id'] . "', '" . $addTime . "', '" . $leadData['students_has_intakes_id'] . "')
");
                                                            $receiptId = (int)Database::$connection->insert_id;

                                                            $columns .= ",`course_payment_receipts_course_payment_receipt_id`";
                                                            $values .= ",'" . $receiptId . "'";


                                                            Database::iud("INSERT INTO `course_payment_has_payments` (" . $columns . ") VALUES (" . $values . ")");
                                                            $cphp_id = Database::$connection->insert_id;

                                                            $discount = $payment['discount'];
                                                            if ($payment['discount_type_discount_type_id'] == 3) {
                                                                $discount = $payment['amount'] / 100 * $payment['discount'];
                                                            }

                                                            $payableAmount = $payment['amount'] - $discount;
                                                            $paidAmount = $markAsPaidPayment['paid_amount'] - $markAsPaidPayment['discount'];

                                                            if ($payableAmount == $paidAmount) {
                                                                Database::iud("UPDATE `course_payments` SET `payment_status_payment_status_id` = 5 WHERE `course_payments_id` = '" . $paymentId . "'");
                                                            }

                                                            $payment_log_columns = "`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`";
                                                            $payment_log_values = "'" . $paymentId . "','" . $cphp_id . "','" . $sessionUserObject['user_id'] . "', '" . $addTime . "', 'Registration Fee Marked as Paid'";

                                                            if (!empty($markAsPaidPayment['note'])) {
                                                                $payment_log_columns .= ",`note`";
                                                                $payment_log_values .= ",'" . Database::escape($markAsPaidPayment['note']) . "'";
                                                            }
                                                            Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");

                                                            Database::iud("INSERT INTO `students_logs` 
                                                        (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`,`note`)
                                                         VALUES ('" . $leadId . "', '" . $addTime . "', '" . $sessionUserObject['user_id'] . "','Registration Fee Approved','3','" . Database::escape($markAsPaidPayment['note']) . "')");

                                                            $encodedPaymentId = base64_encode($paymentId);

                                                            $slipUrl = Project::getBaseUrl() . '/payment-receipt?receipt-id=/' . $encodedPaymentId;

                                                            $mail = EmailNotificationService::build(
                                                                EmailTemplateContent::PAYMENT_SUCCESSFUL,
                                                                [
                                                                    'student_name' => $leadData['first_name'] . ' ' . $leadData['last_name'],
                                                                    'programme' => $leadData['programme'],
                                                                    'payment_type' => "Registration Fee",
                                                                    'paid_amount' => $markAsPaidPayment['paid_amount'],
                                                                    'payment_date' => $markAsPaidPayment['paid_date'],
                                                                    'slip_url' => $slipUrl,
                                                                ]
                                                            );

                                                            $fullName = $leadData['title'] . "." . $leadData['first_name'] . ' ' . $leadData['last_name'];
                                                            sendMail($leadData['email'], $fullName, $mail['subject'], $mail['body']);
                                                        }
                                                    }
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

                                    INNER JOIN students_has_intakes shi
                                        ON shi.students_student_id = s.student_id

                                    INNER JOIN payment_plan_has_students_has_intakes pphsi
                                        ON pphsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                                    INNER JOIN payment_plan pp
                                        ON pphsi.payment_plan_payment_plan_id = pp.payment_plan_id

                                    INNER JOIN payment_category pc
                                        ON pphsi.payment_category_payment_category_id = pc.payment_category_id

                                    INNER JOIN currencies c
                                        ON pc.currencies_currency_id = c.currency_id
                                    
                                    INNER JOIN intakes i
                                        ON i.intake_id = shi.intakes_intake_id

                                    INNER JOIN programmes prog
                                        ON prog.programme_id = i.programmes_programme_id

                                    INNER JOIN faculties f
                                        ON f.faculty_id = prog.faculties_faculty_id

                                    WHERE shi.students_has_intakes_id = '" . $leadId . "'
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
                                        Database::iud("UPDATE `students_has_intakes` SET `lead_student_status_lead_student_status_id` = '4' WHERE `students_has_intakes_id` = '" . $leadId . "'");

                                        Database::iud("INSERT INTO `students_logs` 
                                    (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
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

                                            $log = 'Payment Plan email sent to ' . $studentData['email'] . ' with PDF attachment (Tax Method: Markup, Rate: ' . $taxRate . '%)';

                                            $logTime = $dateTime->format('Y-m-d H:i:s');
                                            Database::iud("INSERT INTO `students_logs` 
                                                        (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`)
                                                         VALUES ('" . $leadId . "', '" . $logTime . "', '" . $sessionUserObject['user_id'] . "','" . Database::escape($log) . "')");


                                            $status->done = 'Payment Plan Created Successfully and emailed to ' . $studentData['email'];
                                        } catch (Exception $e) {
                                            if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);

                                            $log = 'Payment Plan email failed to send to ' . $studentData['email'];

                                            $logTime = $dateTime->format('Y-m-d H:i:s');
                                            Database::iud("INSERT INTO `students_logs` 
                                                        (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`)
                                                         VALUES ('" . $leadId . "', '" . $logTime . "', '" . $sessionUserObject['user_id'] . "','" . Database::escape($log) . "')");


                                            $status->error = 'Failed to send email: ' . $mailer->ErrorInfo;
                                            error_log('Email Error: ' . $mailer->ErrorInfo);
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

// ═══════════════════════════════════════════════════════════════════════════
// ─ Helper Functions ─
// ═══════════════════════════════════════════════════════════════════════════

function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}

function fmtN($n, $dp = 2)
{
    return number_format((float)($n ?? 0), $dp);
}

/**
 * Build professional Payment Plan PDF HTML
 * 
 * Uses Markup Tax method:
 * Tax Amount = Amount × Tax% / (100 - Tax%)
 * Payable = Amount + Tax Amount
 */
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
    <div style="font-size: 10px; color: #6b7280; margin-top: 4px;">No.154, Havelock Road, Thimbirigasyaya, Colombo 05</div>
    <div style="font-size: 10px; color: #6b7280;">Tel: +94 114 325 864</div>
    <div style="font-size: 10px; color: #6b7280;">accounts@aicedu.lk</div>
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
      <img src="https://dashboard.aiccampuslms.com/assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares" style="width: 40px;height: auto;">
    </a></span>
</div>
</div>

</body>
</html>
HTML;
}
