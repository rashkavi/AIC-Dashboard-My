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
            $level_id = isset($_POST['level_id']) ? $_POST['level_id'] : '';
            if (empty($level_id)) {
                $status->error = "Invalid level Id";
            } else if (!is_numeric($level_id)) {
                $status->error = "Invalid level Id";
            } else {
                $validLevel = Database::search("SELECT * FROM `levels` WHERE `level_id`='" . $level_id . "'");
                if ($validLevel->num_rows == 0) {
                    $status->error = "Invalid level Id";
                } else {
                    $levelData = $validLevel->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake', 10, $levelData['intakes_intake_id']))) {
                        $status->error = 'You do not have permission to add level to this intake';
                    } else {
                        $level_name = isset($_POST['level_name']) ? trim($_POST['level_name']) : '';
                        $level_cover;
                        if (empty($level_name)) {
                            $status->name =  "Please enter level Name";
                        } else if (strlen($level_name) > 100) {
                            $status->name =  "Level name should be less than 100 characters";
                        } else {
                            $validLevelName = Database::search("SELECT COUNT(*) as 'count' FROM `levels` WHERE `level`='" . $level_name . "' AND `level_id` != '" . $level_id . "' AND `intakes_intake_id` = '" . $levelData['intakes_intake_id'] . "'");
                            $row = $validLevelName->fetch_assoc();
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
                            require_once("../includes/connection.php");

                            $level_update_query = "`level`='" . $level_name . "'";

                            if (!empty($level_cover)) {
                                $level_update_query .= ", `level_cover`='" . $level_cover . "'";
                            }

                            Database::iud("UPDATE `levels` SET " . $level_update_query . " WHERE `level_id`='" . $level_id . "'");

                            if (isset($_FILES["level_cover"])) {
                                $cover_path = "../assets/imgs/levels-covers/";
                                $qimg_path = $cover_path . $level_cover;
                                move_uploaded_file($_FILES["level_cover"]["tmp_name"], $qimg_path);

                                if (!empty($levelData['level_cover']) && $levelData['branch_cover'] != 'empty-thumbnail.webp' && file_exists($cover_path . $levelData['level_cover'])) {
                                    unlink($cover_path . $levelData['level_cover']);
                                }
                            }
                            $status->done =  "The level has been successfully updated";
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
