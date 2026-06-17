<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

if (!SessionUser::hasValidSession()) {
    die(json_encode(['success' => false, 'message' => 'Invalid session']));
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        die(json_encode(['success' => false, 'message' => 'Invalid role']));
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
            die(json_encode(['success' => false, 'message' => 'Insufficient permissions']));
        } else {
            // Get filter parameters
            $fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : null;
            $toDate = isset($_POST['to_date']) ? $_POST['to_date'] : null;
            $branch = isset($_POST['branch']) ? $_POST['branch'] : null;
            $program = isset($_POST['program']) ? $_POST['program'] : null;
            $intake = isset($_POST['intake']) ? $_POST['intake'] : null;

            // Build WHERE clause based on filters
            $whereConditions = [];
            if ($fromDate) {
                $whereConditions[] = "DATE(cpp.date_paid) >= '" . date('Y-m-d', strtotime($fromDate)) . "'";
            }
            if ($toDate) {
                $whereConditions[] = "DATE(cpp.date_paid) <= '" . date('Y-m-d', strtotime($toDate)) . "'";
            }
            if ($branch && $branch != '0') {
                $whereConditions[] = "cb.branch_id = '" . (int)$branch . "'";
            }
            if ($program && $program != '0') {
                $whereConditions[] = "c.course_id = '" . (int)$program . "'";
            }
            if ($intake && $intake != '0') {
                $whereConditions[] = "ci.intake_id = '" . (int)$intake . "'";
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Main query to fetch payment data
            $query = "
                SELECT
                    cpp.course_payment_has_payment_id,
                    cp.amount,
                    cp.discount,
                    cp.discount_type_discount_type_id,
                    cpp.paid_amount,
                    cpp.discount_amount,
                    cpp.panelty_amount AS receipt_penalty_paid_cur,
                    cpp.tax_rate,
                    cpp.tax_amount AS receipt_tax_paid_cur,
                    cpp.currencies_currency_id AS paid_currency_id,
                    cpp.currency_exchange_rate,
                    cpp.currency_exchange_rate_id,
                    cpp.date_paid,
                    
                    ps.payment_status,
                    ps.payment_status_id,
                    pgp.payment_type,
                    pm.payment_method,
                    
                    ld.first_name,
                    ld.last_name,
                    t.title,
                    
                    c.course_name,
                    cb.branch_name,
                    ci.intake_name,
                    
                    IFNULL(pp.penalty_lkr, 0) AS penalty_lkr,
                    
                    course_cur.currency_id AS course_currency_id,
                    course_cur.code AS course_currency_code,
                    course_cur.decimal_places AS course_decimal_places,
                    
                    COALESCE(paid_cur.code, course_cur.code) AS paid_currency_code,
                    COALESCE(paid_cur.decimal_places, course_cur.decimal_places) AS paid_decimal_places

                FROM course_payment_has_payments cpp
                INNER JOIN course_payments cp ON cp.course_payments_id = cpp.course_payments_course_payments_id
                INNER JOIN payment_status ps ON cpp.payment_status_payment_status_id = ps.payment_status_id
                INNER JOIN payment_method pm ON cpp.payment_method_payment_method_id = pm.payment_method_id
                INNER JOIN payment_type pgp ON cp.payment_type_payment_type_id = pgp.payment_type_id
                INNER JOIN payment_plan_has_students_has_intakes phsi ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id
                INNER JOIN payment_category pc ON phsi.payment_category_payment_category_id = pc.payment_category_id
                INNER JOIN currencies course_cur ON pc.currencies_currency_id = course_cur.currency_id
                LEFT JOIN currencies paid_cur ON cpp.currencies_currency_id = paid_cur.currency_id
                INNER JOIN students_has_intakes shi ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
                INNER JOIN students s ON shi.students_student_id = s.student_id
                INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
                INNER JOIN title t ON s.title_title_id = t.title_id
                INNER JOIN students_has_intakes shi ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
                INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
                INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
                INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
                INNER JOIN branches b ON f.branches_branch_id = b.branch_id
               
                LEFT JOIN (
                    SELECT course_payments_course_payments_id, SUM(amount) AS penalty_lkr
                    FROM panelty_payments
                    GROUP BY course_payments_course_payments_id
                ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                $whereClause
                ORDER BY cpp.date_paid DESC, cpp.course_payment_has_payment_id DESC
            ";

            $results = Database::search($query);

            if (!$results) {
                die(json_encode(['success' => false, 'message' => 'Database query failed']));
            }

            // Helper functions
            function resolveExchangeRate(array $row, int $courseCurrencyId)
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
                        "SELECT `rate` FROM `currency_exchange_rates` WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1"
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

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('Payment Management System')
                ->setTitle('Students Payment Summary Report')
                ->setSubject('Payment Summary');

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Payment Summary');

            // Define colors
            $headerBg = 'FF2E5090';      // Dark Blue
            $summaryBg = 'FFFFCC00';     // Orange/Gold
            $incomeBg = 'FF92D050';      // Light Green
            $taxBg = 'FFFF6B6B';         // Light Red
            $penaltyBg = 'FFAED6F1';     // Light Blue
            $whiteFontColor = 'FFFFFFFF'; // White
            $darkFontColor = 'FF000000';  // Black

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(18);
            $sheet->getColumnDimension('B')->setWidth(15);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(15);
            $sheet->getColumnDimension('F')->setWidth(12);
            $sheet->getColumnDimension('G')->setWidth(15);
            $sheet->getColumnDimension('H')->setWidth(15);
            $sheet->getColumnDimension('I')->setWidth(12);
            $sheet->getColumnDimension('J')->setWidth(15);

            $row = 1;

            // Report Title
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", 'STUDENTS PAYMENT SUMMARY REPORT');
            $sheet->getStyle("A{$row}")->getFont()->setSize(16)->setBold(true)->setColor(new Color($whiteFontColor));
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($headerBg));
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimensions()[1]->setRowHeight(30);
            $row += 2;

            // Filter Information
            if ($fromDate || $toDate || $branch || $program || $intake) {
                $sheet->mergeCells("A{$row}:J{$row}");
                $filterText = 'Filters Applied: ';
                $filters = [];
                if ($fromDate) $filters[] = 'From: ' . date('M d, Y', strtotime($fromDate));
                if ($toDate) $filters[] = 'To: ' . date('M d, Y', strtotime($toDate));
                if ($branch && $branch != '0') $filters[] = 'Branch: #' . $branch;
                if ($program && $program != '0') $filters[] = 'Program: #' . $program;
                if ($intake && $intake != '0') $filters[] = 'Intake: #' . $intake;

                $sheet->setCellValue("A{$row}", $filterText . implode(' | ', $filters));
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(10);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $row += 2;
            }

            // Process data and calculate totals
            $totals = [
                'income' => 0,
                'tax' => 0,
                'penalty' => 0,
                'discount' => 0,
                'payable' => 0,
                'paid' => 0,
                'balance' => 0,
                'count' => 0,
                'currency' => ''
            ];

            $paymentData = [];
            while ($data = $results->fetch_assoc()) {
                $rate = resolveExchangeRate($data, (int)$data['course_currency_id']);

                // Calculate CP discount
                $cpDiscount = 0.0;
                if ($data['discount_type_discount_type_id'] == 3) {
                    $cpDiscount = (float)$data['amount'] / 100 * (float)$data['discount'];
                } elseif ($data['discount_type_discount_type_id'] == 2) {
                    $cpDiscount = (float)$data['discount'];
                }

                $receiptDiscount = (float)$data['discount_amount'];
                $totalDiscount = $cpDiscount + $receiptDiscount;

                // Calculate penalties
                $receiptPenaltyCc = toCourse((float)($data['receipt_penalty_paid_cur'] ?? 0), $rate);
                $penaltyLkr = (float)($data['penalty_lkr'] ?? 0);
                $isCourseLkr = ((int)$data['course_currency_id'] === 1);
                if ($isCourseLkr || $rate === null) {
                    $mainPenaltyCc = $penaltyLkr;
                } else {
                    $mainPenaltyCc = ($rate > 0) ? $penaltyLkr / $rate : $penaltyLkr;
                }
                $totalPenalty = $receiptPenaltyCc + $mainPenaltyCc;

                // Calculate tax
                $taxCc = toCourse((float)($data['receipt_tax_paid_cur'] ?? 0), $rate);

                // Calculate amounts
                $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $taxCc;
                $paidInCourse = toCourse((float)$data['paid_amount'], $rate);
                $balance = $payableAmount - $paidInCourse;

                $paymentData[] = [
                    'student_name' => $data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name'],
                    'program' => $data['course_name'],
                    'branch' => $data['branch_name'],
                    'intake' => $data['intake_name'],
                    'payment_type' => $data['payment_type'],
                    'amount' => (float)$data['amount'],
                    'discount' => $totalDiscount,
                    'tax' => $taxCc,
                    'penalty' => $totalPenalty,
                    'payable' => $payableAmount,
                    'paid' => $paidInCourse,
                    'balance' => $balance,
                    'date_paid' => $data['date_paid'],
                    'method' => $data['payment_method'],
                    'status' => $data['payment_status'],
                    'currency' => $data['course_currency_code'],
                    'decimal_places' => $data['course_decimal_places']
                ];

                // Accumulate totals
                $totals['income'] += $paidInCourse;
                $totals['tax'] += $taxCc;
                $totals['penalty'] += $totalPenalty;
                $totals['discount'] += $totalDiscount;
                $totals['payable'] += $payableAmount;
                $totals['paid'] += $paidInCourse;
                $totals['balance'] += $balance;
                $totals['count']++;
                if (empty($totals['currency'])) {
                    $totals['currency'] = $data['course_currency_code'];
                    $totals['decimal_places'] = $data['course_decimal_places'];
                }
            }

            // Summary Section Header
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", 'SUMMARY TOTALS');
            $sheet->getStyle("A{$row}")->getFont()->setSize(12)->setBold(true)->setColor(new Color($whiteFontColor));
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($summaryBg));
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimensions()[$row]->setRowHeight(22);
            $row++;

            $dp = isset($totals['decimal_places']) ? $totals['decimal_places'] : 2;
            $cc = $totals['currency'];

            // Summary Row 1 - Total Income
            $sheet->setCellValue("A{$row}", 'Total Income (Amount Paid):');
            $sheet->setCellValue("B{$row}", $totals['paid']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($incomeBg));
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"' . $cc . '" #,##0.' . str_repeat('0', $dp));
            $row++;

            // Summary Row 2 - Total Tax
            $sheet->setCellValue("A{$row}", 'Total Tax Collected:');
            $sheet->setCellValue("B{$row}", $totals['tax']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($taxBg));
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"' . $cc . '" #,##0.' . str_repeat('0', $dp));
            $row++;

            // Summary Row 3 - Total Penalty
            $sheet->setCellValue("A{$row}", 'Total Penalties Applied:');
            $sheet->setCellValue("B{$row}", $totals['penalty']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($penaltyBg));
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"' . $cc . '" #,##0.' . str_repeat('0', $dp));
            $row++;

            // Summary Row 4 - Total Discount
            $sheet->setCellValue("A{$row}", 'Total Discounts Applied:');
            $sheet->setCellValue("B{$row}", $totals['discount']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFDCDCDC'));
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"' . $cc . '" #,##0.' . str_repeat('0', $dp));
            $row++;

            // Summary Row 5 - Payable Amount
            $sheet->setCellValue("A{$row}", 'Total Payable Amount:');
            $sheet->setCellValue("B{$row}", $totals['payable']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FF70AD47'));
            $sheet->getStyle("A{$row}:B{$row}")->getFont()->setColor(new Color($whiteFontColor));
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"' . $cc . '" #,##0.' . str_repeat('0', $dp));
            $sheet->getRowDimensions()[$row]->setRowHeight(20);
            $row++;

            // Summary Row 6 - Outstanding Balance
            $sheet->setCellValue("A{$row}", 'Outstanding Balance:');
            $sheet->setCellValue("B{$row}", $totals['balance']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFF0000'));
            $sheet->getStyle("A{$row}:B{$row}")->getFont()->setColor(new Color($whiteFontColor));
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"' . $cc . '" #,##0.' . str_repeat('0', $dp));
            $sheet->getRowDimensions()[$row]->setRowHeight(20);
            $row++;

            // Summary Row 7 - Record Count
            $sheet->setCellValue("A{$row}", 'Total Records:');
            $sheet->setCellValue("B{$row}", $totals['count']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFC5D9F1'));
            $row += 2;

            // Detailed Records Section
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", 'DETAILED PAYMENT RECORDS');
            $sheet->getStyle("A{$row}")->getFont()->setSize(12)->setBold(true)->setColor(new Color($whiteFontColor));
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($headerBg));
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimensions()[$row]->setRowHeight(22);
            $row++;

            // Table Headers
            $headers = ['Student Name', 'Program', 'Branch', 'Intake', 'Type', 'Amount', 'Discount', 'Tax', 'Penalty', 'Payable'];
            $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

            foreach ($cols as $idx => $col) {
                $sheet->setCellValue("{$col}{$row}", $headers[$idx]);
                $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->setColor(new Color($whiteFontColor));
                $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($headerBg));
                $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            }
            $sheet->getRowDimensions()[$row]->setRowHeight(25);
            $row++;

            // Data Rows
            $rowAlt = false;
            foreach ($paymentData as $payment) {
                $sheet->setCellValue("A{$row}", htmlspecialchars($payment['student_name']));
                $sheet->setCellValue("B{$row}", htmlspecialchars($payment['program']));
                $sheet->setCellValue("C{$row}", htmlspecialchars($payment['branch']));
                $sheet->setCellValue("D{$row}", htmlspecialchars($payment['intake']));
                $sheet->setCellValue("E{$row}", htmlspecialchars($payment['payment_type']));
                $sheet->setCellValue("F{$row}", $payment['amount']);
                $sheet->setCellValue("G{$row}", $payment['discount']);
                $sheet->setCellValue("H{$row}", $payment['tax']);
                $sheet->setCellValue("I{$row}", $payment['penalty']);
                $sheet->setCellValue("J{$row}", $payment['payable']);

                // Format currency columns
                $currencyFormat = '"' . $cc . '" #,##0.' . str_repeat('0', $dp);
                $sheet->getStyle("F{$row}:J{$row}")->getNumberFormat()->setFormatCode($currencyFormat);

                // Alternate row colors
                if ($rowAlt) {
                    $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFF2F2F2'));
                }

                // Center align text columns
                $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("F{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Highlight balance if outstanding
                if ($payment['balance'] > 0.01) {
                    $sheet->getStyle("J{$row}")->getFont()->setColor(new Color('FFFF0000'))->setBold(true);
                }

                $rowAlt = !$rowAlt;
                $row++;
            }

            // Add borders to data table
            $$sheet->getStyle("A" . ($row - count($paymentData) - 1) . ":J{$row}")->getBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('FFC0C0C0'));
            // Freeze panes
            $sheet->freezePane('A2', 'B3');

            // Generate filename
            $filename = 'Payment_Summary_Report_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Save file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filepath = './temp/' . $filename;

            // Create temp directory if it doesn't exist
            if (!is_dir('./temp')) {
                mkdir('./temp', 0755, true);
            }

            $writer->save($filepath);

            // Return success with file path
            echo json_encode([
                'success' => true,
                'filename' => $filename,
                'message' => 'Report generated successfully',
                'filepath' => $filepath,
                'records' => $totals['count']
            ]);
        }
    }
}
