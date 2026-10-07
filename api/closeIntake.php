<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';

header('Content-Type: application/json');

function respond($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.');
if (!SessionUser::hasValidSession()) respond(false, 'Session expired. Please log in again.');

$role = SessionUser::getCurrentUserRole();
if ($role == null) respond(false, 'Unauthorized.');

$user = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($user['user_permissions']);

$intakeId = isset($_POST['intake_id']) ? (int)$_POST['intake_id'] : 0;
if ($intakeId <= 0) respond(false, 'Invalid intake.');

$rs = Database::search("SELECT `intake_id`, `intake_status`, `programmes_programme_id`,`intake` FROM `intakes` WHERE `intake_id` = '" . $intakeId . "'");
if ($rs->num_rows == 0) respond(false, 'Intake not found.');
$intake = $rs->fetch_assoc();
$programmeId = $intake['programmes_programme_id'];

// same permission used for Edit on the page
if (!(SessionUser::ifUserIsPro($role) || SessionUser::ifUserIsAcademic($role) || UserPermissions::hasPermission('programme', 10, $programmeId))) {
    respond(false, 'You do not have permission to close this intake.');
}

if ($intake['intake_status'] === 'CLOSED') respond(false, 'This intake is already closed.');

$closedDate = date('Y-m-d H:i:s');
Database::iud("UPDATE `intakes` SET `intake_status` = 'CLOSED', `closed_date` = '" . $closedDate . "' WHERE `intake_id` = '" . $intakeId . "' AND `intake_status` = 'ONGOING'");
addLog($intakeId, $intake['intake'], 'CLOSED', 'ONGOING', 'CLOSED', 'Intake closed on ' . $closedDate);


respond(true, 'Intake closed successfully.', ['closed_date' => $closedDate]);


function addLog($intakeId, $intakeName, $action, $oldStatus = null, $newStatus = null, $description = null)
{
    $user = SessionUser::getCurrentUserObject();
    $userId = isset($user['user_id']) ? (int)$user['user_id'] : 'NULL'; // adjust key to your user object


    $esc = function ($v) {
        return $v === null ? 'NULL' : "'" . Database::$connection->real_escape_string($v) . "'";
    };

    Database::iud(
        "INSERT INTO `intakes_logs`
                (`intake_id`,`intake_name`,`action`,`old_status`,`new_status`,`description`,`performed_by`)
             VALUES (" . (int)$intakeId . "," . $esc($intakeName) . "," . $esc($action) . "," .
            $esc($oldStatus) . "," . $esc($newStatus) . "," . $esc($description) . "," .
            $userId . ")"
    );
}
