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

            $subject_id = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : 0;
            $lecturer_id = isset($_POST['lecturer_id']) ? trim($_POST['lecturer_id']) : 0;
            $lecture_date = isset($_POST['lecture-date']) ? trim($_POST['lecture-date']) : '';
            $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
            $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $physical = isset($_POST['physical']) ? trim($_POST['physical']) : false;
            $online = isset($_POST['online']) ? trim($_POST['online']) : false;
            $link = isset($_POST['link']) ? trim($_POST['link']) : '';

            if ($subject_id == 0) {
                $status->error = 'Please select a subject';
            } else {
                $isValidSubject = Database::search("SELECT * FROM `subjects` WHERE `subject_id` = '" . $subject_id . "'");

                if ($isValidSubject->num_rows == 0) {
                    $status->error = 'Please select a valid subject';
                } else {
                    $subjectData = $isValidSubject->fetch_assoc();
                    if ($lecturer_id == 0) {
                        $status->lecturer = 'Please select a lecturer';
                    } else {
                        $isValidLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `lecturers` WHERE `lecturers_id` = '" . $lecturer_id . "' AND `subjects_subject_id` = '" . $subject_id . "'");
                        $row = $isValidLecturer->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->lecturer = 'Please select a valid lecturer';
                        }
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
                    if (($physical === false) && ($online === false)) {
                        $status->error = "Please select lecture type";
                    }

                    if (($online === true) && empty($link)) {
                        $status->link = 'Please enter a link1';
                    }

                    if (empty($title)) {
                        $status->title = 'Please enter a title';
                    } else if (strlen($title) > 50) {
                        $status->title = 'Title must be less than 50 characters';
                    }

                    $tutes = array();

                    if (isset($_FILES["tutes"])) {
                        // Allowed file extensions (MIME types)
                        $allowed_tute_extensions = array(
                            "application/pdf",
                            "application/msword",
                            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        );

                        // Process each file uploaded in the "tutes" array
                        foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                            $tute_ex = $_FILES["tutes"]["type"][$key]; // Get the MIME type of the current file

                            // Check if the file extension is allowed
                            if (!in_array($tute_ex, $allowed_tute_extensions)) {
                                $status->tute[] = "File '$tute_name' is not a valid type. Only PDF, DOC, and DOCX files are allowed.";
                            } else if (strlen($tute_name) > 45) {
                                $status->tute[] = "File '$tute_name' exceeds the maximum allowed character limit of 45.";
                            } else {
                                $new_tute_extension = ''; // Variable to store the new file extension

                                // Determine the new file extension based on MIME type
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

                                // Check if there is no error with the file
                                if ($_FILES["tutes"]["error"][$key] === 0) {
                                    // Check if the file size is within the allowed limit (2MB in this case)
                                    if ($_FILES["tutes"]["size"][$key] <= 10 * 1024 * 1024) {
                                        // Generate a unique name for the file to prevent name collisions
                                        $tute = uniqid() . $new_tute_extension;

                                        // Store the unique file name in the $tutes array
                                        array_push($tutes, $tute);

                                        // Optionally, you can move the file to a specific directory
                                        // Example: move_uploaded_file($_FILES["tutes"]["tmp_name"][$key], "uploads/$tute");
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
                                                                                WHERE `lecturers`.`users_user_id` = '" . $lecturer_data['user_id'] . "'
                                                                                AND `time_table`.`date` = '" . $lecture_date . "'
                                                                                AND ((`time_table`.`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                OR (`time_table`.`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                ");
                        $row = $isAvailableLecturer->fetch_assoc();
                        if ($row['count'] > 0) {
                            $status->lecturer = 'The selected time is already scheduled for this lecturer.';
                        }
                    }

                    if (!empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                        $isAvailableSelectTimeInIntake = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                                                                                                  WHERE `subjects_subject_id` NOT IN (SELECT `subjects_subject_id` FROM `subjects` WHERE `subject_group_subject_group_id` = '" . $subjectData['subject_group_subject_group_id'] . "' AND `levels_level_id`='" . $subjectData['levels_level_id'] . "') 
                                                                                          AND `date` = '" . $lecture_date . "'
                                                                                          AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                          OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                                                                                                         ");
                        $row = $isAvailableSelectTimeInIntake->fetch_assoc();
                        if ($row['count'] > 0) {
                            $status->start_time = 'Time slot already booked for this Intake.';
                            $status->end_time = 'Time slot already booked for this Intake.';
                        }
                    }

                    $requiredProperties = [
                        'error',
                        'subject',
                        'lecturer',
                        'lecture_date',
                        'start_time',
                        'end_time',
                        'link',
                        'tute',
                        'date',
                        'start_time',
                        'end_time',
                        'title'
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

                        $time_table_insert_columns = "`date`,`start_time`,`end_time`,`duration`,`subjects_subject_id`,`date_added`,`lecturers_lecturers_id`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                        $time_table_insert_values = "'" . $lecture_date . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $subject_id . "','" . $addTime . "','" . $lecturer_id . "','" . $sessionUserObject["user_id"] . "','1','" . $title . "'";

                        if (filter_var($physical, FILTER_VALIDATE_BOOLEAN)) {
                            $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                            $time_table_insert_values .= ",'1',''";
                        }

                        if (filter_var($online, FILTER_VALIDATE_BOOLEAN)) {
                            $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                            $time_table_insert_values .= ",'2','" . $link . "'";
                        }

                        try {
                            Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");

                            $time_table_id = Database::$connection->insert_id;

                            if (isset($_FILES["tutes"])) {
                                for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {

                                    $originalFileName = $_FILES["tutes"]["name"][$i];

                                    $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                    $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $time_table_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                    Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                    $tute_path = "../assets/uploads/lectures-attachments/" . $tutes[$i];
                                    move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                                }
                            }

                            $status->done = "Lecture has been scheduled successfully";
                        } catch (Exception $e) {
                            echo $e->getMessage();
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
