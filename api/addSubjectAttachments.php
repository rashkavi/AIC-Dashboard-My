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

            $subject_id = isset($_POST['subject']) ? trim($_POST['subject']) : 0;

            if ($subject_id == 0) {
                $status->error = 'Please select a subject';
            } else {
                $isValidSubject = Database::search("SELECT * FROM `subjects` WHERE `subject_id` = '" . $subject_id . "'");

                if ($isValidSubject->num_rows == 0) {
                    $status->error = 'Please select a valid subject';
                } else {
                    $subjectData = $isValidSubject->fetch_assoc();

                    $tutes = array();

                    if (isset($_FILES["tutes"])) {
                        $allowedTypes = [
                            "image/jpeg" => ".jpg",
                            "image/jpg" => ".jpg",
                            "image/png" => ".png",
                            "image/gif" => ".gif",
                            "image/webp" => ".webp",
                            "image/bmp" => ".bmp",
                            "image/svg+xml" => ".svg",
                            "application/pdf" => ".pdf",
                            "application/msword" => ".doc",
                            "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => ".docx",
                            "application/zip" => ".zip",
                            "application/x-zip-compressed" => ".zip",
                            "application/x-rar-compressed" => ".rar",
                            "application/vnd.ms-powerpoint" => ".ppt",
                            "application/vnd.openxmlformats-officedocument.presentationml.presentation" => ".pptx"
                        ];

                        foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                            $fileType = $_FILES["tutes"]["type"][$key];

                            if (!array_key_exists($fileType, $allowedTypes)) {
                                $status->file = "File '$tute_name' is not a valid type.Only PDF, DOC, DOCX, ZIP, RAR, PPT, and PPTX files are allowed";
                            } else if (strlen($tute_name) > 45) {
                                $status->tute[] = "File '$tute_name' exceeds the maximum allowed character limit of 45.";
                            } else {
                                if ($_FILES["tutes"]["error"][$key] === 0) {
                                    if ($_FILES["tutes"]["size"][$key] <= 25 * 1024 * 1024) {
                                        $tute = uniqid() . $allowedTypes[$fileType];

                                        array_push($tutes, $tute);
                                    } else {
                                        $status->tute[] = "File '$tute_name' exceeds the maximum allowed size of 25MB. Please compress (ZIP) the file before uploading.";
                                    }
                                } else {
                                    $status->tute[] = "There was an error uploading the file '$tute_name'.";
                                }
                            }
                        }
                    }else{
                        $status->error = 'Please select a attachment';
                    }

                    $requiredProperties = [
                        'tute',
                        'error'
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

                        if (isset($_FILES["tutes"])) {
                            for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {

                                $originalFileName = $_FILES["tutes"]["name"][$i];

                                $attachment_insert_columns = "`attachment`,`attachment_name`,`subjects_subject_id`,`users_user_id`,`date_added`,`attachments_order`";
                                $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $subject_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                Database::iud("INSERT INTO `subject_has_attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                $tute_path = "../assets/uploads/subject-attachments/" . $tutes[$i];
                                move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                            }
                        }

                        $status->done = "Attachments added successfully";
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
