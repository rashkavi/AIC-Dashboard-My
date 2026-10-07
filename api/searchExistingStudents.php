<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

$response = new stdClass();
$response->results = [];

if (!SessionUser::hasValidSession() || SessionUser::getCurrentUserRole() == null) {
    $response->error = 'Authentication Failed!';
    echo json_encode($response);
    exit;
}

$sessionUserRole   = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
    $response->error = 'You are not authorized to register students';
    echo json_encode($response);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (mb_strlen($q) < 2) {
    echo json_encode($response);
    exit;
}

// escape for SQL, then neutralise LIKE wildcards typed by the user
$like = addcslashes(Database::escape($q), '%_');

// phone search: "0771234567" should also match "+94771234567"
$digits = preg_replace('/\D/', '', $q);
$phoneLike = null;
if (strlen($digits) >= 5) {
    $phoneLike = ltrim($digits, '0');
}

$phoneSql = "";
if ($phoneLike !== null && $phoneLike !== '') {
    $p = Database::escape($phoneLike);
    $phoneSql = " OR `students`.`mobile` LIKE '%" . $p . "%' OR `login_details`.`whatsapp` LIKE '%" . $p . "%'";
}

// NOTE: counselors can search every student here (re-enrolment is cross-counselor).
// To restrict, add: AND `students_has_intakes`.`counselor_user_id` = '<id>' with a join.
$sql = "
    SELECT
        `students`.`student_id`,
        `students`.`full_name`,
        `students`.`nic`,
        `students`.`passport`,
        `students`.`mobile`,
        `students`.`profile_img`,
        `login_details`.`first_name`,
        `login_details`.`last_name`,
        `login_details`.`email`,
        `title`.`title`,
        (SELECT COUNT(*) FROM `students_has_intakes` `x`
            WHERE `x`.`students_student_id` = `students`.`student_id`) AS `intake_count`
    FROM `students`
    INNER JOIN `login_details`
        ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
    LEFT JOIN `title`
        ON `students`.`title_title_id` = `title`.`title_id`
    WHERE (
        CONCAT(`login_details`.`first_name`, ' ', `login_details`.`last_name`) LIKE '%" . $like . "%'
        OR `students`.`full_name` LIKE '%" . $like . "%'
        OR `students`.`nic` LIKE '%" . $like . "%'
        OR `students`.`passport` LIKE '%" . $like . "%'
        OR `login_details`.`email` LIKE '%" . $like . "%'
        OR `students`.`mobile` LIKE '%" . $like . "%'
        OR `login_details`.`whatsapp` LIKE '%" . $like . "%'
        " . $phoneSql . "
    )
    ORDER BY `login_details`.`first_name` ASC, `login_details`.`last_name` ASC
    LIMIT 10
";

try {
    $rs = Database::search($sql);
    while ($row = $rs->fetch_assoc()) {
        $hasImg = !empty($row['profile_img']) && file_exists('../assets/imgs/students/' . $row['profile_img']);
        $response->results[] = [
            'id'           => (int)$row['student_id'],
            'name'         => trim(($row['title'] ? $row['title'] . '.' : '') . $row['first_name'] . ' ' . $row['last_name']),
            'initials'     => strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)),
            'email'        => $row['email'],
            'mobile'       => $row['mobile'],
            'id_number'    => !empty($row['nic']) ? $row['nic'] : (!empty($row['passport']) ? $row['passport'] : ''),
            'image'        => $hasImg ? './assets/imgs/students/' . $row['profile_img'] : null,
            'intake_count' => (int)$row['intake_count'],
        ];
    }
} catch (Throwable $e) {
    $response->error = 'Search failed';
}

echo json_encode($response);