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

                    $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : '';

                    $query = "
                                    SELECT 
                                        f.*, 
                                        COUNT(p.programme_id) AS total_programmes
                                    FROM faculties f
                                    LEFT JOIN programmes p 
                                        ON p.faculties_faculty_id = f.faculty_id
                                    WHERE f.faculty_id = '" . $faculty . "'
                                    GROUP BY f.faculty_id
                                    ";

                    $faculty = Database::search($query);

                    if ($faculty->num_rows > 0) {
                        $facultyData = $faculty->fetch_assoc();
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('course_overview', 0))) {
                            $status->error = 'You are not authorized to this action.';
                        } else {
                            if ($facultyData['total_programmes'] == 0) {
                                if (file_exists('../assets/imgs/faculties-covers/' . $facultyData['faculty_cover'])) {
                                    unlink('../assets/imgs/faculties-covers/' . $facultyData['faculty_cover']);
                                }
                                $query = "DELETE FROM faculties WHERE faculty_id = '" . $facultyData['faculty_id'] . "'";
                                Database::iud($query);
                                $status->done = 'Faculty Removed';
                            } else {
                                $status->error = 'Faculty has active programmes';
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
