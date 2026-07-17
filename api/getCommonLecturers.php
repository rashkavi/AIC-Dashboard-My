<?php
/**
 * Given a set of checked subject_ids from the multi-branch table, returns the
 * lecturers (users) who are assigned to EVERY one of them — not just one.
 *
 * This is a relational-division query: GROUP BY the lecturer's user_id, then
 * HAVING COUNT(DISTINCT subjects_subject_id) = the number of subject_ids we were
 * asked about. A lecturer only survives the HAVING if their rows in `lecturers`
 * cover the full set of checked subjects, so a lecturer teaching only some of the
 * selected subjects is correctly excluded (a plain WHERE ... IN (...) would let
 * them through incorrectly, since IN() only requires matching at least one).
 */
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $sessionUserObject = SessionUser::getCurrentUserObject();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $subjectIds = isset($decoded['subjectIds']) && is_array($decoded['subjectIds']) ? $decoded['subjectIds'] : [];
                    $subjectIds = array_map('intval', $subjectIds);
                    $subjectIds = array_values(array_unique(array_filter($subjectIds, function ($id) {
                        return $id > 0;
                    })));

                    if (count($subjectIds) === 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['lecturers' => []]);
                        exit;
                    }

                    $totalSelected = count($subjectIds);
                    $idsList = implode(',', $subjectIds);

                    $query = "SELECT
                            `users`.`user_id`,
                            `title`.`title`,
                            `users`.`user_fname`,
                            `users`.`user_lname`
                        FROM `lecturers`
                        INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                        WHERE `lecturers`.`subjects_subject_id` IN (" . $idsList . ")
                            AND `lecturers`.`status_status_id` = 1
                            AND `users`.`status_status_id` = 1
                        GROUP BY `lecturers`.`users_user_id`
                        HAVING COUNT(DISTINCT `lecturers`.`subjects_subject_id`) = " . $totalSelected . "
                        ORDER BY `users`.`user_fname` ASC";

                    $result = Database::search($query);
                    $lecturers = [];
                    while ($row = $result->fetch_assoc()) {
                        $lecturers[] = [
                            'id'   => $row['user_id'],
                            'name' => $row['title'] . '. ' . $row['user_fname'] . ' ' . $row['user_lname'],
                        ];
                    }

                    header('Content-Type: application/json');
                    echo json_encode(['lecturers' => $lecturers]);
                    exit;
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo json_encode($status);
}