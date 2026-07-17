<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Limitation.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    if (!isset($decoded['intakeId']) || $decoded['intakeId'] == 0) {
                        $status->error = 'Invalid intake id';
                    } else {
                        $sValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id`='" . $decoded['intakeId'] . "'");
                        $row = $sValidIntake->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->error = 'Invalid intake id';
                        } else {
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 4, $decoded['intakeId']))) {
                                $status->error = 'You do not have permission to update students status to this intake';
                            } else {
                                if (!isset($decoded['update']) && $decoded["update"] != 0) {
                                    http_response_code(400);
                                    $status->error = 'Invalid user update request';
                                }

                                if (!isset($decoded['checkedStudents']) && !is_array($decoded['checkedStudents'])) {
                                    $status->error = 'Please select atleast one user';
                                }

                                if (!isset($decoded['uncheckedStudents']) && !is_array($decoded['uncheckedStudents'])) {
                                    $status->error = 'Please select atleast one user';
                                }

                                if (!property_exists($status, 'error')) {
                                    $intakeId = $decoded['intakeId'];
                                    $checkedStudents = $decoded['checkedStudents'];
                                    $uncheckedStudents = $decoded['uncheckedStudents'];
                                    $update = $decoded['update'];

                                    if ($update == 1 || $update == 2) {
                                        $changeStatusId = $update;
                                        $activeStatusId = ($changeStatusId == 1 ? 2 : 1);

                                        if ($changeStatusId == 1) {
                                            if (!Limitation::availableStudents()) {
                                                $status->error = "You cannot add more students to intakes because the active student limit has been reached";
                                                echo json_encode($status);
                                                return;
                                            }
                                        }

                                        $idsToUpdate = [];

                                        $baseSelect = "
                                            SELECT shi.students_has_intakes_id
                                            FROM students_has_intakes shi
                                            INNER JOIN students s ON shi.students_student_id = s.student_id
                                            WHERE shi.intakes_intake_id = '$intakeId'
                                            AND shi.status_status_id = '$activeStatusId'
                                        ";

                                        if (!empty($checkedStudents)) {
                                            $baseSelect .= " AND shi.students_has_intakes_id IN (" . implode(',', $checkedStudents) . ")";
                                        }

                                        if (!empty($uncheckedStudents)) {
                                            $baseSelect .= " AND shi.students_has_intakes_id NOT IN (" . implode(',', $uncheckedStudents) . ")";
                                        }

                                        if (empty($checkedStudents) && empty($uncheckedStudents)) {
                                            $currentPage = $decoded['currentPage'];
                                            $resultPerPage = $decoded['resultPerPage'];
                                            $sort = $decoded['sortSelector'];
                                            $searchText = $decoded['searchText'];
                                            $pageResults = ($currentPage - 1) * $resultPerPage;

                                            if (!empty($searchText)) {
                                                $baseSelect .= "
                                                    AND (
                                                        s.first_name LIKE '%$searchText%' OR
                                                        s.last_name  LIKE '%$searchText%' OR
                                                        s.email      LIKE '%$searchText%' OR
                                                        s.mobile     LIKE '%$searchText%'
                                                    )
                                                ";
                                            }

                                            if ($sort != 0) {
                                                $baseSelect .= " AND shi.status_status_id = '$sort'";
                                            }

                                            $baseSelect .= " LIMIT $resultPerPage OFFSET $pageResults";
                                        }

                                        $result = Database::search($baseSelect);

                                        while ($row = $result->fetch_assoc()) {
                                            $idsToUpdate[] = $row['students_has_intakes_id'];
                                        }

                                        if (!empty($idsToUpdate)) {
                                            if ($changeStatusId == 1 && (count($idsToUpdate) + Limitation::getActiveStudentCount()) > 100) {
                                                $status->error = 'You cannot add more students to intakes because the active student limit has been reached';
                                            } else {
                                                $updateQuery = "
                                                UPDATE students_has_intakes
                                                SET status_status_id = '$changeStatusId'
                                                WHERE students_has_intakes_id IN (" . implode(',', $idsToUpdate) . ")
                                            ";

                                                Database::iud($updateQuery);
                                                $status->done = 'All selected Students are now' . " " . ($update == 1 ? 'activated' : 'deactivated');
                                            }
                                        }
                                    }
                                }
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
