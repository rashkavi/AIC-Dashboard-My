<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once '../includes/phpmailer/src/Exception.php';
require_once '../includes/phpmailer/src/PHPMailer.php';
require_once '../includes/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── SMTP settings (move these to a config / .env file and rotate the password) ──
const RPP_MAIL_HOST     = 'smtp.titan.email';
const RPP_MAIL_USERNAME = 'no-reply@aiccampuslms.com';
const RPP_MAIL_PASSWORD = 'AicLMS#@!2026Campus';
const RPP_MAIL_PORT     = 465;

// ── DOMPDF ──────────────────────────────────────────────────────────────
$usePdf = false;
$dompdfPaths = [
    '../vendor/autoload.php',
    '../includes/dompdf/autoload.inc.php',
];

foreach ($dompdfPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $usePdf = true;
        break;
    }
}

header('Content-Type: application/json');

// ── Auth ────────────────────────────────────────────────────────────────
if (!SessionUser::hasValidSession() || SessionUser::getCurrentUserRole() == null) {
    $status->error = 'Authentication Failed!';
    echo json_encode($status);
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject();

// ── Request validation ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json') {
    http_response_code(400);
    $status->error = 'Invalid request';
    echo json_encode($status);
    exit;
}

$decoded = json_decode(trim(file_get_contents('php://input')), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    $status->error = 'Invalid JSON';
    echo json_encode($status);
    exit;
}

$leadId = isset($decoded['leadId']) ? (int)$decoded['leadId'] : 0;
if ($leadId <= 0) {
    $status->error = 'Invalid Lead Id';
    echo json_encode($status);
    exit;
}

// ── Student + latest payment plan ───────────────────────────────────────
$studentRs = Database::search("
    SELECT s.student_id, ld.first_name, ld.last_name, ld.email, s.mobile, t.title,
           pp.payment_plan, pc.payment_category, c.code,
           pphsi.payment_plan_has_students_has_intakes_id AS plan_id,
           prog.programme, i.intake, f.faculty
    FROM students_has_intakes shi
    INNER JOIN students s ON s.student_id = shi.students_student_id
    INNER JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
    INNER JOIN title t ON t.title_id = s.title_title_id
    INNER JOIN payment_plan_has_students_has_intakes pphsi
        ON pphsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN payment_plan pp ON pp.payment_plan_id = pphsi.payment_plan_payment_plan_id
    INNER JOIN payment_category pc ON pc.payment_category_id = pphsi.payment_category_payment_category_id
    INNER JOIN currencies c ON c.currency_id = pc.currencies_currency_id
    INNER JOIN intakes i ON i.intake_id = shi.intakes_intake_id
    INNER JOIN programmes prog ON prog.programme_id = i.programmes_programme_id
    INNER JOIN faculties f ON f.faculty_id = prog.faculties_faculty_id
    WHERE shi.students_has_intakes_id = '" . $leadId . "'
    ORDER BY pphsi.payment_plan_has_students_has_intakes_id DESC
    LIMIT 1
");

if (!$studentRs || $studentRs->num_rows == 0) {
    $status->error = 'No payment plan found for this student';
    echo json_encode($status);
    exit;
}
$studentData = $studentRs->fetch_assoc();
$planId = (int)$studentData['plan_id'];

if (empty($studentData['email'])) {
    $status->error = 'This student has no email address';
    echo json_encode($status);
    exit;
}

// ── Tax rate (same logic as create) ─────────────────────────────────────
$taxRate = 2.5;
$taxRs = Database::search("SELECT * FROM tax_rates ORDER BY date_added DESC LIMIT 1");
if ($taxRs && $taxRs->num_rows > 0) {
    $taxRate = (float)$taxRs->fetch_assoc()['tax_rate'];
}
if ($taxRate >= 100) {
    error_log('Invalid tax rate: ' . $taxRate . '% - Tax rate cannot be 100% or higher');
    $taxRate = 0;
}

// ── Payments of the plan ────────────────────────────────────────────────
$coursePayments = Database::search("
    SELECT cp.*, pt.payment_type
    FROM course_payments cp
    INNER JOIN payment_type pt ON cp.payment_type_payment_type_id = pt.payment_type_id
    WHERE cp.payment_plan_has_students_has_intakes_id = '" . $planId . "'
    ORDER BY cp.due_date ASC
");

$paymentRows = '';
$paymentTableData = [];
$totalAmount = $totalDiscount = $totalTax = $totalPayable = 0;
$count = 1;

while ($p = $coursePayments->fetch_assoc()) {
    $discount = $p['discount'];
    if ($p['discount_type_discount_type_id'] == 3) {
        $discount = ($p['amount'] / 100) * $p['discount'];
    }

    $afterDiscount = $p['amount'] - $discount;
    $tax = ($taxRate > 0) ? $afterDiscount * $taxRate / (100 - $taxRate) : 0;
    $payable = $afterDiscount + $tax;

    $totalAmount += $p['amount'];
    $totalDiscount += $discount;
    $totalTax += $tax;
    $totalPayable += $payable;

    $paymentRows .= '<tr>
        <td style="padding:10px;border:1px solid #ddd;">' . $count . '</td>
        <td style="padding:10px;border:1px solid #ddd;">' . htmlspecialchars($p['payment_type']) . '</td>
        <td style="padding:10px;border:1px solid #ddd;">' . htmlspecialchars($p['due_date'] ?? 'N/A') . '</td>
        <td style="padding:10px;border:1px solid #ddd;text-align:right;">' . $studentData['code'] . ' ' . number_format($p['amount'], 2) . '</td>
        <td style="padding:10px;border:1px solid #ddd;text-align:right;">' . ($discount > 0 ? $studentData['code'] . ' ' . number_format($discount, 2) : '—') . '</td>
        <td style="padding:10px;border:1px solid #ddd;text-align:right;">' . number_format($taxRate, 2) . '%</td>
        <td style="padding:10px;border:1px solid #ddd;text-align:right;">' . $studentData['code'] . ' ' . number_format($tax, 2) . '</td>
        <td style="padding:10px;border:1px solid #ddd;text-align:right;font-weight:bold;">' . $studentData['code'] . ' ' . number_format($payable, 2) . '</td>
    </tr>';

    $paymentTableData[] = [
        'count'        => $count,
        'payment_type' => $p['payment_type'],
        'due_date'     => $p['due_date'] ?? 'N/A',
        'amount'       => $p['amount'],
        'discount'     => $discount,
        'tax_rate'     => $taxRate,
        'tax_amount'   => $tax,
        'payable'      => $payable
    ];
    $count++;
}

if (empty($paymentTableData)) {
    $status->error = 'This payment plan has no payments';
    echo json_encode($status);
    exit;
}

// ── Generate PDF ────────────────────────────────────────────────────────
$timezone = new DateTimeZone("Asia/Colombo");
$now = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');

$pdfPath = null;
$pdfError = null;

if (!$usePdf || !class_exists('Dompdf\Dompdf')) {
    $pdfError = 'PDF library (Dompdf) was not found.';
    error_log('Resend Payment Plan: ' . $pdfError);
} else {
    try {
        $html = buildPaymentPlanPdf($studentData, $paymentTableData, $totalAmount, $totalDiscount, $totalTax, $totalPayable, $planId, $now);

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfPath = sys_get_temp_dir() . '/payment_plan_' . $planId . '_' . time() . '.pdf';
        if (file_put_contents($pdfPath, $dompdf->output()) === false) {
            throw new \RuntimeException('Could not write the PDF to the temp folder.');
        }
    } catch (\Throwable $e) {
        $pdfError = 'PDF generation failed.';
        error_log('PDF Generation Error: ' . $e->getMessage());
        $pdfPath = null;
    }
}

// Do not send an email without the PDF
if (!$pdfPath || !file_exists($pdfPath)) {
    $status->error = 'Could not generate the payment plan PDF. Please try again.';
    echo json_encode($status);
    exit;
}

// ── Email ───────────────────────────────────────────────────────────────
$mail = EmailNotificationService::build(
    EmailTemplateContent::PAYMENT_PLAN_CREATED,
    [
        'student_name'     => $studentData['first_name'] . ' ' . $studentData['last_name'],
        'payment_plan'     => $studentData['payment_plan'],
        'payment_category' => $studentData['payment_category'],
        'payment_table'    => '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-top:15px;font-size:14px;">
            <thead><tr style="background:#f5f5f5;">
                <th style="padding:10px;border:1px solid #ddd;">#</th>
                <th style="padding:10px;border:1px solid #ddd;">Payment Type</th>
                <th style="padding:10px;border:1px solid #ddd;">Due Date</th>
                <th style="padding:10px;border:1px solid #ddd;">Amount</th>
                <th style="padding:10px;border:1px solid #ddd;">Discount</th>
                <th style="padding:10px;border:1px solid #ddd;">Tax %</th>
                <th style="padding:10px;border:1px solid #ddd;">Tax Amount</th>
                <th style="padding:10px;border:1px solid #ddd;">Payable</th>
            </tr></thead><tbody>' . $paymentRows . '</tbody></table>'
    ]
);

$mailer = new PHPMailer(true);
try {
    $mailer->SMTPDebug  = 0;
    $mailer->isSMTP();
    $mailer->Host       = RPP_MAIL_HOST;
    $mailer->SMTPAuth   = true;
    $mailer->Username   = RPP_MAIL_USERNAME;
    $mailer->Password   = RPP_MAIL_PASSWORD;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mailer->Port       = RPP_MAIL_PORT;

    $mailer->setFrom(RPP_MAIL_USERNAME, Project::getBaseName());
    $fullName = $studentData['title'] . ". " . $studentData['first_name'] . ' ' . $studentData['last_name'];
    $mailer->addAddress($studentData['email'], $fullName);

    $mailer->addAttachment(
        $pdfPath,
        'Payment-Plan-' . date('Y-m-d') . '.pdf',
        PHPMailer::ENCODING_BASE64,
        'application/pdf'
    );

    $mailer->isHTML(true);
    $mailer->Subject = $mail['subject'];
    $mailer->Body    = $mail['body'];
    $mailer->AltBody = 'Your payment plan details are attached.';
    $mailer->send();

    @unlink($pdfPath);

    Database::iud("INSERT INTO `students_logs`
        (`students_has_intakes_students_has_intakes_id`,`date_updated`,`users_user_id`,`log`)
        VALUES ('" . $leadId . "','" . $now . "','" . $sessionUserObject['user_id'] . "','" . Database::escape('Payment Plan resent to ' . $studentData['email'] . ' with PDF attachment') . "')");

    $status->done = 'Payment plan was emailed to ' . $studentData['email'];
} catch (Exception $e) {
    if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);

    $log = 'Payment Plan email failed to send to ' . $studentData['email'];

    Database::iud("INSERT INTO `students_logs` 
                                                        (`students_has_intakes_students_has_intakes_id`,  `date_updated`, `users_user_id`,`log`)
                                                         VALUES ('" . $leadId . "', '" . $now . "', '" . $sessionUserObject['user_id'] . "','" . Database::escape($log) . "')");

    error_log('Resend Email Error: ' . $mailer->ErrorInfo);
    $status->error = 'Failed to send email. Please try again.';
}

echo json_encode($status);

// ═══════════════════════════════════════════════════════════════════════════
// ─ Helper Functions ─
// ═══════════════════════════════════════════════════════════════════════════

function fmtN($n, $dp = 2)
{
    return number_format((float)($n ?? 0), $dp);
}

/**
 * Build Payment Plan PDF HTML
 * Markup tax: Tax = Amount × Tax% / (100 - Tax%)
 */
function buildPaymentPlanPdf($studentData, $paymentTableData, $totalAmount, $totalDiscount, $totalTax, $totalPayable, $planId, $createdDate)
{
    $planDate = substr($createdDate, 0, 10);
    $year     = date('Y');
    $currency = $studentData['code'];

    // ── Payment schedule rows ──────────────────────────────────────────────
    $paymentRows = '';
    foreach ($paymentTableData as $payment) {
        $bg = ($payment['count'] % 2 === 0) ? '#ffffff' : '#f8fafc';
        $C  = 'padding:8px 10px;border-bottom:1px solid #f3f4f6;font-size:10px;vertical-align:top;';

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

    $studentName  = htmlspecialchars($studentData['title'] . '. ' . $studentData['first_name'] . ' ' . $studentData['last_name']);
    $studentId    = htmlspecialchars($studentData['student_id']);
    $studentEmail = htmlspecialchars($studentData['email']);
    $studentMob   = htmlspecialchars($studentData['mobile'] ?? '');
    $faculty      = htmlspecialchars($studentData['faculty']);
    $programme    = htmlspecialchars($studentData['programme']);
    $intake       = htmlspecialchars($studentData['intake']);
    $planName     = htmlspecialchars($studentData['payment_plan']);
    $category     = htmlspecialchars($studentData['payment_category']);

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payment Plan - {$studentId}</title>
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

<div style="height: 2px; background: #1e3a5f; margin-bottom: 18px;"></div>

<!-- Student & Programme Details -->
<table style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
<tr>
  <td style="width: 50%; vertical-align: top; padding-right: 10px;">
    <div style="border: 1px solid #e5e7eb; padding: 12px 14px;">
      <div style="font-size: 8px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 7px;">Student Information</div>
      <div style="font-size: 13px; font-weight: 700; color: #111827;">{$studentName}</div>
      <div style="font-size: 10px; color: #6b7280; margin-top: 4px; line-height: 1.8;">
        Student ID: {$studentId}<br>
        Email: {$studentEmail}<br>
        Mobile: {$studentMob}
      </div>
    </div>
  </td>
  <td style="width: 50%; vertical-align: top; padding-left: 10px;">
    <div style="border: 1px solid #e5e7eb; padding: 12px 14px;">
      <div style="font-size: 8px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 7px;">Programme &amp; Plan Details</div>
      <div style="font-size: 11px; font-weight: 600; color: #111827;">{$faculty}</div>
      <div style="font-size: 10px; color: #6b7280; margin-top: 3px; line-height: 1.8;">
        <strong>Programme:</strong> {$programme}<br>
        <strong>Intake:</strong> {$intake}<br>
        <strong>Plan:</strong> {$planName}<br>
        <strong>Category:</strong> {$category}
      </div>
    </div>
  </td>
</tr>
</table>

<!-- Payment Schedule Table -->
<div style="font-size: 8px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 7px;">Payment Schedule</div>
<div style="border: 1px solid #e5e7eb; margin-bottom: 18px;">
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

<!-- Notes -->
<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
<tr>
  <td style="width: 55%; vertical-align: top; padding-right: 16px;">
    <div style="font-size: 9px; color: #9ca3af; line-height: 1.8; background: #f9fafb; padding: 12px 14px; border: 1px solid #f3f4f6;">
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
<div style="position: fixed; bottom: 20px; left: 32px; right: 32px; border-top: 1px dashed #d1d5db; padding-top: 10px;">
  <div style="font-size: 9px; color: #9ca3af;">This is a computer-generated payment plan document and does not require a physical signature.</div>
  <div style="font-size: 9px; color: #6b7280; text-align: right;">Plan ID: <strong>{$planId}</strong> | Generated: {$planDate}</div>

  <div style="text-align: center; margin-top: 14px; padding-top: 10px; border-top: 1px solid #f3f4f6;">
    <span style="font-size: 9px; color: #c4c9d4; letter-spacing: .04em;">&copy;{$year} ENLIXO Uni. All Rights Reserved | Powered by
      <a href="https://enviiz.com" target="_blank" style="text-decoration:none;">
        <img src="https://dashboard.aiccampuslms.com/assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares" style="width: 40px;height: auto;">
      </a>
    </span>
  </div>
</div>

</body>
</html>
HTML;
}
