<?php
ob_start();

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../vendor/autoload.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/* ================================================================
   Lecturer Lecture Payment report (client format: Lecturer_payments.xlsx)

   Data source = the same query logic as lecturers-lecture-payments.php:
     - a "lecture" is one session per lecturer + date + start + end
       (concurrent batches are paid ONCE, so hours are counted once)
     - amount per lecture = lecturer_payments.amount ("Schedule Amt to Pay")
     - hours per lecture  = time_table.duration (minutes) / 60
     - hourly rate        = amount / hours
   One report row = lecturer + programme(s) + hourly rate.
   Amount to be transferred = hourly rate x hours (Excel formula, like the sample).
   ================================================================ */

// ---------------- CONFIG ----------------

// Text shown in the "Account for" column
define('ACCOUNT_FOR', 'AIC');

/*
 * Bank details source.
 * Leave EMPTY to auto-detect (looks in `lecturers`, `users` and common bank tables
 * for columns like bank_name, bank_code, branch_name, branch_code, account_number,
 * account_holder_name). If your bank details live in a specific table, set it here:
 *
 * $BANK_OVERRIDE = [
 *     'table'   => 'lecturer_bank_details',
 *     'key'     => 'lecturers_lecturers_id',   // column that links to the lecturer
 *     'type'    => 'lecturer',                 // 'lecturer' = lecturers.lecturers_id, 'user' = users.user_id
 *     'columns' => [
 *         'bank_name'      => 'bank_name',
 *         'bank_code'      => 'bank_code',
 *         'branch_name'    => 'branch_name',
 *         'branch_code'    => 'branch_code',
 *         'account_number' => 'account_number',
 *         'account_holder' => 'account_holder_name',
 *     ],
 * ];
 */
$BANK_OVERRIDE = [];

// ---------------- HELPERS ----------------

function putText($sheet, $cell, $value)
{
    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
}

/** columns + primary key of a table (empty when the table does not exist) */
function tableInfo($table)
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $info = ['cols' => [], 'pk' => null];
    try {
        $t = Database::search("SHOW TABLES LIKE '" . Database::escape($table) . "'");
        if ($t && $t->num_rows > 0) {
            $r = Database::search("SHOW COLUMNS FROM `" . $table . "`");
            while ($r && ($c = $r->fetch_assoc())) {
                $info['cols'][] = $c['Field'];
                if ($c['Key'] === 'PRI' && $info['pk'] === null) $info['pk'] = $c['Field'];
            }
        }
    } catch (Throwable $e) {
    }
    return $cache[$table] = $info;
}

function firstExisting(array $cols, array $candidates)
{
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

/**
 * Bank details per lecturer.
 * Returns ['lecturer' => [lecturers_id => [field => value]], 'user' => [user_id => [field => value]]]
 */
function loadBankDetails(array $lecturerIds, array $userIds, array $override)
{
    $fieldCandidates = [
        'bank_name'      => ['bank_name', 'bank'],
        'bank_code'      => ['bank_code'],
        'branch_name'    => ['branch_name', 'branch'],
        'branch_code'    => ['branch_code'],
        'account_number' => ['account_number', 'account_no', 'acc_no', 'bank_account_number', 'bank_account_no', 'account'],
        'account_holder' => ['account_holder_name', 'account_holder', 'account_name', 'holder_name', 'bank_account_name'],
    ];
    $keyCandidates = [
        ['lecturers_lecturers_id', 'lecturer'],
        ['lecturers_id', 'lecturer'],
        ['users_user_id', 'user'],
        ['user_id', 'user'],
    ];

    $sources = [];
    if ($override) {
        $sources[] = $override;
    } else {
        foreach (['lecturers', 'users', 'lecturer_bank_details', 'lecturers_bank_details', 'lecturer_bank_accounts', 'user_bank_details', 'bank_details'] as $t) {
            $info = tableInfo($t);
            if (!$info['cols']) continue;
            $key = null;
            $type = null;
            foreach ($keyCandidates as $kc) {
                if (in_array($kc[0], $info['cols'], true)) {
                    $key = $kc[0];
                    $type = $kc[1];
                    break;
                }
            }
            if ($key === null) continue;
            $map = [];
            foreach ($fieldCandidates as $field => $cands) {
                $col = firstExisting($info['cols'], $cands);
                if ($col !== null) $map[$field] = $col;
            }
            if ($map) $sources[] = ['table' => $t, 'key' => $key, 'type' => $type, 'columns' => $map];
        }
    }

    $out = ['lecturer' => [], 'user' => []];
    $resolved = [];

    foreach ($sources as $src) {
        // only fields not already filled by an earlier source
        $cols = [];
        foreach ($src['columns'] as $field => $col) {
            if (!isset($resolved[$field])) $cols[$field] = $col;
        }
        if (!$cols) continue;

        $ids = $src['type'] === 'lecturer' ? $lecturerIds : $userIds;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) continue;

        $info = tableInfo($src['table']);
        $select = "`{$src['key']}` AS k";
        foreach ($cols as $field => $col) $select .= ", `{$col}` AS `f_{$field}`";
        $order = $info['pk'] ? " ORDER BY `{$info['pk']}` ASC" : "";

        try {
            foreach (array_chunk($ids, 500) as $chunk) {
                $rs = Database::search("SELECT {$select} FROM `{$src['table']}` WHERE `{$src['key']}` IN (" . implode(",", $chunk) . ")" . $order);
                while ($rs && ($r = $rs->fetch_assoc())) {
                    foreach ($cols as $field => $col) {
                        $v = trim((string) $r['f_' . $field]);
                        if ($v !== '') $out[$src['type']][(int) $r['k']][$field] = $v;
                    }
                }
            }
        } catch (Throwable $e) {
            continue; // unusable source -> try the next one
        }
        foreach ($cols as $field => $col) $resolved[$field] = true;
    }

    return $out;
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

    if (json_last_error() != JSON_ERROR_NONE || !is_array($decoded)) {
        http_response_code(400);
        throw new Exception("Invalid JSON");
    }

    set_time_limit(0);
    ini_set('memory_limit', '1024M');
    date_default_timezone_set('Asia/Colombo');

    // Payload (filter ids on lecturers-lecture-payments.php)
    $text     = isset($decoded['text']) ? Database::escape(trim((string) $decoded['text'])) : "";
    $lecturer = (int) ($decoded['lecturer'] ?? 0);   // users.user_id
    $from     = (string) ($decoded['from'] ?? '');
    $to       = (string) ($decoded['to'] ?? '');
    $filter   = (int) ($decoded['filter'] ?? 0);     // 0 all | 1 Pending | 2 Scheduled | 3 Cancelled | 4 Completed

    $validDate = function ($d) {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    };

    /* ----------------------------------------------------------
       Query (same joins / conditions as the page, but NOT grouped,
       so every batch's subject + programme is available)
       ---------------------------------------------------------- */
    $subCols = tableInfo('subjects')['cols'];
    $subNameCol = firstExisting($subCols, ['subject', 'subject_name', 'name', 'subject_title']);
    $subCodeCol = firstExisting($subCols, ['subject_code', 'code', 'module_code']);
    $subNameExpr = $subNameCol ? "`subjects`.`{$subNameCol}`" : "''";
    $subCodeExpr = $subCodeCol ? "`subjects`.`{$subCodeCol}`" : "''";

    $query = "SELECT
            tt.time_table_id,
            tt.date,
            tt.start_time,
            tt.end_time,
            tt.duration,
            tt.lecture_title,
            tt.lecturers_lecturers_id AS lecturer_id,
            users.user_id,
            users.user_fname,
            users.user_lname,
            title.title,
            {$subNameExpr} AS subject_name,
            {$subCodeExpr} AS subject_code,
            programmes.programme,
            lecturer_payments.lecturer_payments_id,
            lecturer_payments.amount,
            lecturer_payments.earning_status_earning_status_id AS earning_status_id
        FROM lecturer_payments
        INNER JOIN `lecturer_payments_has_lectures` 
        ON `lecturer_payments`.`lecturer_payments_id` = `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`

        INNER JOIN `time_table` AS `tt` ON `tt`.`time_table_id` = `lecturer_payments_has_lectures`.`time_table_time_table_id`
        INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `tt`.`lecturers_lecturers_id`
        INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
        INNER JOIN `subjects` ON `subjects`.`subject_id` = `tt`.`subjects_subject_id`
        INNER JOIN `levels` ON `levels`.`level_id` = `subjects`.`levels_level_id`
        INNER JOIN `intakes` ON `intakes`.`intake_id` = `levels`.`intakes_intake_id`
        INNER JOIN `programmes` ON `programmes`.`programme_id` = `intakes`.`programmes_programme_id`
        WHERE tt.time_table_status_time_table_status_id = '1'
        AND tt.lecture_type_lecture_type_id != 3
        AND CONCAT(tt.date, ' ', tt.end_time) <= '" . date('Y-m-d H:i:s') . "'";

    if ($lecturer > 0) {
        $query .= " AND users.user_id = {$lecturer}";
    }
    if ($validDate($from)) $query .= " AND tt.date >= '{$from}'";
    if ($validDate($to))   $query .= " AND tt.date <= '{$to}'";
    if ($text !== '') {
        $query .= " AND (CONCAT(users.user_fname, ' ', users.user_lname) LIKE '%{$text}%'
                    OR programmes.programme LIKE '%{$text}%'
                    OR tt.lecture_title LIKE '%{$text}%')";
    }
    $query .= "GROUP BY lecturer_payments.`lecturer_payments_id`, tt.date, tt.start_time, tt.end_time ORDER BY users.user_fname ASC, users.user_lname ASC, tt.date ASC, tt.start_time ASC";

    $rs = Database::search($query);

    /* ----------------------------------------------------------
       1) rows -> lecture sessions (lecturer + date + start + end)
       ---------------------------------------------------------- */
    $sessions = [];
    while ($rs && ($r = $rs->fetch_assoc())) {
        $key = $r['lecturer_id'] . '|' . $r['date'] . '|' . $r['start_time'] . '|' . $r['end_time'];
        if (!isset($sessions[$key])) {
            $sessions[$key] = [
                'user_id'     => (int) $r['user_id'],
                'lecturer_id' => (int) $r['lecturer_id'],
                'name'        => trim($r['title'] . ". " . $r['user_fname'] . " " . $r['user_lname']),
                'minutes'     => 0,
                'programmes'  => [],
                'modules'     => [],
                'payments'    => [],
            ];
        }
        $s = &$sessions[$key];

        $minutes = (int) $r['duration'];
        if ($minutes <= 0) {
            $minutes = (int) round((strtotime($r['end_time']) - strtotime($r['start_time'])) / 60);
        }
        if ($minutes > $s['minutes']) $s['minutes'] = $minutes;

        if (trim((string) $r['programme']) !== '') $s['programmes'][trim($r['programme'])] = true;

        $name = trim((string) $r['subject_name']);
        $code = trim((string) $r['subject_code']);
        $module = $name !== '' ? ($code !== '' ? $code . ' - ' . $name : $name) : trim((string) $r['lecture_title']);
        if ($module !== '') $s['modules'][$module] = true;

        if ($r['lecturer_payments_id'] !== null) {
            $s['payments'][(int) $r['lecturer_payments_id']] = [
                'amount' => (float) $r['amount'],
                'est'    => (int) $r['earning_status_id'],
            ];
        }
        unset($s);
    }

    /* ----------------------------------------------------------
       2) pick the payment of every session, apply the status filter,
          group into report rows (lecturer + programmes + hourly rate)
       ---------------------------------------------------------- */
    $lecturers = [];
    foreach ($sessions as $s) {
        // prefer a live payment (1 scheduled / 2 completed) over a cancelled (3) one
        $pay = null;
        krsort($s['payments']);
        foreach ($s['payments'] as $p) {
            if ($p['est'] === 1 || $p['est'] === 2) {
                $pay = $p;
                break;
            }
        }
        if ($pay === null && $s['payments']) $pay = reset($s['payments']);

        $est = $pay ? $pay['est'] : null;
        $amount = $pay ? $pay['amount'] : 0.0;

        // status filter (same values as the page's filter select)
        if ($filter === 0 && !($est === 1 || $est === 2)) continue; // default: scheduled + completed (an amount to transfer)
        if ($filter === 1 && $est !== null) continue;               // pending approval
        if ($filter === 2 && $est !== 1) continue;                  // scheduled
        if ($filter === 3 && $est !== 3) continue;                  // cancelled
        if ($filter === 4 && $est !== 2) continue;                  // completed

        $hours = $s['minutes'] / 60;
        $rate = $hours > 0 ? round($amount / $hours, 2) : 0.0;

        $programmes = array_keys($s['programmes']);
        sort($programmes);
        $gKey = implode(' | ', $programmes) . '#' . number_format($rate, 2, '.', '');

        $uid = $s['user_id'];
        if (!isset($lecturers[$uid])) {
            $lecturers[$uid] = ['name' => $s['name'], 'lecturer_id' => $s['lecturer_id'], 'groups' => []];
        }
        if (!isset($lecturers[$uid]['groups'][$gKey])) {
            $lecturers[$uid]['groups'][$gKey] = [
                'programmes' => $programmes,
                'modules'    => [],
                'rate'       => $rate,
                'minutes'    => 0,
            ];
        }
        $g = &$lecturers[$uid]['groups'][$gKey];
        foreach (array_keys($s['modules']) as $m) $g['modules'][$m] = true;
        $g['minutes'] += $s['minutes'];
        unset($g);
    }

    uasort($lecturers, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    $bank = loadBankDetails(
        array_column($lecturers, 'lecturer_id'),
        array_keys($lecturers),
        $BANK_OVERRIDE
    );

    /* ----------------------------------------------------------
       3) sheet (layout copied from Lecturer_payments.xlsx)
       ---------------------------------------------------------- */
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Lecturer Payments');
    $spreadsheet->getDefaultStyle()->getFont()->setName('Cambria')->setSize(11);

    $headers = [
        'A' => ['No', 6],
        'B' => ['Lecturer/ Teacher', 28],
        'C' => ['Module teaching', 62],
        'D' => ['Programme', 42],
        'E' => ['Account for', 13],
        'F' => ['Hourly rate (LKR)/ Payment plan', 17],
        'G' => ['No of hours teaching', 15],
        'H' => ['Bank Name', 25],
        'I' => ['Bank Code', 12],
        'J' => ['Branch Name', 20],
        'K' => ['Branch Code', 12],
        'L' => ['Account Number', 20],
        'M' => ['Account Holder Name', 28],
        'N' => ['Amount to be transferred (LKR)', 19],
    ];
    foreach ($headers as $col => $h) {
        putText($sheet, $col . '1', $h[0]);
        $sheet->getColumnDimension($col)->setWidth($h[1]);
    }
    $sheet->getStyle("A1:N1")->getFont()->setBold(true);
    $sheet->getStyle("A1:N1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
    $sheet->getStyle("A1:N1")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getRowDimension(1)->setRowHeight(55);

    $row = 2;
    $no = 1;

    foreach ($lecturers as $uid => $lec) {
        $b = $bank['lecturer'][$lec['lecturer_id']] ?? [];
        $bu = $bank['user'][$uid] ?? [];
        $bankVal = function ($field) use ($b, $bu) {
            return $b[$field] ?? ($bu[$field] ?? '');
        };

        $groups = $lec['groups'];
        uasort($groups, function ($x, $y) {
            return strcmp(implode('|', $x['programmes']), implode('|', $y['programmes'])) ?: ($y['rate'] <=> $x['rate']);
        });

        $startRow = $row;
        foreach ($groups as $g) {
            putText($sheet, "C$row", implode(', ', array_keys($g['modules'])));
            putText($sheet, "D$row", implode(', ', $g['programmes']));
            putText($sheet, "E$row", ACCOUNT_FOR);
            $sheet->setCellValueExplicit("F$row", (float) $g['rate'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("G$row", round($g['minutes'] / 60, 2), DataType::TYPE_NUMERIC);
            putText($sheet, "H$row", $bankVal('bank_name'));
            putText($sheet, "I$row", $bankVal('bank_code'));
            putText($sheet, "J$row", $bankVal('branch_name'));
            putText($sheet, "K$row", $bankVal('branch_code'));
            putText($sheet, "L$row", $bankVal('account_number'));
            putText($sheet, "M$row", $bankVal('account_holder'));
            $sheet->setCellValueExplicit("N$row", "=F$row*G$row", DataType::TYPE_FORMULA);

            $sheet->getStyle("F$row")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("G$row")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("N$row")->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }
        $endRow = $row - 1;

        $sheet->setCellValueExplicit("A$startRow", $no, DataType::TYPE_NUMERIC);
        putText($sheet, "B$startRow", $lec['name']);
        if ($endRow > $startRow) {
            $sheet->mergeCells("A{$startRow}:A{$endRow}");
            $sheet->mergeCells("B{$startRow}:B{$endRow}");
        }
        $no++;
    }

    $lastDataRow = $row - 1;

    if ($lastDataRow >= 2) {
        $dataRange = "A2:N{$lastDataRow}";
        $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A2:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B2:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        $sheet->getStyle("C2:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        $sheet->getStyle("E2:G{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("I2:L{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M2:M{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("N2:N{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("N2:N{$lastDataRow}")->getFont()->setBold(true);

        // Total row
        $totalRow = $lastDataRow + 1;
        $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
        putText($sheet, "A{$totalRow}", "Total");
        $sheet->setCellValueExplicit("G{$totalRow}", "=SUM(G2:G{$lastDataRow})", DataType::TYPE_FORMULA);
        $sheet->setCellValueExplicit("N{$totalRow}", "=SUM(N2:N{$lastDataRow})", DataType::TYPE_FORMULA);
        $sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("N{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A{$totalRow}:N{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:N{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
        $sheet->getStyle("A{$totalRow}:N{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle("A1:N{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Note line under the table
        $userData = Database::search("SELECT * FROM `users`
            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
            WHERE `user_id`='" . (int) $sessionUserObject['user_id'] . "'")->fetch_assoc();
        $statusNames = [0 => 'Scheduled + Completed', 1 => 'Pending Approval', 2 => 'Scheduled', 3 => 'Cancelled', 4 => 'Completed'];
        $period = ($validDate($from) || $validDate($to))
            ? ($validDate($from) ? "From " . $from : "") . ($validDate($to) ? " To " . $to : "")
            : "All";
        $note = "Period : " . trim($period)
            . "   |   Payment Status : " . ($statusNames[$filter] ?? 'All')
            . "   |   Generated By : " . $userData['title'] . "." . $userData['user_fname'] . " " . $userData['user_lname']
            . "   |   Generated On : " . date("Y-m-d h:i A");
        $noteRow = $totalRow + 2;
        putText($sheet, "A{$noteRow}", $note);
        $sheet->getStyle("A{$noteRow}")->getFont()->setItalic(true)->setSize(10);
    } else {
        $sheet->getStyle("A1:N1")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    $sheet->freezePane("C2");
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

    $fileName = "lecturer_payments_" . date('Ymd_His') . ".xlsx";

    ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
    echo json_encode([
        "error" => $e->getMessage()
    ]);
    exit;
}
