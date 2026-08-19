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
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0) || UserPermissions::validPermisisonType('course_overview', 0))) {
            $status->error = "You are not authorized to this action.";
        } else {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {

                $recording = isset($_POST['recording']) ? trim($_POST['recording']) : 0;
                $expireDate = isset($_POST['expire_date']) ? trim($_POST['expire_date']) : '';
                $recordingLink = isset($_POST['recording_link']) ? trim($_POST['recording_link']) : '';
                $image = '';

                if (empty($recording) || $recording == 0) {
                    $status->recording = "Invalid recording";
                } else {
                    $isValidRecording = Database::search("SELECT COUNT(`lecture_recording_id`),`recording_thumbnail` FROM `lecture_recording` WHERE `lecture_recording_id`='" . $recording . "'");
                    $recordingData = $isValidRecording->fetch_assoc();

                    if ($recordingData['COUNT(`lecture_recording_id`)'] == 0) {
                        $status->recording = "Invalid recording";
                    } else {

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
                        }

                        if (empty($expireDate)) {
                            if (!checkIfFutureDateTime($expireDate)) {
                                $status->expire_date = "Please enter a future date";
                            }
                        }

                        if (empty($recordingLink)) {
                            $status->recording_link = "Recording link is required";
                        } elseif (
                            !preg_match(
                                '/<iframe\b[^>]*\bsrc=["\']https:\/\/aicedulksl\.sharepoint\.com\/[^"\']+["\'][^>]*><\/iframe>/is',
                                $recordingLink
                            )
                        ) {
                            $status->recording_link = "Recording link must be a valid SharePoint iframe embed code";
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

                            $recording_update_query = "`lecture_recording`='" . $recordingLink . "',`expire_date`='" . $expireDate . "'";

                            if (isset($_FILES["image"])) {
                                $recording_update_query .= ",`recording_thumbnail`='" . $image . "'";
                            }

                            Database::iud("UPDATE `lecture_recording` SET " . $recording_update_query . " WHERE `lecture_recording_id`='" . $recording . "'");

                            if (isset($_FILES["image"])) {
                                $qimg_path = "../assets/imgs/recording-thumbnail/" . $image;
                                move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);

                                if (file_exists($qimg_path . $recordingData["recording_thumbnail"])) {
                                    unlink("../assets/imgs/recording-thumbnail/" . $recordingData["recording_thumbnail"]);
                                }
                            }

                            $status->done = "Recording Updated Successfully";
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
