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
   Same due/penalty calculation as the outstanding-balances export
   and due-report.php. Duplicated here on purpose so this export
   never disagrees with the on-screen report.

   PENALTY COLUMN NOTE (fixed): the Penalty column — both per-row
   and totalled — must mirror due-report.php exactly, which shows
   $summary['penalty_lkr'] (raw SUM from the panelty_payments LOG
   table, always LKR). It is NOT the same as $summary['penalties']
   (the receipt-level panelty_amount already folded into the Due
   Amount in course currency). Those two numbers are intentionally
   unrelated — do not swap them.
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
            /* Due Amount's penalty component: ONLY the receipt-level
               panelty_amount (course currency). panelty_payments LOG
               table does NOT feed into this — see penalty_lkr below. */
            $penalties += toCourse((float)$r['panelty_amount'], $rate);
            $tax += toCourse((float)$r['tax_amount'], $rate);

            if ((int)$r['payment_status_payment_status_id'] === 3) {
                $settled += toCourse((float)$r['paid_amount'], $rate);
            }
        }
    }

    /* penalty_lkr: raw SUM from panelty_payments, shown as-is in the
       Penalty column — always LKR, no conversion, NOT part of the
       Due Amount calculation above. This is what the Penalty column
       must display, matching due-report.php exactly. */
    $penaltyLkr = 0;
    $pr = Database::search("
        SELECT SUM(amount) AS total_lkr_penalty
        FROM panelty_payments
        WHERE course_payments_course_payments_id = '" . $cpId . "'
    ");
    if ($pr && $pr->num_rows > 0) {
        $prRow = $pr->fetch_assoc();
        if (!empty($prRow['total_lkr_penalty'])) {
            $penaltyLkr = (float)$prRow['total_lkr_penalty'];
        }
    }

    return [
        'discounts'   => $discounts,
        'penalties'   => $penalties,
        'tax'         => $tax,
        'settled'     => $settled,
        'penalty_lkr' => $penaltyLkr,
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

    $text       = isset($decoded['text']) ? Database::escape($decoded['text']) : "";
    $branch     = isset($decoded['branch']) ? Database::escape($decoded['branch']) : "";
    $faculty    = isset($decoded['faculty']) ? Database::escape($decoded['faculty']) : "";
    $programme  = isset($decoded['programme']) ? Database::escape($decoded['programme']) : "";
    $intake     = isset($decoded['intake']) ? Database::escape($decoded['intake']) : "";
    $counsellor = isset($decoded['counsellor']) ? Database::escape($decoded['counsellor']) : "";
    $currency   = isset($decoded['currency']) ? Database::escape($decoded['currency']) : "";
    $studentStatus = isset($decoded['status']) ? Database::escape($decoded['status']) : "";
    $dateFilter = isset($decoded['dateFilter']) ? Database::escape($decoded['dateFilter']) : "";
    $fromDate   = isset($decoded['fromDate']) ? Database::escape($decoded['fromDate']) : "";
    $toDate     = isset($decoded['toDate']) ? Database::escape($decoded['toDate']) : "";

    date_default_timezone_set('Asia/Colombo');

    /* Place the due-report.xlsx template here on the server */
    $spreadsheet = IOFactory::load("../assets/uploads/reports/due-report.xlsx");
    $sheet = $spreadsheet->getActiveSheet();

    /*
        Layout:
        - B1 title (merged B1:H1)
        - C3 Generated By, C4 Generated On
        - D6 Period, D7 Filter, D8 Counsellor, D9 Currency, D10 Status
        - Headers on row 12: B Program, C Intake, D Branch, E Student Name,
          F Due Amount (Foreign Currency), G Penalty, H Counsellor.
        - Data from row 13.
    */
    $sheet->setCellValue("B1", Project::getBaseName() . " - Due Report");

    $query = "
        SELECT
            cp.course_payments_id,
            cp.amount,
            cp.discount,
            cp.discount_type_discount_type_id,
            cp.due_date,

            c.code AS currency_code,
            c.decimal_places,
            c.currency_id AS course_currency_id,

            shi.status_status_id,

            s.student_id AS aId,
            ld.first_name,
            ld.last_name,
            t.title,

            intk.intake,
            intk.intake_id,
            prog.programme,
            prog.programme_id,
            fac.faculty_id,
            br.branch,
            br.branch_id,

            cu.user_id      AS counsellor_id,
            cu.user_fname   AS counsellor_first_name,
            cu.user_lname   AS counsellor_last_name

        FROM course_payments AS cp

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

        INNER JOIN intakes AS intk
            ON shi.intakes_intake_id = intk.intake_id

        INNER JOIN programmes AS prog
            ON intk.programmes_programme_id = prog.programme_id

        INNER JOIN faculties AS fac
            ON prog.faculties_faculty_id = fac.faculty_id

        INNER JOIN branches AS br
            ON fac.branches_branch_id = br.branch_id

        LEFT JOIN users cu
            ON s.users_user_id = cu.user_id

        WHERE cp.payment_status_payment_status_id != 5
        AND cp.due_date < CURDATE()
    ";

    if (!empty($text)) {
        $query .= " AND (
            s.student_id LIKE '%$text%'
            OR ld.first_name LIKE '%$text%'
            OR ld.last_name LIKE '%$text%'
        ) ";
    }

    if (!empty($intake) && $intake != '0') {
        $query .= " AND intk.intake_id = '$intake' ";
    } elseif (!empty($programme) && $programme != '0') {
        $query .= " AND prog.programme_id = '$programme' ";
    } elseif (!empty($faculty) && $faculty != '0') {
        $query .= " AND fac.faculty_id = '$faculty' ";
    } elseif (!empty($branch) && $branch != '0') {
        $query .= " AND br.branch_id = '$branch' ";
    }

    if (!empty($counsellor) && $counsellor != '0') {
        $query .= " AND cu.user_id = '$counsellor' ";
        $cuRow = Database::search("SELECT user_fname, user_lname FROM users WHERE user_id = '$counsellor' LIMIT 1");
        $counsellorLabel = "All";
        if ($cuRow && $cuRow->num_rows > 0) {
            $cuData = $cuRow->fetch_assoc();
            $counsellorLabel = trim($cuData['user_fname'] . ' ' . $cuData['user_lname']);
        }
        $sheet->setCellValue("D8", $counsellorLabel);
    } else {
        $sheet->setCellValue("D8", "All");
    }

    $currencyLabel = "All";
    if (!empty($currency) && $currency != '0') {
        $query .= " AND c.currency_id = '$currency' ";
        $curRow = Database::search("SELECT code FROM currencies WHERE currency_id = '$currency' LIMIT 1");
        if ($curRow && $curRow->num_rows > 0) {
            $currencyLabel = $curRow->fetch_assoc()['code'];
        }
    }
    $sheet->setCellValue("D9", $currencyLabel);

    if (!empty($studentStatus) && $studentStatus != '0') {
        $query .= " AND shi.status_status_id = '$studentStatus' ";
        $stRow = Database::search("SELECT status FROM status WHERE status_id = '$studentStatus' LIMIT 1");
        $sheet->setCellValue("D10", $stRow && $stRow->num_rows > 0 ? $stRow->fetch_assoc()['status'] : "All");
    } else {
        $sheet->setCellValue("D10", "All");
    }

    $periodLabel = "All";
    if (!empty($dateFilter) && $dateFilter == '1') {
        $query .= " AND MONTH(cp.due_date) = MONTH(CURDATE()) AND YEAR(cp.due_date) = YEAR(CURDATE())";
        $periodLabel = "This Month";
    } elseif (!empty($dateFilter) && $dateFilter == '2') {
        $query .= " AND MONTH(cp.due_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(cp.due_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        $periodLabel = "Last Month";
    } elseif (!empty($dateFilter) && $dateFilter == '3') {
        if (!empty($fromDate)) {
            $query .= " AND cp.due_date >= '$fromDate' ";
        }
        if (!empty($toDate)) {
            $query .= " AND cp.due_date <= '$toDate' ";
        }
        $periodLabel = "Custom Range" . ($fromDate !== "" ? " : From " . $fromDate : "") . ($toDate !== "" ? " - To " . $toDate : "");
    }
    $sheet->setCellValue("D6", $periodLabel);

    $filterLabel = "All";
    if (!empty($intake) && $intake != '0') {
        $intkRow = Database::search("SELECT intake FROM intakes WHERE intake_id = '$intake' LIMIT 1");
        if ($intkRow && $intkRow->num_rows > 0) $filterLabel = "Intake: " . $intkRow->fetch_assoc()['intake'];
    } elseif (!empty($programme) && $programme != '0') {
        $progRow = Database::search("SELECT programme FROM programmes WHERE programme_id = '$programme' LIMIT 1");
        if ($progRow && $progRow->num_rows > 0) $filterLabel = "Programme: " . $progRow->fetch_assoc()['programme'];
    } elseif (!empty($faculty) && $faculty != '0') {
        $facRow = Database::search("SELECT faculty FROM faculties WHERE faculty_id = '$faculty' LIMIT 1");
        if ($facRow && $facRow->num_rows > 0) $filterLabel = "Faculty: " . $facRow->fetch_assoc()['faculty'];
    } elseif (!empty($branch) && $branch != '0') {
        $brRow = Database::search("SELECT branch FROM branches WHERE branch_id = '$branch' LIMIT 1");
        if ($brRow && $brRow->num_rows > 0) $filterLabel = "Branch: " . $brRow->fetch_assoc()['branch'];
    }
    $sheet->setCellValue("D7", $filterLabel);

    $query .= " ORDER BY cp.course_payments_id DESC ";

    $results = Database::search($query);

    $headerRow = 12;
    $row = $headerRow + 1;

    $sumDue = 0;
    $sumTotal = 0;
    $sumTax = 0;
    $sumPenaltyLkr = 0;
    $rowCount = 0;

    /* Only meaningful to total Due Amount when a single currency is
       in play — otherwise different courses' amounts (GBP + USD +
       LKR...) would be added together nonsensically. Penalty is
       always LKR (see penalty_lkr note above), so it can always be
       totalled regardless of the currency filter. */
    $singleCurrencyActive = (!empty($currency) && $currency != '0');


    $latestTaxRate = 2.5;
    $rate = Database::search("SELECT * FROM tax_rates ORDER BY tax_rate_id DESC LIMIT 1");
    if ($rate && $rate->num_rows > 0) {
        $latestTaxRate = (float)$rate->fetch_assoc()['tax_rate'];
    }

    function calculateTaxInclusiveAmount($base, $taxRate)
    {
        $base = (float)$base;
        $taxRate = (float)$taxRate;
        if ($taxRate <= 0 || $base <= 0) {
            return $base;
        }
        $basePortion = 1 - ($taxRate / 100);
        if ($basePortion <= 0) {
            return $base;
        }
        return round($base / $basePortion, 2);
    }

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
        $penaltyLkr    = $summary['penalty_lkr'];

        $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $totalTax;
        $dueAmount = $payableAmount - $settled;

        $dueAmountWithTax = calculateTaxInclusiveAmount($dueAmount, $latestTaxRate);
        $dueAmountSchouldPaidTax = $dueAmountWithTax - $dueAmount;

        if ($dueAmount <= 0.009) {
            continue;
        }

        $studentName = trim($data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name']);
        $counsellorName = trim(($data['counsellor_first_name'] ?? '') . ' ' . ($data['counsellor_last_name'] ?? ''));
        if ($counsellorName === '') {
            $counsellorName = 'N/A';
        }

        $sheet->setCellValue("B$row", $data['programme']);
        $sheet->setCellValue("C$row", $data['intake']);
        $sheet->setCellValue("D$row", $data['branch']);
        $sheet->setCellValue("E$row", $studentName);
        $sheet->setCellValue("F$row", $data['currency_code'] . ' ' . round($dueAmount, 2));
        $sheet->setCellValue("G$row", $data['currency_code'] . ' ' . round($dueAmountSchouldPaidTax, 2));
        $sheet->setCellValue("H$row", $data['currency_code'] . ' ' . round($dueAmountWithTax, 2));
        /* Penalty column: mirrors due-report.php — penalty_lkr (LOG
           table total, always LKR), NOT $totalPenalty. */
        $sheet->setCellValue("I$row", $penaltyLkr > 0 ? 'LKR ' . number_format($penaltyLkr, 2) : "");
        $sheet->setCellValue("J$row", $counsellorName);

        $sheet->getStyle("B$row:J$row")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sumDue        += $dueAmount;
        $sumTotal += $dueAmountWithTax;
        $sumTax += $dueAmountSchouldPaidTax;
        $sumPenaltyLkr += $penaltyLkr;
        $rowCount++;

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

        $sheet->mergeCells("B{$totalRow}:E{$totalRow}");
        $sheet->setCellValue("B{$totalRow}", "Total (" . $rowCount . " installments)");

        if ($singleCurrencyActive) {
            $sheet->setCellValue("F{$totalRow}", $currencyLabel . ' ' . round($sumDue, 2));
            $sheet->setCellValue("G{$totalRow}", $currencyLabel . ' ' . round($sumTax, 2));
            $sheet->setCellValue("H{$totalRow}", $currencyLabel . ' ' . round($sumTotal, 2));
        } else {
            $sheet->setCellValue("F{$totalRow}", "Mixed currencies");
        }
        /* Always LKR, regardless of the currency filter. */
        $sheet->setCellValue("I{$totalRow}", 'LKR ' . number_format($sumPenaltyLkr, 2));

        $sheet->getStyle("B{$totalRow}:J{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$totalRow}:J{$totalRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setAutoFilter("B{$headerRow}:J{$lastDataRow}");
    } else {
        $sheet->setAutoFilter("B{$headerRow}:J{$headerRow}");
    }

    $fileName = "due_report_" . date('Ymd_His') . ".xlsx";

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
