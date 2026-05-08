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

            $programme_name = isset($_POST['programme_name']) ? $_POST['programme_name'] : '';
            $programme_duration = isset($_POST['programme_duration']) ? $_POST['programme_duration'] : 0;
            $new_programme_duration = isset($_POST['new_programme_duration']) ? $_POST['new_programme_duration'] : 0;
            $faculty_id = isset($_POST['faculty_id']) ? $_POST['faculty_id'] : '';
            $programme_cover;

            if (empty($faculty_id)) {
                $status->error = "Invalid faculty Id";
            } else if (!is_numeric($faculty_id)) {
                $status->error = "Invalid faculty Id";
            } else {
                $isValidFaculty = Database::search("SELECT COUNT(*) as 'count' FROM `faculties` WHERE `faculty_id`='" . $faculty_id . "'");
                $row = $isValidFaculty->fetch_assoc();
                if ($row['count'] == 0) {
                    $status->error = "Invalid faculty Id";
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_id']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faculty', 2,  $faculty_id))) {
                        $status->error = "You don't have permission to add programmes";
                    } else {
                        if (empty($programme_name)) {
                            $status->name =  "Please enter programme Name";
                        } else if (strlen($programme_name) > 100) {
                            $status->name =  "Programme Name should be less than 100 characters";
                        } else {
                            $isValidProgramme = Database::search("SELECT COUNT(*) as 'count' FROM `programmes` WHERE `programme`='" . $programme_name . "' AND `faculties_faculty_id` = '" . $faculty_id . "'");
                            $row = $isValidProgramme->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->name =  "  Programme name already exists in this faculty";
                            }
                        }

                        if ((empty($programme_duration) || $programme_duration == 0) && empty($new_programme_duration)) {
                            $status->duration = "Please select Programme Duration or add new duration";
                        } else {

                            if (!empty($programme_duration) && $programme_duration != 0) {
                                $isValidDuration = Database::search("SELECT COUNT(*) as 'count' FROM `programme_duration` WHERE `programme_duration_id`='" . $programme_duration . "'");
                                $row = $isValidDuration->fetch_assoc();
                                if ($row['count'] == 0) {
                                    $status->duration = "Please select valid Programme Duration";
                                }
                            }

                            if (!empty($new_programme_duration)) {

                                $normalizedInput = normalizeDuration($new_programme_duration);
                                // Search normalized version in DB
                                $isValidDuration = Database::search("
                                    SELECT * FROM `programme_duration`
                                ");

                                $isDuplicate = false;

                                while ($row = $isValidDuration->fetch_assoc()) {
                                    $normalizedFromDb = normalizeDuration($row['programme_duration']);
                                    if ($normalizedFromDb === $normalizedInput) {
                                        $isDuplicate = true;
                                        break;
                                    }
                                }

                                if ($isDuplicate) {
                                    $status->new_duration = "Programme Duration already exists";
                                }
                            }
                        }
                        if (isset($_FILES["programme_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["programme_cover"]["type"];
                            if (!in_array($file_ex, $allowed_image_extentions)) {
                                $status->cover = "Only jpg,jpeg,png and svg allowed";
                            } else {
                                $new_file_extention;
                                if ($file_ex == "image/jpg") {
                                    $new_file_extention = ".jpg";
                                } else if ($file_ex == "image/jpeg") {
                                    $new_file_extention = ".jpeg";
                                } else if ($file_ex == "image/png") {
                                    $new_file_extention = ".png";
                                } else if ($file_ex == "image/svg+xml") {
                                    $new_file_extention = ".svg";
                                }
                                if ($_FILES["programme_cover"]["error"] === 0) {
                                    if ($_FILES["programme_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["programme_cover"]["tmp_name"])) {
                                            $programme_cover = uniqid() . $new_file_extention;
                                        } else {
                                            $status->cover = "Invalid image file!";
                                        }
                                    } else {
                                        $status->cover = "Maximum allowed file size is 2MB";
                                    }
                                } else {
                                    $status->cover = "There is an error with the selected image";
                                }
                            }
                        } else {
                            $status->cover = "Please select programme cover";
                        }
                        if (!property_exists($status, 'name') && !property_exists($status, 'cover') && !property_exists($status, 'duration')) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');

                            $programmeDurationid = $programme_duration;

                            if (!empty($new_programme_duration)) {
                                Database::iud("INSERT INTO `programme_duration` (`programme_duration`,`programme_duration_hours`,`order_by`) 
                                VALUES ('" . $new_programme_duration . "','0','0')");
                                $programmeDurationid = Database::$connection->insert_id;
                            }

                            $programme_insert_columns = "`programme`,`date_added`,`users_user_id`,`faculties_faculty_id`,`programme_duration_programme_duration_id`,`programmes_order`";
                            $programme_insert_values = "'" . $programme_name . "','" . $date . "','" . $sessionUserObject['user_id'] . "','" . $faculty_id . "','" . $programmeDurationid . "','0'";

                            if (!empty($programme_cover)) {
                                $programme_insert_columns .= ", `programme_cover`";
                                $programme_insert_values .= ",'" . $programme_cover . "'";
                            }
                            Database::iud("INSERT INTO `programmes` (" . $programme_insert_columns . ") VALUES (" . $programme_insert_values . ")");

                            if (isset($_FILES["programme_cover"])) {
                                $qimg_path = "../assets/imgs/programmes-covers/" . $programme_cover;
                                move_uploaded_file($_FILES["programme_cover"]["tmp_name"], $qimg_path);
                            }
                            $status->done =  "The programme has been successfully added";
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

function normalizeDuration($text)
{
    return strtolower(str_replace(' ', '', $text));
}
