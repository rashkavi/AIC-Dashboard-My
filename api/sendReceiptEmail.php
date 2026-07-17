<?php

/**
 * api/sendReceiptEmail.php - v9
 * Logo fix: replaced text-based AIC/CAMPUS block with actual logo image
 */

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');
require_once('../includes/phpmailer/EmailLayoutRenderer.php');
require_once('../includes/phpmailer/EmailNotificationService.php');
require_once('../includes/phpmailer/EmailTemplateContent.php');
require_once('../includes/phpmailer/mail-process.php');

header('Content-Type: application/json');
$status = new stdClass();

if (!SessionUser::hasValidSession()) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();

if ($sessionUserRole == null) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(
    SessionUser::ifUserIsPro($sessionUserRole) ||
    UserPermissions::validPermisisonType('payments')
)) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// ── Parse JSON body ────────────────────────────────────────────────────────
$body       = json_decode(file_get_contents('php://input'), true);
$receipt_id = isset($body['receipt_id']) ? (int)$body['receipt_id'] : 0;

if ($receipt_id <= 0) {
    echo json_encode(['error' => 'Invalid receipt ID']);
    exit;
}

// ── Fetch receipt data ─────────────────────────────────────────────────────
$receiptRow = Database::search("
    SELECT
        cpr.course_payment_receipt_id,
        cpr.receipt_number,
        cpr.date_added,
        cpr.students_has_intakes_students_has_intakes_id,
        u.user_fname, u.user_lname,
        t2.title AS user_title
    FROM course_payment_receipts cpr
    LEFT JOIN users u  ON cpr.users_user_id  = u.user_id
    LEFT JOIN title t2 ON u.title_title_id   = t2.title_id
    WHERE cpr.course_payment_receipt_id = '" . $receipt_id . "'
    LIMIT 1
");

if (!$receiptRow || $receiptRow->num_rows == 0) {
    echo json_encode(['error' => 'Receipt not found']);
    exit;
}
$receipt = $receiptRow->fetch_assoc();
$shi_id  = (int)$receipt['students_has_intakes_students_has_intakes_id'];

// ── Fetch student + programme + intake + faculty + branch ──────────────────
$studentRow = Database::search("
    SELECT
        s.student_id,
        ld.first_name, ld.last_name, ld.email,
        s.mobile,
        t.title AS student_title,
        i.intake_id, i.intake,
        p.programme_id, p.programme,
        f.faculty_id, f.faculty,
        b.branch_id, b.branch

    FROM students_has_intakes shi
    INNER JOIN students      s   ON shi.students_student_id          = s.student_id
    INNER JOIN login_details ld  ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN title         t   ON s.title_title_id                 = t.title_id
    INNER JOIN intakes       i   ON shi.intakes_intake_id            = i.intake_id
    INNER JOIN programmes    p   ON i.programmes_programme_id        = p.programme_id
    INNER JOIN faculties     f   ON p.faculties_faculty_id           = f.faculty_id
    INNER JOIN branches      b   ON f.branches_branch_id             = b.branch_id
    WHERE shi.students_has_intakes_id = '" . $shi_id . "'
    LIMIT 1
");

if (!$studentRow || $studentRow->num_rows == 0) {
    echo json_encode(['error' => 'Student not found']);
    exit;
}
$student = $studentRow->fetch_assoc();

// ── Fetch all payment rows linked to this receipt ─────────────────────────
$paymentsResult = Database::search("
    SELECT
        cpp.paid_amount, cpp.discount_amount, cpp.panelty_amount,
        cpp.tax_rate,    cpp.tax_amount,      cpp.date_paid,
        cpp.currencies_currency_id,
        cpp.currency_exchange_rate,
        cpp.currency_exchange_rate_id,
        cur.code          AS paid_currency,
        pm.payment_method,
        cp.refference     AS reference,
        cp.amount         AS cp_amount,
        pgp.payment_type,
        c_course.code     AS course_currency,
        c_course.currency_id AS course_currency_id
    FROM course_payment_has_payments cpp
    INNER JOIN payment_method pm   ON cpp.payment_method_payment_method_id  = pm.payment_method_id
    INNER JOIN course_payments cp  ON cpp.course_payments_course_payments_id = cp.course_payments_id
    INNER JOIN payment_type pgp    ON cp.payment_type_payment_type_id        = pgp.payment_type_id
    INNER JOIN payment_plan_has_students_has_intakes phsi
                                   ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category pc ON phsi.payment_category_payment_category_id  = pc.payment_category_id
    INNER JOIN currencies c_course ON pc.currencies_currency_id                  = c_course.currency_id
    LEFT JOIN currencies cur       ON cpp.currencies_currency_id = cur.currency_id
    WHERE cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
    ORDER BY cpp.course_payment_has_payment_id ASC
");

// ── Helper functions ────────────────────────────────────────────────────────
function resolveRate($row, $courseCurrId)
{
    if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrId) return null;
    if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) return (float)$row['currency_exchange_rate'];
    if (!empty($row['currency_exchange_rate_id'])) {
        $r = Database::search("SELECT rate FROM currency_exchange_rates WHERE currency_exchange_rate_id='" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $rd = $r->fetch_assoc();
            if (!empty($rd['rate']) && $rd['rate'] > 0) return (float)$rd['rate'];
        }
    }
    return null;
}

function toCourse($amount, $rate)
{
    if ($rate === null || $rate <= 0) return (float)$amount;
    return (float)$amount / $rate;
}

function fmtN($n, $dp = 2)
{
    return number_format((float)($n ?? 0), $dp);
}

function esc($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ── Calculate totals ──────────────────────────────────────────────────────
$rows            = [];
$cc              = 'LKR';
$courseCurrId    = 1;
$grandPaid       = 0;
$grandDiscount   = 0;
$grandPenalty    = 0;
$grandTax        = 0;
$grandAmount     = 0;

if ($paymentsResult && $paymentsResult->num_rows > 0) {
    while ($row = $paymentsResult->fetch_assoc()) {
        $cc           = $row['course_currency'];
        $courseCurrId = (int)$row['course_currency_id'];
        $rate         = resolveRate($row, $courseCurrId);
        $paidInCourse = toCourse($row['paid_amount'], $rate);
        $taxInCourse  = toCourse((float)($row['tax_amount'] ?? 0), $rate);
        $penInCourse  = toCourse((float)($row['panelty_amount'] ?? 0), $rate);
        $discInCourse = toCourse((float)($row['discount_amount'] ?? 0), $rate);

        $rows[] = [
            'reference'       => $row['reference'],
            'payment_type'    => $row['payment_type'],
            'date_paid'       => $row['date_paid'] ? substr($row['date_paid'], 0, 10) : '',
            'payment_method'  => $row['payment_method'],
            'cp_amount'       => (float)$row['cp_amount'],
            'paid_amount_raw' => (float)$row['paid_amount'],
            'paid_in_course'  => $paidInCourse,
            'paid_currency'   => $row['paid_currency'] ?: $cc,
            'exchange_rate'   => $rate,
            'tax_rate'        => (float)($row['tax_rate'] ?? 0),
            'tax_amount_raw'  => (float)($row['tax_amount'] ?? 0),
            'tax_in_course'   => $taxInCourse,
            'discount_course' => $discInCourse,
            'penalty_course'  => $penInCourse,
        ];

        $grandAmount   += (float)$row['cp_amount'];
        $grandDiscount += $discInCourse;
        $grandPenalty  += $penInCourse;
        $grandTax      += $taxInCourse;
        $grandPaid     += $paidInCourse;
    }
}

$courseFeeActuallyPaid = max(0, $grandPaid - $grandTax);
$isPaid = ($courseFeeActuallyPaid >= $grandAmount - 0.005);

$paymentDate = substr($receipt['date_added'], 0, 10);

// ── Generate PDF HTML ─────────────────────────────────────────────────────
$receiptHtml = buildReceiptPdf(
    $receipt,
    $student,
    $rows,
    $cc,
    $grandAmount,
    $grandDiscount,
    $grandPenalty,
    $grandTax,
    $grandPaid,
    $courseFeeActuallyPaid,
    $isPaid
);

// ── Generate PDF from HTML ─────────────────────────────────────────────────
$pdfPath = null;
$usePdf  = false;

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

if ($usePdf && class_exists('Dompdf\Dompdf')) {
    try {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($receiptHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $pdfPath   = sys_get_temp_dir() . '/receipt_' . $receipt_id . '_' . time() . '.pdf';
        file_put_contents($pdfPath, $pdfOutput);
    } catch (Exception $e) {
        echo json_encode(['error' => 'PDF Error', 'message' => $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['error' => 'PDF generation library not found']);
    exit;
}

// ── Build email ────────────────────────────────────────────────────────────
$studentName = $student['student_title'] . '. ' . $student['first_name'] . ' ' . $student['last_name'];
$toEmail     = $student['email'];

$emailData = [
    'student_name'   => $studentName,
    'receipt_number' => $receipt['receipt_number'],
    'programme'      => $student['programme'],
    'intake'         => $student['intake'],
    'total_paid'     => $cc . ' ' . fmtN($grandPaid),
    'balance_due'    => $isPaid ? 'FULLY PAID' : $cc . ' ' . fmtN(max(0, $grandAmount - $courseFeeActuallyPaid)),
    'payment_date'   => $paymentDate,
];

$emailContent = EmailNotificationService::build(
    EmailTemplateContent::RECEIPT_EMAIL,
    $emailData
);

// ── Send email via PHPMailer ───────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../includes/phpmailer/src/Exception.php';
require_once '../includes/phpmailer/src/PHPMailer.php';
require_once '../includes/phpmailer/src/SMTP.php';

try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug  = 0;
    $mail->isSMTP();
    $mail->Host       = 'smtp.titan.email';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'no-reply@aiccampuslms.com';
    $mail->Password   = 'AicLMS#@!2026Campus';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('no-reply@aiccampuslms.com', Project::getBaseName());
    $mail->addAddress($toEmail, $studentName);

    if ($usePdf && $pdfPath && file_exists($pdfPath)) {
        $mail->addAttachment(
            $pdfPath,
            'Receipt-' . $receipt['receipt_number'] . '.pdf',
            PHPMailer::ENCODING_BASE64,
            'application/pdf'
        );
    }

    $mail->isHTML(true);
    $mail->Subject = $emailContent['subject'];
    $mail->Body    = $emailContent['body'];
    $mail->AltBody = 'Dear ' . $studentName . ', your payment receipt ' . $receipt['receipt_number'] . ' is attached. Total Paid: ' . $cc . ' ' . fmtN($grandPaid);

    $mail->send();

    if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);

    $timezone = new DateTimeZone("Asia/Colombo");
    $now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');
    Database::iud("
        INSERT INTO payment_logs
            (course_payments_course_payments_id, users_user_id, date_updated, log)
        SELECT
            cpp.course_payments_course_payments_id,
            '" . (int)$sessionUserObject['user_id'] . "',
            '" . $now . "',
            'Invoice email sent to " . Database::escape($toEmail) . " — Receipt " . Database::escape($receipt['receipt_number']) . " with PDF attachment'
        FROM course_payment_has_payments cpp
        WHERE cpp.course_payment_receipts_course_payment_receipt_id = '" . $receipt_id . "'
        LIMIT 1
    ");

    echo json_encode(['done' => 'Invoice email sent successfully to ' . $toEmail . ' with PDF attachment']);
} catch (Exception $e) {
    if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);
    echo json_encode(['error' => 'Failed to send email: ' . $mail->ErrorInfo]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// BUILD RECEIPT PDF
// ═══════════════════════════════════════════════════════════════════════════
function buildReceiptPdf(
    $receipt,
    $student,
    $rows,
    $cc,
    $grandAmount,
    $grandDiscount,
    $grandPenalty,
    $grandTax,
    $grandPaid,
    $courseFeeActuallyPaid,
    $isPaid
) {
    $recDate = substr($receipt['date_added'], 0, 10);
    $recNum  = esc($receipt['receipt_number']);

    // ── Build payment rows ─────────────────────────────────────────────────
    $payRows = '';
    $idx     = 0;

    foreach ($rows as $p) {
        $bg  = ($idx % 2 === 0) ? '#ffffff' : '#f8fafc';
        $idx++;

        $paidMain = ($p['exchange_rate'] && $p['paid_currency'] !== $cc)
            ? esc($p['paid_currency']) . ' ' . fmtN($p['paid_amount_raw'])
            . '<div style="font-size:9px;color:#6b7280;margin-top:2px;">= ' . $cc . ' ' . fmtN($p['paid_in_course']) . '</div>'
            : $cc . ' ' . fmtN($p['paid_in_course']);

        $taxMain     = $p['tax_in_course']   > 0 ? $cc . ' ' . fmtN($p['tax_in_course'])   : '<span style="color:#d1d5db;">—</span>';
        $discMain    = $p['discount_course'] > 0 ? $cc . ' ' . fmtN($p['discount_course']) : '<span style="color:#d1d5db;">—</span>';
        $penMain     = $p['penalty_course']  > 0 ? $cc . ' ' . fmtN($p['penalty_course'])  : '<span style="color:#d1d5db;">—</span>';
        $taxRateDisp = $p['tax_rate']        > 0 ? fmtN($p['tax_rate']) . '%'              : '<span style="color:#d1d5db;">—</span>';

        $C = 'padding:7px 9px;border-bottom:1px solid #f3f4f6;font-size:10px;vertical-align:top;';

        $payRows .= '<tr style="background:' . $bg . ';">'
            . '<td style="' . $C . 'color:#9ca3af;width:22px;">' . $idx . '</td>'
            . '<td style="' . $C . 'font-weight:700;color:#1e3a5f;">'     . esc($p['reference'])      . '</td>'
            . '<td style="' . $C . 'color:#6b7280;">'                     . esc($p['payment_type'])   . '</td>'
            . '<td style="' . $C . 'white-space:nowrap;color:#6b7280;">'  . $p['date_paid']           . '</td>'
            . '<td style="' . $C . 'color:#6b7280;">'                     . esc($p['payment_method']) . '</td>'
            . '<td style="' . $C . 'text-align:right;">'                  . $cc . ' ' . fmtN($p['cp_amount']) . '</td>'
            . '<td style="' . $C . 'text-align:right;">'                  . $discMain    . '</td>'
            . '<td style="' . $C . 'text-align:right;">'                  . $penMain     . '</td>'
            . '<td style="' . $C . 'text-align:center;">'                 . $taxRateDisp . '</td>'
            . '<td style="' . $C . 'text-align:right;">'                  . $taxMain     . '</td>'
            . '<td style="' . $C . 'text-align:right;font-weight:700;color:#111827;">' . $paidMain . '</td>'
            . '</tr>';
    }

    if (!$payRows) {
        $payRows = '<tr><td colspan="11" style="text-align:center;padding:24px;color:#9ca3af;font-size:10px;">No payment records found</td></tr>';
    }

    // ── Summary rows ──────────────────────────────────────────────────────
    $RS = 'padding:8px 13px;font-size:11px;border-bottom:1px solid #f3f4f6;';
    $LS = $RS . 'color:#374151;font-weight:500;';
    $VS = $RS . 'color:#111827;font-weight:600;text-align:right;';

    $discDisplay = $grandDiscount > 0.005 ? $cc . ' ' . fmtN($grandDiscount) : '<span style="color:#d1d5db;">—</span>';
    $penDisplay  = $grandPenalty  > 0.005 ? $cc . ' ' . fmtN($grandPenalty)  : '<span style="color:#d1d5db;">—</span>';
    $taxDisplay  = $grandTax      > 0.005 ? $cc . ' ' . fmtN($grandTax)      : '<span style="color:#d1d5db;">—</span>';

    $summaryRows =
        // 1 — Total Course Fee
        '<tr>'
        . '<td style="' . $LS . '">Total Course Fee</td>'
        . '<td style="' . $VS . '">' . $cc . ' ' . fmtN($courseFeeActuallyPaid) . '</td>'
        . '</tr>'
        // 2 — Discount
        . '<tr>'
        . '<td style="' . $LS . '">Total Discount</td>'
        . '<td style="' . $VS . '">' . $discDisplay . '</td>'
        . '</tr>'
        // 3 — Penalty
        . '<tr>'
        . '<td style="' . $LS . '">Total Penalty</td>'
        . '<td style="' . $VS . '">' . $penDisplay . '</td>'
        . '</tr>'
        // 4 — Tax
        . '<tr>'
        . '<td style="' . $LS . '">Total Tax</td>'
        . '<td style="' . $VS . '">' . $taxDisplay . '</td>'
        . '</tr>'
        // 5 — TOTAL PAID
        . '<tr style="background:#1e3a5f;">'
        . '<td style="padding:10px 13px;font-size:12px;font-weight:700;color:#fff;">TOTAL PAID</td>'
        . '<td style="padding:10px 13px;font-size:13px;font-weight:800;color:#fff;text-align:right;">' . $cc . ' ' . fmtN($grandPaid) . '</td>'
        . '</tr>';

    $TH = 'padding:8px 9px;font-size:9px;font-weight:600;color:rgba(255,255,255,.85);letter-spacing:.04em;';

    $studentName = esc($student['student_title'] . '. ' . $student['first_name'] . ' ' . $student['last_name']);
    $processedBy = esc($receipt['user_title'] . '. ' . $receipt['user_fname'] . ' ' . $receipt['user_lname']);

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payment Receipt {$recNum}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Segoe UI", system-ui, -apple-system, sans-serif; font-size: 11px; line-height: 1.5; color: #111827; background: #fff; }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { size: A4 portrait; margin: 8mm 10mm; }
  }
  table { page-break-inside: auto; }
  tr { page-break-inside: avoid; }
</style>
</head>
<body>

<!-- ════ HEADER BAND ════ -->
<div style="padding:20px 28px;">
<table style="width:100%;border-collapse:collapse;"><tr>

<!-- Left — logo + branch -->
<td style="vertical-align:top;width:55%;">
  <img src="https://dashboard.aiccampuslms.com/assets/imgs/common/logo.png"
       alt="AIC Campus"
       style="height:44px;width:auto;display:block;margin-bottom:8px;">
  <div style="font-size:8px;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.12em;margin-bottom:4px;">Learning Management System</div>
  <div style="font-size:9px;color:rgba(255,255,255,.55);line-height:1.8;">
    No.154, Havelock Road, Thimbirigasyaya, Colombo 05<br>
    Tel: +94 114 325 864<br>
    Email: accounts@aicedu.lk
  </div>
</td>

<!-- Right — receipt ID -->
<td style="vertical-align:top;text-align:right;">
  <div style="font-size:8px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px;">Official Payment Receipt</div>
  <div style="font-size:24px;font-weight:800;color:#fff;letter-spacing:-.5px;margin-bottom:4px;">{$recNum}</div>
  <div style="font-size:9px;color:rgba(255,255,255,.6);margin-bottom:8px;">Date: {$recDate}</div>
  <span style="display:inline-block;border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:4px 12px;font-size:9px;font-weight:700;color:rgba(255,255,255,.8);letter-spacing:.05em;">APPROVED</span>
</td>
</tr></table>
</div>

<!-- ════ BODY ════ -->
<div>

<!-- Bill To + Programme -->
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;"><tr>
<td style="width:50%;vertical-align:top;padding-right:10px;">
  <div style="border:1px solid #e5e7eb;padding:12px 14px;background:#fafafa;">
    <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;">Bill To</div>
    <div style="font-size:13px;font-weight:700;color:#111827;">{$studentName}</div>
    <div style="font-size:10px;color:#6b7280;margin-top:4px;line-height:1.9;">
      ID: {$student['student_id']}<br>{$student['email']}<br>{$student['mobile']}
    </div>
  </div>
</td>
<td style="width:50%;vertical-align:top;padding-left:10px;">
  <div style="border:1px solid #e5e7eb;padding:12px 14px;background:#fafafa;">
    <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;">Programme Details</div>
    <div style="font-size:11px;font-weight:700;color:#111827;">{$student['faculty']}</div>
    <div style="font-size:10px;color:#6b7280;margin-top:3px;line-height:1.9;">{$student['programme']}<br>{$student['intake']}</div>
    <div style="font-size:9px;color:#9ca3af;margin-top:7px;padding-top:7px;border-top:1px solid #f3f4f6;">Processed by: {$processedBy}</div>
  </div>
</td>
</tr></table>

<!-- Section label -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
  <span style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.12em;white-space:nowrap;">Payment Records</span>
  <div style="flex:1;height:1px;background:#f3f4f6;"></div>
</div>

<!-- Payment table -->
<div style="border:1px solid #e5e7eb;overflow:hidden;margin-bottom:20px;">
<table style="width:100%;border-collapse:collapse;min-width:640px;">
<thead><tr style="background:#1e3a5f;">
  <th style="{$TH}text-align:left;">#</th>
  <th style="{$TH}text-align:left;">Reference</th>
  <th style="{$TH}text-align:left;">Type</th>
  <th style="{$TH}text-align:left;">Date</th>
  <th style="{$TH}text-align:left;">Method</th>
  <th style="{$TH}text-align:right;">Schedule Amt</th>
  <th style="{$TH}text-align:right;">Discount</th>
  <th style="{$TH}text-align:right;">Penalty</th>
  <th style="{$TH}text-align:center;">Tax %</th>
  <th style="{$TH}text-align:right;">Tax Amt</th>
  <th style="{$TH}text-align:right;">Paid</th>
</tr></thead>
<tbody>{$payRows}</tbody>
</table>
</div>

<!-- Summary + Notes -->
<table style="width:100%;border-collapse:collapse;margin-bottom:22px;"><tr>

<!-- Left — notes -->
<td style="width:50%;vertical-align:top;padding-right:16px;">
  <div style="font-size:9px;color:#9ca3af;line-height:1.95;margin-top:2px;">
    &#8226; Payments made will not be refundable or transferable under any circumstances.<br>
    &#8226; This receipt is valid subject to realisation of cheque.<br>
    &#8226; Taxes or bank charges may be applied where required.<br>
    &#8226; Only penalties added on or before the payment date are included in this receipt.
  </div>
</td>

<!-- Right — totals -->
<td style="vertical-align:top;">
  <table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;overflow:hidden;">
    {$summaryRows}
  </table>
</td>
</tr></table>

</div><!-- /body -->

<!-- ════ FOOTER ════ -->
<div style="border-top:1px dashed #e5e7eb;padding:10px 28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;background:#fafafa;">
  <div style="font-size:9px;color:#9ca3af;">This is a computer-generated receipt and does not require a physical signature.</div>
  <div style="font-size:9px;color:#6b7280;text-align:right;">
    Receipt No: <strong style="color:#374151;">{$recNum}</strong> &nbsp;|&nbsp; Generated: {$recDate}
  </div>
</div>

<div style="text-align:center;padding:8px;border-top:1px solid #f3f4f6;background:#fff;">
  <span style="font-size:8px;color:#d1d5db;letter-spacing:.06em;">ENLIXO LMS 2026 &copy; — All rights reserved</span>
</div>

</body>
</html>
HTML;
}
