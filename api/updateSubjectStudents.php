<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $subject_id = isset($decoded['subjectId']) ? $decoded['subjectId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $checkedStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $uncheckedStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

                    if (empty($subject_id) || $subject_id == 0) {
                        $status->error = 'Invalid subject id';
                    } else {

                        $isValidSubject = Database::search("SELECT COUNT(*) as 'count' FROM `subjects` WHERE `subject_id` = '" . $subject_id . "'");
                        if ($isValidSubject->num_rows == 0) {
                            $status->error = 'Invalid subject id';
                        }
                    }
                    if ($update == 0) {
                        http_response_code(400);
                        $status->error = 'Invalid student update request';
                    }

                    if (!is_array($checkedStudents)) {
                        $status->error = 'Please select atleast one user';
                    }

                    if (!is_array($uncheckedStudents)) {
                        $status->error = 'Please select atleast one user';
                    }

                    if (!property_exists($status, 'error')) {
                        require_once("../includes/connection.php");
                        if ($update == 1 || $update == 2) {
                            $changeStatusId = $update;
                            $activeStatusId = ($changeStatusId == 1 ? 2 : 1);

                            if (count($checkedStudents) > 0) {
                                $query = "UPDATE `students_has_subjects` SET `status_status_id` = '" . $changeStatusId . "' WHERE `status_status_id` = '" . $activeStatusId . "' AND `students_has_subjects_id`  IN (" . implode(",", $checkedStudents) . ")";
                            }

                            if (count($uncheckedStudents) > 0) {
                                $query = "UPDATE `students_has_subjects` SET `status_status_id` = '" . $changeStatusId . "' WHERE `status_status_id` = '" . $activeStatusId . "' AND `students_has_subjects_id`  NOT IN (" . implode(",", $uncheckedStudents) . ")";
                            }

                            if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                $currentPage = $decoded['currentPage'];
                                $resultPerPage = $decoded['resultPerPage'];
                                $sortSelector = $decoded['sortSelector'];
                                $searchText = $decoded['searchText'];
                                $pageResults = ($currentPage - 1) * $resultPerPage;

                                $query = "UPDATE `students_has_subjects` JOIN(SELECT `students_has_subjects_id` FROM `students_has_subjects` INNER JOIN `students` 
                                                        ON `students_has_subjects`.`students_student_id`=`students`.`student_id` 
                                                        WHERE `subjects_subject_id`='" . $subject_id . "' ";

                                if ($searchText != "") {
                                    $query .= " AND `first_name` LIKE '%" . $searchText . "%' OR `last_name` LIKE '%" . $searchText . "%' OR `email` LIKE '%" . $searchText . "%' OR `mobile` LIKE '%" . $searchText . "%'";
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
                                            $query .= " AND `students_has_subjects`.`status_status_id` = '1' ORDER BY `added_at` DESC";
                                            break;
                                        case 4:
                                            $query .= " AND `students_has_subjects`.`status_status_id` = '2' ORDER BY `added_at` DESC";
                                            break;
                                        case 5:
                                            $query .= " ORDER BY `added_at` DESC";
                                            break;
                                        case 6:
                                            $query .= " ORDER BY `added_at` ASC";
                                            break;
                                    }
                                } else {
                                    $query .= " ORDER BY `added_at` DESC";
                                }


                                $query .= " LIMIT " . $resultPerPage . " OFFSET " . $pageResults . ") AS paginated_students
                                                    ON `students_has_subjects`.`students_has_subjects_id` = paginated_students.`students_has_subjects_id`
                                                    SET `students_has_subjects`.`status_status_id` = '" . $changeStatusId . "'
                                                    WHERE `students_has_subjects`.`status_status_id` = '" . $activeStatusId . "'";
                            }

                            Database::iud($query);
                            $status->done = 'All selected Students are now' . " " . ($update == 1 ? 'active' : 'inactive');
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
