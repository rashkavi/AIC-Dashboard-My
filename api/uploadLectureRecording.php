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
            $id = isset($_POST['id']) ? trim($_POST['id']) : 0;
            $expireDate = isset($_POST['expire_date']) ? trim($_POST['expire_date']) : '';
            $recordingLink = isset($_POST['recording_link']) ? trim($_POST['recording_link']) : '';
            $image;

            if (empty($id) || $id == 0) {
                $status->lecture = "Invalid lecture Id";
            } else {
                $isValidLectureId = Database::search("SELECT * FROM `time_table` WHERE `time_table_id`='" . $id . "'");
                if ($isValidLectureId->num_rows == 0) {
                    $status->lecture = "Invalid lecture Id";
                } else {
                    $lectureData = $isValidLectureId->fetch_assoc();

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 28)) || UserPermissions::hasPermission('lecture_schedule', 28, $lectureData['subjects_subject_id'])) {
                        $status->lecture = "You don't have permission to upload recordings";
                    } else {
                        $isHasRecording = Database::search("SELECT COUNT(`lecture_recording_id`) FROM `lecture_recording` WHERE `time_table_time_table_id`='" . $id . "'");
                        $row = $isHasRecording->fetch_assoc();
                        if ($row['COUNT(`lecture_recording_id`)'] > 0) {
                            $status->lecture = "Lecture already has a recording";
                        }

                        if (isset($_FILES["image"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["image"]["type"];
                            if (!in_array($file_ex, $allowed_image_extentions)) {
                                $status->image = "Only jpg,jpeg,png and svg allowed";
                            } else {
                                $new_file_extention;
                                switch ($file_ex) {
                                    case "image/jpg":
                                        $new_file_extention = ".jpg";
                                        break;
                                    case "image/jpeg":
                                        $new_file_extention = ".jpeg";
                                        break;
                                    case "image/png":
                                        $new_file_extention = ".png";
                                        break;
                                    case "image/svg+xml":
                                        $new_file_extention = ".svg";
                                        break;
                                }

                                if ($_FILES["image"]["error"] === 0) {
                                    if ($_FILES["image"]["size"] <= 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["image"]["tmp_name"])) {
                                            $image = uniqid() . $new_file_extention;
                                        } else {
                                            $status->image = "Invalid image file!";
                                        }
                                    } else {
                                        $status->image = "Maximum allowed file size is 2MB";
                                    }
                                } else {
                                    $status->image = "There is an error with the selected image";
                                }
                            }
                        } else {
                            $status->image = "Image is required";
                        }

                        if (empty($expireDate)) {
                            $status->expire_date = "Expire date is required";
                        } else if (!checkIfFutureDateTime($expireDate)) {
                            $status->expire_date = "Please enter a future date";
                        }

                        if (empty($recordingLink)) {
                            $status->recording_link = "Recording link is required";
                        } elseif (!preg_match('/<iframe.*src="https:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]+.*".*<\/iframe>/i', $recordingLink)) {
                            $status->recording_link = "Recording link must be a valid YouTube iframe embed code";
                        }

                        $requiredProperties = [
                            'lecture',
                            'image',
                            'expire_date',
                            'recording_link'
                        ];
                        $allPropertiesExist = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allPropertiesExist = false;
                                break; // Exit loop on the first missing property
                            }
                        }

                        if ($allPropertiesExist) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $editTime = $dateTime->format('Y-m-d H:i:s');

                            $recording_table_insert_columns = "`time_table_time_table_id`,`recording_thumbnail`,`lecture_recording`,`added_at`,`users_user_id`,`expire_date`";
                            $recording_table_insert_values = "'" . $id . "','" . $image . "','" . $recordingLink . "','" . $editTime . "','" . $sessionUserObject['user_id'] . "','" . $expireDate . "'";

                            Database::iud("INSERT INTO `lecture_recording` (" . $recording_table_insert_columns . ") VALUES (" . $recording_table_insert_values . ")");

                            if (isset($_FILES["image"])) {
                                $qimg_path = "../assets/imgs/recording-thumbnail/" . $image;
                                move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
                            }

                            $status->done = "Recording Added Successfully";
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

function checkIfFutureDateTime($lecture_date)
{
    if ($lecture_date) {
        $startDateTime = new DateTime("$lecture_date");
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
