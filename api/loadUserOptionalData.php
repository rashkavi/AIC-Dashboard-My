<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                $data = Database::search("SELECT * FROM `optional_details` WHERE `users_user_id`='" . $id . "'");
                if ($data->num_rows != 0) {
                    $data = $data->fetch_assoc();
                    $status->data = $data;
                }
            } else {
                $status->error = 'Invalid request';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed2!';
    }
} else {
    $status->error = 'Authentication Failed1!';
}

echo (json_encode($status));
