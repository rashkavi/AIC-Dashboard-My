<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

header('Content-Type: application/json');

// ── Auth ────────────────────────────────────────────────────────────────
if (!SessionUser::hasValidSession() || SessionUser::getCurrentUserRole() == null) {
    $status->error = 'Authentication Failed!';
    echo json_encode($status);
    exit;
}

// ── Request validation ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json') {
    http_response_code(400);
    $status->error = 'Invalid request';
    echo json_encode($status);
    exit;
}

$decoded = json_decode(trim(file_get_contents('php://input')), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    $status->error = 'Invalid JSON';
    echo json_encode($status);
    exit;
}

$leadId = isset($decoded['leadId']) ? (int)$decoded['leadId'] : 0;
if ($leadId <= 0) {
    $status->error = 'Invalid Lead Id';
    echo json_encode($status);
    exit;
}

// ── Latest payment plan of the student ──────────────────────────────────
$rs = Database::search("
    SELECT
        t.title,
        ld.first_name,
        ld.last_name,
        ld.email,
        prog.programme,
        i.intake,
        pp.payment_plan,
        pphsi.payment_plan_has_students_has_intakes_id AS plan_id,
        (SELECT COUNT(*)
           FROM course_payments cp
          WHERE cp.payment_plan_has_students_has_intakes_id = pphsi.payment_plan_has_students_has_intakes_id
        ) AS installments
    FROM students_has_intakes shi
    INNER JOIN students s ON s.student_id = shi.students_student_id
    INNER JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
    INNER JOIN title t ON t.title_id = s.title_title_id
    INNER JOIN intakes i ON i.intake_id = shi.intakes_intake_id
    INNER JOIN programmes prog ON prog.programme_id = i.programmes_programme_id
    INNER JOIN payment_plan_has_students_has_intakes pphsi
        ON pphsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN payment_plan pp ON pp.payment_plan_id = pphsi.payment_plan_payment_plan_id
    WHERE shi.students_has_intakes_id = '" . $leadId . "'
    ORDER BY pphsi.payment_plan_has_students_has_intakes_id DESC
    LIMIT 1
");

if (!$rs || $rs->num_rows == 0) {
    $status->error = 'No payment plan found for this student';
    echo json_encode($status);
    exit;
}

$row = $rs->fetch_assoc();

if (empty($row['email'])) {
    $status->error = 'This student has no email address';
    echo json_encode($status);
    exit;
}

// ── Response (keys match what the modal JS reads) ───────────────────────
$status->student_name = $row['title'] . '. ' . $row['first_name'] . ' ' . $row['last_name'];
$status->programme    = $row['programme'];
$status->intake       = $row['intake'];
$status->payment_plan = $row['payment_plan'];
$status->installments = (int)$row['installments'];
$status->email        = $row['email'];

echo json_encode($status);