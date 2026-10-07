<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/StudentDocuments.php");

/**
 * Lookup tables, taken from add-registration.php.
 * Format: [table, id column, name column].
 * If a lookup fails, the raw id is shown instead (nothing breaks).
 */
$LOOKUPS = [
    'gender'   => ['gender',            'gender_id',            'gender'],
    'marital'  => ['marital_status',    'marital_status_id',    'marital_status'],
    'country'  => ['countries',         'country_id',           'country_name'],
    'city'     => ['city',              'city_id',              'city'],
    'relation' => ['guardian_relation', 'guardian_relation_id', 'guardian_relation'],
];

function lookupName($cfg, $id)
{
    if ($id === null || $id === '' || (int)$id === 0) {
        return null;
    }
    try {
        $rs = Database::search("SELECT `" . $cfg[2] . "` FROM `" . $cfg[0] . "` WHERE `" . $cfg[1] . "`='" . (int)$id . "' LIMIT 1");
        if ($rs && $rs->num_rows > 0) {
            $row = $rs->fetch_assoc();
            return $row[$cfg[2]];
        }
    } catch (Throwable $e) {
        // fall through
    }
    return (string)$id;
}

function pushIfFilled(&$list, $label, $value)
{
    if ($value !== null && trim((string)$value) !== '') {
        $list[] = ['label' => $label, 'value' => (string)$value];
    }
}

function respond($data)
{
    echo json_encode($data);
    exit;
}

if (!SessionUser::hasValidSession() || SessionUser::getCurrentUserRole() == null) {
    respond(['error' => 'Authentication Failed!']);
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
    respond(['error' => 'You are not authorized to register students']);
}

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($studentId <= 0) {
    respond(['error' => 'Invalid student']);
}

try {
    $rs = Database::search("
        SELECT `students`.*, `login_details`.`first_name`, `login_details`.`last_name`,
               `login_details`.`email`, `login_details`.`whatsapp`, `title`.`title` AS `title_name`
        FROM `students`
        INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
        LEFT JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
        WHERE `students`.`student_id` = '" . $studentId . "' LIMIT 1
    ");

    if ($rs->num_rows === 0) {
        respond(['error' => 'Student not found']);
    }
    $s = $rs->fetch_assoc();

    /* ---------- basic (always shown, read-only) ---------- */
    $basic = [];
    pushIfFilled($basic, 'Title', $s['title_name']);
    pushIfFilled($basic, 'First Name', $s['first_name']);
    pushIfFilled($basic, 'Last Name', $s['last_name']);
    pushIfFilled($basic, 'Full Name', $s['full_name']);
    pushIfFilled($basic, 'Name with Initials', $s['name_with_initials']);
    pushIfFilled($basic, 'Gender', lookupName($LOOKUPS['gender'], $s['gender_gender_id']));
    pushIfFilled($basic, 'Email', $s['email']);
    pushIfFilled($basic, 'Mobile', $s['mobile']);
    pushIfFilled($basic, 'WhatsApp', $s['whatsapp']);

    /* ---------- advanced (ONLY filled parts) ---------- */
    $advanced = [];
    pushIfFilled($advanced, 'Date of Birth', !empty($s['date_of_birth']) ? date('Y-m-d', strtotime($s['date_of_birth'])) : null);
    pushIfFilled($advanced, 'NIC', $s['nic']);
    pushIfFilled($advanced, 'Passport', $s['passport']);
    pushIfFilled($advanced, 'Marital Status', lookupName($LOOKUPS['marital'], $s['marital_status_marital_status_id']));
    pushIfFilled($advanced, 'Nationality', lookupName($LOOKUPS['country'], $s['nationality']));
    pushIfFilled($advanced, 'Country of Birth', lookupName($LOOKUPS['country'], $s['country_of_birth']));
    pushIfFilled($advanced, 'Country of Residence', lookupName($LOOKUPS['country'], $s['country_of_residence']));

    // addresses
    $addrRs = Database::search("SELECT * FROM `student_has_address` WHERE `students_student_id`='" . $studentId . "'");
    $i = 0;
    while ($a = $addrRs->fetch_assoc()) {
        $i++;
        $parts = array_filter([
            $a['address_line_1'] ?? '',
            $a['address_line_2'] ?? '',
            lookupName($LOOKUPS['city'], $a['city_city_id'] ?? null),
            $a['postal_code'] ?? '',
        ], function ($v) {
            return trim((string)$v) !== '';
        });
        pushIfFilled($advanced, $i > 1 ? 'Address ' . $i : 'Address', implode(', ', $parts));
    }

    // guardians
    $gRs = Database::search("
        SELECT `guardian_details`.*, `title`.`title` AS `g_title`
        FROM `guardian_details`
        LEFT JOIN `title` ON `guardian_details`.`title_title_id` = `title`.`title_id`
        WHERE `students_student_id`='" . $studentId . "'
    ");
    $g = 0;
    while ($gd = $gRs->fetch_assoc()) {
        $g++;
        $suffix = $g > 1 ? ' ' . $g : '';
        $name = trim(($gd['g_title'] ? $gd['g_title'] . '.' : '') . ($gd['first_name'] ?? '') . ' ' . ($gd['last_name'] ?? ''));
        pushIfFilled($advanced, 'Guardian' . $suffix, $name);
        pushIfFilled($advanced, 'Guardian Relationship' . $suffix, lookupName($LOOKUPS['relation'], $gd['guardian_relation_guardian_relation_id'] ?? null));
        pushIfFilled($advanced, 'Guardian Mobile' . $suffix, $gd['mobile'] ?? '');
        pushIfFilled($advanced, 'Guardian Email' . $suffix, $gd['email'] ?? '');
        pushIfFilled($advanced, 'Guardian WhatsApp' . $suffix, $gd['whatsapp'] ?? '');
    }

    /* ---------- engaged intakes ---------- */
    $intakes = [];
    $engagedIds = [];
    $iRs = Database::search("
        SELECT
            `shi`.`students_has_intakes_id`,
            `shi`.`addmission_date`,
            `shi`.`entry_type`,
            `shi`.`payment_option`,
            `shi`.`lead_student_status_lead_student_status_id` AS `status_id`,
            `lss`.`lead_student_status` AS `status_name`,
            `p`.`programme`,
            `f`.`faculty`,
            `b`.`branch`,
            `i`.*
        FROM `students_has_intakes` `shi`
        INNER JOIN `intakes` `i` ON `i`.`intake_id` = `shi`.`intakes_intake_id`
        INNER JOIN `programmes` `p` ON `p`.`programme_id` = `i`.`programmes_programme_id`
        INNER JOIN `faculties` `f` ON `f`.`faculty_id` = `p`.`faculties_faculty_id`
        INNER JOIN `branches` `b` ON `b`.`branch_id` = `f`.`branches_branch_id`
        LEFT JOIN `lead_student_status` `lss` ON `lss`.`lead_student_status_id` = `shi`.`lead_student_status_lead_student_status_id`
        WHERE `shi`.`students_student_id` = '" . $studentId . "'
        ORDER BY `shi`.`addmission_date` DESC
    ");
    while ($r = $iRs->fetch_assoc()) {
        $engagedIds[] = (int)$r['intake_id'];
        // intake name column isn't visible in the files I received - try common names
        $intakeLabel = $r['intake'] ?? ($r['intake_name'] ?? ($r['name'] ?? ('Intake #' . $r['intake_id'])));
        $intakes[] = [
            'shi_id'         => (int)$r['students_has_intakes_id'],
            'intake_id'      => (int)$r['intake_id'],
            'intake'         => $intakeLabel,
            'programme'      => $r['programme'],
            'faculty'        => $r['faculty'],
            'branch'         => $r['branch'],
            'admission_date' => !empty($r['addmission_date']) ? date('Y-m-d h:i A', strtotime($r['addmission_date'])) : '',
            'entry_type'     => $r['entry_type'],
            'payment_option' => $r['payment_option'],
            'status_id'      => (int)$r['status_id'],
            'status'         => $r['status_name'] ?? 'Enrollment Completed',
        ];
    }

    /* ---------- identity documents on file: student-level (common) only, never other intakes' documents ---------- */
    $documents = [];
    $docTypesOnFile = [];
    $dRs = Database::search("
        SELECT `d`.`students_identity_documents_type_id` AS `type_id`, `d`.`document`, `d`.`date_added`,
               `d`.`students_has_intakes_students_has_intakes_id` AS `shi_id`,
               `t`.`students_identity_documents_type` AS `type_name`
        FROM `students_identity_documents` `d`
        INNER JOIN `students_identity_documents_type` `t`
            ON `t`.`students_identity_documents_type_id` = `d`.`students_identity_documents_type_id`
        WHERE `d`.`students_student_id` = '" . $studentId . "'
          AND `d`.`students_has_intakes_students_has_intakes_id` IS NULL
        ORDER BY `d`.`date_added` DESC
    ");
    while ($d = $dRs->fetch_assoc()) {
        $folder = getDocumentFolderByTypeId($d['type_id']);
        $docTypesOnFile[(int)$d['type_id']] = true;
        $documents[] = [
            'type_id'   => (int)$d['type_id'],
            'type_name' => $d['type_name'],
            'file_name' => $d['document'],
            'url'       => $folder ? './assets/uploads/student-identity-documents/' . $folder . '/' . $d['document'] : null,
            'date'      => !empty($d['date_added']) ? date('Y-m-d', strtotime($d['date_added'])) : '',
            'scope'     => $d['shi_id'] ? 'intake' : 'common',
        ];
    }

    $hasImg = !empty($s['profile_img']) && file_exists('../assets/imgs/students/' . $s['profile_img']);

    respond([
        'student' => [
            'id'       => $studentId,
            'name'     => trim(($s['title_name'] ? $s['title_name'] . '.' : '') . $s['first_name'] . ' ' . $s['last_name']),
            'initials' => strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1)),
            'image'    => $hasImg ? './assets/imgs/students/' . $s['profile_img'] : null,
        ],
        'basic'               => $basic,
        'advanced'            => $advanced,
        'intakes'             => $intakes,
        'engaged_intake_ids'  => $engagedIds,
        'documents'           => $documents,
        'common_on_file'      => [
            'passport_nic'        => isset($docTypesOnFile[5]),
            'passport_size_photo' => isset($docTypesOnFile[10]),
        ],
        'required_documents'  => getRequiredDocumentsForStudent($studentId),
    ]);
} catch (Throwable $e) {
    respond(['error' => 'Unable to load student details']);
}
