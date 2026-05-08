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
            $duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
            $link = isset($_POST['link']) ? trim($_POST['link']) : '';

            if ($subject_id == 0) {
                $status->error = 'Please select a subject';
            } else {
                $isValidSubject = Database::search("SELECT * FROM `subjects` WHERE `subject_id` = '" . $subject_id . "'");

                if ($isValidSubject->num_rows == 0) {
                    $status->error = 'Please select a valid subject';
                } else {
                    $subjectData = $isValidSubject->fetch_assoc();

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('pre_Recorded_lectures', 2, $subject_id))) {
                        $status->error = 'You do not have permission to add pre-recorded lectures to this subject';
                    } else {
                        if ($lecturer_id == 0) {
                            $status->lecturer = 'Please select a lecturer';
                        } else {
                            $isValidLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `lecturers` WHERE `lecturers_id` = '" . $lecturer_id . "' AND `subjects_subject_id` = '" . $subject_id . "'");
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
                        } elseif (!preg_match('/<iframe.*src="https:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]+.*".*<\/iframe>/i', $link)) {
                            $status->link = "Recording link must be a valid YouTube iframe embed code";
                        }

                        $tutes = array();
                        if (isset($_FILES["tutes"])) {
                            $allowed_tute_extensions = array(
                                "application/pdf",
                                "application/msword",
                                "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            );
                            foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                                $tute_ex = $_FILES["tutes"]["type"][$key];
                                if (!in_array($tute_ex, $allowed_tute_extensions)) {
                                    $status->tute[] = "File '$tute_name' is not a valid type. Only PDF, DOC, and DOCX files are allowed.";
                                } else if (strlen($tute_name) > 45) {
                                    $status->tute[] = "File '$tute_name' exceeds the maximum allowed character limit of 45.";
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
                            'tute'
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

                            $time_table_insert_columns = "`duration`,`subjects_subject_id`,`date_added`,`lecturers_lecturers_id`,`users_user_id`,`time_table_status_time_table_status_id`";
                            $time_table_insert_values = "'" . $duration . "','" . $subject_id . "','" . $addTime . "','" . $lecturer_id . "','" . $sessionUserObject["user_id"] . "','1'";

                            $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                            $time_table_insert_values .= ",'3','" . $link . "'";

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

                                $status->done = "Pre-recorded Lecture has been added successfully";
                            } catch (Exception $e) {
                                echo $e->getMessage();
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
