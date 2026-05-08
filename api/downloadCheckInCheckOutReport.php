<?php
require_once("../vendor/autoload.php");
require_once("../includes/connection.php");
require_once("../includes/UserClass.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

date_default_timezone_set("Asia/Colombo");
$status = new stdClass();

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    header('Content-Type: application/json');
    $status->error = "Authentication Failed!";
    echo json_encode($status);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    $status->error = "Invalid request method";
    echo json_encode($status);
    exit;
}

$contentType = $_SERVER["CONTENT_TYPE"] ?? "";
if (stripos($contentType, "application/json") === false) {
    http_response_code(400);
    header('Content-Type: application/json');
    $status->error = "Invalid content type";
    echo json_encode($status);
    exit;
}

$payload = json_decode(file_get_contents("php://input"), true);
if (!$payload) {
    http_response_code(400);
    header('Content-Type: application/json');
    $status->error = "Invalid JSON";
    echo json_encode($status);
    exit;
}

/*
|--------------------------------------------------------------------------
| Input values
|--------------------------------------------------------------------------
*/
$facultyId    = (int)($payload["faculty"] ?? 0);
$programmeId  = (int)($payload["programme"] ?? 0);
$intakeId     = (int)($payload["intake"] ?? 0);
$monthValue   = trim($payload["month"] ?? date("Y-m"));

if (empty($facultyId) || empty($programmeId) || empty($intakeId) || empty($monthValue)) {
    if (empty($facultyId)) {
        $status->faculty = "Faculty is required";
    }
    if (empty($programmeId)) {
        $status->programme = "Programme is required";
    }
    if (empty($intakeId)) {
        $status->intake = "Intake is required";
    }
    if(empty($monthValue)){
        $status->month = "Month is required";
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode($status);
    exit;
}

if (empty($monthValue) || !preg_match('/^\d{4}-\d{2}$/', $monthValue)) {
    $monthValue = date("Y-m");
}

$monthStart = $monthValue . "-01";
$monthEnd   = date("Y-m-t", strtotime($monthStart));
$monthName  = strtoupper(date("F", strtotime($monthStart)));
$yearName   = date("Y", strtotime($monthStart));

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function col($index)
{
    return Coordinate::stringFromColumnIndex($index);
}

function ordinalLabel($num)
{
    if ($num == 1) return "01st Week";
    if ($num == 2) return "02nd Week";
    if ($num == 3) return "03rd Week";
    if ($num == 4) return "04th Week";
    return "05th Week";
}

function applyThinBorder($sheet, $range)
{
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

function setCenter($sheet, $range)
{
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}

function setFillColor($sheet, $range, $color)
{
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
}

function getPercentageBandColor($percentage)
{
    if ($percentage >= 90) return '00B050'; // Green
    if ($percentage >= 80) return '5B9BD5'; // Blue
    if ($percentage >= 60) return 'FFFF00'; // Yellow
    return 'FF0000'; // Red
}

function getWeekNumberInMonth($date)
{
    $day = (int)date('j', strtotime($date));
    return (int)ceil($day / 7);
}

function isWeekday($date)
{
    $n = (int)date('N', strtotime($date)); // 1=Mon ... 7=Sun
    return $n <= 5;
}


/*
|--------------------------------------------------------------------------
| Intake / batch details
|--------------------------------------------------------------------------
*/
$batchInfoQuery = "
    SELECT 
        i.intake_id,
        i.intake,
        p.programme,
        f.faculty
    FROM `intakes` i
    INNER JOIN `programmes` p ON i.`programmes_programme_id` = p.`programme_id`
    INNER JOIN `faculties` f ON p.`faculties_faculty_id` = f.`faculty_id`
    WHERE 1 = 1
";

if ($intakeId > 0) {
    $batchInfoQuery .= " AND i.`intake_id` = '" . $intakeId . "'";
}
if ($programmeId > 0) {
    $batchInfoQuery .= " AND p.`programme_id` = '" . $programmeId . "'";
}
if ($facultyId > 0) {
    $batchInfoQuery .= " AND f.`faculty_id` = '" . $facultyId . "'";
}
$batchInfoQuery .= " ORDER BY i.`intake_id` DESC LIMIT 1";

$batchInfoResult = Database::search($batchInfoQuery);
if ($batchInfoResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "No intake / batch found"]);
    exit;
}
$batchInfo = $batchInfoResult->fetch_assoc();
$displayBatch = $batchInfo["intake"] ?? "-";

/*
|--------------------------------------------------------------------------
| Students list
|--------------------------------------------------------------------------
*/
$studentQuery = "
    SELECT 
        shi.`students_has_intakes_id`,
        shi.`student_id`,
        s.`full_name`,
        s.`name_with_initials`,
        ld.`first_name`,
        ld.`last_name`,
        i.`intake`
    FROM `students_has_intakes` shi
    INNER JOIN `students` s 
        ON shi.`students_student_id` = s.`student_id`
    INNER JOIN `login_details` ld
        ON s.`login_details_login_details_id` = ld.`login_details_id`
    INNER JOIN `intakes` i
        ON shi.`intakes_intake_id` = i.`intake_id`
    INNER JOIN `programmes` p
        ON i.`programmes_programme_id` = p.`programme_id`
    INNER JOIN `faculties` f
        ON p.`faculties_faculty_id` = f.`faculty_id`
    WHERE 1 = 1
";

if ($intakeId > 0) {
    $studentQuery .= " AND i.`intake_id` = '" . $intakeId . "'";
}
if ($programmeId > 0) {
    $studentQuery .= " AND p.`programme_id` = '" . $programmeId . "'";
}
if ($facultyId > 0) {
    $studentQuery .= " AND f.`faculty_id` = '" . $facultyId . "'";
}

$studentResult = Database::search($studentQuery);

$students = [];
$studentIdsHasIntakes = [];

while ($row = $studentResult->fetch_assoc()) {
    $displayName = !empty($row["name_with_initials"])
        ? $row["name_with_initials"]
        : trim(($row["first_name"] ?? "") . " " . ($row["last_name"] ?? ""));

    $students[] = [
        "students_has_intakes_id" => $row["students_has_intakes_id"],
        "student_id" => $row["student_id"],
        "student_name" => $displayName,
    ];

    $studentIdsHasIntakes[] = $row["students_has_intakes_id"];
}

if (empty($students)) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "No students found"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Lecture working days from timetable
| - skip Saturday and Sunday
|--------------------------------------------------------------------------
*/
$lectureQuery = "
    SELECT DISTINCT
        tt.`date`
    FROM `time_table` tt
    INNER JOIN `subjects` subj ON tt.`subjects_subject_id` = subj.`subject_id`
    INNER JOIN `levels` lv ON subj.`levels_level_id` = lv.`level_id`
    INNER JOIN `intakes` i ON lv.`intakes_intake_id` = i.`intake_id`
    INNER JOIN `programmes` p ON i.`programmes_programme_id` = p.`programme_id`
    INNER JOIN `faculties` f ON p.`faculties_faculty_id` = f.`faculty_id`
    WHERE tt.`date` BETWEEN '{$monthStart}' AND '{$monthEnd}'
";

if ($intakeId > 0) {
    $lectureQuery .= " AND i.`intake_id` = '" . $intakeId . "'";
}
if ($programmeId > 0) {
    $lectureQuery .= " AND p.`programme_id` = '" . $programmeId . "'";
}
if ($facultyId > 0) {
    $lectureQuery .= " AND f.`faculty_id` = '" . $facultyId . "'";
}

$lectureQuery .= " ORDER BY tt.`date` ASC";

$lectureResult = Database::search($lectureQuery);

$lectureDates = [];
while ($row = $lectureResult->fetch_assoc()) {
    if (isWeekday($row["date"])) {
        $lectureDates[] = $row["date"];
    }
}

/*
|--------------------------------------------------------------------------
| Fallback: if no timetable dates, use all weekdays in month
|--------------------------------------------------------------------------
*/
if (empty($lectureDates)) {
    $daysInMonth = (int)date("t", strtotime($monthStart));
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = date("Y-m-d", strtotime($monthValue . "-" . str_pad($d, 2, "0", STR_PAD_LEFT)));
        if (isWeekday($date)) {
            $lectureDates[] = $date;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Group lecture dates by week in month
|--------------------------------------------------------------------------
*/
$weekGroups = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
$allWeekdays = [];

$daysInMonth = (int)date('t', strtotime($monthStart));

for ($d = 1; $d <= $daysInMonth; $d++) {
    $date = date('Y-m-d', strtotime($monthValue . '-' . str_pad($d, 2, '0', STR_PAD_LEFT)));
    $dayOfWeek = (int)date('N', strtotime($date)); // 1=Mon ... 7=Sun

    if ($dayOfWeek <= 5) { // only Mon-Fri
        $weekNo = (int)ceil($d / 7);
        if ($weekNo > 5) {
            $weekNo = 5;
        }

        $weekGroups[$weekNo][] = $date;
        $allWeekdays[] = $date;
    }
}

$classDatesPerWeek = [];
for ($w = 1; $w <= 5; $w++) {
    $classDatesPerWeek[$w] = count($weekGroups[$w]);
}

$totalClassDates = count($allWeekdays);/*
|--------------------------------------------------------------------------
| Attendance records map
|--------------------------------------------------------------------------
*/
$recordMap = [];
if (!empty($studentIdsHasIntakes)) {
    $shiList = implode(",", array_map("intval", $studentIdsHasIntakes));

    $recordQuery = "
        SELECT 
            sir.`students_has_intakes_students_has_intakes_id` AS shi_id,
            DATE(sir.`date_updated`) AS att_date,
            COUNT(*) AS rec_count
        FROM `student_in_out_records` sir
        WHERE sir.`students_has_intakes_students_has_intakes_id` IN ({$shiList})
          AND DATE(sir.`date_updated`) BETWEEN '{$monthStart}' AND '{$monthEnd}'
        GROUP BY sir.`students_has_intakes_students_has_intakes_id`, DATE(sir.`date_updated`)
    ";

    $recordResult = Database::search($recordQuery);
    while ($row = $recordResult->fetch_assoc()) {
        $recordMap[$row["shi_id"]][$row["att_date"]] = (int)$row["rec_count"];
    }
}

/*
|--------------------------------------------------------------------------
| Spreadsheet
|--------------------------------------------------------------------------
*/
$spreadsheet = new Spreadsheet();

/*
|--------------------------------------------------------------------------
| SHEET 1: Attendance Details
|--------------------------------------------------------------------------
*/
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle("Attendance Details");
$sheet1->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet1->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);

$sheet1->getColumnDimension('A')->setWidth(14);
$sheet1->getColumnDimension('B')->setWidth(32);

/*
|--------------------------------------------------------------------------
| Build display dates = all weekdays in month
|--------------------------------------------------------------------------
*/
$displayDates = [];
$weekGroups = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];

$daysInMonth = (int)date('t', strtotime($monthStart));

for ($d = 1; $d <= $daysInMonth; $d++) {
    $date = date('Y-m-d', strtotime($monthValue . '-' . str_pad($d, 2, '0', STR_PAD_LEFT)));
    $dayOfWeek = (int)date('N', strtotime($date)); // 1=Mon ... 7=Sun

    if ($dayOfWeek <= 5) { // only Monday to Friday
        $weekNo = (int)ceil($d / 7);
        if ($weekNo > 5) {
            $weekNo = 5;
        }

        $displayDates[] = $date;
        $weekGroups[$weekNo][] = $date;
    }
}

/*
|--------------------------------------------------------------------------
| Lecture date lookup map
|--------------------------------------------------------------------------
*/
$lectureDateMap = [];
foreach ($lectureDates as $ld) {
    $lectureDateMap[$ld] = true;
}

/*
|--------------------------------------------------------------------------
| Count display / lecture dates
|--------------------------------------------------------------------------
*/
$totalDisplayDates = count($displayDates);

$classDatesPerWeek = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
for ($w = 1; $w <= 5; $w++) {
    foreach ($weekGroups[$w] as $wd) {
        if (isset($lectureDateMap[$wd])) {
            $classDatesPerWeek[$w]++;
        }
    }
}

$totalClassDates = count($lectureDates);

/*
|--------------------------------------------------------------------------
| Layout positions
|--------------------------------------------------------------------------
*/
$startDayColIndex = 3; // C
$lastDynamicDayCol = $startDayColIndex + $totalDisplayDates - 1;
$weekTotalStartCol = $lastDynamicDayCol + 1;
$percentCol = $weekTotalStartCol + 6; // week1..week5 + total = 6 columns

/*
|--------------------------------------------------------------------------
| Titles
|--------------------------------------------------------------------------
*/
$sheet1->mergeCells('A1:' . col($percentCol) . '1');
$sheet1->setCellValue('A1', "Monthly Attendants - {$yearName}");
$sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(16);
setCenter($sheet1, 'A1:' . col($percentCol) . '1');

$sheet1->mergeCells('A2:H2');
$sheet1->setCellValue('A2', "Batch No: {$displayBatch}");
$sheet1->getStyle('A2')->getFont()->setBold(true);

$sheet1->mergeCells(col($percentCol - 6) . '2:' . col($percentCol) . '2');
$sheet1->setCellValue(col($percentCol - 6) . '2', "Month: {$monthName}");
$sheet1->getStyle(col($percentCol - 6) . '2')->getFont()->setBold(true);
setCenter($sheet1, col($percentCol - 6) . '2:' . col($percentCol) . '2');

/*
|--------------------------------------------------------------------------
| Main headers
|--------------------------------------------------------------------------
*/
$sheet1->setCellValue('A4', 'Batch No.');
$sheet1->setCellValue('B4', 'Student Name');
$sheet1->mergeCells('A4:A5');
$sheet1->mergeCells('B4:B5');

setFillColor($sheet1, 'A4:B5', 'D9D9D9');

$weekColors = [
    1 => 'F6E6A8',
    2 => 'F4B4B4',
    3 => 'D6F0D0',
    4 => 'D9E8FF',
    5 => 'DCC6F2',
];

/*
|--------------------------------------------------------------------------
| Week headers + day headers
|--------------------------------------------------------------------------
*/
$currentCol = $startDayColIndex;

for ($w = 1; $w <= 5; $w++) {
    $days = $weekGroups[$w];

    if (!empty($days)) {
        $weekStartCol = $currentCol;
        $weekEndCol = $currentCol + count($days) - 1;

        $sheet1->mergeCells(col($weekStartCol) . '4:' . col($weekEndCol) . '4');
        $sheet1->setCellValue(col($weekStartCol) . '4', ordinalLabel($w));
        setFillColor($sheet1, col($weekStartCol) . '4:' . col($weekEndCol) . '4', $weekColors[$w]);
        setCenter($sheet1, col($weekStartCol) . '4:' . col($weekEndCol) . '4');

        foreach ($days as $dayDate) {
            $sheet1->setCellValue(col($currentCol) . '5', date('d', strtotime($dayDate)));
            $sheet1->getColumnDimension(col($currentCol))->setWidth(4);
            setCenter($sheet1, col($currentCol) . '5');
            $currentCol++;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Total headers
|--------------------------------------------------------------------------
*/
$sheet1->mergeCells(col($weekTotalStartCol) . '4:' . col($weekTotalStartCol + 5) . '4');
$sheet1->setCellValue(col($weekTotalStartCol) . '4', 'Total');
setCenter($sheet1, col($weekTotalStartCol) . '4:' . col($weekTotalStartCol + 5) . '4');

$totalHeadColors = ['F6E6A8', 'F4B4B4', 'D6F0D0', 'D9E8FF', 'DCC6F2', 'D9D9D9'];
for ($i = 0; $i < 6; $i++) {
    $c = $weekTotalStartCol + $i;
    $label = ($i < 5)
        ? sprintf(
            '%02d%s',
            $i + 1,
            ($i + 1 == 1 ? 'st' : (($i + 1 == 2) ? 'nd' : (($i + 1 == 3) ? 'rd' : 'th')))
        )
        : 'Total';

    $sheet1->setCellValue(col($c) . '5', $label);
    setFillColor($sheet1, col($c) . '5', $totalHeadColors[$i]);
    $sheet1->getColumnDimension(col($c))->setWidth(8);
    setCenter($sheet1, col($c) . '5');
}

$sheet1->mergeCells(col($percentCol) . '4:' . col($percentCol) . '5');
$sheet1->setCellValue(col($percentCol) . '4', '%');
$sheet1->getColumnDimension(col($percentCol))->setWidth(8);
setCenter($sheet1, col($percentCol) . '4:' . col($percentCol) . '5');

/*
|--------------------------------------------------------------------------
| Header styling
|--------------------------------------------------------------------------
*/
$headerRange1 = 'A4:' . col($percentCol) . '5';
$sheet1->getStyle($headerRange1)->getFont()->setBold(true)->setSize(9);
setCenter($sheet1, $headerRange1);
applyThinBorder($sheet1, $headerRange1);

/*
|--------------------------------------------------------------------------
| Data rows
|--------------------------------------------------------------------------
*/
$rowIndex = 6;
$percentageSheetRows = [];

foreach ($students as $student) {
    $sheet1->setCellValue("A{$rowIndex}", $student["student_id"]);
    $sheet1->setCellValue("B{$rowIndex}", $student["student_name"]);

    $currentCol = $startDayColIndex;
    $weekAttendanceCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

    foreach ($displayDates as $date) {
        $cellRef = col($currentCol) . $rowIndex;

        $isLectureDate = isset($lectureDateMap[$date]);
        $hasAttendance = !empty($recordMap[$student["students_has_intakes_id"]][$date]);

        if ($isLectureDate) {
            if ($hasAttendance) {
                $sheet1->setCellValue($cellRef, 1);
                $sheet1->getStyle($cellRef)->getFont()->setBold(true)->getColor()->setARGB('008000');
            } else {
                $sheet1->setCellValue($cellRef, 0);
                $sheet1->getStyle($cellRef)->getFont()->setBold(true)->getColor()->setARGB('FF0000');
            }

            $weekNo = (int)ceil((int)date('d', strtotime($date)) / 7);
            if ($weekNo > 5) {
                $weekNo = 5;
            }

            if ($hasAttendance) {
                $weekAttendanceCounts[$weekNo]++;
            }
        } else {
            $sheet1->setCellValue($cellRef, '');
            setFillColor($sheet1, $cellRef, 'F2F2F2'); // non-lecture weekday
        }

        setCenter($sheet1, $cellRef);
        $currentCol++;
    }

    $grandTotal = 0;
    for ($w = 1; $w <= 5; $w++) {
        $weekCol = $weekTotalStartCol + ($w - 1);
        $sheet1->setCellValue(col($weekCol) . $rowIndex, $weekAttendanceCounts[$w]);
        setCenter($sheet1, col($weekCol) . $rowIndex);
        $grandTotal += $weekAttendanceCounts[$w];
    }

    $sheet1->setCellValue(col($weekTotalStartCol + 5) . $rowIndex, $grandTotal);
    setCenter($sheet1, col($weekTotalStartCol + 5) . $rowIndex);

    $percentage = $totalClassDates > 0 ? round(($grandTotal / $totalClassDates) * 100, 0) : 0;
    $sheet1->setCellValue(col($percentCol) . $rowIndex, $percentage . '%');
    setCenter($sheet1, col($percentCol) . $rowIndex);

    $rowRange = 'A' . $rowIndex . ':' . col($percentCol) . $rowIndex;
    applyThinBorder($sheet1, $rowRange);
    $sheet1->getStyle($rowRange)->getFont()->setSize(9);

    $percentageSheetRows[] = [
        'student_id' => $student["student_id"],
        'student_name' => $student["student_name"],
        'week1' => $weekAttendanceCounts[1],
        'week2' => $weekAttendanceCounts[2],
        'week3' => $weekAttendanceCounts[3],
        'week4' => $weekAttendanceCounts[4],
        'week5' => $weekAttendanceCounts[5],
        'total' => $grandTotal,
        'class_dates' => $totalClassDates,
        'attendance_percent_raw' => $totalClassDates > 0 ? round(($grandTotal / $totalClassDates) * 100, 8) : 0,
        'attendance_percent_rounded' => $percentage,
    ];

    $rowIndex++;
}

/*
|--------------------------------------------------------------------------
| Final styling
|--------------------------------------------------------------------------
*/
$fullRange1 = 'A4:' . col($percentCol) . ($rowIndex - 1);
$sheet1->getStyle($fullRange1)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

for ($r = 4; $r < $rowIndex; $r++) {
    $sheet1->getRowDimension($r)->setRowHeight(18);
}
$sheet1->getRowDimension(1)->setRowHeight(28);
$sheet1->getRowDimension(2)->setRowHeight(22);
$sheet1->getRowDimension(4)->setRowHeight(24);
$sheet1->getRowDimension(5)->setRowHeight(22);
/*
|--------------------------------------------------------------------------
| SHEET 2: Attendance Percentage
|--------------------------------------------------------------------------
*/
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle("Attendance Percentage");
$sheet2->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet2->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);

$sheet2->getColumnDimension('A')->setWidth(14);
$sheet2->getColumnDimension('B')->setWidth(34);
$sheet2->getColumnDimension('C')->setWidth(10);
$sheet2->getColumnDimension('D')->setWidth(10);
$sheet2->getColumnDimension('E')->setWidth(10);
$sheet2->getColumnDimension('F')->setWidth(10);
$sheet2->getColumnDimension('G')->setWidth(10);
$sheet2->getColumnDimension('H')->setWidth(8);
$sheet2->getColumnDimension('I')->setWidth(10);
$sheet2->getColumnDimension('J')->setWidth(14);
$sheet2->getColumnDimension('K')->setWidth(8);
$sheet2->getColumnDimension('M')->setWidth(10);
$sheet2->getColumnDimension('N')->setWidth(10);

$sheet2->setCellValue('A1', 'Batch No.');
$sheet2->setCellValue('B1', 'Student Names');
$sheet2->setCellValue('C1', '01st Week');
$sheet2->setCellValue('D1', '02nd Week');
$sheet2->setCellValue('E1', '03rd Week');
$sheet2->setCellValue('F1', '04th Week');
$sheet2->setCellValue('G1', '05th Week');
$sheet2->setCellValue('H1', 'Total');
$sheet2->setCellValue('I1', 'Class Dates');
$sheet2->setCellValue('J1', 'Attendance %');
$sheet2->setCellValue('K1', '%');

$headerRange2 = 'A1:K1';
$sheet2->getStyle($headerRange2)->getFont()->setBold(true)->setSize(10);
setCenter($sheet2, $headerRange2);
applyThinBorder($sheet2, $headerRange2);
setFillColor($sheet2, $headerRange2, 'F6E6A8');

$sheet2->setCellValue('M2', '100 - 90');
$sheet2->setCellValue('M3', '80 - 89');
$sheet2->setCellValue('M4', '60 - 79');
$sheet2->setCellValue('M5', '0 - 59');

$sheet2->setCellValue('N2', 'Green');
$sheet2->setCellValue('N3', 'Blue');
$sheet2->setCellValue('N4', 'Yellow');
$sheet2->setCellValue('N5', 'Red');

applyThinBorder($sheet2, 'M2:N5');
setCenter($sheet2, 'M2:N5');
$sheet2->getStyle('M2:N5')->getFont()->setBold(true);

setFillColor($sheet2, 'N2', '00B050');
setFillColor($sheet2, 'N3', '5B9BD5');
setFillColor($sheet2, 'N4', 'FFFF00');
setFillColor($sheet2, 'N5', 'FF0000');

$sheet2->getStyle('N2')->getFont()->getColor()->setARGB('FFFFFF');
$sheet2->getStyle('N3')->getFont()->getColor()->setARGB('FFFFFF');
$sheet2->getStyle('N4')->getFont()->getColor()->setARGB('000000');
$sheet2->getStyle('N5')->getFont()->getColor()->setARGB('FFFFFF');

$row2 = 2;
foreach ($percentageSheetRows as $item) {
    $sheet2->setCellValue("A{$row2}", $item['student_id']);
    $sheet2->setCellValue("B{$row2}", $item['student_name']);
    $sheet2->setCellValue("C{$row2}", $item['week1']);
    $sheet2->setCellValue("D{$row2}", $item['week2']);
    $sheet2->setCellValue("E{$row2}", $item['week3']);
    $sheet2->setCellValue("F{$row2}", $item['week4']);
    $sheet2->setCellValue("G{$row2}", $item['week5']);
    $sheet2->setCellValue("H{$row2}", $item['total']);
    $sheet2->setCellValue("I{$row2}", $item['class_dates']);
    $sheet2->setCellValue("J{$row2}", $item['attendance_percent_raw']);
    $sheet2->setCellValue("K{$row2}", $item['attendance_percent_rounded']);

    applyThinBorder($sheet2, "A{$row2}:K{$row2}");
    $sheet2->getStyle("A{$row2}:K{$row2}")->getFont()->setSize(9);
    setCenter($sheet2, "A{$row2}:K{$row2}");
    $sheet2->getStyle("B{$row2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $bandColor = getPercentageBandColor((int)$item['attendance_percent_rounded']);
    setFillColor($sheet2, "K{$row2}", $bandColor);

    if ($bandColor === 'FFFF00') {
        $sheet2->getStyle("K{$row2}")->getFont()->getColor()->setARGB('000000');
    } else {
        $sheet2->getStyle("K{$row2}")->getFont()->getColor()->setARGB('FFFFFF');
    }

    $row2++;
}

for ($r = 1; $r < $row2; $r++) {
    $sheet2->getRowDimension($r)->setRowHeight(20);
}

/*
|--------------------------------------------------------------------------
| Output
|--------------------------------------------------------------------------
*/
$spreadsheet->setActiveSheetIndex(0);

$fileName = "Monthly_Attendants_" . $displayBatch . "_" . $monthValue . ".xlsx";
$fileName = str_replace([" ", "/", "\\"], "_", $fileName);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
