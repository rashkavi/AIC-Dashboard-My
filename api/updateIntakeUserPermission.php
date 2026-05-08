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
                    $userId = isset($decoded['userId']) ? $decoded['userId'] : 0;
                    $checkAll = isset($decoded['checkAll']) ? $decoded['checkAll'] : false;
                    $updateStatus = isset($decoded['status']) ? $decoded['status'] : 0;
                    $selectedPermissions = isset($decoded['selectedPermissions']) ? $decoded['selectedPermissions'] : [];
                    $unselectedPermissions = isset($decoded['unselectedPermissions']) ? $decoded['unselectedPermissions'] : [];


                    if ($userId == 0) {
                        $status->error = "Invalid user id";
                    } else {
                        if ($updateStatus == 0) {
                            $status->error = "Invalid permission status";
                        }

                        if (count($selectedPermissions) == 0 && count($unselectedPermissions) == 0 && !$checkAll) {
                            $status->error = "Please select at least one permission";
                        }

                        if (!property_exists($status, "error")) {
                            date_default_timezone_set('Asia/Colombo');
                            $timestamp = date('Y-m-d H:i:s');


                            if ($checkAll) {
                                Database::iud("UPDATE `users_has_permission_to_intakes` SET `status_status_id`='" . $updateStatus . "' 
                                WHERE `users_user_id`='" . $userId . "'");
                            }

                            if (count($selectedPermissions) > 0) {
                                Database::iud("UPDATE `users_has_permission_to_intakes` SET `status_status_id`='" . $updateStatus . "' 
                                WHERE `users_has_permission_to_intakes_id` IN (" . implode(",", $selectedPermissions) . ")");
                            }

                            if (count($unselectedPermissions) > 0) {
                                Database::iud("UPDATE `users_has_permission_to_intakes` SET `status_status_id`='" . $updateStatus . "' 
                                WHERE `users_has_permission_to_intakes_id` NOT IN (" . implode(",", $unselectedPermissions) . ")
                                AND `users_user_id`='" . $userId . "'
                                 ");
                            }

                            if (count($selectedPermissions) == 1) {
                                $status->done = "Selected permission is now " . ($updateStatus == 1 ? "Active" : "Inactive");
                            } else {
                                $status->done = "All selected permissions are now " . ($updateStatus == 1 ? "Active" : "Inactive");
                            }
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
