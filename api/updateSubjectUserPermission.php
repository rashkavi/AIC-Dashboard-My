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
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users',6))) {
                        $status->error = "You are not authorized to update user permissions.";
                    } else {
                        $updateStatus = isset($decoded['status']) ? $decoded['status'] : 0;
                        $selectedPermissions = isset($decoded['selectedPermissions']) ? $decoded['selectedPermissions'] : [];
                        if ($updateStatus == 0) {
                            $status->error = "Invalid permission status";
                        }
                        if (count($selectedPermissions) == 0) {
                            $status->error = "Please select at least one user";
                        }
                        if (!property_exists($status, "error")) {
                            date_default_timezone_set('Asia/Colombo');
                            $timestamp = date('Y-m-d H:i:s');

                            Database::iud("UPDATE `users_has_permission_to_subject` SET `status_status_id`='" . $updateStatus . "',`added_by`='" . $sessionUserObject["user_id"] . "',`added_at`='" . $timestamp . "' WHERE `users_has_permission_to_subject_id` IN (" . implode(",", $selectedPermissions) . ")");
                            $status->done = "All selected permissions are now " . ($updateStatus == 1 ? "Active" : "Inactive");
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

echo (json_encode($status));
