<?php
$status = new stdClass();
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once("../includes/connection.php");
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
                    if (!(SessionUser::ifUserIsPro($sessionUserRole)  || SessionUser::ifUserIsAccounts($sessionUserRole) ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4))) {
                        $status->error = 'You are not authorized to update student status';
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

                            $checkedStudents = $decoded['checkedStudents'];
                            $uncheckedStudents = $decoded['uncheckedStudents'];
                            $update = $decoded['update'];

                            if ($update == 1 || $update == 2) {
                                $changeStatusId = $update;
                                $activeStatusId = ($changeStatusId == 1 ? 2 : 1);

                                if (count($checkedStudents) > 0) {
                                    $query = "UPDATE `login_details` SET `status_status_id` = '" . $changeStatusId . "' WHERE `status_status_id` = '" . $activeStatusId . "' AND `login_details_id` 
                                IN (SELECT `login_details_login_details_id` FROM `students` WHERE `student_id` IN (" . implode(",", $checkedStudents) . "))";
                                    Database::iud($query);
                                }

                                if (count($uncheckedStudents) > 0) {
                                    $query = "UPDATE `login_details` SET `status_status_id` = '" . $changeStatusId . "' WHERE `status_status_id` = '" . $activeStatusId . "' AND `login_details_id` 
                                IN (SELECT `login_details_login_details_id` FROM `students` WHERE `studnet_id` IN (" . implode(",", $uncheckedStudents) . "))";
                                    Database::iud($query);
                                }

                                if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                    $currentPage = $decoded['currentPage'];
                                    $resultPerPage = $decoded['resultPerPage'];
                                    $sortSelector = $decoded['sortSelector'];
                                    $searchText = $decoded['searchText'];
                                    $pageResults = ($currentPage - 1) * $resultPerPage;

                                    $query = "SELECT `login_details_id`,`status_status_id`
                                            FROM `students`  
                                           INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                            INNER JOIN `status` ON `login_details`.`status_status_id`=`status`.`status_id`
                                          ";

                                    if ($searchText != "") {
                                        $query .= " WHERE (`first_name` LIKE '%" . $searchText . "%' OR `last_name` LIKE '%" . $searchText . "%' OR `email` LIKE '%" . $searchText . "%' OR `mobile` LIKE '%" . $searchText . "%')";
                                    }

                                    if ($sortSelector != 0) {
                                        switch ($sortSelector) {
                                            case 1:
                                                $query .= " ORDER BY `first_name` ASC";
                                                break;
                                            case 2:
                                                $query .= " ORDER BY `first_name` DESC";
                                                break;
                                            case 3:
                                                if (strpos($query, 'WHERE') == false) {
                                                    $query .= "  WHERE `status_status_id` = '1' ORDER BY `student_id` DESC";
                                                } else {
                                                    $query .= " AND `status_status_id` = '1' ORDER BY `student_id` DESC";
                                                }
                                                break;
                                            case 4:
                                                if (strpos($query, 'WHERE') == false) {
                                                    $query .= "  WHERE `status_status_id` = '2' ORDER BY `student_id` DESC";
                                                } else {
                                                    $query .= " AND `status_status_id` = '2' ORDER BY `student_id` DESC";
                                                }
                                                break;
                                            case 5:
                                                $query .= " ORDER BY `student_id` DESC";
                                                break;
                                            case 6:
                                                $query .= " ORDER BY `student_id` ASC";
                                                break;
                                        }
                                    } else {
                                        $query .= " ORDER BY `student_id` DESC";
                                    }


                                    $query .= " LIMIT " . $resultPerPage . " OFFSET " . $pageResults . " ";

                                    $students = Database::search($query);
                                    while ($student = $students->fetch_assoc()) {
                                        if ($student['status_status_id'] == $activeStatusId) {
                                            Database::iud("UPDATE `login_details` SET `status_status_id`='" . $changeStatusId . "' WHERE `login_details_id`='" . $student['login_details_id'] . "'");
                                        }
                                    }
                                }

                                $status->done = 'All selected Students are now' . " " . ($update == 1 ? 'activated' : 'deactivated');
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
