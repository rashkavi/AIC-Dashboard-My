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
            $intake_id = isset($_POST['intake_id']) ? $_POST['intake_id'] : '';
            if (empty($intake_id)) {
                $status->error = "Invalid intake Id";
            } else if (!is_numeric($intake_id)) {
                $status->error = "Invalid intake Id";
            } else {
                $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id`='" . $intake_id . "'");
                if ($isValidIntake->num_rows == 0) {
                    $status->error = "Invalid intake Id";
                } else {
                    $intakeData = $isValidIntake->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('programme', 10,  $intakeData['programmes_programme_id']))) {
                        $status->error = "You don't have permission to update intakes to this programme";
                    } else {
                        $intake_name = isset($_POST['intake_name']) ? trim($_POST['intake_name']) : '';
                        $intake_cover;
                        if (empty($intake_name)) {
                            $status->name =  "Please enter intake Name";
                        } else if (strlen($intake_name) > 100) {
                            $status->name =  "Intake name should be less than 100 characters";
                        } else {

                            $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake`='" . $intake_name . "' AND `intake_id` != '" . $intake_id . "' AND `programmes_programme_id` = '" . $intakeData['programmes_programme_id'] . "'");
                            $row = $isValidIntake->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->name =  "  Intake name already exists in this programme";
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
                            $intake_update_query = "`intake`='" . $intake_name . "'";

                            if (!empty($intake_cover)) {
                                $intake_update_query .= ", `intake_cover`='" . $intake_cover . "'";
                            }

                            Database::iud("UPDATE `intakes` SET " . $intake_update_query . " WHERE `intake_id`='" . $intake_id . "'");

                            if (isset($_FILES["intake_cover"])) {
                                $cover_path = "../assets/imgs/intakes-covers/";
                                $qimg_path = $cover_path . $intake_cover;
                                move_uploaded_file($_FILES["intake_cover"]["tmp_name"], $qimg_path);

                                if (!empty($intakeData['intake_cover']) && $intakeData['branch_cover'] != 'empty-thumbnail.webp' && file_exists($cover_path . $intakeData['intake_cover'])) {
                                    unlink($cover_path . $intakeData['intake_cover']);
                                }
                            }
                            $status->done =  "The intake has been successfully updated";
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
