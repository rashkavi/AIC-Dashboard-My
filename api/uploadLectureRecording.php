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
                $isValidLectureId = Database::search("SELECT * FROM `time_table`
                INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` 
                INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                WHERE `time_table_id`='" . $id . "'");
                if ($isValidLectureId->num_rows == 0) {
                    $status->lecture = "Invalid lecture Id";
                } else {
                    $lectureData = $isValidLectureId->fetch_assoc();

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0) || UserPermissions::validPermisisonType('course_overview', 0))) {
                        $status->error = 'You are not authorized to this action.';
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
                            $status->expire_date = "Expire date is required";
                        } else if (!checkIfFutureDateTime($expireDate)) {
                            $status->expire_date = "Please enter a future date";
                        }

                        if (empty($recordingLink)) {

                            $status->recording_link = "Recording link is required";
                        } elseif (
                            !preg_match(
                                '/<iframe\b[^>]*\bsrc=["\']https:\/\/aicedulksl\.sharepoint\.com\/[^"\']+["\'][^>]*><\/iframe>/is',
                                $recordingLink
                            )
                        ) {
                            $status->recording_link = "Recording embeded code must be a valid SharePoint iframe embed code";
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
                                break;
                            }
                        }

                        if ($allPropertiesExist) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $editTime = $dateTime->format('Y-m-d H:i:s');

                            $sameLectures =  Database::search("SELECT * FROM `time_table`
                            INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                            INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` 
                            INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                            WHERE 
                            `time_table`.`date`='" . $lectureData['date'] . "'
                            AND `time_table`.`start_time`='" . $lectureData['start_time'] . "'
                            AND `time_table`.`end_time`='" . $lectureData['end_time'] . "'
                            AND `users`.`user_id`='" . $lectureData['user_id'] . "'
                            ");

                            $recording_table_insert_columns = "`lecture_recording`,`added_at`,`users_user_id`,`expire_date`";
                            $recording_table_insert_values = "'" . Database::escape($recordingLink) . "','" . $editTime . "','" . $sessionUserObject['user_id'] . "','" . $expireDate . "'";

                            if (isset($_FILES["image"])) {
                                $recording_table_insert_columns .= ",`recording_thumbnail`";
                                $recording_table_insert_values .= ",'" . $image . "'";
                            }
                            Database::iud("INSERT INTO `lecture_recording` (" . $recording_table_insert_columns . ") VALUES (" . $recording_table_insert_values . ")");
                            $recordingID = Database::$connection->insert_id;

                            while ($data = $sameLectures->fetch_assoc()) {
                                $lectures_insert_columns = "`time_table_time_table_id`,`lecture_recording_lecture_recording_id`";
                                $lectures_insert_values = "'" . $data['time_table_id'] . "','" . $recordingID . "'";

                                Database::iud("INSERT INTO `lecture_recording_has_lectures` (" . $lectures_insert_columns . ") VALUES (" . $lectures_insert_values . ")");
                            }

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
