<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
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
                        $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id`='" . $decoded['intakeId'] . "'");
                        if ($isValidIntake->num_rows == 0) {
                            $status->error = 'Invalid intake id';
                        } else {
                            $intakeData = $isValidIntake->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('students', 0) || UserPermissions::validPermisisonType('course_overview', 0))) {
                                $status->error = 'You are not authorized to this action.';
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
                                    $reson = $decoded['reson'];
                                    $resonNote = $decoded['resonNote'];

                                    if (empty($update)) {
                                        $status->error = 'Invalid user update request';
                                    } else if (!($update == 1 || $update == 2)) {
                                        $status->error = 'Invalid user update request';
                                    } else {

                                        if ($update == 2) {
                                            if (empty($reson)) {
                                                $status->reson = "Reson is required";
                                            } else {
                                                $isValidReson = Database::search("SELECT COUNT(*) as 'count' FROM `students_inactive_reasons` WHERE `students_inactive_reasons_id`='" . $reson . "'");
                                                $row = $isValidReson->fetch_assoc();
                                                if ($row['count'] == 0) {
                                                    $status->reson = 'Invalid Reson';
                                                }
                                            }
                                        }

                                        if (!property_exists($status, 'reson')) {

                                            $timezone = new DateTimeZone("Asia/Colombo");
                                            $dateTime = new DateTime("now", $timezone);
                                            $now = $dateTime->format('Y-m-d H:i:s');

                                            $changeStatusId = $update;
                                            $activeStatusId = ($changeStatusId == 1 ? 2 : 1);
                                            if ($changeStatusId == 1) {
                                                $template = "re_activated_to_batch";
                                            } else {
                                                $template = "de_activated_from_batch";
                                            }

                                            if (count($checkedStudents) > 0) {
                                                $updatedStudents = Database::search("SELECT `students_has_intakes_id`,`whatsapp` FROM `students_has_intakes`
                                                INNER JOIN `students` ON `students_has_intakes`.`students_student_id`=`students`.`student_id`
                                                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                                 WHERE `students_has_intakes_id` IN (" . implode(",", $checkedStudents) . ")");
                                                $updatedStudents = $updatedStudents->fetch_all(MYSQLI_ASSOC);
                                                foreach ($updatedStudents as $student) {

                                                    $columns = "`students_has_intakes_students_has_intakes_id`,`users_user_id`,`date_updated`";
                                                    $values = "'" . $student['students_has_intakes_id'] . "','" . $sessionUserObject['user_id'] . "','" . $now . "'";

                                                    if ($update == 2) {
                                                        $columns .= ",`log`,`students_inactive_reasons_students_inactive_reasons_id`";
                                                        $values .= ",'Deactivate student','" . $reson . "'";

                                                        if (!empty($resonNote)) {
                                                            $columns .= ",`note`";
                                                            $values .= ",'" . Database::escape($resonNote) . "'";
                                                        }
                                                    } else {
                                                        $columns .= ",`log`";
                                                        $values .= ",'Activate student'";
                                                    }

                                                    Database::iud("UPDATE `students_has_intakes` SET `status_status_id` = '" . $changeStatusId . "' WHERE `students_has_intakes_id` = '" . $student['students_has_intakes_id'] . "'");
                                                    Database::iud("INSERT INTO `students_has_intakes_logs`(" . $columns . ") VALUES(" . $values . ")");
                                                }
                                            }

                                            if (count($uncheckedStudents) > 0) {
                                                $updatedStudents = Database::search("SELECT `students_has_intakes_id`,`whatsapp` FROM `students_has_intakes`
                                                INNER JOIN `students` ON `students_has_intakes`.`students_student_id`=`students`.`student_id`
                                                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
                                                WHERE `intakes_intake_id`='" . $intakeId . "' AND `status_status_id` = '" . $activeStatusId . "' AND `students_has_intakes_id`  NOT IN (" . implode(",", $uncheckedStudents) . ")");
                                                $updatedStudents = $updatedStudents->fetch_all(MYSQLI_ASSOC);
                                                foreach ($updatedStudents as $student) {
                                                    $columns = "`students_has_intakes_students_has_intakes_id`,`users_user_id`,`date_updated`";
                                                    $values = "'" . $student['students_has_intakes_id'] . "','" . $sessionUserObject['user_id'] . "','" . $now . "'";

                                                    if ($update == 2) {
                                                        $columns .= ",`log`,`students_inactive_reasons_students_inactive_reasons_id`";
                                                        $values .= ",'Deactivate student','" . $reson . "'";

                                                        if (!empty($resonNote)) {
                                                            $columns .= ",`note`";
                                                            $values .= ",'" . Database::escape($resonNote) . "'";
                                                        }
                                                    } else {
                                                        $columns .= ",`log`";
                                                        $values .= ",'Activate student'";
                                                    }

                                                    Database::iud("UPDATE `students_has_intakes` SET `status_status_id` = '" . $changeStatusId . "' WHERE `students_has_intakes_id`='" . $student['students_has_intakes_id'] . "'");
                                                    Database::iud("INSERT INTO `students_has_intakes_logs`(" . $columns . ") VALUES(" . $values . ")");
                                                }
                                            }

                                            if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                                $currentPage = $decoded['currentPage'];
                                                $resultPerPage = $decoded['resultPerPage'];
                                                $sort = $decoded['sortSelector'];
                                                $searchText = $decoded['searchText'];
                                                $pageResults = ($currentPage - 1) * $resultPerPage;

                                                $query = "SELECT `students_has_intakes_id`,`intake`,`whatsapp` FROM `students_has_intakes` INNER JOIN `students` 
                                                                    ON `students_has_intakes`.`students_student_id`=`students`.`student_id`
                                                                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                                                    INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id`=`intakes`.`intake_id`

                                                                    WHERE `intakes_intake_id`='" . $intakeId . "'";

                                                if ($searchText != "") {
                                                    $query .= " AND `first_name` LIKE '%" . $searchText . "%' OR `last_name` LIKE '%" . $searchText . "%' OR `email` LIKE '%" . $searchText . "%' OR `mobile` LIKE '%" . $searchText . "%'";
                                                }

                                                if ($sort != 0) {
                                                    if (strpos($query, 'WHERE') == false) {
                                                        $query .= " WHERE `students_has_intakes`.`status_status_id` = '" . $sort . "'";
                                                    } else {
                                                        $query .= " AND `students_has_intakes`.`status_status_id` = '" . $sort . "'";
                                                    }
                                                }

                                                $query .= " AND `students_has_intakes`.`status_status_id` = '" . $activeStatusId . "'";

                                                $query .= " LIMIT " . $resultPerPage . " OFFSET " . $pageResults . "";

                                                $results = Database::search($query);
                                                $results = $results->fetch_all(MYSQLI_ASSOC);
                                                foreach ($results as $result) {
                                                    $columns = "`students_has_intakes_students_has_intakes_id`,`users_user_id`,`date_updated`";
                                                    $values = "'" . $result['students_has_intakes_id'] . "','" . $sessionUserObject['user_id'] . "','" . $now . "'";

                                                    if ($update == 2) {
                                                        $columns .= ",`log`,`students_inactive_reasons_students_inactive_reasons_id`";
                                                        $values .= ",'Deactivate student','" . $reson . "'";

                                                        if (!empty($resonNote)) {
                                                            $columns .= ",`note`";
                                                            $values .= ",'" . Database::escape($resonNote) . "'";
                                                        }
                                                    } else {
                                                        $columns .= ",`log`";
                                                        $values .= ",'Activate student'";
                                                    }
                                                    Database::iud("UPDATE `students_has_intakes` SET `status_status_id` = '" . $changeStatusId . "' WHERE `students_has_intakes_id`='" . $result['students_has_intakes_id'] . "'");
                                                    Database::iud("INSERT INTO `students_has_intakes_logs`(" . $columns . ") VALUES(" . $values . ")");
                                                }
                                            }

                                            $status->done = 'All selected Students are now' . " " . ($update == 1 ? 'activated' : 'deactivated');
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

function normalizePhoneNumber($number)
{
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        if ($number[0] !== '+') {
            return '+' . $number;
        }
        return $number;
    }

    return false;
}
