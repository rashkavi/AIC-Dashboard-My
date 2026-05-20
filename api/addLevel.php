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
            $level_name = isset($_POST['level_name']) ? $_POST['level_name'] : '';
            $intake_id = isset($_POST['intake_id']) ? $_POST['intake_id'] : '';
            $level_cover = '';
            if (empty($intake_id)) {
                $status->error = "Invalid intake Id";
            } else if (!is_numeric($intake_id)) {
                $status->error = "Invalid intake Id";
            } else {
                $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id`='" . $intake_id . "'");
                $row = $isValidIntake->fetch_array();
                if ($row['count'] == 0) {
                    $status->error = "Invalid intake Id";
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake', 2, $intake_id))) {
                        $status->error = 'You do not have permission to add level to this intake';
                    } else {
                        if (empty($level_name)) {
                            $status->name =  "Please enter level name";
                        } else if (strlen($level_name) > 100) {
                            $status->name =  "Level Name should be less than 100 characters";
                        } else {
                            $isValidLevel  = Database::search("SELECT COUNT(*) as 'count' FROM `levels` WHERE `level`='" . $level_name . "' AND `intakes_intake_id` = '" . $intake_id . "'");
                            $row = $isValidLevel->fetch_array();
                            if ($row['count'] > 0) {
                                $status->name =  "Level name already exists in this intake";
                            }
                        }

                        if (isset($_FILES["level_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["level_cover"]["type"];
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
                                if ($_FILES["level_cover"]["error"] === 0) {
                                    if ($_FILES["level_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["level_cover"]["tmp_name"])) {
                                            $level_cover = uniqid() . $new_file_extention;
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

                            $level_insert_columns = "`level`,`date_added`,`users_user_id`,`intakes_intake_id`,`levels_order`";
                            $level_insert_values = "'" . $level_name . "','" . $date . "','" . $sessionUserObject['user_id'] . "','" . $intake_id . "','0'";

                            if (!empty($level_cover)) {
                                $level_insert_columns .= ", `level_cover`";
                                $level_insert_values .= ",'" . $level_cover . "'";
                            } else {
                                $level_insert_columns .= ", `level_cover`";
                                $level_insert_values .= ",'empty-thumbnail.webp'";
                            }
                            Database::iud("INSERT INTO `levels` (" . $level_insert_columns . ") VALUES (" . $level_insert_values . ")");

                            if (isset($_FILES["level_cover"])) {
                                $qimg_path = "../assets/imgs/levels-covers/" . $level_cover;
                                move_uploaded_file($_FILES["level_cover"]["tmp_name"], $qimg_path);
                            }
                            $status->done =  "Level has been successfully added";
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
