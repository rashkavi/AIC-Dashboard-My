<?php
ob_start();

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../vendor/autoload.php");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/* ================================================================
   Same currency/tax/penalty logic used across the payment reports.
   Kept identical on purpose so this export never disagrees with
   the balance-due / payment-summary numbers.
   ================================================================ */

function resolveExchangeRate(array $row, int $courseCurrencyId): ?float
{
    $paidCurrId = (int)($row['currencies_currency_id'] ?? 0);
    if (!$paidCurrId || $paidCurrId === $courseCurrencyId) {
        return null;
    }
    if (!empty($row['currency_exchange_rate']) && (float)$row['currency_exchange_rate'] > 0) {
        return (float)$row['currency_exchange_rate'];
    }
    if (!empty($row['currency_exchange_rate_id'])) {
        $rr = Database::search(
            "SELECT `rate` FROM `currency_exchange_rates`
             WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "'
             LIMIT 1"
        );
        if ($rr && $rr->num_rows > 0) {
            $r = $rr->fetch_assoc();
            if ((float)$r['rate'] > 0) return (float)$r['rate'];
        }
    }
    return null;
}

function toCourse(float $amount, ?float $rate): float
{
    if ($rate === null || $rate <= 0) return $amount;
    return $amount / $rate;
}

function convertPenaltyLkr(float $lkrAmount, int $courseCurrencyId): float
{
    static $lkrCurrencyId = null;
    if ($lkrCurrencyId === null) {
        $res = Database::search("SELECT `currency_id` FROM `currencies` WHERE `code` = 'LKR' LIMIT 1");
        $lkrCurrencyId = ($res && $res->num_rows > 0) ? (int)$res->fetch_assoc()['currency_id'] : 0;
    }
    if ($lkrCurrencyId === 0 || $courseCurrencyId == $lkrCurrencyId) {
        return $lkrAmount;
    }
    $rateRow = Database::search(
        "SELECT `rate` FROM `currency_exchange_rates`
         WHERE `base_currency_id` = '" . (int)$courseCurrencyId . "'
         ORDER BY `currency_exchange_rate_id` DESC LIMIT 1"
    );
    if ($rateRow && $rateRow->num_rows > 0) {
        $rate = (float)$rateRow->fetch_assoc()['rate'];
        if ($rate > 0) return $lkrAmount / $rate;
    }
    return $lkrAmount;
}

/* Discount / penalty / tax / settled totals for ONE course_payment (installment),
   summed across its receipts — same shape as the per-cp logic on the
   balance-due and payment-summary reports, just scoped to a single cp id. */
function getInstallmentSummary(int $cpId, int $courseCurrencyId): array
{
    $discounts = 0;
    $penalties = 0;
    $tax = 0;
    $settled = 0;

    $res = Database::search("
        SELECT paid_amount, discount_amount, panelty_amount, tax_amount,
               currencies_currency_id, currency_exchange_rate,
               currency_exchange_rate_id, payment_status_payment_status_id
        FROM course_payment_has_payments
        WHERE course_payments_course_payments_id = '" . $cpId . "'
        AND payment_status_payment_status_id IN (2, 3)
    ");
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $rate = resolveExchangeRate($r, $courseCurrencyId);
            $discounts += toCourse((float)$r['discount_amount'], $rate);
            $penalties += toCourse((float)$r['panelty_amount'], $rate);

            if ((int)$r['payment_status_payment_status_id'] === 3) {
                $settled += toCourse((float)$r['paid_amount'], $rate);
                $tax     += toCourse((float)$r['tax_amount'], $rate);
            }
        }
    }

    $penaltyLkrConverted = 0;
    $pr = Database::search("
        SELECT SUM(amount) AS total_lkr_penalty
        FROM panelty_payments
        WHERE course_payments_course_payments_id = '" . $cpId . "'
    ");
    if ($pr && $pr->num_rows > 0) {
        $prRow = $pr->fetch_assoc();
        if (!empty($prRow['total_lkr_penalty'])) {
            $penaltyLkrConverted = convertPenaltyLkr((float)$prRow['total_lkr_penalty'], $courseCurrencyId);
        }
    }

    return [
        'discounts' => $discounts,
        'penalties' => $penalties + $penaltyLkrConverted,
        'tax'       => $tax,
        'settled'   => $settled,
    ];
}

try {
    if (!SessionUser::hasValidSession()) {
        throw new Exception("Authentication Failed!");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        throw new Exception("Invalid request method");
    }

    if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] != 'application/json') {
        http_response_code(400);
        throw new Exception("Invalid JSON");
    }

    $sessionUserObject = SessionUser::getCurrentUserObject();
    $content = trim(file_get_contents('php://input'));
    $decoded = json_decode($content, true);

    if (json_last_error() != JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Invalid JSON");
    }

    $text      = isset($decoded['text']) ? Database::escape($decoded['text']) : "";
    $branch    = isset($decoded['branch']) ? Database::escape($decoded['branch']) : "";
    $faculty   = isset($decoded['faculty']) ? Database::escape($decoded['faculty']) : "";
    $programme = isset($decoded['programme']) ? Database::escape($decoded['programme']) : "";
    $intake    = isset($decoded['intake']) ? Database::escape($decoded['intake']) : "";
    $sort      = isset($decoded['sort']) ? Database::escape($decoded['sort']) : ""; // active/inactive, matches balance-due report's "sort"
    $filter    = isset($decoded['filter']) ? Database::escape($decoded['filter']) : "";
    $from      = isset($decoded['from']) ? Database::escape($decoded['from']) : "";
    $to        = isset($decoded['to']) ? Database::escape($decoded['to']) : "";
    $status    = isset($decoded['status']) ? Database::escape($decoded['status']) : "";

    date_default_timezone_set('Asia/Colombo');

    $spreadsheet = IOFactory::load("../assets/uploads/reports/students-outstanding-balances-report.xlsx");
    $sheet = $spreadsheet->getActiveSheet();

    /*
        Layout (matches the uploaded template):
        - Header info rows 1-9, headers on row 12, data from row 13.
        - B Student Intake ID, C Student Name, D Email, E Mobile, F WhatsApp,
          G Intake Detail, H Status, I Payment Category, J Payment Type,
          K Currency, L Scheduled Amount, M Discount, N Penalty, O Tax,
          P Payable Amount, Q Settled Amount.
        - R Outstanding Balance: NOT in the uploaded template — added here
          since it's the point of the report. Remove the two R-column
          lines below if you'd rather compute it separately.
    */
    $sheet->setCellValue("R12", "Outstanding Balance");

    $query = "
        SELECT
            cp.course_payments_id,
            cp.amount,
            cp.discount,
            cp.discount_type_discount_type_id,
            cp.due_date,

            pt.payment_type,
            pc.payment_category,

            c.code,
            c.decimal_places,
            c.currency_id AS course_currency_id,

            shi.student_id AS shiStudentId,
            shi.status_status_id,

            s.mobile,
            s.student_id AS aId,

            ld.first_name,
            ld.last_name,
            ld.email,
            ld.whatsapp,
            t.title,

            i.intake,

            st.status

        FROM course_payments AS cp

        INNER JOIN payment_type AS pt
            ON cp.payment_type_payment_type_id = pt.payment_type_id

        INNER JOIN payment_plan_has_students_has_intakes AS phsi
            ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

        INNER JOIN payment_category AS pc
            ON phsi.payment_category_payment_category_id = pc.payment_category_id

        INNER JOIN currencies AS c
            ON pc.currencies_currency_id = c.currency_id

        INNER JOIN students_has_intakes AS shi
            ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

        INNER JOIN students AS s
            ON shi.students_student_id = s.student_id

        INNER JOIN login_details AS ld
            ON s.login_details_login_details_id = ld.login_details_id

        INNER JOIN title AS t
            ON s.title_title_id = t.title_id

        INNER JOIN intakes AS i
            ON shi.intakes_intake_id = i.intake_id

        INNER JOIN programmes AS prog
            ON i.programmes_programme_id = prog.programme_id

        INNER JOIN faculties AS fac
            ON prog.faculties_faculty_id = fac.faculty_id

        INNER JOIN status AS st
            ON shi.status_status_id = st.status_id

        WHERE cp.payment_status_payment_status_id != 5
    ";

    if (!empty($text)) {
        $query .= " AND (
            s.student_id LIKE '%$text%'
            OR ld.first_name LIKE '%$text%'
            OR ld.last_name LIKE '%$text%'
            OR ld.email LIKE '%$text%'
            OR s.mobile LIKE '%$text%'
        ) ";
    }
    if (!empty($sort)) {
        $query .= " AND shi.status_status_id = '$sort' ";
        $sheet->setCellValue("D9", $sort == 1 ? "Active" : "Inactive");
    } else {
        $sheet->setCellValue("D9", "All Student");
    }
    // TODO: confirm faculties.branches_branch_id is the real FK to branches
    if (!empty($branch) && $branch != '0') {
        $query .= " AND fac.branches_branch_id = '$branch' ";
    }
    if (!empty($faculty) && $faculty != '0') {
        $query .= " AND fac.faculty_id = '$faculty' ";
    }
    if (!empty($programme) && $programme != '0') {
        $query .= " AND prog.programme_id = '$programme' ";
    }
    if (!empty($intake) && $intake != '0') {
        $query .= " AND i.intake_id = '$intake' ";
    }
    if (!empty($status) && $status != '0') {
        $query .= " AND EXISTS (
            SELECT 1 FROM course_payment_has_payments cpp2
            WHERE cpp2.course_payments_course_payments_id = cp.course_payments_id
            AND cpp2.payment_status_payment_status_id = '$status'
        ) ";
        $sheet->setCellValue("D8", "Filtered");
    } else {
        $sheet->setCellValue("D8", "All");
    }

    $periodLabel = "All";
    if (!empty($filter) && $filter == '1') {
        $query .= " AND MONTH(cp.due_date) = MONTH(CURDATE())
                AND YEAR(cp.due_date) = YEAR(CURDATE())";
        $periodLabel = "This Month";
    } elseif (!empty($filter) && $filter == '2') {
        $query .= " AND MONTH(cp.due_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND YEAR(cp.due_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $periodLabel = "Last Month";
    } elseif (!empty($filter) && $filter == '3') {
        if (!empty($from)) {
            $query .= " AND DATE(cp.due_date) >= '$from' ";
        }
        if (!empty($to)) {
            $query .= " AND DATE(cp.due_date) <= '$to' ";
        }
        $periodLabel = "Custom Range" . ($from !== "" ? " : From " . $from : "") . ($to !== "" ? " - To " . $to : "");
    }
    $sheet->setCellValue("D6", $periodLabel);
    $sheet->setCellValue("D7", "All");

    $query .= " ORDER BY cp.course_payments_id DESC ";

    $results = Database::search($query);

    $headerRow = 12;
    $row = $headerRow + 1;

    $totalStudents = 0;
    $activeStudents = 0;
    $inactiveStudents = 0;
    $seenStudentIds = [];

    $sumScheduled = 0;
    $sumDiscount = 0;
    $sumPenalty = 0;
    $sumTax = 0;
    $sumPayable = 0;
    $sumSettled = 0;
    $sumOutstanding = 0;

    while ($data = $results->fetch_assoc()) {

        $cpDiscount = 0.0;
        if ($data['discount_type_discount_type_id'] == 3) {
            $cpDiscount = (float)$data['amount'] / 100 * (float)$data['discount'];
        } elseif ($data['discount_type_discount_type_id'] == 2) {
            $cpDiscount = (float)$data['discount'];
        }

        $summary = getInstallmentSummary((int)$data['course_payments_id'], (int)$data['course_currency_id']);

        $totalDiscount = $cpDiscount + $summary['discounts'];
        $totalPenalty  = $summary['penalties'];
        $totalTax      = $summary['tax'];
        $settled       = $summary['settled'];

        $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $totalTax;
        $outstandingBalance = $payableAmount - $settled;
        if ($outstandingBalance < 0) $outstandingBalance = 0;

        // Outstanding balances report: skip installments that are fully settled
        if ($outstandingBalance <= 0.009) {
            continue;
        }

        $studentName = trim($data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name']);

        if (!isset($seenStudentIds[$data['aId']])) {
            $seenStudentIds[$data['aId']] = true;
            $totalStudents++;
            if ((int)$data['status_status_id'] === 1 || strtolower($data['status']) === 'active') {
                $activeStudents++;
            } else {
                $inactiveStudents++;
            }
        }

        $sheet->setCellValue("B$row", $data['shiStudentId']);
        $sheet->setCellValue("C$row", $studentName);
        $sheet->setCellValue("D$row", $data['email']);
        $sheet->setCellValue("E$row", $data['mobile']);
        $sheet->setCellValue("F$row", $data['whatsapp']);
        $sheet->setCellValue("G$row", $data['intake']);
        $sheet->setCellValue("H$row", $data['status']);
        $sheet->setCellValue("I$row", $data['payment_category']);
        $sheet->setCellValue("J$row", $data['payment_type']);
        $sheet->setCellValue("K$row", $data['code']);
        $sheet->setCellValue("L$row", round((float)$data['amount'], 2));
        $sheet->setCellValue("M$row", round($totalDiscount, 2));
        $sheet->setCellValue("N$row", round($totalPenalty, 2));
        $sheet->setCellValue("O$row", round($totalTax, 2));
        $sheet->setCellValue("P$row", round($payableAmount, 2));
        $sheet->setCellValue("Q$row", round($settled, 2));
        $sheet->setCellValue("R$row", "=+P$row-Q$row");

        $sheet->getStyle("B$row:R$row")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sumScheduled   += (float)$data['amount'];
        $sumDiscount    += $totalDiscount;
        $sumPenalty     += $totalPenalty;
        $sumTax         += $totalTax;
        $sumPayable     += $payableAmount;
        $sumSettled     += $settled;
        $sumOutstanding += $outstandingBalance;

        $row++;
    }

    $lastDataRow = $row - 1;

    $userData = Database::search("SELECT * FROM `users`
        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
        WHERE `user_id`='" . $sessionUserObject['user_id'] . "'")->fetch_assoc();

    $sheet->setCellValue("C3", $userData['title'] . " " . $userData['user_fname'] . " " . $userData['user_lname']);
    $sheet->setCellValue("C4", date("Y-m-d h:i A"));

    if ($lastDataRow >= $headerRow + 1) {
        $totalRow = $lastDataRow + 1;

        $sheet->mergeCells("B{$totalRow}:K{$totalRow}");
        $sheet->setCellValue("B{$totalRow}", "Total");

        $sheet->setCellValue("L{$totalRow}", round($sumScheduled, 2));
        $sheet->setCellValue("M{$totalRow}", round($sumDiscount, 2));
        $sheet->setCellValue("N{$totalRow}", round($sumPenalty, 2));
        $sheet->setCellValue("O{$totalRow}", round($sumTax, 2));
        $sheet->setCellValue("P{$totalRow}", round($sumPayable, 2));
        $sheet->setCellValue("Q{$totalRow}", round($sumSettled, 2));
        $sheet->setCellValue("R{$totalRow}", "=+P{$totalRow}-Q{$totalRow}");

        $sheet->getStyle("B{$totalRow}:R{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$totalRow}:R{$totalRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $statsRow = $totalRow + 2;
        $sheet->setCellValue(
            "B{$statsRow}",
            "Total No of Students : " . str_pad($totalStudents, 2, '0', STR_PAD_LEFT)
                . ",   Active Students : " . str_pad($activeStudents, 2, '0', STR_PAD_LEFT)
                . ",    Inactive Students : " . str_pad($inactiveStudents, 2, '0', STR_PAD_LEFT) . ",  "
        );
        $sheet->getStyle("B{$statsRow}")->getFont()->setBold(true);
    }

    $fileName = "students_outstanding_balances_report_" . date('Ymd_His') . ".xlsx";

    ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
    echo json_encode([
        "error" => $e->getMessage()
    ]);
    exit;
}
