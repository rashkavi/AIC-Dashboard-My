<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5))) {
                        $status->error = 'You do not have permission to add permissions.';
                    } else {
                        $subjectId = isset($decoded['subjectId']) ? $decoded['subjectId'] : 0;
                        $usersPermissions = isset($decoded['usersPermissions']) ? $decoded['usersPermissions'] : [];

                        if ($subjectId != 0) {
                            if (count($usersPermissions) > 0) {
                                $processedPermissions = [];
                                foreach ($usersPermissions as $userId => $permissions) {
                                    foreach ($permissions as $permission) {
                                        date_default_timezone_set('Asia/Colombo');
                                        $timestamp = date('Y-m-d H:i:s');

                                        $query = "INSERT INTO `users_has_permission_to_subject` 
                                              (`users_user_id`, `permission_to_permission_to_id`, `subjects_subject_id`, `added_by`, `status_status_id`, `added_at`)
                                              VALUES ('" . $userId . "', '" . $permission . "', '" . $subjectId . "', '" . $sessionUserObject['user_id'] . "', '1', '" . $timestamp . "')";

                                        try {
                                            Database::iud($query);
                                        } catch (Exception $e) {
                                            echo $e->getMessage();
                                        }
                                    }
                                }

                                $status->done = 'Permissions added successfully.';
                            } else {
                                $status->error = 'Select new users permissions and continue.';
                            }
                        } else {
                            $status->error = 'Invalid Programme.';
                        }
                    }
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

echo json_encode($status);
