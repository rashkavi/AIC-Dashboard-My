<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $programme_id = isset($_POST['programme_id']) ? $_POST['programme_id'] : '';
            if (empty($programme_id)) {
                $status->error = "Invalid programme Id";
            } else if (!is_numeric($programme_id)) {
                $status->error = "Invalid programme Id";
            } else {
                $isValidProgramme = Database::search("SELECT * FROM `programmes` WHERE `programme_id`='" . $programme_id . "'");

                if ($isValidProgramme->num_rows == 0) {
                    $status->error = "Invalid programme Id";
                } else {
                    $programmeData = $isValidProgramme->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_id']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faculty', 10, $programmeData['faculties_faculty_id']))) {
                        $status->error = "You don't have permission to add programmes";
                    } else {
                        $programme_name = isset($_POST['programme_name']) ? trim($_POST['programme_name']) : '';
                        $programme_duration = isset($_POST['programme_duration']) ? $_POST['programme_duration'] : 0;
                        $new_programme_duration = isset($_POST['new_programme_duration']) ? $_POST['new_programme_duration'] : 0;
                        $programme_cover;
                        if (empty($programme_name)) {
                            $status->name =  "Please enter programme Name";
                        } else if (strlen($programme_name) > 100) {
                            $status->name =  "Programme name should be less than 100 characters";
                        } else {
                            $isValidprogramm = Database::search("SELECT COUNT(*) as 'count' FROM `programmes` WHERE `programme`='" . $programme_name . "' AND `programme_id` != '" . $programme_id . "' AND `faculties_faculty_id` = '" . $programmeData['faculties_faculty_id'] . "'");
                            $row = $isValidprogramm->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->name =  "Programme name already exists in this faculty";
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
                        }
                        if (!property_exists($status, 'name') &&  !property_exists($status, 'cover')) {
                            require_once("../includes/connection.php");

                            $programmeDurationid = $programme_duration;

                            if (!empty($new_programme_duration)) {
                                Database::iud("INSERT INTO `programme_duration` (`programme_duration`,`programme_duration_hours`,`order_by`) 
                                VALUES ('" . $new_programme_duration . "','0','0')");
                                $programmeDurationid = Database::$connection->insert_id;
                            }

                            $programme_update_query = "`programme`='" . $programme_name . "', `programme_duration_programme_duration_id`='" . $programmeDurationid . "'";

                            if (!empty($programme_cover)) {
                                $programme_update_query .= ", `programme_cover`='" . $programme_cover . "'";
                            }

                            Database::iud("UPDATE `programmes` SET " . $programme_update_query . " WHERE `programme_id`='" . $programme_id . "'");

                            if (isset($_FILES["programme_cover"])) {
                                $cover_path = "../assets/imgs/programmes-covers/";
                                $qimg_path = $cover_path . $programme_cover;
                                move_uploaded_file($_FILES["programme_cover"]["tmp_name"], $qimg_path);

                                if (!empty($programmeData['programme_cover']) && $programmeData['branch_cover'] != 'empty-thumbnail.webp' && file_exists($cover_path . $programmeData['programme_cover'])) {
                                    unlink($cover_path . $programmeData['programme_cover']);
                                }
                            }
                            $status->done =  "The programme has been successfully updated";
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
