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
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4))) {
                        $status->error = 'You do not have permission to perform this action';
                    }else{
                    
                    if (!isset($decoded['update']) && $decoded["update"] != 0) {
                        http_response_code(400);
                        $status->error = 'Invalid user update request';
                    }

                    if (!isset($decoded['checkedUsers']) && !is_array($decoded['checkedUsers'])) {
                        $status->error = 'Please select atleast one user';
                    }

                    if (!isset($decoded['uncheckedUsers']) && !is_array($decoded['uncheckedUsers'])) {
                        $status->error = 'Please select atleast one user';
                    }

                    if (!property_exists($status, 'error')) {
                        $checkedUsers = $decoded['checkedUsers'];
                        $uncheckedUsers = $decoded['uncheckedUsers'];
                        $update = $decoded['update'];

                        if ($update == 1 || $update == 2) {
                            $changeStatusId = $update;
                            $activeStatusId = ($changeStatusId == 1 ? 2 : 1);

                            if (count($checkedUsers) > 0) {
                                $query = "UPDATE `users` SET `status_status_id` = '" . $changeStatusId . "' WHERE `status_status_id` = '" . $activeStatusId . "' AND `users`.`user_id` IN (" . implode(",", $checkedUsers) . ")";
                            }

                            if (count($uncheckedUsers) > 0) {
                                $query = "UPDATE `users` SET `status_status_id` = '" . $changeStatusId . "' WHERE `status_status_id` = '" . $activeStatusId . "' AND `users`.`user_id` NOT IN (" . implode(",", $uncheckedUsers) . ")";
                            }

                            if (count($checkedUsers) == 0 && count($uncheckedUsers) == 0) {
                                $currentPage = $decoded['currentPage'];
                                $resultPerPage = $decoded['resultPerPage'];
                                $sortSelector = $decoded['sortSelector'];
                                $role = $decoded['role'];
                                $searchText = $decoded['text'];
                                $pageResults = ($currentPage - 1) * $resultPerPage;

                                $query = "UPDATE `users` JOIN(SELECT `user_id` FROM `users`";

                                if ($searchText != "") {
                                    $query .= " WHERE (`user_fname` LIKE '%" . $searchText . "%' OR `user_lname` LIKE '%" . $searchText . "%' OR `user_email` LIKE '%" . $searchText . "%' OR `user_mobile` LIKE '%" . $searchText . "%')";
                                }

                                if ($sortSelector != 0) {
                                    switch ($sortSelector) {
                                        case 1:
                                            $query .= " ORDER BY `user_fname` ASC";
                                            break;
                                        case 2:
                                            $query .= " ORDER BY `user_lname` DESC";
                                            break;
                                        case 3:
                                            if (strpos($query, 'WHERE') == false) {
                                                $query .= " WHERE `status_status_id` = '1' ORDER BY `user_reg_date` DESC";
                                            } else {
                                                $query .= " AND `status_status_id` = '1' ORDER BY `user_reg_date` DESC";
                                            }

                                            break;
                                        case 4:
                                            if (strpos($query, 'WHERE') == false) {
                                                $query .= " WHERE `status_status_id` = '2' ORDER BY `user_reg_date` DESC";
                                            } else {
                                                $query .= " AND `status_status_id` = '2' ORDER BY `user_reg_date` DESC";
                                            }
                                            break;
                                        case 5:
                                            $query .= " ORDER BY `user_reg_date` DESC";
                                            break;
                                        case 6:
                                            $query .= " ORDER BY `user_reg_date` ASC";
                                            break;
                                    }
                                } else {
                                    $query .= " ORDER BY `user_reg_date` DESC";
                                }

                                $query .= " LIMIT " . $resultPerPage . " OFFSET " . $pageResults . ") AS paginated_users
                                                    ON `users`.`user_id` = paginated_users.`user_id`
                                                    SET `users`.`status_status_id` = '" . $changeStatusId . "'
                                                    WHERE `users`.`status_status_id` = '" . $activeStatusId . "'";
                            }

                            Database::iud($query);
                            if (count($checkedUsers) == 1) {
                                $status->done = 'Selected user is now' . " " . ($update == 1 ? 'activated' : 'deactivated');
                            } else {
                                $status->done = 'All selected users are now' . " " . ($update == 1 ? 'activated' : 'deactivated');
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
