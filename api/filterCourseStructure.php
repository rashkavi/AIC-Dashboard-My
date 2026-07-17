<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/courseStructureHelpers.php");

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

                    $filters = [
                        'text'      => isset($decoded['text']) ? trim($decoded['text']) : '',
                        'branch'    => isset($decoded['branch']) ? (int)$decoded['branch'] : 0,
                        'faculty'   => isset($decoded['faculty']) ? (int)$decoded['faculty'] : 0,
                        'programme' => isset($decoded['programme']) ? (int)$decoded['programme'] : 0,
                        'intake'    => isset($decoded['intake']) ? (int)$decoded['intake'] : 0,
                        'level'     => isset($decoded['level']) ? (int)$decoded['level'] : 0,
                    ];

                    $sort_subject    = isset($decoded['sortSubject']) ? (int)$decoded['sortSubject'] : 0;
                    $sort_date       = isset($decoded['sortDate']) ? (int)$decoded['sortDate'] : 0;
                    $resultPerPage   = isset($decoded['resultPerPage']) ? (int)$decoded['resultPerPage'] : 10;
                    $pageno          = isset($decoded['page']) ? (int)$decoded['page'] : 1;

                    // Render table HTML into a buffer, exactly like filterUsers.php does inline
                    ob_start();
                    renderCourseStructureTable($filters, $pageno, $resultPerPage, $sort_subject, $sort_date);
                    $tableHtml = ob_get_clean();

                    // Recompute every dropdown's distinct option list against the *other* active filters,
                    // so selectors always cascade correctly regardless of which one was just changed
                    // (branch -> faculty -> programme -> intake -> level -> subject, or the reverse).
                    $options = getCourseStructureOptions($filters);

                    header('Content-Type: application/json');
                    echo json_encode([
                        'table'   => $tableHtml,
                        'options' => $options,
                    ]);
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