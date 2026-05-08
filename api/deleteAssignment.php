<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $assignment = isset($decoded['assignment']) ? $decoded['assignment'] : '';

                    if (empty($assignment)) {
                        $status->error = 'Invalid Assignemnt ID';
                    } else {
                        $isValidAssignment = Database::search("SELECT COUNT(*) as 'count' FROM `assignments` WHERE `assignment_id` = '" . $assignment . "'");
                        $isValidAssignmentData = $isValidAssignment->fetch_assoc();
                        if ($isValidAssignmentData['count'] > 0) {
                            Database::iud("DELETE FROM `assignment_recorrection_fee` WHERE `assignments_assignment_id`='" . $assignment . "'");
                            Database::iud("DELETE FROM `assignments_logs` WHERE `assignments_assignment_id`='" . $assignment . "'");
                            Database::iud("DELETE FROM `assignments` WHERE `assignment_id`='" . $assignment . "'");

                            $status->done = 'Assignment removed successfully';
                        } else {
                            $status->error = 'Invalid Assignemnt ID';
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
