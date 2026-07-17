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
            $subject_id = isset($_POST['subject_id']) ? $_POST['subject_id'] : 0;
            $code = isset($_POST['code']) ? $_POST['code'] : '';
            $topic = isset($_POST['topic']) ? $_POST['topic'] : '';
            $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
            $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
            $plagiarism_report = isset($_POST['plagiarism_report']) ? $_POST['plagiarism_report'] : 0;
            $attachment;
            if (empty($subject_id)) {
                $status->error = 'Subject ID is required';
            } else if ($subject_id == 0) {
                $status->error = 'Invalid Subject ID';
            } else {
                $isValidSubject = Database::search("SELECT COUNT(*) as 'count' FROM `subjects` WHERE `subject_id` = '" . $subject_id . "'");
                $row = $isValidSubject->fetch_assoc();
                if ($row['count'] == 0) {
                    $status->error = 'Invalid Subject ID';
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 2, $subject_id))) {
                        $status->error = "You are not authorized to add assignments.";
                    } else {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $addTime = $dateTime->format('Y-m-d H:i:s');
                        $today = $dateTime->format('Y-m-d');
                        $now = $dateTime->format('Y-m-d H:i');
                        if (empty($code)) {
                            $status->code = 'Assignment code is required';
                        } else if (strlen($code) > 45) {
                            $status->code = 'Assignment code should be less than 45 characters';
                        } else {
                            $isValidCode = Database::search("SELECT COUNT(*) as 'count' FROM `assignments` WHERE `assignment_code` = '" . $code . "'");
                            $row = $isValidCode->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->code = 'Assignment code already exists';
                            }
                        }
                        if (empty($topic)) {
                            $status->topic = 'Assignment topic is required';
                        }
                        if (!empty($start_date) || !empty($start_time) || !empty($end_date) || !empty($end_time)) {
                            if (empty($start_date)) {
                                $status->start_date = 'Select a start date.';
                            }
                            if (empty($start_time)) {
                                $status->start_time = 'Select a start time.';
                            }
                            if (empty($end_date)) {
                                $status->end_date = 'Select an end date.';
                            }
                            if (empty($end_time)) {
                                $status->end_time = 'Select an end time.';
                            }
                        }
                        if (!empty($start_date) || !empty($start_time)) {
                            if (empty($start_date)) {
                                $status->start_date = 'A start date is required for the selected start time.';
                            }
                            if (empty($start_time)) {
                                $status->start_time = 'A start time is required for the selected start date.';
                            }
                            if (!property_exists($status, 'start_date') && !property_exists($status, 'start_time')) {
                                if (!checkIfFutureDateTime($start_date, $start_time)) {
                                    $status->start_date = 'The selected start date and time is in the past.';
                                }
                            }
                        }
                        if (!empty($end_date) || !empty($end_time)) {
                            if (empty($end_date)) {
                                $status->end_date = 'An end date is required for the selected end time.';
                            }
                            if (empty($end_time)) {
                                $status->end_time = 'An end time is required for the selected end date.';
                            }
                            if (!property_exists($status, 'end_date') && !property_exists($status, 'end_time')) {
                                if (!checkIfFutureDateTime($end_date, $end_time)) {
                                    $status->end_date = 'The selected end date and time is in the past.';
                                }
                            }
                        }
                        if (!empty($start_date) && (new DateTime($start_date))->format('Y-m-d') === $today) {
                            if (!isset($_FILES["file"])) {
                                $status->file = 'Schedule the assignment for today and make sure to attach the necessary document.';
                            }
                        }
                        if (!empty($start_date) && !empty($end_date)) {
                            if (!isDateAfter($start_date, $end_date)) {
                                $status->end_date = "The selected end date is not matching";
                            }
                        }
                        if (empty($plagiarism_report)) {
                            $status->plagiarism_report = 'Please select a plagiarism report status';
                        }
                        if (isset($_FILES["file"])) {
                            $allowed_file_extensions = array("application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
                            $file_ex = $_FILES["file"]["type"];
                            if (!in_array($file_ex, $allowed_file_extensions)) {
                                $status->file = "Only PDF, DOC, and DOCX files are allowed";
                            } else {
                                $new_file_extension = '';
                                switch ($file_ex) {
                                    case "application/pdf":
                                        $new_file_extension = ".pdf";
                                        break;
                                    case "application/msword":
                                        $new_file_extension = ".doc";
                                        break;
                                    case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                        $new_file_extension = ".docx";
                                        break;
                                }
                                if ($_FILES["file"]["error"] === 0) {
                                    if ($_FILES["file"]["size"] <= 10 * 1024 * 1024) {
                                        $attachment = uniqid() . $new_file_extension;
                                    } else {
                                        $status->file = "Maximum allowed file size is 10MB";
                                    }
                                } else {
                                    $status->file = "There is an error with the selected file file";
                                }
                            }
                        }
                        $requiredProperties = [
                            'error',
                            'file',
                            'plagiarism_report',
                            'end_date',
                            'start_date',
                            'topic',
                            'code'
                        ];
                        $allPropertiesExist = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allPropertiesExist = false;
                                break;
                            }
                        }
                        if ($allPropertiesExist) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');
                            $assignment_table_insert_columns = "`assignment_code`,`assignment`,`added_date`,`users_user_id`,`subjects_subject_id`,`plagiarism_status_plagiarism_status_id`,`assignments_order`";
                            $assignment_table_insert_values = "'" . $code . "','" . $topic . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "','" . $subject_id . "','" . $plagiarism_report . "','0'";
                            if (!empty($start_date) && !empty($end_date)) {
                                $start = (new DateTime("$start_date $start_time"))->format('Y-m-d H:i');
                                $end = (new DateTime("$end_date $end_time"))->format('Y-m-d H:i');
                                $assignment_table_insert_columns .= ",`start_date`,`end_date`";
                                $assignment_table_insert_values .= ",'" . $start . "','" . $end . "'";
                            }
                            if (!empty($start_date) && !empty($end_date) && isset($_FILES["file"])) {
                                $assignment_table_insert_columns .= ",`assignment_status_assignment_status_id`";
                                $assignment_table_insert_values .= ",'2'";
                            } else {
                                $assignment_table_insert_columns .= ",`assignment_status_assignment_status_id`";
                                $assignment_table_insert_values .= ",'1'";
                            }
                            if (isset($_FILES["file"])) {
                                $assignment_table_insert_columns .= ",`attachment`";
                                $assignment_table_insert_values .= ",'" . $attachment . "'";
                            }
                            Database::iud("INSERT INTO `assignments` (" . $assignment_table_insert_columns . ") VALUES (" . $assignment_table_insert_values . ")");
                            $assignmentId = Database::$connection->insert_id;
                            $assignmnet_log_table_insert_columns = "`users_user_id`,`assignments_assignment_id`,`log`,`date_time`";
                            $assignmnet_log_table_insert_values = "'" . $sessionUserObject['user_id'] . "','" . $assignmentId . "','Add Assignmnet','" . $date . "'";
                            Database::iud("INSERT INTO `assignments_logs` (" . $assignmnet_log_table_insert_columns . ") VALUES (" . $assignmnet_log_table_insert_values . ")");
                            if (isset($_FILES["file"])) {
                                $qcv_path = "../assets/uploads/assignments-attachments/" . $attachment;
                                move_uploaded_file($_FILES["file"]["tmp_name"], $qcv_path);
                            }
                            $status->done = "Assignment added successfully!";
                        }
                    }
                }
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

function checkIfFutureDateTime($start_date, $start_time)
{
    if ($start_date && $start_time) {
        $startDateTime = new DateTime("$start_date $start_time");
        $now = new DateTime();
        $isToday = $startDateTime->format('Y-m-d') === $now->format('Y-m-d');
        if (($isToday && $startDateTime > $now) || $startDateTime > $now) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function isDateAfter($startDate, $endDate)
{
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);

    return $end > $start;
}
