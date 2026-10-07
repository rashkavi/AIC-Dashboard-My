<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/StudentDocuments.php");

$status = new stdClass();

function respond($status)
{
    echo json_encode($status);
    exit;
}

if (!SessionUser::hasValidSession() || SessionUser::getCurrentUserRole() == null) {
    $status->error = 'Authentication Failed!';
    respond($status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    $status->error = 'Invalid request method';
    respond($status);
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
    $status->error = 'You are not authorized to add inquiry';
    respond($status);
}

$studentId      = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$intake         = isset($_POST['intake']) ? (int)$_POST['intake'] : 0;
$counselor      = isset($_POST['counselor']) ? (int)$_POST['counselor'] : 0;
$entry_type     = isset($_POST['entry_type']) ? trim($_POST['entry_type']) : '';
$payment_option = isset($_POST['payment_option']) ? trim($_POST['payment_option']) : '';

/* ---------- student ---------- */
if ($studentId <= 0) {
    $status->error = 'Please select a student';
    respond($status);
}
$studentRs = Database::search("SELECT `student_id` FROM `students` WHERE `student_id`='" . $studentId . "' LIMIT 1");
if ($studentRs->num_rows === 0) {
    $status->error = 'Student not found';
    respond($status);
}

/* ---------- intake ---------- */
if ($intake <= 0) {
    $status->intake = "Please select student intake";
} else {
    $intakeRs = Database::search("SELECT `intake_id` FROM `intakes` WHERE `intake_id`='" . $intake . "' LIMIT 1");
    if ($intakeRs->num_rows === 0) {
        $status->intake = "Selected intake is invalid";
    } else {
        $dupRs = Database::search("SELECT `students_has_intakes_id` FROM `students_has_intakes`
            WHERE `students_student_id`='" . $studentId . "' AND `intakes_intake_id`='" . $intake . "' LIMIT 1");
        if ($dupRs->num_rows > 0) {
            $status->intake = "This student is already registered for the selected intake";
        }
    }
}

/* ---------- counselor ---------- */
if (SessionUser::ifUserIsCounsiler($sessionUserRole) && $counselor <= 0) {
    $counselor = (int)$sessionUserObject['user_id'];
}
if ($counselor <= 0) {
    $status->counselor = "Please select student counselor";
} else {
    $cRs = Database::search("SELECT `user_id` FROM `users` WHERE `user_id`='" . $counselor . "' AND `user_roles_user_role_id` IN (3,9,10) LIMIT 1");
    if ($cRs->num_rows === 0) {
        $status->counselor = "Selected counselor is invalid";
    }
}

/* ---------- entry type ---------- */
if ($entry_type === '') {
    $status->entry_type = "Please select student entry type";
}

/* ---------- documents ---------- */
$requiredDocuments = getRequiredDocumentsForStudent($studentId);
$studentDocuments  = validateStudentDocuments($requiredDocuments);
foreach ($studentDocuments["errors"] as $key => $message) {
    $status->$key = $message;
}

if (!empty(get_object_vars($status))) {
    respond($status);
}

/* ---------- save ---------- */
try {
    $registerTime = (new DateTime("now", new DateTimeZone("Asia/Colombo")))->format('Y-m-d H:i:s');

    Database::$connection->begin_transaction();

    $columns = "`students_student_id`,`addmission_date`,`users_user_id`,`counselor_user_id`,`intakes_intake_id`,`lead_student_status_lead_student_status_id`,`entry_type`";
    $values  = "'" . $studentId . "','" . $registerTime . "','" . (int)$sessionUserObject['user_id'] . "','" . $counselor . "','" . $intake . "','1','" . Database::escape($entry_type) . "'";

    if ($payment_option !== '') {
        $columns .= ",`payment_option`";
        $values  .= ",'" . Database::escape($payment_option) . "'";
    }

    Database::iud("INSERT INTO `students_has_intakes` (" . $columns . ") VALUES (" . $values . ")");
    $studentHasIntakeId = Database::$connection->insert_id;

    uploadStudentDocuments($studentId, $studentHasIntakeId, $studentDocuments);

    Database::$connection->commit();

    $status->done = "Registration Added Successfully";
} catch (Throwable $e) {
    try {
        Database::$connection->rollback();
    } catch (Throwable $ignored) {
    }
    $status->error = "Something went wrong while saving the registration. Please try again.";
}

respond($status);