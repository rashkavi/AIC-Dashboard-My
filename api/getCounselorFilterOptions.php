<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Your session has expired. Please log in again.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

function nameFilter($value)
{
    if (!isset($value)) return '';
    $value = trim((string) $value);
    return ($value === '' || $value === '0') ? '' : $value;
}

$stage         = $input['stage'] ?? '';
$branchId      = isset($input['branch_id']) ? (int) $input['branch_id'] : 0;
$facultyName   = nameFilter($input['faculty'] ?? null);
$programmeName = nameFilter($input['programme'] ?? null);
$intakeName    = nameFilter($input['intake'] ?? null);

// Always join the full chain so every stage can be filtered by whatever
// is already selected above it.
$base = "
    FROM subjects s
    INNER JOIN levels l ON s.levels_level_id = l.level_id
    INNER JOIN intakes i ON l.intakes_intake_id = i.intake_id
    INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
    INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
    INNER JOIN branches b ON f.branches_branch_id = b.branch_id
    WHERE 1 = 1
";

// Programmes/intakes/faculties don't need subjects, but joining subjects
// would hide programmes without subjects, so use a lighter base for those.
$lightBase = "
    FROM intakes i
    INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
    INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
    INNER JOIN branches b ON f.branches_branch_id = b.branch_id
    WHERE 1 = 1
";

function applyFilters($branchId, $facultyName, $programmeName, $intakeName, $useFaculty, $useProgramme, $useIntake)
{
    $sql = '';
    if ($branchId > 0) {
        $sql .= " AND b.branch_id = '" . Database::escape($branchId) . "'";
    }
    if ($useFaculty && $facultyName !== '') {
        $sql .= " AND f.faculty = '" . Database::escape($facultyName) . "'";
    }
    if ($useProgramme && $programmeName !== '') {
        $sql .= " AND p.programme = '" . Database::escape($programmeName) . "'";
    }
    if ($useIntake && $intakeName !== '') {
        $sql .= " AND i.intake = '" . Database::escape($intakeName) . "'";
    }
    return $sql;
}

function fetchColumn($query, $column)
{
    $rows = Database::search($query)->fetch_all(MYSQLI_ASSOC);
    return array_values(array_map(fn($r) => $r[$column], $rows));
}

switch ($stage) {
    case 'faculties':
        $q = "SELECT DISTINCT f.faculty " . $lightBase
            . applyFilters($branchId, '', '', '', false, false, false)
            . " ORDER BY f.faculty ASC";
        echo json_encode(['faculties' => fetchColumn($q, 'faculty')]);
        break;

    case 'programmes':
        $q = "SELECT DISTINCT p.programme " . $lightBase
            . applyFilters($branchId, $facultyName, '', '', true, false, false)
            . " ORDER BY p.programme ASC";
        echo json_encode(['programmes' => fetchColumn($q, 'programme')]);
        break;

    case 'intakes':
        $q = "SELECT DISTINCT i.intake " . $lightBase
            . applyFilters($branchId, $facultyName, $programmeName, '', true, true, false)
            . " ORDER BY i.intake ASC";
        echo json_encode(['intakes' => fetchColumn($q, 'intake')]);
        break;

    case 'subjects':
        $q = "SELECT DISTINCT s.subject " . $base
            . applyFilters($branchId, $facultyName, $programmeName, $intakeName, true, true, true)
            . " ORDER BY s.subject ASC";
        echo json_encode(['subjects' => fetchColumn($q, 'subject')]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown stage.']);
}