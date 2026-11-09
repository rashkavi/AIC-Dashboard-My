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
            $code = isset($_POST['code']) ? trim($_POST['code']) : '';
            $topic = isset($_POST['topic']) ? trim($_POST['topic']) : '';
            $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
            $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
            $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
            $plagiarism_report = isset($_POST['plagiarism_report']) ? $_POST['plagiarism_report'] : 0;
            $assignment_status = isset($_POST['assignment_status']) ? $_POST['assignment_status'] : '';

            $late_date = trim($_POST['late_date'] ?? '');
            $late_time = trim($_POST['late_time'] ?? '');
            $rec_date  = trim($_POST['recorrection_date'] ?? '');
            $rec_time  = trim($_POST['recorrection_time'] ?? '');
            $rec_fee   = trim($_POST['recorrection_fee'] ?? '');

            $attachment = '';

            if (empty($assignment_id)) {
                $status->error = 'Invalid assignment id';
            } else {
                $assignment_id = (int)$assignment_id;
                $isValidAssiId = Database::search("SELECT * FROM `assignments` 
                INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                WHERE `assignment_id` = '" . $assignment_id . "'");

                if ($isValidAssiId->num_rows == 0) {
                    $status->error = 'Invalid assignment id';
                } else {
                    $assignmentData = $isValidAssiId->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('assignments', 10, $assignment_id))) {
                        $status->error = 'You are not authorized to edit this assignment.';
                    } else {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $today = $dateTime->format('Y-m-d');

                        // Stored values split into date/time parts ('' when null)
                        $startStored = splitDateTime($assignmentData['start_date']);
                        $endStored   = splitDateTime($assignmentData['end_date']);
                        $lateStored  = splitDateTime($assignmentData['late_submission_end_date']);
                        $recStored   = splitDateTime($assignmentData['recorrection_apply_end_date']);

                        // ---- Code / topic ----
                        if (empty($code)) {
                            $status->code = 'Assignment code is required';
                        } else if (strlen($code) > 45) {
                            $status->code = 'Assignment code should be less than 45 characters';
                        } 

                        if (empty($topic)) {
                            $status->topic = 'Assignment topic is required';
                        }

                        if (empty($assignment_status)) {
                            $status->assi_status = 'Please select an assignment status';
                        }

                        // ---- Start / end ----
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
                    
                        if (!empty($start_date) && !empty($end_date) && !property_exists($status, 'start_date') && !property_exists($status, 'end_date')) {
                            if ((new DateTime("$start_date $start_time")) >= (new DateTime("$end_date $end_time"))) {
                                $status->end_date = 'The end date and time must be after the start date and time.';
                            }
                        }

                        // Effective assignment end (posted value, else stored value)
                        $effEnd = null;
                        if (!empty($end_date) && !empty($end_time)) {
                            $effEnd = new DateTime("$end_date $end_time");
                        } else if (!empty($assignmentData['end_date'])) {
                            $effEnd = new DateTime($assignmentData['end_date']);
                        }

                        // ---- Late submission ----
                        $lateDT = null;
                        $lateValue = null;
                        $clearLate = false;
                        if ($late_date !== '' || $late_time !== '') {
                            if ($late_date === '') {
                                $status->late_date = 'A late submission date is required for the selected time.';
                            }
                            if ($late_time === '') {
                                $status->late_time = 'A late submission time is required for the selected date.';
                            }
                            if (!property_exists($status, 'late_date') && !property_exists($status, 'late_time')) {
                                if ($effEnd === null) {
                                    $status->late_date = 'Set the assignment end date & time before adding a late submission date.';
                                } else {
                                    $lateDT = new DateTime("$late_date $late_time");
                                    $lateChanged = ($lateStored[0] != $late_date || $lateStored[1] != $late_time);
                                    if ($lateDT <= $effEnd) {
                                        $status->late_date = 'Late submission end must be after the assignment end date & time.';
                                    } else if ($lateChanged && !checkIfFutureDateTime($late_date, $late_time)) {
                                        $status->late_date = 'The selected late submission end date and time is in the past.';
                                    } else {
                                        $lateValue = $lateDT->format('Y-m-d H:i');
                                    }
                                }
                            }
                        } else if (!empty($assignmentData['late_submission_end_date'])) {
                            $hasLateSubmitAssi = Database::search("SELECT COUNT(`students_has_assignments_id`) AS 'count' FROM `students_has_assignments` 
                            WHERE `assignments_assignment_id`='" . $assignment_id . "' AND `submission_submission_id`='2'")->fetch_assoc()['count'];
                            if ($hasLateSubmitAssi > 0) {
                                $status->late_date = 'Late submissions already exist, so the late submission deadline cannot be removed.';
                            } else {
                                $clearLate = true;
                            }
                        }

                        // ---- Recorrection ----
                        $recDT = null;
                        $recValue = null;
                        $clearRec = false;
                        if ($rec_date !== '' || $rec_time !== '') {
                            if ($rec_date === '') {
                                $status->recorrection_date = 'A recorrection end date is required for the selected time.';
                            }
                            if ($rec_time === '') {
                                $status->recorrection_time = 'A recorrection end time is required for the selected date.';
                            }
                            if (!property_exists($status, 'recorrection_date') && !property_exists($status, 'recorrection_time')) {
                                $baseDT = $lateDT ?? $effEnd;
                                if ($baseDT === null) {
                                    $status->recorrection_date = 'Set the assignment end date & time before adding a recorrection date.';
                                } else {
                                    $recDT = new DateTime("$rec_date $rec_time");
                                    $recChanged = ($recStored[0] != $rec_date || $recStored[1] != $rec_time);
                                    if ($recDT <= $baseDT) {
                                        $status->recorrection_date = $lateDT
                                            ? 'Recorrection end must be after the late submission end.'
                                            : 'Recorrection end must be after the assignment end date & time.';
                                    } else if ($recChanged && !checkIfFutureDateTime($rec_date, $rec_time)) {
                                        $status->recorrection_date = 'The selected recorrection end date and time is in the past.';
                                    } else {
                                        $recValue = $recDT->format('Y-m-d H:i');
                                    }
                                }
                            }
                        } else if (!empty($assignmentData['recorrection_apply_end_date'])) {
                            $hasRecorrectionRequest = Database::search("SELECT COUNT(`assignment_recorrection_payment_id`) AS 'count' FROM `assignment_recorrection_payment` 
                            WHERE `students_has_assignments_students_has_assignments_id` IN (SELECT `students_has_assignments_id` 
                            FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $assignment_id . "')")->fetch_assoc()['count'];
                            if ($hasRecorrectionRequest > 0) {
                                $status->recorrection_date = 'Recorrection requests already exist, so the recorrection deadline cannot be removed.';
                            } else {
                                $clearRec = true;
                            }
                        }

                        // ---- Recorrection fee ----
                        $feeValue = null;
                        if ($rec_fee !== '') {
                            if (!is_numeric($rec_fee) || (float)$rec_fee < 0) {
                                $status->recorrection_fee = 'Recorrection fee must be a valid non-negative number.';
                            } else if ((float)$rec_fee > 0 && $recDT === null && !property_exists($status, 'recorrection_date')) {
                                $status->recorrection_date = 'Set a recorrection end date & time for the fee.';
                            } else {
                                $feeValue = round((float)$rec_fee, 2);
                            }
                        }

                        // ---- Status specific requirements ----
                        if (!property_exists($status, 'assi_status')) {
                            if ($assignment_status == 4 && $recDT === null) {
                                $status->assi_status = "Please select recorrection end date and time";
                            } else if ($assignment_status == 5 && $lateDT === null) {
                                $status->assi_status = "Please select late submission end date and time";
                            }
                        }

                        // ---- File ----
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

                        if (empty(get_object_vars($status))) {
                            $date = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');

                            $assignment_table_update_query = "`assignment_code`='" . esc($code) . "',`assignment`='" . esc($topic) . "',`plagiarism_status_plagiarism_status_id`='" . (int)$plagiarism_report . "'";

                            if (!empty($end_date) && !empty($end_time)) {
                                $start = (new DateTime("$start_date $start_time"))->format('Y-m-d H:i');
                                $end = (new DateTime("$end_date $end_time"))->format('Y-m-d H:i');

                                $assignment_table_update_query .= ",`start_date`='" . $start . "',`end_date`='" . $end . "'";

                                if ($assignmentData['assignment_status_assignment_status_id'] != $assignment_status) {
                                    $assignment_table_update_query .= ",`assignment_status_assignment_status_id`='" . (int)$assignment_status . "'";
                                } else if (isset($_FILES["file"])) {
                                    $assignment_table_update_query .= ",`assignment_status_assignment_status_id`='2'";
                                }
                            } else {
                                $assignment_table_update_query .= ",`assignment_status_assignment_status_id`='1'";
                            }

                            if (isset($_FILES["file"])) {
                                $assignment_table_update_query .= ",`attachment`='" . $attachment . "'";
                            }

                            // Late submission end (set / clear)
                            if ($lateValue !== null) {
                                $assignment_table_update_query .= ",`late_submission_end_date`='" . $lateValue . "'";
                            } else if ($clearLate) {
                                $assignment_table_update_query .= ",`late_submission_end_date`=NULL";
                            }

                            // Recorrection apply end (set / clear)
                            if ($recValue !== null) {
                                $assignment_table_update_query .= ",`recorrection_apply_end_date`='" . $recValue . "'";
                            } else if ($clearRec) {
                                $assignment_table_update_query .= ",`recorrection_apply_end_date`=NULL";
                            }

                            try {
                                Database::iud("UPDATE `assignments` SET " . $assignment_table_update_query . " WHERE `assignment_id`='" . $assignment_id . "'");

                                Database::iud("INSERT INTO `assignments_logs` (`users_user_id`,`assignments_assignment_id`,`log`,`date_time`) 
                                VALUES ('" . $sessionUserObject['user_id'] . "','" . $assignment_id . "','Update Assignmnet','" . $date . "')");

                                // Recorrection fee history: insert a new row only when the fee changed
                                if ($feeValue !== null) {
                                    $latestFee = Database::search("SELECT `recorrection_fee` FROM `assignment_recorrection_fee` 
                                    WHERE `assignments_assignment_id`='" . $assignment_id . "' ORDER BY `recorrection_fee_id` DESC LIMIT 1");
                                    $feeChanged = true;
                                    if ($latestFee->num_rows > 0) {
                                        $feeChanged = abs((float)$latestFee->fetch_assoc()['recorrection_fee'] - $feeValue) > 0.001;
                                    }
                                    if ($feeChanged) {
                                        Database::iud("INSERT INTO `assignment_recorrection_fee` (`recorrection_fee`,`assignments_assignment_id`,`users_user_id`,`date_added`) 
                                        VALUES ('" . $feeValue . "','" . $assignment_id . "','" . $sessionUserObject['user_id'] . "','" . $date . "')");
                                    }
                                }

                                if (isset($_FILES["file"])) {
                                    $path = "../assets/uploads/assignments-attachments/";
                                    move_uploaded_file($_FILES["file"]["tmp_name"], $path . $attachment);

                                    if (!empty($assignmentData['attachment']) && file_exists($path . $assignmentData['attachment'])) {
                                        unlink($path . $assignmentData['attachment']);
                                    }
                                }

                                $status->done = "Assignment updated successfully!";
                            } catch (Exception $e) {
                                $status->error = "Failed to update assignment. " . $e->getMessage();
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

function checkIfFutureDateTime($date, $time)
{
    $timezone = new DateTimeZone("Asia/Colombo");
    $dt = new DateTime("$date $time", $timezone);
    $now = new DateTime('now', $timezone);
    return $dt > $now;
}

/** Splits a stored DATETIME into ['Y-m-d', 'H:i'], or ['', ''] when null/empty. */
function splitDateTime($value): array
{
    if (empty($value)) {
        return ['', ''];
    }
    $dt = new DateTime($value);
    return [$dt->format('Y-m-d'), $dt->format('H:i')];
}

function esc($value): string
{
    return Database::$connection->real_escape_string((string)$value);
}
