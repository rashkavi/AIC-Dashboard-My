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

            $assignment_id = isset($_POST['assignment_id']) ? $_POST['assignment_id'] : 0;
            $code = isset($_POST['code']) ? $_POST['code'] : '';
            $topic = isset($_POST['topic']) ? $_POST['topic'] : '';
            $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
            $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
            $plagiarism_report = isset($_POST['plagiarism_report']) ? $_POST['plagiarism_report'] : 0;
            $assignment_status = isset($_POST['assignment_status']) ? $_POST['assignment_status'] : '';

            if ($assignment_status == 4 || $assignment_status == 5) {
                $late_submi_end_date = isset($_POST['late_submi_end_date']) ? $_POST['late_submi_end_date'] : '';
                $late_submi_end_time = isset($_POST['late_submi_end_time']) ? $_POST['late_submi_end_time'] : '';
                $recorection_fee = isset($_POST['recorection_fee']) ? $_POST['recorection_fee'] : 0;
                $recorrection_end_date = isset($_POST['recorrection_end_date']) ? $_POST['recorrection_end_date'] : '';
                $recorrection_end_time = isset($_POST['recorrection_end_time']) ? $_POST['recorrection_end_time'] : '';
            }

            $attachment;

            if (empty($assignment_id) || $assignment_id == 0) {
                $status->error = 'Invalid assignment id';
            } else {
                $isValidAssiId = Database::search("SELECT * FROM `assignments` WHERE `assignment_id` = '" . $assignment_id . "'");

                if ($isValidAssiId->num_rows == 0) {
                    $status->error = 'Invalid assignment id';
                } else {
                    $assignmentData = $isValidAssiId->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('assignments', 10, $assignementData['subjects_subject_id']))) {
                        $status->error = 'You are not authorized to edit this assignment.';
                    } else {

                        $assignmentMarkReleased = Database::search("SELECT COUNT(`lecturers_has_students_has_assignments_id`) AS 'count' FROM `lecturers_has_students_has_assignments` 
                    WHERE `students_has_assignments_students_has_assignments_id` IN (SELECT `students_has_assignments_id` 
                    FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $assignmentData['assignment_id'] . "')")->fetch_assoc()['count'];



                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $addTime = $dateTime->format('Y-m-d H:i:s');
                        $today = $dateTime->format('Y-m-d');
                        $now = $dateTime->format('Y-m-d H:i');

                        $startdateTime = new DateTime($assignmentData['start_date']);
                        $startDate = $startdateTime->format('Y-m-d');
                        $startTime = $startdateTime->format('H:i');

                        $enddateTime = new DateTime($assignmentData['end_date']);
                        $endDate = $enddateTime->format('Y-m-d');
                        $endTime = $enddateTime->format('H:i');

                        $lateSubmitEnddateTime = new DateTime($assignmentData['late_submission_end_date']);
                        $lateSubmitEndDate = $lateSubmitEnddateTime->format('Y-m-d');
                        $lateSubmitEndTime = $lateSubmitEnddateTime->format('H:i');

                        $recorrectionEnddateTime = new DateTime($assignmentData['recorrection_apply_end_date']);
                        $recorrectionEndDate = $recorrectionEnddateTime->format('Y-m-d');
                        $recorrectionEndTime = $recorrectionEnddateTime->format('H:i');

                        if (!empty($code) && strlen($code) > 45) {

                            $status->code = 'Assignment code should be less than 45 characters';
                        } else {
                            $isValidCode = Database::search("SELECT COUNT(*) as 'count' FROM `assignments` WHERE `assignment_code` = '" . $code . "'  AND `assignment_id` != '" . $assignment_id . "'");
                            $row = $isValidCode->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->code = 'Assignment code already exists';
                            }
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
                            if (!property_exists($status, 'start_date') && !property_exists($status, 'start_time')) {
                                if ($assignmentData['start_date'] != null) {
                                    if ($startDate != $start_date || $startTime != $start_time) {
                                        if (!checkIfFutureDateTime($start_date, $start_time)) {
                                            $status->error = 'The selected start date and time is in the past.';
                                            $status->start_date = 'Selected date is invalid';
                                            $status->start_time = 'Selected time is invalid';
                                        }
                                    }
                                } else if (!checkIfFutureDateTime($start_date, $start_time)) {
                                    $status->error = 'The selected start date and time is in the past.';
                                    $status->start_date = 'Selected date is invalid';
                                    $status->start_time = 'Selected time is invalid';
                                }
                            }
                            if ((new DateTime($start_date))->format('Y-m-d') === $today) {

                                if (!isset($_FILES["file"])) {
                                    $checkAlreadyAddedAtachment = Database::search("SELECT `attachment` FROM `assignments` WHERE `assignment_id`='" . $assignment_id . "'");
                                    $data = $checkAlreadyAddedAtachment->fetch_assoc();
                                    if ($data["attachment"] == null) {
                                        $status->file = 'Schedule the assignment for today and make sure to attach the necessary document.';
                                    }
                                }
                            }
                        }

                        if (!empty($end_date) || !empty($end_time)) {
                            if (!property_exists($status, 'end_date') && !property_exists($status, 'end_time')) {
                                if ($assignmentData['end_date'] != null) {
                                    if ($endDate != $end_date || $endTime != $end_time) {
                                        if (!checkIfFutureDateTime($end_date, $end_time)) {
                                            $status->error = 'The selected end date and time is in the past.';
                                            $status->end_date = 'Selected date is invalid';
                                            $status->end_time = 'Selected time is invalid';
                                        }
                                    }
                                } else if (!checkIfFutureDateTime($end_date, $end_time)) {
                                    $status->error = 'The selected end date and time is in the past.';
                                    $status->end_date = 'Selected date is invalid';
                                    $status->end_time = 'Selected time is invalid';
                                }
                            }
                        }

                        if ($assignment_status > 2) {
                            if (!empty($late_submi_end_date) || !empty($late_submi_end_time)) {

                                if (empty($late_submi_end_date)) {
                                    $status->late_submi_end_date = "Please select late submission end date";
                                }

                                if (empty($late_submi_end_time)) {
                                    $status->late_submi_end_time = "Please select late submission end time";
                                }

                                if (!property_exists($status, 'late_submi_end_date') && !property_exists($status, 'late_submi_end_time')) {
                                    if ($assignmentData['late_submission_end_date'] != null) {
                                        if ($lateSubmitEndDate != $late_submi_end_date || $lateSubmitEndTime != $late_submi_end_time) {
                                            if (!checkIfFutureDateTime($late_submi_end_date, $late_submi_end_time)) {
                                                $status->error = 'The selected late submission end date and time is in the past.';
                                                $status->late_submi_end_date = 'Selected date is invalid';
                                                $status->late_submi_end_time = 'Selected time is invalid';
                                            }
                                        }
                                    } else if (!checkIfFutureDateTime($late_submi_end_date, $late_submi_end_time)) {
                                        $status->error = 'The selected late submission end date and time is in the past.';
                                        $status->late_submi_end_date = 'Selected date is invalid';
                                        $status->late_submi_end_time = 'Selected time is invalid';
                                    }
                                }
                            } else {

                                $hasLateSubmitAssi = Database::search("SELECT COUNT(`students_has_assignments_id`) AS 'count' FROM `students_has_assignments` 
                        WHERE `assignments_assignment_id`='" . $assignment_id . "' AND `submission_submission_id`='2'")->fetch_assoc()['count'];

                                if ($hasLateSubmitAssi > 0) {
                                    $status->error = 'Late submissions already exist for this assignment.';
                                    $status->late_submi_end_date = 'Cannot change the late submission deadline to a past date.';
                                    $status->late_submi_end_time = 'Please select a valid future time for late submissions.';
                                }
                            }


                            if ($assignmentMarkReleased > 0) {
                                if (!empty($recorection_fee) || !empty($recorrection_end_date) || !empty($recorrection_end_time)) {
                                    if (empty($recorection_fee)) {
                                        $status->recorrection_fee = "Please add recorrection fee";
                                    }

                                    if (empty($recorrection_end_date)) {
                                        $status->recorrection_end_date = "Please select recorrection end date";
                                    }

                                    if (empty($recorrection_end_time)) {
                                        $status->recorrection_end_time = "Please select recorrection end time";
                                    }

                                    if (!property_exists($status, 'recorrection_end_date') && !property_exists($status, 'recorrection_end_time')) {
                                        if ($assignmentData['recorrection_apply_end_date'] != null) {
                                            if ($lateSubmitEndDate != $recorrection_end_date || $lateSubmitEndTime != $recorrection_end_time) {
                                                if (!checkIfFutureDateTime($recorrection_end_date, $recorrection_end_time)) {
                                                    $status->error = 'The selected recorrection end date and time is in the past.';
                                                    $status->recorrection_end_date = 'Selected date is invalid';
                                                    $status->recorrection_end_time = 'Selected time is invalid';
                                                }
                                            }
                                        } else if (!checkIfFutureDateTime($recorrection_end_date, $recorrection_end_time)) {
                                            $status->error = 'The selected recorrection end date and time is in the past.';
                                            $status->recorrection_end_date = 'Selected date is invalid';
                                            $status->recorrection_end_time = 'Selected time is invalid';
                                        }
                                    }
                                } else {
                                    $hasRecorrectionRequest = Database::search("SELECT COUNT(`assignment_recorrection_payment_id`) AS 'count' FROM `assignment_recorrection_payment` 
                        WHERE `students_has_assignments_students_has_assignments_id` IN (SELECT `students_has_assignments_id` 
                        FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $assignment_id . "')")->fetch_assoc()['count'];

                                    if ($hasRecorrectionRequest > 0) {
                                        $status->error = 'Cannot empty recorrection deadline.';
                                        $status->recorrection_end_date = 'Recorrection requests already exist for this assignment.';
                                    }
                                }
                            }
                        }



                        if ($assignment_status == 4) {
                            if (empty($recorrection_end_date) || empty($recorrection_end_time)) {
                                $status->assi_status = "Please select recorrection end date and time";
                            }
                        } else if ($assignment_status == 5) {
                            if (empty($late_submi_end_date) || empty($late_submi_end_time)) {
                                $status->assi_status = "Please select late submission end date and time";
                            }
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
                            'code',
                            'start_date',
                            'start_time',
                            'end_date',
                            'end_time',
                            'file',
                            'recorection_fee',
                            'late_submi_end_date',
                            'late_submi_end_time',
                            'recorrection_end_date',
                            'recorrection_end_time',
                            'assi_status'

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

                            $assignment_table_update_query =  "`assignment_code`='" . $code . "',`plagiarism_status_plagiarism_status_id`='" . $plagiarism_report . "'";

                            if (!empty($end_date) && !empty($end_time)) {
                                $start = (new DateTime("$start_date $start_time"))->format('Y-m-d H:i');
                                $end = (new DateTime("$end_date $end_time"))->format('Y-m-d H:i');

                                $assignment_table_update_query .= ",`start_date`='" . $start . "',`end_date`='" . $end . "'";

                                if ($assignmentData['assignment_status_assignment_status_id'] != $assignment_status) {
                                    $assignment_table_update_query .= ",`assignment_status_assignment_status_id`='" . $assignment_status . "'";
                                } else {
                                    if (isset($_FILES["file"])) {
                                        $assignment_table_update_query .= ",`assignment_status_assignment_status_id`='2'";
                                    }
                                }
                            } else {
                                $assignment_table_update_query .= ",`assignment_status_assignment_status_id`='1'";
                            }

                            if (isset($_FILES["file"])) {
                                $assignment_table_update_query .=  ",`attachment`='" . $attachment . "'";
                            }

                            if ($assignment_status == 4 || $assignment_status == 5) {
                                if ($assignmentMarkReleased > 0) {
                                    if (!empty($recorrection_end_date) && !empty($recorrection_end_time)) {
                                        $recorrection_start = (new DateTime("$recorrection_end_date $recorrection_end_time"))->format('Y-m-d H:i');
                                        $assignment_table_update_query .= ",`recorrection_apply_end_date`='" . $recorrection_start . "'";
                                    }
                                }

                                if (!empty($lateSubmitEndDate) && !empty($lateSubmitEndTime)) {
                                    $lateSubmitStart = (new DateTime("$lateSubmitEndDate $lateSubmitEndTime"))->format('Y-m-d H:i');
                                    $assignment_table_update_query .= ",`late_submission_end_date`='" . $lateSubmitStart . "'";
                                }
                            }


                            try {
                                Database::iud("UPDATE `assignments` SET " . $assignment_table_update_query . " WHERE `assignment_id`='" . $assignment_id . "'");

                                $assignmnet_log_table_insert_columns = "`users_user_id`,`assignments_assignment_id`,`log`,`date_time`";
                                $assignmnet_log_table_insert_values = "'" . $sessionUserObject['user_id'] . "','" . $assignment_id . "','Update Assignmnet','" . $date . "'";

                                Database::iud("INSERT INTO `assignments_logs` (" . $assignmnet_log_table_insert_columns . ") VALUES (" . $assignmnet_log_table_insert_values . ")");

                                if ($assignment_status == 4 || $assignment_status == 5) {
                                    $assignmentRecorection_rs = Database::search("SELECT * FROM `assignment_recorrection_fee` WHERE `assignments_assignment_id`='" . $assignment_id . "'");
                                    if ($assignmentRecorection_rs->num_rows > 0) {
                                        $assignmentRecorection_data = $assignmentRecorection_rs->fetch_assoc();
                                        $assignment_recorrection_fee_table_update_query = "`recorrection_fee`='" . $recorection_fee . "'";

                                        Database::iud("UPDATE `assignment_recorrection_fee` SET " . $assignment_recorrection_fee_table_update_query . " WHERE `recorrection_fee_id`='" . $assignmentRecorection_data['recorrection_fee_id'] . "'");
                                    } else {
                                        $assignment_recorrection_fee_table_insert_columns = "`recorrection_fee`,`assignments_assignment_id`,`date_added`,`users_user_id`";
                                        $assignment_recorrection_fee_table_insert_values = "'" . $recorection_fee . "','" . $assignment_id . "','" . $date . "','" . $sessionUserObject['user_id'] . "'";

                                        Database::iud("INSERT INTO `assignment_recorrection_fee` (" . $assignment_recorrection_fee_table_insert_columns . ") VALUES (" . $assignment_recorrection_fee_table_insert_values . ")");
                                    }
                                }

                                if (isset($_FILES["file"])) {
                                    $path = "../assets/uploads/assignments-attachments/";
                                    $attchment_path = "../assets/uploads/assignments-attachments/" . $attachment;
                                    move_uploaded_file($_FILES["file"]["tmp_name"], $attchment_path);

                                    if (!empty($assignementData['attachment']) && file_exists($path . $assignementData['attachment'])) {
                                        unlink($path . $assignementData['attachment']);
                                    }
                                }

                                $status->done = "Assignment updated successfully!";
                            } catch (Exception $e) {
                                $status->error = "Faild to update assignment" . $e->getMessage();
                            }
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
    $timezone = new DateTimeZone("Asia/Colombo");
    $startDateTime = new DateTime("$start_date $start_time", $timezone);
    $now = new DateTime('now', $timezone);

    return ($startDateTime > $now) || $startDateTime > $now;
}
