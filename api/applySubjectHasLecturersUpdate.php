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

                    if (!isset($decoded['subjectId']) || $decoded['subjectId'] == 0) {
                        $status->error = 'Invalid intake id';
                    } else {
                        $sValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id`='" . $decoded['subjectId'] . "'");
                        $row = $sValidIntake->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->error = 'Invalid intake id';
                        } else {
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 4, $decoded['subjectId']))) {
                                $status->error = 'You do not have permission to update lecturer to this subject';
                            } else {
                                if (!isset($decoded['update']) && $decoded["update"] != 0) {
                                    http_response_code(400);
                                    $status->error = 'Invalid lecturer update request';
                                }

                                if (!isset($decoded['checkedLecturers']) && !is_array($decoded['checkedLecturers'])) {
                                    $status->error = 'Please select atleast one lecturer';
                                }

                                if (!isset($decoded['uncheckedLecturers']) && !is_array($decoded['uncheckedLecturers'])) {
                                    $status->error = 'Please select atleast one lecturer';
                                }

                                if (!property_exists($status, 'error')) {
                                    $subjectId = $decoded['subjectId'];
                                    $checkedLecturers = $decoded['checkedLecturers'];
                                    $uncheckedLecturers = $decoded['uncheckedLecturers'];
                                    $update = $decoded['update'];

                                    if ($update == 1 || $update == 2) {
                                        $changeStatusId = $update;
                                        $activeStatusId = ($changeStatusId == 1 ? 2 : 1);

                                        if (count($checkedLecturers) > 0) {
                                            $query = "UPDATE `lecturers` SET `status_status_id` = '" . $changeStatusId . "' WHERE `subjects_subject_id` = '" . $subjectId . "'
                                        AND `status_status_id` = '" . $activeStatusId . "' AND `lecturers_id`  IN (" . implode(",", $checkedLecturers) . ")";
                                        }

                                        if (count($uncheckedLecturers) > 0) {
                                            $query = "UPDATE `lecturers` SET `status_status_id` = '" . $changeStatusId . "' WHERE `subjects_subject_id`= '" . $subjectId . "' 
                                        AND `status_status_id` = '" . $activeStatusId . "' AND `lecturers_id` NOT IN (" . implode(",", $uncheckedLecturers) . ")";
                                        }

                                        if (count($checkedLecturers) == 0 && count($uncheckedLecturers) == 0) {
                                            $query = "UPDATE `lecturers` SET `status_status_id` = '" . $changeStatusId . "' WHERE `subjects_subject_id` = '" . $subjectId . "'
                                        AND `status_status_id` = '" . $activeStatusId . "'";
                                        }

                                        Database::iud($query);
                                        if (count($checkedLecturers) == 1) {
                                            $status->done = 'Selected Lecturer is now' . " " . ($update == 1 ? 'active' : 'inactive');
                                        } else {
                                            $status->done = 'All selected Lecturers are now' . " " . ($update == 1 ? 'active' : 'inactive');
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
