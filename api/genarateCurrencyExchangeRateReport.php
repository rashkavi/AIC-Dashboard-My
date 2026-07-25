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

    $currencyID = isset($decoded['currency']) ? Database::escape($decoded['currency']) : "";
    $actionType = isset($decoded['actionType']) ? Database::escape($decoded['actionType']) : "";
    $filter     = isset($decoded['filter']) ? Database::escape($decoded['filter']) : ""; // 1 = this month, 2 = last month, 3 = custom range
    $from       = isset($decoded['from']) ? Database::escape($decoded['from']) : "";
    $to         = isset($decoded['to']) ? Database::escape($decoded['to']) : "";

    if (empty($currencyID)) {
        throw new Exception("Currency is required");
    }

    date_default_timezone_set('Asia/Colombo');

    $spreadsheet = IOFactory::load("../assets/uploads/reports/currency-exchange-rate-report.xlsx");
    $sheet = $spreadsheet->getActiveSheet();

    /*
        Layout (matches the uploaded template):
        - B1 Title (merged B1:D1)
        - B3 "Genarated By" label / D3 value
        - B4 "Genarated On" label / D4 value
        - B6 "Period" label (merged B6:C6) / D6 value
        - Headers on row 8, data from row 9.
        - B Currency Pair, C Effective Date, D Previous Rate, E New Rate,
          F Action, G Updated By, H Updated At.
    */
    $headerRow = 8;
    $row = $headerRow + 1;

    $query = "
        SELECT

            cerl.currency_exchange_rate_log_id,
            cerl.effective_date,
            cerl.old_rate,
            cerl.new_rate,
            cerl.action_type,
            cerl.note,
            cerl.date_added,

            cer.currency_exchange_rate_id,
            bc.symbol,

            bc.code AS base_currency_code,
            bc.symbol AS base_currency_symbol,

            tc.code AS target_currency_code,
            tc.symbol AS target_currency_symbol,

            users.user_fname,
            users.user_lname

        FROM currency_exchange_rate_logs cerl

        INNER JOIN currency_exchange_rates cer
            ON cerl.currency_exchange_rates_currency_exchange_rate_id = cer.currency_exchange_rate_id

        INNER JOIN currencies bc
            ON cer.base_currency_id = bc.currency_id

        INNER JOIN currencies tc
            ON cer.target_currency_id = tc.currency_id

        LEFT JOIN users
            ON cerl.updated_by = users.user_id

        WHERE bc.currency_id = '$currencyID'
    ";

    if (!empty($actionType) && $actionType != '0') {
        $query .= " AND cerl.action_type = '$actionType' ";
    }

    $periodLabel = "All";
    if (!empty($filter) && $filter == '1') {
        $query .= " AND MONTH(cerl.effective_date) = MONTH(CURDATE())
                AND YEAR(cerl.effective_date) = YEAR(CURDATE())";
        $periodLabel = "This Month";
    } elseif (!empty($filter) && $filter == '2') {
        $query .= " AND MONTH(cerl.effective_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND YEAR(cerl.effective_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $periodLabel = "Last Month";
    } elseif (!empty($filter) && $filter == '3') {
        if (!empty($from)) {
            $query .= " AND DATE(cerl.effective_date) >= '$from' ";
        }
        if (!empty($to)) {
            $query .= " AND DATE(cerl.effective_date) <= '$to' ";
        }
        $periodLabel = "Custom Range" . ($from !== "" ? " : From " . $from : "") . ($to !== "" ? " - To " . $to : "");
    }

    $query .= " ORDER BY cerl.date_added DESC ";

    $results = Database::search($query);

    if (!$results || $results->num_rows === 0) {
        throw new Exception("No exchange rate records found for the selected filters");
    }

    $currencyPairLabel = "";
    $rowCount = 0;

    while ($data = $results->fetch_assoc()) {

        if ($currencyPairLabel === "") {
            $currencyPairLabel = $data['base_currency_code'] . " / " . $data['target_currency_code'];
        }

        $updatedBy = (!empty($data['user_fname']) || !empty($data['user_lname']))
            ? trim($data['user_fname'] . ' ' . $data['user_lname'])
            : "System";

        $sheet->setCellValue("B$row", $data['base_currency_code'] . " / " . $data['target_currency_code']);
        $sheet->setCellValue("C$row", !empty($data['effective_date']) ? date("Y-m-d", strtotime($data['effective_date'])) : "");
        $sheet->setCellValue("D$row", round((float)$data['old_rate'], 4));
        $sheet->setCellValue("E$row", round((float)$data['new_rate'], 4));
        $sheet->setCellValue("F$row", ucfirst($data['action_type']));
        $sheet->setCellValue("G$row", $updatedBy);
        $sheet->setCellValue("H$row", !empty($data['date_added']) ? date("Y-m-d h:i A", strtotime($data['date_added'])) : "");

        $sheet->getStyle("B$row:H$row")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $rowCount++;
        $row++;
    }

    $lastDataRow = $row - 1;

    $userData = Database::search("SELECT * FROM `users`
        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
        WHERE `user_id`='" . $sessionUserObject['user_id'] . "'")->fetch_assoc();

    $sheet->setCellValue("D3", $userData['title'] . " " . $userData['user_fname'] . " " . $userData['user_lname']);
    $sheet->setCellValue("D4", date("Y-m-d h:i A"));
    $sheet->setCellValue("D6", $periodLabel);

    if ($lastDataRow >= $headerRow + 1) {
        $totalRow = $lastDataRow + 2;
        $sheet->setCellValue("B{$totalRow}", "Total No of Rate Changes : " . str_pad($rowCount, 2, '0', STR_PAD_LEFT));
        $sheet->getStyle("B{$totalRow}")->getFont()->setBold(true);
    }

    $fileName = "currency_exchange_rate_report_" . date('Ymd_His') . ".xlsx";

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
