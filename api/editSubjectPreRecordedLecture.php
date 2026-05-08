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

            $lecture_id = isset($_POST['lecture_id']) ? trim($_POST['lecture_id']) : 0;
            $lecturer_id = isset($_POST['lecturer_id']) ? trim($_POST['lecturer_id']) : 0;
            $duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
            $time_table_status = isset($_POST['status']) ? trim($_POST['status']) : 0;
            $link = isset($_POST['link']) ? trim($_POST['link']) : '';

            if (empty($lecture_id) || $lecture_id == 0) {
                $status->error = "Invalid lecture Id";
            } else {
                $isValidLecture = Database::search("SELECT * FROM `time_table`
                INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                WHERE `time_table_id`='" . $lecture_id . "'");
                if ($isValidLecture->num_rows == 0) {
                    $status->error = "Invalid lecture Id";
                } else {
                    $lectureData = $isValidLecture->fetch_assoc();
                    if ($lecturer_id == 0) {
                        $status->lecturer = 'Please select a lecturer';
                    } else {
                        $isValidLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `lecturers` WHERE `lecturers_id` = '" . $lecturer_id . "' AND `subjects_subject_id` = '" . $lectureData['subjects_subject_id'] . "'");
                        $row = $isValidLecturer->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->lecturer = 'Please select a valid lecturer';
                        }
                    }

                    if (empty($duration) || !is_numeric($duration) || $duration <= 0) {
                        $status->duration = 'Please enter a valid duration';
                    }

                    if (empty($link)) {
                        $status->link = 'Please enter a link';
                    }

                    if ($time_table_status == 0) {
                        $status->time_table_status = 'Please select a time table status';
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



                    $requiredProperties = [
                        'error',
                        'subject',
                        'lecturer',
                        'duration',
                        'link',
                        'tute',
                        'date',
                        'time_table_status'
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

                        $time_table_update_query = "`lecturers_lecturers_id`='" . $lecturer_id . "',`time_table_status_time_table_status_id`='" . $time_table_status . "',`duration`='" . $duration . "'";

                        $time_table_update_query .= ",`lecture`='" . $link . "'";

                        try {
                            Database::iud("UPDATE `time_table` SET " . $time_table_update_query . " WHERE `time_table_id` = '" . $lecture_id . "'");

                            if (isset($_FILES["tutes"])) {

                                $attchments = Database::search("SELECT * FROM `attachments` WHERE `time_table_time_table_id`='" . $lecture_id . "'");

                                if ($attchments->num_rows > 0) {
                                    while ($attachmentData = $attchments->fetch_assoc()) {
                                        $attachment_path = "../assets/uploads/lectures-attachments/" . $attachmentData["attachment"];
                                        if (file_exists($attachment_path)) {
                                            unlink($attachment_path);
                                        }
                                        Database::iud("DELETE FROM `attachments` WHERE `attachment_id`='" . $attachmentData["attachment_id"] . "'");
                                    }
                                }

                                for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {

                                    $originalFileName = $_FILES["tutes"]["name"][$i];

                                    $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`";
                                    $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $lecture_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "'";

                                    Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                    $tute_path = "../assets/uploads/lectures-attachments/" . $tutes[$i];
                                    move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                                }
                            }

                            $status->done = "Pre-recorded Lecture has been successfully updated";
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
