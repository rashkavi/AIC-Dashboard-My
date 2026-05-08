<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole  == "Developer") {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    if (!isset($decoded['update']) && $decoded["update"] != 0) {
                        http_response_code(400);
                        $status->error = 'Invalid payemnt update request';
                    }

                    if (!isset($decoded['selectPayments']) && !is_array($decoded['selectPayments'])) {
                        $status->error = 'Please select atleast one payemnt';
                    }

                    if (!isset($decoded['unSelectPayments']) && !is_array($decoded['unSelectPayments'])) {
                        $status->error = 'Please select atleast one payemnt';
                    }


                    if (!property_exists($status, 'error')) {
                        $selectPayments = $decoded['selectPayments'];
                        $unSelectPayments = $decoded['unSelectPayments'];
                        $update = $decoded['update'];

                        if ($update == 1 || $update == 2) {
                            $changeStatusId = $update;

                            if (count($selectPayments) > 0) {
                                $query = "UPDATE `portal_payment` SET `portal_payment_status_portal_payment_status_id` = '" . $changeStatusId . "' WHERE  `portal_payment_id` IN (" . implode(",", $selectPayments) . ")";
                                $logQuery = "SELECT `portal_payment_id` FROM `portal_payment` WHERE `portal_payment_id` IN (" . implode(",", $selectPayments) . ")";
                            }

                            if (count($unSelectPayments) > 0) {
                                $query = "UPDATE `portal_payment` SET `portal_payment_status_portal_payment_status_id` = '" . $changeStatusId . "' WHERE  `portal_payment_id` NOT IN (" . implode(",", $selectPayments) . ")";
                                $logQuery = "SELECT `portal_payment_id` FROM `portal_payment` WHERE `portal_payment_id` NOT IN (" . implode(",", $selectPayments) . ")";
                            }

                            if (count($selectPayments) == 0 && count($unSelectPayments) == 0) {
                                $currentPage = $decoded['currentPage'];
                                $searchText = isset($decoded['text']) ? $decoded['text'] : '';
                                $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                                $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                                $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                                $pageResults = ($currentPage - 1) * $resultPerPage;

                                $query = "UPDATE `portal_payment` JOIN(SELECT `portal_payment_id` FROM `portal_payment`
                                INNER JOIN `students` ON `portal_payment`.`students_student_id`=`students`.`student_id`
                                INNER JOIN `portal_payment_status` ON `portal_payment`.`portal_payment_status_portal_payment_status_id`=`portal_payment_status`.`portal_payment_status_id`
                                ";

                                $logQuery = "SELECT `portal_payment_id` FROM `portal_payment`
                                INNER JOIN `students` ON `portal_payment`.`students_student_id`=`students`.`student_id`
                                INNER JOIN `portal_payment_status` ON `portal_payment`.`portal_payment_status_portal_payment_status_id`=`portal_payment_status`.`portal_payment_status_id`";

                                if ($searchText != "") {
                                    $query .= " WHERE (`student_id` LIKE '%" . $searchText . "%' OR `first_name` LIKE '%" . $searchText . "%' OR `last_name` LIKE '%" . $searchText . "%' OR `email` LIKE '%" . $searchText . "%' OR `mobile` LIKE '%" . $searchText . "%')";
                                    $logQuery .= " WHERE (`student_id` LIKE '%" . $searchText . "%' OR `first_name` LIKE '%" . $searchText . "%' OR `last_name` LIKE '%" . $searchText . "%' OR `email` LIKE '%" . $searchText . "%' OR `mobile` LIKE '%" . $searchText . "%')";
                                }

                                if ($sort != 0) {
                                    if ($sort == 1) {
                                        $query .= " ORDER BY `first_name` ASC";
                                        $logQuery .= " ORDER BY `first_name` ASC";
                                    } else if ($sort == 2) {
                                        $query .= " ORDER BY `first_name` DESC";
                                        $logQuery .= " ORDER BY `first_name` DESC";
                                    } else if ($sort == 3) {
                                        $query .= " ORDER BY `portal_payment`.`date_paid` DESC";
                                        $logQuery .= " ORDER BY `portal_payment`.`date_paid` DESC";
                                    } else if ($sort == 4) {
                                        $query .= " ORDER BY `portal_payment`.`date_paid` ASC";
                                        $logQuery .= " ORDER BY `portal_payment`.`date_paid` ASC";
                                    } else if ($sort == 5) {
                                        if (strpos($query, 'WHERE') == false) {
                                            $query .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '1'";
                                            $logQuery .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '1'";
                                        } else {
                                            $query .= " AND `portal_payment_status`.`portal_payment_status_id` = '1'";
                                            $logQuery .= " AND `portal_payment_status`.`portal_payment_status_id` = '1'";
                                        }
                                    } else if ($sort == 6) {
                                        if (strpos($query, 'WHERE') == false) {
                                            $query .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '2'";
                                            $logQuery .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '2'";
                                        } else {
                                            $query .= " AND `portal_payment_status`.`portal_payment_status_id` = '2'";
                                            $logQuery .= " AND `portal_payment_status`.`portal_payment_status_id` = '2'";
                                        }
                                    } else if ($sort == 7) {
                                        if (strpos($query, 'WHERE') == false) {
                                            $query .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '3'";
                                            $logQuery .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '3'";
                                        } else {
                                            $query .= " AND `portal_payment_status`.`portal_payment_status_id` = '3'";
                                            $logQuery .= " AND `portal_payment_status`.`portal_payment_status_id` = '3'";
                                        }
                                    }
                                }

                                if ($sort == 0 || $sort > 4) {
                                    $query .= " ORDER BY `date_paid` DESC";
                                    $logQuery .= " ORDER BY `date_paid` DESC";
                                }


                                $query .= " LIMIT " . $resultPerPage . " OFFSET " . $pageResults . ") AS paginated_payemnts
                                                    ON `portal_payment`.`portal_payment_id` = paginated_payemnts.`portal_payment_id`
                                                    SET `portal_payment`.`portal_payment_status_portal_payment_status_id` = '" . $changeStatusId . "'";



                                $logQuery .= " LIMIT " . $resultPerPage . " OFFSET " . $pageResults . "";
                            }

                            Database::iud($query);
                            $note = $changeStatusId == 2 ? 'Accepted Paymnet' : 'Rejected';
                            $logs = Database::search($logQuery);
                            if ($logs->num_rows > 0) {
                                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                                while ($data = $logs->fetch_assoc()) {

                                    $paymnet_log_columns = "`portal_payment_portal_payment_id`,`users_user_id`,`date_updated`,`note`";
                                    $payment_log_values = "'" . $data['portal_payment_id'] . "','" . $sessionUserObject['user_id'] . "', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $note . "'";
                                    Database::iud("INSERT INTO `portal_payment_logs` (" . $paymnet_log_columns . ") VALUES (" . $payment_log_values . ")");
                                }
                            }

                            if (count($selectPayments) == 1) {
                                $status->done = 'Selected payemnt is ' . " " . ($update == 2 ? 'accepted' : 'rejected');
                            } else {
                                $status->done = 'All selected payments are ' . " " . ($update == 2 ? 'active' : 'rejected');
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
