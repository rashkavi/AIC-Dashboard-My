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

                    $branch = isset($decoded['branch']) ? $decoded['branch'] : '';

                    $query = "
                                     SELECT 
                                         b.*, 
                                         COUNT(f.faculty_id) AS total_faculties
                                     FROM branches b
                                     LEFT JOIN faculties f
                                         ON f.branches_branch_id = b.branch_id
                                    WHERE b.branch_id='" . $branch . "'
                                     GROUP BY b.branch_id
                                     ";

                    $branch = Database::search($query);

                    if ($branch->num_rows > 0) {
                        $branchData = $branch->fetch_assoc();
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('course_overview', 0))) {
                            $status->error = 'You are not authorized to this action.';
                        } else {
                            if ($branchData['total_faculties'] == 0) {
                                if (file_exists('../assets/imgs/branches-covers/' . $branchData['branch_cover'])) {
                                    unlink('../assets/imgs/branches-covers/' . $branchData['branch_cover']);
                                }
                                $query = "DELETE FROM branches WHERE branch_id = '" . $branchData['branch_id'] . "'";
                                Database::iud($query);
                                $status->done = 'Branch Removed';
                            } else {
                                $status->error = 'Branch has active faculties';
                            }
                        }
                    } else {
                        $status->error = 'Invalid Batch ID';
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
