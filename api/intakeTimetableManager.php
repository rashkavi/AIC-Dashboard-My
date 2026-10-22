<?php
// api/intakeTimetableManager.php
//
// JSON API for the Intake Summary Timetable page.
// POST, Content-Type: application/json, body: { "action": "...", ... }
//
//   options     { intake_id }                       subjects + lecturers for the builder
//   list        { intake_id }                       all timetables of the intake + recipient count
//   get         { timetable_id }                    one timetable with sections and rows
//   save        { intake_id, timetable_id?, title, note, sections:[{heading, rows:[...]}] }
//               timetable_id 0/missing = create (the current ACTIVE one becomes INACTIVE)
//   set_status  { timetable_id, status: ACTIVE|INACTIVE }
//
// Response: stdClass with done / error (+ data), same style as the other APIs.

header('Content-Type: application/json');

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');
require_once('../includes/IntakeTimetableManager.php');

$status = new stdClass();

function respond(stdClass $status): void
{
    echo json_encode($status);
    exit;
}

if (!SessionUser::hasValidSession()) {
    $status->error = 'Authentication Failed!';
    respond($status);
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
if ($sessionUserRole == null) {
    $status->error = 'Authentication Failed!';
    respond($status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    $status->error = 'Invalid request method';
    respond($status);
}

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
$userId = (int) $sessionUserObject['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = (string) ($input['action'] ?? '');

// Resolve the intake (directly, or through the timetable)
$intakeId    = (int) ($input['intake_id'] ?? 0);
$timetableId = (int) ($input['timetable_id'] ?? 0);

if (in_array($action, ['get', 'set_status'], true)) {
    $header = IntakeTimetableManager::getHeader($timetableId);
    if (!$header) {
        $status->error = 'Timetable not found.';
        respond($status);
    }
    $intakeId = (int) $header['intake_id'];
}

if ($intakeId <= 0 || !IntakeTimetableManager::getIntake($intakeId)) {
    $status->error = 'Invalid intake.';
    respond($status);
}

$canView   = IntakeTimetableManager::canView($sessionUserRole, $intakeId);
$canManage = IntakeTimetableManager::canManage($sessionUserRole, $intakeId);

if (!$canView) {
    $status->error = 'You are not authorized to this action.';
    respond($status);
}

switch ($action) {

    case 'options':
        $status->done = 'ok';
        $status->data = IntakeTimetableManager::getOptions($intakeId);
        break;

    case 'list':
        $status->done = 'ok';
        $status->data = [
            'timetables'      => IntakeTimetableManager::listTimetables($intakeId),
            'recipient_count' => count(IntakeTimetableManager::getRecipients($intakeId)),
            'can_manage'      => $canManage,
        ];
        break;

    case 'get':
        $status->done = 'ok';
        $status->data = IntakeTimetableManager::getTimetable($timetableId);
        break;

    case 'save':
        if (!$canManage) {
            $status->error = 'You are not authorized to this action.';
            break;
        }
        $result = IntakeTimetableManager::save($input, $intakeId, $userId);
        if (!empty($result['errors'])) {
            $status->error  = $result['errors'][0];
            $status->errors = $result['errors'];
        } else {
            $status->done = $timetableId > 0
                ? 'Timetable updated successfully.'
                : 'Timetable created successfully.' . ($result['deactivated'] > 0 ? ' The previous timetable is now inactive.' : '');
            $status->timetable_id = $result['id'];
        }
        break;

    case 'set_status':
        if (!$canManage) {
            $status->error = 'You are not authorized to this action.';
            break;
        }
        $result = IntakeTimetableManager::setStatus($timetableId, (string) ($input['status'] ?? ''), $userId);
        if (!empty($result['errors'])) {
            $status->error = $result['errors'][0];
        } else {
            $status->done = strtoupper((string) $input['status']) === 'ACTIVE'
                ? 'Timetable is now active.' . ($result['deactivated'] > 0 ? ' The previous timetable is now inactive.' : '')
                : 'Timetable marked inactive.';
        }
        break;

    default:
        http_response_code(400);
        $status->error = 'Unknown action.';
}

respond($status);
