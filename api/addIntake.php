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
            $intake_name = isset($_POST['intake_name']) ? $_POST['intake_name'] : '';
            $programme_id = isset($_POST['programme_id']) ? $_POST['programme_id'] : '';
            $intake_cover = '';

            if (empty($programme_id)) {
                $status->error = "Invalid programme Id";
            } else if (!is_numeric($programme_id)) {
                $status->error = "Invalid programme Id";
            } else {
                $isValidProgramme = Database::search("SELECT COUNT(*) as 'count' FROM `programmes` WHERE `programme_id`='" . $programme_id . "'");
                $row = $isValidProgramme->fetch_array();
                if ($row["count"] == 0) {
                    $status->error = "Invalid programme Id";
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('programme', 2,  $programme_id))) {
                        $status->error = "You don't have permission to add intakes to this programme";
                    } else {
                        if (empty($intake_name)) {
                            $status->name =  "Please enter intake Name";
                        } else if (strlen($intake_name) > 100) {
                            $status->name =  "Intake Name should be less than 100 characters";
                        } else {
                            $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake`='" . $intake_name . "' AND `programmes_programme_id` = '" . $programme_id . "'");
                            $row = $isValidIntake->fetch_array();
                            if ($row["count"] > 0) {
                                $status->name =  "Intake name already exists in this programme";
                            }
                        }

                        if (isset($_FILES["intake_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["intake_cover"]["type"];
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
                                if ($_FILES["intake_cover"]["error"] === 0) {
                                    if ($_FILES["intake_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["intake_cover"]["tmp_name"])) {
                                            $intake_cover = uniqid() . $new_file_extention;
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
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');

                            $intake_insert_columns = "`intake`,`date_added`,`users_user_id`,`programmes_programme_id`,`intakes_order`";
                            $intake_insert_values = "'" . $intake_name . "','" . $date . "','" . $sessionUserObject['user_id'] . "','" . $programme_id . "','0'";

                            if (!empty($intake_cover)) {
                                $intake_insert_columns .= ", `intake_cover`";
                                $intake_insert_values .= ",'" . $intake_cover . "'";
                            }else{
                                $intake_insert_columns .= ", `intake_cover`";
                                $intake_insert_values .= ",'empty-thumbnail.webp'";
                            }
                            Database::iud("INSERT INTO `intakes` (" . $intake_insert_columns . ") VALUES (" . $intake_insert_values . ")");

                            if (isset($_FILES["intake_cover"])) {
                                $qimg_path = "../assets/imgs/intakes-covers/" . $intake_cover;
                                move_uploaded_file($_FILES["intake_cover"]["tmp_name"], $qimg_path);
                            }
                            $status->done =  "The intake has been successfully added";
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
