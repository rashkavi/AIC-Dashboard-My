<?php

/**
 * api/generateStudentLetter.php
 * Confirmation of Studentship letter (AIC Campus) — written on letterHead.pdf.
 * No Composer / no external PDF library: uses includes/LetterheadPdf.php only.
 *
 * Usage:  api/generateStudentLetter.php?id={students_has_intakes_id}[&download=1]
 * File name: {Student_Name}_{Registration_No}.pdf
 */

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/LetterheadPdf.php");

// ---------------------------------------------------------------------------
// 1. Auth
// ---------------------------------------------------------------------------
$status = new stdClass();

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        http_response_code(401);
        $status->error = 'Authentication Failed!';
        echo (json_encode($status));
        exit;
    }
} else {
    http_response_code(401);
    $status->error = 'Authentication Failed!';
    echo (json_encode($status));
    exit;
}

// ---------------------------------------------------------------------------
// 2. Config
// ---------------------------------------------------------------------------
const LETTERHEAD_PDF = __DIR__ . '/../assets/letterhead/letterHead.pdf';
const CAMPUS_NAME    = 'AIC Campus';
const CAMPUS_ADDRESS = 'No. 154, Havelock Road, Colombo 05, Sri Lanka';
const AWARDING_BODY  = 'AIC Campus Sri Lanka';

// Layout (points; 1 mm = 2.835 pt). Adjust TOP if text touches the purple header.
const PAGE_MARGIN_LEFT  = 35;
const PAGE_MARGIN_RIGHT = 35;
const PAGE_TEXT_TOP     = 105;   // distance from top of page to the first line
const FONT_SIZE         = 11;

// ---------------------------------------------------------------------------
// 3. Input
// ---------------------------------------------------------------------------
$enrollmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($enrollmentId <= 0) {
    http_response_code(400);
    exit('Invalid enrollment id');
}

// ---------------------------------------------------------------------------
// 4. Data
// ---------------------------------------------------------------------------
$sql = "
    SELECT
        s.full_name,
        s.nic,
        s.passport,
        t.title                 AS title_name,
        shi.student_id          AS registration_no,
        i.intake,
        p.programme,
        pd.programme_duration   AS programme_duration
    FROM students_has_intakes shi
    INNER JOIN students           s  ON s.student_id             = shi.students_student_id
    LEFT  JOIN title              t  ON t.title_id               = s.title_title_id
    INNER JOIN intakes            i  ON i.intake_id              = shi.intakes_intake_id
    INNER JOIN programmes         p  ON p.programme_id           = i.programmes_programme_id
    LEFT  JOIN programme_duration pd ON pd.programme_duration_id = p.programme_duration_programme_duration_id
    WHERE shi.students_has_intakes_id = {$enrollmentId}
    LIMIT 1
";

$rs = Database::search($sql);
if (!$rs || $rs->num_rows === 0) {
    http_response_code(404);
    exit('Student enrollment not found');
}
$row = $rs->fetch_assoc();

// ---------------------------------------------------------------------------
// 5. Prepare values
// ---------------------------------------------------------------------------
$v = static fn($x, $fallback = '-') => ($x = trim((string) $x)) !== '' ? $x : $fallback;

$title    = trim((string) ($row['title_name'] ?? ''));
$fullName = ($title !== '' ? rtrim($title, '.') . '. ' : '') . trim($row['full_name']);
$idNumber = $v($row['nic'] ?: $row['passport']);

$letterDate   = date('d F Y');               // e.g. 20 October 2026
$programme    = $v($row['programme']);
$awardingBody = AWARDING_BODY;
$intake       = $v($row['intake']);
$duration     = $v($row['programme_duration']);
$regNo        = $v($row['registration_no']);

// File name: Student_Name_RegistrationNo  (e.g. Kavindu_Sahan_Perera_AIC_BSC_2025_0142)
$clean    = static fn($s) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $s), '_');
$fileBase = $clean($row['full_name']) . '_' . $clean($regNo !== '-' ? $regNo : $enrollmentId);

// ---------------------------------------------------------------------------
// 6. Build the PDF
// ---------------------------------------------------------------------------
try {
    $pdf = new LetterheadPdf(LETTERHEAD_PDF);
    $pdf->setInfo($fileBase, CAMPUS_NAME);   // shown as the tab title in the PDF viewer
    $pdf->setMargins(PAGE_MARGIN_LEFT, PAGE_TEXT_TOP, PAGE_MARGIN_RIGHT);
    $pdf->addPage();

    $fs = FONT_SIZE;

    // Date
    $pdf->paragraph([[$letterDate, 'R']], 'left', $fs, 1.45, 12);

    // TO WHOM IT MAY CONCERN
    $pdf->paragraph([['TO WHOM IT MAY CONCERN', 'B']], 'left', $fs, 1.45, 12);

    // Subject
    $pdf->paragraph([['CONFIRMATION OF STUDENTSHIP', 'B']], 'center', $fs, 1.45, 12);

    // Paragraph 1
    $pdf->paragraph([
        ['This is to certify that ', 'R'],
        [$fullName, 'B'],
        [', holder of National Identity Card/Passport No. ', 'R'],
        [$idNumber, 'B'],
        [', is a duly registered student of ', 'R'],
        [CAMPUS_NAME, 'B'],
        [', located at ' . CAMPUS_ADDRESS . '.', 'R'],
    ], 'justify', $fs);

    // Paragraph 2
    $pdf->paragraph([
        ['The student is currently enrolled in the ', 'R'],
        [$programme, 'B'],
        [', awarded by ', 'R'],
        [$awardingBody, 'B'],
        ['.', 'R'],
    ], 'justify', $fs);

    // Paragraph 3
    $pdf->paragraph([["The student's registration details are as follows:", 'R']], 'left', $fs, 1.45, 4);

    // Details table (Description | Details)
    $col2 = 170;   // x-offset of the "Details" column from the left margin
    $pdf->cells([['Description', 'B', 4], ['Details', 'B', $col2]], $fs, 22);
    $pdf->cells([['Intake', 'B', 4], [$intake, 'R', $col2]], $fs, 26);
    $pdf->cells([['Program Duration', 'B', 4], [$duration, 'R', $col2]], $fs, 26);
    $pdf->cells([['AIC Registration Number', 'B', 4], [$regNo, 'R', $col2]], $fs, 26);

    // Output — Student_Name_RegistrationNo.pdf
    $pdf->stream($fileBase . '.pdf', !empty($_GET['download']));
} catch (Throwable $ex) {
    http_response_code(500);
    exit('PDF generation failed: ' . $ex->getMessage());
}
