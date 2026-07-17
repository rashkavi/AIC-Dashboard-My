<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 10))) {
            $status->error = 'You are not authorized to edit scheduled lectures';
        } else {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $lecture_id = isset($_POST['lecture_id']) ? trim($_POST['lecture_id']) : 0;
                $level_id = isset($_POST['level_id']) ? trim($_POST['level_id']) : 0;
                $subject_id = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : 0;
                $lecturer_id = isset($_POST['lecturer_id']) ? trim($_POST['lecturer_id']) : 0;
                $lecture_date = isset($_POST['lecture_date']) ? trim($_POST['lecture_date']) : '';
                $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
                $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
                $physical = isset($_POST['physical']) && $_POST['physical'] === 'true' ? true : false;
                $online = isset($_POST['online']) && $_POST['online'] === 'true' ? true : false;
                $link = isset($_POST['link']) ? trim($_POST['link']) : '';

                if (empty($lecture_id) || $lecture_id == 0) {
                    $status->error = "Invalid lecture Id";
                } else {
                    $isValidLecture = Database::search("SELECT COUNT(*) as 'count' FROM `time_table` WHERE `time_table_id`='" . $lecture_id . "'");
                    $row = $isValidLecture->fetch_assoc();
                    if ($row['count'] == 0) {
                        $status->error = "Invalid lecture Id";
                    } else {
                        if ($subject_id == 0) {
                            $status->subject = 'Please select a subject';
                        }
                        if ($lecturer_id == 0) {
                            $status->lecturer = 'Please select a lecturer';
                        }

                        if (empty($lecture_date)) {
                            $status->date = 'Please select a lecture date';
                        }
                        if (empty($start_time)) {
                            $status->start_time = 'Please select a start time';
                        }
                        if (empty($end_time)) {
                            $status->end_time = 'Please select an end time';
                        }
                        if (!empty($lecture_date) && !empty($start_time)) {
                            if (!checkIfFutureDateTime($lecture_date, $start_time)) {
                                $status->start_time = 'The selected date and start time is in the past';
                            }
                        }
                        if (!empty($lecture_date) && !empty($end_time)) {
                            if (!checkIfFutureDateTime($lecture_date, $end_time)) {
                                $status->end_time = 'The selected date and end time is in the past';
                            }
                        }
                        if ($online && empty($link)) {
                            $status->link = 'Please enter a link';
                        }

                        $tutes = array();

                        if (isset($_FILES["tutes"])) {
                            $allowed_tute_extensions = array(
                                "application/pdf",
                            );

                            foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                                $tute_ex = $_FILES["tutes"]["type"][$key];

                                if (!in_array($tute_ex, $allowed_tute_extensions)) {
                                    $status->tute[] = "File '$tute_name' is not a valid type. Only PDF, DOC, and DOCX files are allowed.";
                                } else {
                                    $new_tute_extension = '';
                                    switch ($tute_ex) {
                                        case "application/pdf":
                                            $new_tute_extension = ".pdf";
                                            break;
                                        case "application/msword":
                                            $new_tute_extension = ".doc";
                                            break;
                                        case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                            $new_tute_extension = ".docx";
                                            break;
                                    }

                                    if ($_FILES["tutes"]["error"][$key] === 0) {
                                        if ($_FILES["tutes"]["size"][$key] <= 10 * 1024 * 1024) {
                                            $tute = uniqid() . $new_tute_extension;
                                            array_push($tutes, $tute);
                                        } else {
                                            $status->tute[] = "File '$tute_name' exceeds the maximum allowed size of 10MB.";
                                        }
                                    } else {
                                        $status->tute[] = "There was an error uploading the file '$tute_name'.";
                                    }
                                }
                            }
                        }

                        if ($lecturer_id != 0 && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                            $lecturer_rs = Database::search("SELECT `lecturers`.`lecturers_id`,`users_user_id` as 'user_id' FROM `lecturers` WHERE `lecturers`.`lecturers_id` = '" . $lecturer_id . "'");
                            $lecturer_data = $lecturer_rs->fetch_assoc();

                            $isAvailableLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                                                                    INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                                                    WHERE `time_table_id` != '" . $lecture_id . "' AND `lecturers`.`users_user_id` = '" . $lecturer_data['user_id'] . "'
                                                                                    AND `time_table`.`date` = '" . $lecture_date . "'
                                                                                    AND ((`time_table`.`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                    OR (`time_table`.`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                    
                                                                                    ");
                            $row = $isAvailableLecturer->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->lecturer = 'The selected time is unavailable for this lecturer.';
                            }
                        }

                        if ($level_id != 0 && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                            $isAvailableSelectTimeInIntake = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                        INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                                          WHERE `subjects_subject_id` IN (SELECT `subjects_subject_id` FROM `subjects` WHERE `levels_level_id` = '" . $level_id . "') 
                                                                                          AND `date` = '" . $lecture_date . "'
                                                                                          AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                          OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                                                                                                         ");
                            $row = $isAvailableSelectTimeInIntake->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->date = 'The selected time has already been scheduled for a lecture in this Intake.';
                            }
                        }

                        $requiredProperties = [
                            'subject',
                            'lecturer',
                            'lecture_date',
                            'start_time',
                            'end_time',
                            'link',
                            'tute',
                        ];
                        $allFieldsPresent = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allFieldsPresent = false;
                                break;
                            }
                        }

                        if ($allFieldsPresent) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $addTime = $dateTime->format('Y-m-d H:i:s');

                            $start = new DateTime($start_time);
                            $end = new DateTime($end_time);

                            $interval = $start->diff($end);

                            $duration = ($interval->h * 60) + $interval->i;

                            $time_table_update_query = "`date`='" . $lecture_date . "',`start_time`='" . $start_time . "',`end_time`='" . $end_time . "',`duration`='" . $duration . "'";

                            if ($physical) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='1'";
                            }

                            if ($online) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='2',`lecture`='" . $link . "'";
                            }

                            try {
                                Database::iud("UPDATE `time_table` SET " . $time_table_update_query . " WHERE `time_table_id` = '" . $lecture_id . "'");

                                if (isset($_FILES["tutes"])) {
                                    for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {
                                        $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                        $attachment_insert_values = "'" . $tutes[$i] . "','abc','" . $lecture_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                        Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                        $attachment_path = "../assets/uploads/lectures-attachments/" . $tutes[$i];
                                        move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $attachment_path);
                                    }
                                }

                                $status->done = "Lecture has been successfully updated";
                            } catch (Exception $e) {
                                $status->error = $e->getMessage();
                            }
                        }
                    }
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid request method';
            }
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));


function checkIfFutureDateTime($lecture_date, $start_time)
{
    if ($lecture_date && $start_time) {
        $startDateTime = new DateTime("$lecture_date $start_time");
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
