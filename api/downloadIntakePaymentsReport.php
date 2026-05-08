<?php
require_once "../includes/connection.php";
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {

            $id = isset($decoded["intake_id"]) ? (int)$decoded["intake_id"] : 0;

            $query = "SELECT
    cp.course_payments_id,
    ps.payment_status,
    ps.payment_status_id,
    cp.due_date,
    cp.amount,
    cp.discount,
    cp.discount_type_discount_type_id,
    shi.student_id,
    ld.first_name,
    ld.last_name,
    t.title,
    c.code,
    c.decimal_places,
    pt.payment_type,
    IFNULL(pp.panelty_amount, 0) AS panelty_amount,
    IFNULL(cpp.paid_amount, 0) AS paid_amount,
    IFNULL(cpp.total_discount, 0) AS total_discount,
    IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
FROM course_payments AS cp
INNER JOIN payment_status AS ps 
    ON cp.payment_status_payment_status_id = ps.payment_status_id
INNER JOIN payment_plan_has_students_has_intakes AS pshi 
    ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
INNER JOIN payment_category AS pc
    ON pshi.payment_category_payment_category_id = pc.payment_category_id
INNER JOIN currencies AS c
    ON pc.currencies_currency_id = c.currency_id
INNER JOIN payment_type AS pt
    ON cp.payment_type_payment_type_id = pt.payment_type_id
INNER JOIN students_has_intakes AS shi 
    ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
INNER JOIN students AS s 
    ON shi.students_student_id = s.student_id
INNER JOIN login_details AS ld
    ON s.login_details_login_details_id = ld.login_details_id
INNER JOIN title AS t 
    ON s.title_title_id = t.title_id
LEFT JOIN (
    SELECT
        course_payments_course_payments_id,
        SUM(amount) AS panelty_amount
    FROM panelty_payments
    GROUP BY course_payments_course_payments_id
) pp
    ON cp.course_payments_id = pp.course_payments_course_payments_id
LEFT JOIN (
    SELECT
        course_payments_course_payments_id,
        SUM(paid_amount) AS paid_amount,
        SUM(discount_amount) AS total_discount,
        SUM(panelty_amount) AS total_sub_panelty_amount
    FROM course_payment_has_payments
    WHERE payment_status_payment_status_id IN (2,3,5,6,8)
    GROUP BY course_payments_course_payments_id
) cpp
    ON cp.course_payments_id = cpp.course_payments_course_payments_id
WHERE shi.intakes_intake_id = '$id'
GROUP BY cp.course_payments_id
ORDER BY cp.due_date ASC";

            $result = Database::search($query);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle("Payments Report");

            /* =========================
   PAGE SETUP
========================= */

            $sheet->getPageSetup()
                ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

            $sheet->getPageMargins()->setTop(0.25);
            $sheet->getPageMargins()->setRight(0.25);
            $sheet->getPageMargins()->setLeft(0.25);
            $sheet->getPageMargins()->setBottom(0.25);

            $sheet->getDefaultRowDimension()->setRowHeight(22);

            /* =========================
LOGO + TITLE
========================= */

            $logoPath = '../assets/imgs/common/logo.png'; // change this path

            // Row heights
            $sheet->getRowDimension(1)->setRowHeight(65);
            $sheet->getRowDimension(2)->setRowHeight(24);
            $sheet->getRowDimension(3)->setRowHeight(22);

            // Merge only center area for logo
            $sheet->mergeCells('A1:L1');

            if (file_exists($logoPath)) {
                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $drawing->setName('SWLC Logo');
                $drawing->setPath($logoPath);
                $drawing->setHeight(70);

                // Center area anchor
                $drawing->setCoordinates('F1');

                // Fine tune
                $drawing->setOffsetX(12);
                $drawing->setOffsetY(4);

                $drawing->setWorksheet($sheet);
                $sheet->getStyle('A1')->getAlignment();
            }

            // Main title
            $sheet->mergeCells('A2:L2');
            $sheet->setCellValue('A2', 'Times Academy Intake Payments Reports');
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            // Sub title
            $sheet->mergeCells('A3:L3');
            $sheet->setCellValue('A3',  "Generated Date: " . date("Y-m-d h:i A"));
            $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('A3')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);/* =========================
HEADERS
========================= */

            $headers = [
                "#",
                "Student ID",
                "Student Name",
                "Payment Type",
                "Due Date",
                "Amount",
                "Discount",
                "Penalty",
                "Payable",
                "Paid",
                "Balance",
                "Status"
            ];

            $headerRow = 5;
            $col = "A";

            foreach ($headers as $header) {
                $sheet->setCellValue($col . $headerRow, $header);
                $col++;
            }

            $sheet->getStyle("A5:L5")->applyFromArray([
                "font" => [
                    "bold" => true,
                    "color" => ["rgb" => "FFFFFF"]
                ],
                "fill" => [
                    "fillType" => Fill::FILL_SOLID,
                    "startColor" => ["rgb" => "F0C71F"]
                ],
                "alignment" => [
                    "horizontal" => Alignment::HORIZONTAL_CENTER,
                    "vertical" => Alignment::VERTICAL_CENTER
                ],
                "borders" => [
                    "allBorders" => [
                        "borderStyle" => Border::BORDER_THIN,
                        "color" => ["rgb" => "f3f0d9"]
                    ]
                ]
            ]);

            $rowNo = 6;
            $count = 1;

            $totalAmount = 0;
            $totalDiscount = 0;
            $totalPenalty = 0;
            $totalPayable = 0;
            $totalPaid = 0;
            $totalBalance = 0;
            $overdueCount = 0;

            while ($data = $result->fetch_assoc()) {

                $discountAmount = (float)$data["discount"];

                if ((int)$data["discount_type_discount_type_id"] == 3) {
                    $discountAmount = ((float)$data["amount"] / 100) * (float)$data["discount"];
                }

                $totalDiscountAmount = $discountAmount + (float)$data["total_discount"];
                $totalPenaltyAmount = (float)$data["panelty_amount"] + (float)$data["total_sub_panelty_amount"];

                $payableAmount =
                    (float)$data["amount"]
                    - $discountAmount
                    - (float)$data["total_discount"]
                    + (float)$data["panelty_amount"]
                    + (float)$data["total_sub_panelty_amount"];

                $balance = $payableAmount - (float)$data["paid_amount"];

                $isOverdue = false;

                if (!empty($data["due_date"]) && strtotime($data["due_date"]) < strtotime(date("Y-m-d")) && $balance > 0) {
                    $isOverdue = true;
                    $overdueCount++;
                }

                $status = $data["payment_status"];

                if ($balance <= 0) {
                    $status = "Paid";
                } elseif ($isOverdue) {
                    $status = "Overdue";
                }

                $studentName = trim($data["title"] . " " . $data["first_name"] . " " . $data["last_name"]);

                $sheet->setCellValue("A" . $rowNo, $count);
                $sheet->setCellValue("B" . $rowNo, $data["student_id"]);
                $sheet->setCellValue("C" . $rowNo, $studentName);
                $sheet->setCellValue("D" . $rowNo, $data["payment_type"]);
                $sheet->setCellValue("E" . $rowNo, !empty($data["due_date"]) ? date("Y-m-d", strtotime($data["due_date"])) : "-");
                $sheet->setCellValue("F" . $rowNo, (float)$data["amount"]);
                $sheet->setCellValue("G" . $rowNo, $totalDiscountAmount);
                $sheet->setCellValue("H" . $rowNo, $totalPenaltyAmount);
                $sheet->setCellValue("I" . $rowNo, $payableAmount);
                $sheet->setCellValue("J" . $rowNo, (float)$data["paid_amount"]);
                $sheet->setCellValue("K" . $rowNo, $balance);
                $sheet->setCellValue("L" . $rowNo, $status);

                if ($isOverdue) {
                    $sheet->getStyle("A{$rowNo}:L{$rowNo}")->applyFromArray([
                        "fill" => [
                            "fillType" => Fill::FILL_SOLID,
                            "startColor" => ["rgb" => "FDE2E2"]
                        ],
                        "font" => [
                            "color" => ["rgb" => "B91C1C"]
                        ]
                    ]);
                }

                if ($balance <= 0) {
                    $sheet->getStyle("L{$rowNo}")->getFont()->getColor()->setRGB("15803D");
                } elseif ($isOverdue) {
                    $sheet->getStyle("L{$rowNo}")->getFont()->getColor()->setRGB("DC2626");
                }

                $totalAmount += (float)$data["amount"];
                $totalDiscount += $totalDiscountAmount;
                $totalPenalty += $totalPenaltyAmount;
                $totalPayable += $payableAmount;
                $totalPaid += (float)$data["paid_amount"];
                $totalBalance += $balance;

                $rowNo++;
                $count++;
            }

            $totalRow = $rowNo;

            $sheet->setCellValue("A" . $totalRow, "TOTAL");
            $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
            $sheet->setCellValue("F" . $totalRow, $totalAmount);
            $sheet->setCellValue("G" . $totalRow, $totalDiscount);
            $sheet->setCellValue("H" . $totalRow, $totalPenalty);
            $sheet->setCellValue("I" . $totalRow, $totalPayable);
            $sheet->setCellValue("J" . $totalRow, $totalPaid);
            $sheet->setCellValue("K" . $totalRow, $totalBalance);
            $sheet->setCellValue("L" . $totalRow, "");

            $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
                "font" => [
                    "bold" => true
                ],
                "fill" => [
                    "fillType" => Fill::FILL_SOLID,
                    "startColor" => ["rgb" => "F3F4F6"]
                ],
                "borders" => [
                    "allBorders" => [
                        "borderStyle" => Border::BORDER_THIN,
                        "color" => ["rgb" => "D1D5DB"]
                    ]
                ]
            ]);

            $summaryStart = $totalRow + 3;

            $sheet->setCellValue("A" . $summaryStart, "Summary");
            $sheet->getStyle("A" . $summaryStart)->getFont()->setBold(true)->setSize(13);

            $sheet->setCellValue("A" . ($summaryStart + 1), "Total Records");
            $sheet->setCellValue("B" . ($summaryStart + 1), $count - 1);

            $sheet->setCellValue("A" . ($summaryStart + 2), "Overdue Payments");
            $sheet->setCellValue("B" . ($summaryStart + 2), $overdueCount);

            $sheet->setCellValue("A" . ($summaryStart + 3), "Total Payable");
            $sheet->setCellValue("B" . ($summaryStart + 3), $totalPayable);

            $sheet->setCellValue("A" . ($summaryStart + 4), "Total Paid");
            $sheet->setCellValue("B" . ($summaryStart + 4), $totalPaid);

            $sheet->setCellValue("A" . ($summaryStart + 5), "Total Balance");
            $sheet->setCellValue("B" . ($summaryStart + 5), $totalBalance);

            $sheet->getStyle("A{$summaryStart}:B" . ($summaryStart + 5))->applyFromArray([
                "borders" => [
                    "allBorders" => [
                        "borderStyle" => Border::BORDER_THIN,
                        "color" => ["rgb" => "D1D5DB"]
                    ]
                ]
            ]);

            $sheet->getStyle("A" . ($summaryStart + 1) . ":A" . ($summaryStart + 5))->getFont()->setBold(true);

            $sheet->getStyle("F6:K{$totalRow}")
                ->getNumberFormat()
                ->setFormatCode("#,##0.00");

            $sheet->getStyle("A5:L{$totalRow}")->applyFromArray([
                "borders" => [
                    "allBorders" => [
                        "borderStyle" => Border::BORDER_THIN,
                        "color" => ["rgb" => "E5E7EB"]
                    ]
                ],
                "alignment" => [
                    "vertical" => Alignment::VERTICAL_CENTER
                ]
            ]);

            foreach (range("A", "L") as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // $sheet->freezePane("A6");

            $filename = "SWLC_Intake_Students_Payments_Report_" . date("Ymd_His") . ".xlsx";

            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header("Cache-Control: max-age=0");

            $writer = new Xlsx($spreadsheet);
            $writer->save("php://output");
            exit;
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

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
