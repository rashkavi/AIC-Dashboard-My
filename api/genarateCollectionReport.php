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
   Same base-currency / paid-currency / LKR conversion logic used on
   the collection-report view + AJAX pages. Duplicated here on purpose
   (matches how resolveExchangeRate()/toCourse() are duplicated across
   the other report exports in this project) so this file has no
   external dependency beyond Database + PhpSpreadsheet.
   ================================================================ */

$LKR_CURRENCY_ID = 1;

function resolveLkrConversion(int $foreignCurrencyId, ?int $exchangeRateId, ?float $inlineRate): array
{
    if ($foreignCurrencyId === 1 || $foreignCurrencyId === 0) {
        return ['rate' => null, 'mode' => 'none'];
    }
    if ($exchangeRateId) {
        $rr = Database::search(
            "SELECT `base_currency_id`, `target_currency_id`, `rate`
             FROM `currency_exchange_rates`
             WHERE `currency_exchange_rate_id` = '" . (int)$exchangeRateId . "'
             LIMIT 1"
        );
        if ($rr && $rr->num_rows > 0) {
            $r      = $rr->fetch_assoc();
            $base   = (int)$r['base_currency_id'];
            $target = (int)$r['target_currency_id'];
            $rate   = (float)$r['rate'];
            if ($rate > 0) {
                if ($base === $foreignCurrencyId && $target === 1) {
                    return ['rate' => $rate, 'mode' => 'multiply'];
                }
                if ($base === 1 && $target === $foreignCurrencyId) {
                    return ['rate' => $rate, 'mode' => 'divide'];
                }
                return ['rate' => $rate, 'mode' => 'multiply'];
            }
        }
    }
    if ($inlineRate !== null && $inlineRate > 0) {
        return ['rate' => $inlineRate, 'mode' => 'multiply'];
    }
    return ['rate' => null, 'mode' => 'none'];
}

function foreignToLkr(float $foreignAmount, array $conv): float
{
    if ($conv['mode'] === 'multiply' && $conv['rate'] !== null) {
        return $foreignAmount * $conv['rate'];
    }
    if ($conv['mode'] === 'divide' && $conv['rate'] !== null && $conv['rate'] > 0) {
        return $foreignAmount / $conv['rate'];
    }
    return $foreignAmount;
}

function lkrToForeign(float $lkrAmount, array $conv): float
{
    if ($conv['mode'] === 'multiply' && $conv['rate'] !== null && $conv['rate'] > 0) {
        return $lkrAmount / $conv['rate'];
    }
    if ($conv['mode'] === 'divide' && $conv['rate'] !== null) {
        return $lkrAmount * $conv['rate'];
    }
    return $lkrAmount;
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
    $currency   = isset($decoded['currency']) ? Database::escape($decoded['currency']) : ""; // filter by base OR paid currency id
    $dateFilter = isset($decoded['dateFilter']) ? Database::escape($decoded['dateFilter']) : ""; // 1=This Month, 2=Last Month, 3=Range
    $fromDate   = isset($decoded['fromDate']) ? Database::escape($decoded['fromDate']) : "";
    $toDate     = isset($decoded['toDate']) ? Database::escape($decoded['toDate']) : "";
    $currency = isset($decoded['currency']) ? Database::escape($decoded['currency']) : ""; // filter by base OR paid currency id

    date_default_timezone_set('Asia/Colombo');

    /* Place the uploaded collection-report.xlsx template here on the server */
    $spreadsheet = IOFactory::load("../assets/uploads/reports/collection-report.xlsx");
    $sheet = $spreadsheet->getActiveSheet();

    /*
        Layout (matches the uploaded template):
        - B1 title (merged B1:E1)
        - C3 Generated By, C4 Generated On
        - D6 Period, D7 Filter, D8 Counsellor, D9 Currency  (filter summary)
        - Headers on row 12: B Branch, C Paid Date, D Receipt Number,
          E Student Name, F Foreign amount (USD/GBP/EUR), G LKR Amount,
          H Counsellor, I Payment type (RF / CF / Penalty), J Program,
          K Exchange rate.
        - Data from row 13.
    */
    $sheet->setCellValue("B1", Project::getBaseName() . " - Collection Report");

    /* ── Base grouped-by-receipt query — same shape as the view/AJAX pages ── */
    $query = "
        SELECT
            r.course_payment_receipt_id,
            r.receipt_number,

            br.branch,
            fac.faculty_id,
            fac.faculty,
            prog.programme_id,
            prog.programme,
            intk.intake_id,

            s.student_id AS aId,
            ld.first_name,
            ld.last_name,
            t.title,

            cu.user_id      AS counsellor_id,
            cu.user_fname   AS counsellor_first_name,
            cu.user_lname   AS counsellor_last_name,

            GROUP_CONCAT(DISTINCT pgp.payment_type ORDER BY pgp.payment_type SEPARATOR ', ') AS payment_types,

            MIN(cpp.date_paid)                    AS paid_date,
            SUM(cpp.paid_amount)                  AS total_paid_amount,

            MAX(cpp.currencies_currency_id)       AS raw_paid_currency_id,

            MAX(course_cur.currency_id)           AS base_currency_id,
            MAX(course_cur.code)                  AS base_currency_code,
            MAX(course_cur.decimal_places)        AS base_decimal_places,

            MAX(cpp.currency_exchange_rate_id)    AS exchange_rate_id,
            MAX(cpp.currency_exchange_rate)       AS inline_exchange_rate,

            COALESCE(paid_cur.code, course_cur.code) AS paid_currency_code,
            COALESCE(paid_cur.decimal_places, course_cur.decimal_places) AS paid_decimal_places

        FROM course_payment_receipts r

        INNER JOIN course_payment_has_payments cpp
            ON cpp.course_payment_receipts_course_payment_receipt_id = r.course_payment_receipt_id

        INNER JOIN course_payments cp
            ON cp.course_payments_id = cpp.course_payments_course_payments_id

        INNER JOIN payment_type pgp
            ON cp.payment_type_payment_type_id = pgp.payment_type_id

        INNER JOIN payment_plan_has_students_has_intakes phsi
            ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

        INNER JOIN payment_category pc
            ON phsi.payment_category_payment_category_id = pc.payment_category_id

        INNER JOIN currencies course_cur
            ON pc.currencies_currency_id = course_cur.currency_id

        LEFT JOIN currencies paid_cur
            ON cpp.currencies_currency_id = paid_cur.currency_id

        INNER JOIN students_has_intakes shi
            ON r.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

        INNER JOIN students s
            ON shi.students_student_id = s.student_id

        INNER JOIN login_details ld
            ON s.login_details_login_details_id = ld.login_details_id

        INNER JOIN title t
            ON s.title_title_id = t.title_id

        LEFT JOIN users cu
            ON s.users_user_id = cu.user_id

        INNER JOIN intakes intk
            ON shi.intakes_intake_id = intk.intake_id

        INNER JOIN programmes prog
            ON intk.programmes_programme_id = prog.programme_id

        INNER JOIN faculties fac
            ON prog.faculties_faculty_id = fac.faculty_id

        INNER JOIN branches br
            ON fac.branches_branch_id = br.branch_id

        WHERE 1=1
    ";

    if (!empty($text)) {
        $query .= " AND (
            ld.first_name LIKE '%$text%'
            OR ld.last_name LIKE '%$text%'
            OR r.receipt_number LIKE '%$text%'
        ) ";
    }

    $filterLabelParts = [];

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

    if ($currency != 0) {
        $query .= " AND paid_cur.currency_id = '$currency' ";
    }

    $currencyLabel = "All";
    if (!empty($currency) && $currency != '0') {
        $curRow = Database::search("SELECT code FROM currencies WHERE currency_id = '$currency' LIMIT 1");
        if ($curRow && $curRow->num_rows > 0) {
            $currencyLabel = $curRow->fetch_assoc()['code'];
        }
    }
    $sheet->setCellValue("D9", $currencyLabel);

    $periodLabel = "All";
    if (!empty($dateFilter) && $dateFilter == '1') {
        $query .= " AND YEAR(cpp.date_paid) = YEAR(CURDATE()) AND MONTH(cpp.date_paid) = MONTH(CURDATE())";
        $periodLabel = "This Month";
    } elseif (!empty($dateFilter) && $dateFilter == '2') {
        $query .= " AND YEAR(cpp.date_paid) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(cpp.date_paid) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
        $periodLabel = "Last Month";
    } elseif (!empty($dateFilter) && $dateFilter == '3') {
        if (!empty($fromDate)) {
            $query .= " AND cpp.date_paid >= '$fromDate' ";
        }
        if (!empty($toDate)) {
            $query .= " AND cpp.date_paid <= '$toDate' ";
        }
        $periodLabel = "Custom Range" . ($fromDate !== "" ? " : From " . $fromDate : "") . ($toDate !== "" ? " - To " . $toDate : "");
    }
    $sheet->setCellValue("D6", $periodLabel);

    /* "Filter" (D7): summary of the most specific location filter applied */
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

    $query .= " GROUP BY r.course_payment_receipt_id ORDER BY r.course_payment_receipt_id DESC ";

    $results = Database::search($query);

    $headerRow = 12;
    $row = $headerRow + 1;

    $sumLkr = 0;
    $sumForeign = 0;

    while ($data = $results->fetch_assoc()) {

        $baseCurrencyId = (int)($data['base_currency_id'] ?? 0);

        $rawPaidCurrencyId = $data['raw_paid_currency_id'] !== null ? (int)$data['raw_paid_currency_id'] : null;
        $paidCurrencyId    = $rawPaidCurrencyId ?? $baseCurrencyId;

        /* ── Currency filter (applied after resolution, matches either
               the base or the actually-paid currency) ────────────────── */
        if (!empty($currency) && $currency != '0') {
            if ((int)$currency !== $baseCurrencyId && (int)$currency !== $paidCurrencyId) {
                continue;
            }
        }

        $totalPaidAmount = (float)$data['total_paid_amount'];

        $exchangeRateId = !empty($data['exchange_rate_id']) ? (int)$data['exchange_rate_id'] : null;
        $inlineRate     = isset($data['inline_exchange_rate']) && $data['inline_exchange_rate'] !== null ? (float)$data['inline_exchange_rate'] : null;

        $paidIsLkr    = ($paidCurrencyId === $LKR_CURRENCY_ID);
        $baseIsLkr    = ($baseCurrencyId === $LKR_CURRENCY_ID);
        $isAnyForeign = !$paidIsLkr || !$baseIsLkr;

        $foreignAmount = null;
        $lkrAmount     = null;
        $foreignCode   = null;
        $conv          = ['rate' => null, 'mode' => 'none'];

        if (!$isAnyForeign) {
            $lkrAmount = $totalPaidAmount;
        } elseif (!$baseIsLkr && $paidCurrencyId === $baseCurrencyId) {
            /* Paid directly in the course's own foreign currency
               (e.g. base EUR, paid EUR) — no conversion happened,
               no genuine LKR figure to show. */
            $foreignAmount = $totalPaidAmount;
            $foreignCode   = $data['base_currency_code'];
            $lkrAmount     = null;
        } elseif ($paidIsLkr) {
            $lkrAmount     = $totalPaidAmount;
            $conv          = resolveLkrConversion($baseCurrencyId, $exchangeRateId, $inlineRate);
            $foreignAmount = lkrToForeign($lkrAmount, $conv);
            $foreignCode   = $data['base_currency_code'];
        } else {
            $foreignAmount = $totalPaidAmount;
            $conv          = resolveLkrConversion($paidCurrencyId, $exchangeRateId, $inlineRate);
            $lkrAmount     = foreignToLkr($foreignAmount, $conv);
            $foreignCode   = $data['paid_currency_code'];
        }

        $studentName = trim($data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name']);

        $counsellorName = trim(($data['counsellor_first_name'] ?? '') . ' ' . ($data['counsellor_last_name'] ?? ''));
        if ($counsellorName === '') {
            $counsellorName = 'N/A';
        }

        $sheet->setCellValue("B$row", $data['branch']);
        $sheet->setCellValue("C$row", $data['paid_date'] ? date('Y-m-d', strtotime($data['paid_date'])) : '');
        $sheet->setCellValue("D$row", $data['receipt_number']);
        $sheet->setCellValue("E$row", $studentName);

        if ($isAnyForeign && !$paidIsLkr && $foreignAmount !== null) {
            $sheet->setCellValue("F$row", $foreignCode . ' ' . round($foreignAmount, 2));
        } else {
            $sheet->setCellValue("F$row", "");
        }

        if ($lkrAmount !== null) {
            $sheet->setCellValue("G$row", round($lkrAmount, 2));
        } else {
            $sheet->setCellValue("G$row", "");
        }
        $sheet->setCellValue("H$row", $counsellorName);
        $sheet->setCellValue("I$row", $data['payment_types']);
        $sheet->setCellValue("J$row", $data['programme']);

        if ($isAnyForeign && $conv['rate'] !== null) {
            $sheet->setCellValue("K$row", round($conv['rate'], 2));
        } else {
            $sheet->setCellValue("K$row", "");
        }

        $sheet->getStyle("B$row:K$row")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sumLkr += ($lkrAmount ?? 0);

        if ($currency != 0) {
            $sumForeign += ($foreignAmount ?? 0);
        }

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



        if ($currency != 0) {
            $sheet->mergeCells("B{$totalRow}:E{$totalRow}");
            $sheet->setCellValue("B{$totalRow}", "Total(" . $currencyLabel . ")");
            $sheet->setCellValue("F{$totalRow}", round($sumForeign, 2));
        } else {
            $sheet->mergeCells("B{$totalRow}:F{$totalRow}");
            $sheet->setCellValue("B{$totalRow}", "Total (LKR)");
            $sheet->setCellValue("G{$totalRow}", round($sumLkr, 2));
        }


        $sheet->getStyle("B{$totalRow}:K{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$totalRow}:K{$totalRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /* Every column filterable/sortable in Excel */
        $sheet->setAutoFilter("B{$headerRow}:K{$lastDataRow}");
    } else {
        $sheet->setAutoFilter("B{$headerRow}:K{$headerRow}");
    }

    $fileName = "collection_report_" . date('Ymd_His') . ".xlsx";

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
