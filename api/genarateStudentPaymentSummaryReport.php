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
   Same currency-conversion helpers used on students-payment.php.
   Kept identical on purpose so this report and that page never
   disagree on a number.
   ================================================================ */

function resolveExchangeRate(array $row, int $courseCurrencyId): ?float
{
    $paidCurrId = (int)($row['paid_currency_id'] ?? 0);
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

    // Matches the payload sent by genarateStudentPaymentSummaryReport() in JS
    $text      = isset($decoded['text']) ? Database::escape($decoded['text']) : "";
    $branch    = isset($decoded['branch']) ? Database::escape($decoded['branch']) : "";
    $faculty   = isset($decoded['faculty']) ? Database::escape($decoded['faculty']) : "";
    $programme = isset($decoded['programme']) ? Database::escape($decoded['programme']) : "";
    $intake    = isset($decoded['intake']) ? Database::escape($decoded['intake']) : "";
    $filter    = isset($decoded['filter']) ? Database::escape($decoded['filter']) : "";
    $from      = isset($decoded['from']) ? Database::escape($decoded['from']) : "";
    $to        = isset($decoded['to']) ? Database::escape($decoded['to']) : "";

    date_default_timezone_set('Asia/Colombo');

    // TODO: confirm this template path/filename matches wherever you store it
    $spreadsheet = IOFactory::load("../assets/uploads/reports/student-summary-report.xlsx");
    $sheet = $spreadsheet->getActiveSheet();

    /*
        Layout (matches "AIC Campus - Student payment summaries" template):
        - Header info on rows 1-9 (already in the template).
        - Column headers on row 12.
        - Data starts at row 13.
        - Columns: B Student Intake ID, C Student Name, D Email, E Mobile,
          F WhatsApp, G Intake Detail, H Assigned User, I Status,
          J Fees Type, K Amount, L Paid Currency, M Currency Rate,
          N Converted Amount, O Tax, P Discount, Q Penalties,
          R Payable Amount, S Paid Amount, T Paid Date, U Date Added,
          V Receipt Number, W Receipt Date.
    */

    $query = "
        SELECT
            cp.course_payments_id,
            cpp.course_payment_has_payment_id,

            cp.amount,
            cp.discount,
            cp.discount_type_discount_type_id,

            cpp.paid_amount,
            cpp.discount_amount,
            cpp.panelty_amount          AS receipt_penalty_paid_cur,
            cpp.tax_rate,
            cpp.tax_amount              AS receipt_tax_paid_cur,
            cpp.currencies_currency_id  AS paid_currency_id,
            cpp.currency_exchange_rate,
            cpp.currency_exchange_rate_id,
            cpp.date_paid,
            cpp.date_added              AS receipt_date_added,

            ps.payment_status,
            ps.payment_status_id,
            pgp.payment_type,

            shi.student_id  AS shiStudentId,
            ld.first_name,
            ld.last_name,
            ld.email,
            ld.whatsapp,
            t.title,

            s.mobile,
            s.student_id    AS aId,

            i.intake,

            u.user_fname,
            u.user_lname,

            st.status,
            st.status_id,

            course_cur.currency_id    AS course_currency_id,
            course_cur.code           AS course_currency_code,
            course_cur.decimal_places AS course_decimal_places,

            COALESCE(paid_cur.code, course_cur.code) AS paid_currency_code,
            COALESCE(paid_cur.decimal_places, course_cur.decimal_places) AS paid_decimal_places,

            IFNULL(pp.penalty_lkr, 0) AS penalty_lkr,

            cr.receipt_number,
            cr.date_added AS receipt_date

        FROM course_payment_has_payments cpp

        INNER JOIN course_payments cp
            ON cp.course_payments_id = cpp.course_payments_course_payments_id

        INNER JOIN payment_status ps
            ON cpp.payment_status_payment_status_id = ps.payment_status_id

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
            ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

        INNER JOIN intakes AS i
            ON shi.intakes_intake_id = i.intake_id

        INNER JOIN students s
            ON shi.students_student_id = s.student_id

        INNER JOIN login_details ld
            ON s.login_details_login_details_id = ld.login_details_id

        INNER JOIN title t
            ON s.title_title_id = t.title_id

        INNER JOIN status AS st
            ON shi.status_status_id = st.status_id

        LEFT JOIN users AS u
            ON s.users_user_id = u.user_id

        LEFT JOIN course_payment_receipts cr
            ON cr.course_payment_receipt_id = cpp.course_payment_receipts_course_payment_receipt_id

        LEFT JOIN (
            SELECT
                course_payments_course_payments_id,
                SUM(amount) AS penalty_lkr
            FROM panelty_payments
            GROUP BY course_payments_course_payments_id
        ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id

        WHERE 1 = 1
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

    // TODO: confirm the real join path branch -> faculty -> programme -> intake.
    // Placeholder columns below assume: intakes.programmes_programme_id,
    // programmes.faculties_faculty_id, faculties.branches_branch_id.
    // Swap these for the actual column/table names used by loadFaculties.php /
    // loadProgrammes.php / loadLevels.php once confirmed.
    if (!empty($branch) && $branch != '0') {
        $query .= " AND fac.branches_branch_id = '$branch' ";
    }
    if (!empty($faculty) && $faculty != '0') {
        $query .= " AND prog.faculties_faculty_id = '$faculty' ";
    }
    if (!empty($programme) && $programme != '0') {
        $query .= " AND i.programmes_programme_id = '$programme' ";
    }
    if (!empty($intake) && $intake != '0') {
        $query .= " AND i.intake_id = '$intake' ";
    }

    $periodLabel = "All";
    if (!empty($filter) && $filter == '1') {
        // This Month
        $query .= " AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                AND YEAR(cpp.date_paid) = YEAR(CURDATE())";
        $periodLabel = "This Month";
    } elseif (!empty($filter) && $filter == '2') {
        // Last Month
        $query .= " AND MONTH(cpp.date_paid) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND YEAR(cpp.date_paid) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $periodLabel = "Last Month";
    } elseif (!empty($filter) && $filter == '3') {
        // Custom Date Range
        if (!empty($from)) {
            $query .= " AND DATE(cpp.date_paid) >= '$from' ";
        }
        if (!empty($to)) {
            $query .= " AND DATE(cpp.date_paid) <= '$to' ";
        }
        $periodLabel = "Custom Range" . ($from !== "" ? " : From " . $from : "") . ($to !== "" ? " - To " . $to : "");
    }

    $query .= " ORDER BY cpp.course_payment_has_payment_id DESC ";

    $results = Database::search($query);

    $headerRow = 12;
    $row = $headerRow + 1; // data starts at row 13

    $totalStudents = 0;
    $activeStudents = 0;
    $inactiveStudents = 0;
    $seenStudentIds = [];

    $sumAmount = 0;
    $sumConverted = 0;
    $sumTax = 0;
    $sumDiscount = 0;
    $sumPenalty = 0;
    $sumPayable = 0;
    $sumPaid = 0;

    while ($data = $results->fetch_assoc()) {

        $rate = resolveExchangeRate($data, (int)$data['course_currency_id']);

        $cpDiscount = 0.0;
        if ($data['discount_type_discount_type_id'] == 3) {
            $cpDiscount = (float)$data['amount'] / 100 * (float)$data['discount'];
        } elseif ($data['discount_type_discount_type_id'] == 2) {
            $cpDiscount = (float)$data['discount'];
        }
        $receiptDiscount = (float)$data['discount_amount'];
        $totalDiscount = $cpDiscount + $receiptDiscount;

        $receiptPenaltyCc = toCourse((float)($data['receipt_penalty_paid_cur'] ?? 0), $rate);

        $penaltyLkr = (float)($data['penalty_lkr'] ?? 0);
        $isCourseLkr = ((int)$data['course_currency_id'] === 1);
        if ($isCourseLkr || $rate === null) {
            $mainPenaltyCc = $penaltyLkr;
        } else {
            $mainPenaltyCc = ($rate > 0) ? $penaltyLkr / $rate : $penaltyLkr;
        }
        $totalPenalty = $receiptPenaltyCc + $mainPenaltyCc;

        $taxCc = toCourse((float)($data['receipt_tax_paid_cur'] ?? 0), $rate);

        $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $taxCc;
        $paidInCourse = toCourse((float)$data['paid_amount'], $rate);

        $assignedUser = trim($data['user_fname'] . ' ' . $data['user_lname']);
        $studentName = trim($data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name']);

        if (!isset($seenStudentIds[$data['aId']])) {
            $seenStudentIds[$data['aId']] = true;
            $totalStudents++;
            if ((int)$data['status_id'] === 1 || strtolower($data['status']) === 'active') {
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
        $sheet->setCellValue("H$row", $assignedUser);
        $sheet->setCellValue("I$row", $data['status']);
        $sheet->setCellValue("J$row", $data['payment_type']);
        $sheet->setCellValue("K$row", $data['currency_code'] . ' ' . (float)$data['amount']);

        $sheet->setCellValue("L$row", round((float)$taxCc, 2));
        $sheet->setCellValue("M$row", $totalDiscount);
        $sheet->setCellValue("N$row", $totalPenalty);
        $sheet->setCellValue("O$row", round($payableAmount, 2));
        $sheet->setCellValue("P$row", $data['paid_currency_code']);
        $sheet->setCellValue("Q$row", $rate !== null ? $rate : 1);
        $sheet->setCellValue("R$row", (float)$data['paid_amount']);
        $sheet->setCellValue("S$row", $data['date_paid'] ? date("Y-m-d", strtotime($data['date_paid'])) : '');
        $sheet->setCellValue("T$row", $data['receipt_date_added'] ? date("Y-m-d", strtotime($data['receipt_date_added'])) : '');
        // TODO: swap in the real receipt number column/table if one exists;
        // falling back to the payment record id as the receipt reference.
        $sheet->setCellValue("U$row", $data['receipt_number']);
        $sheet->setCellValue("V$row", $data['receipt_date'] ? date("Y-m-d", strtotime($data['receipt_date'])) : '');

        $sheet->getStyle("B$row:V$row")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sumAmount    += (float)$data['amount'];
        $sumConverted += $paidInCourse;
        $sumTax       += $taxCc;
        $sumDiscount  += $totalDiscount;
        $sumPenalty   += $totalPenalty;
        $sumPayable   += $payableAmount;
        $sumPaid      += (float)$data['paid_amount'];

        $row++;
    }

    $lastDataRow = $row - 1;

    // ---- Header info text ----
    $userData = Database::search("SELECT * FROM `users`
        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
        WHERE `user_id`='" . $sessionUserObject['user_id'] . "'")->fetch_assoc();

    $sheet->setCellValue("C3", $userData['title'] . " " . $userData['user_fname'] . " " . $userData['user_lname']);
    $sheet->setCellValue("C4", date("Y-m-d h:i A"));
    $sheet->setCellValue("D6", $periodLabel);
    $sheet->setCellValue("D7", !empty($text) ? "Search: " . $text : "All");
    $sheet->setCellValue("D8", "All");
    $sheet->setCellValue("D9", !empty($intake) && $intake != '0' ? "Filtered Intake" : "All Student");

    if ($lastDataRow >= $headerRow + 1) {
        // ---- Total row ----
        $totalRow = $lastDataRow + 1;

        $sheet->mergeCells("B{$totalRow}:J{$totalRow}");
        $sheet->setCellValue("B{$totalRow}", "Total");

        $sheet->setCellValue("K{$totalRow}", $sumAmount);
        $sheet->setCellValue("N{$totalRow}", $sumConverted);
        $sheet->setCellValue("O{$totalRow}", $sumTax);
        $sheet->setCellValue("P{$totalRow}", $sumDiscount);
        $sheet->setCellValue("Q{$totalRow}", $sumPenalty);
        $sheet->setCellValue("R{$totalRow}", $sumPayable);
        $sheet->setCellValue("S{$totalRow}", $sumPaid);

        $sheet->getStyle("B{$totalRow}:W{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$totalRow}:W{$totalRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ---- Student count summary row ----
        $statsRow = $totalRow + 2;
        $sheet->setCellValue(
            "B{$statsRow}",
            "Total No of Students : " . str_pad($totalStudents, 2, '0', STR_PAD_LEFT)
                . ",   Active Students : " . str_pad($activeStudents, 2, '0', STR_PAD_LEFT)
                . ",    Inactive Students : " . str_pad($inactiveStudents, 2, '0', STR_PAD_LEFT) . ",  "
        );
        $sheet->getStyle("B{$statsRow}")->getFont()->setBold(true);
    }

    $fileName = "student_payment_summary_report_" . date('Ymd_His') . ".xlsx";

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
