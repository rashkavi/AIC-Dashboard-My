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
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 2))) {
                $status->error = "You don't have permission to update branch";
            } else {
                $branch_id = isset($_POST['branch_id']) ? $_POST['branch_id'] : '';
                if (empty($branch_id)) {
                    $status->error = "Invalid branch Id";
                } else if (!is_numeric($branch_id)) {
                    $status->error = "Invalid branch Id";
                } else {
                    $isValidbranch = Database::search("SELECT COUNT(*) as 'count' FROM `branches` WHERE `branch_id`='" . $branch_id . "'");
                    $row = $isValidbranch->fetch_assoc();
                    if ($row['count'] == 0) {
                        $status->error = "Invalid branch Id";
                    } else {
                        $branch_name = isset($_POST['branch_name']) ? trim($_POST['branch_name']) : '';
                        $branch_cover;
                        if (empty($branch_name)) {
                            $status->name =  "Please enter branch Name";
                        } else if (strlen($branch_name) > 100) {
                            $status->name =  "branch name should be less than 100 characters";
                        } else {
                            $isValidbranch = Database::search("SELECT COUNT(*) as 'count' FROM `branches` WHERE `branch`='" . $branch_name . "' AND `branch_id` != '" . $branch_id . "'");
                            $row = $isValidbranch->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->name =  "branch already exists";
                            }
                        }

                        if (isset($_FILES["branch_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["branch_cover"]["type"];
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
                                if ($_FILES["branch_cover"]["error"] === 0) {
                                    if ($_FILES["branch_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["branch_cover"]["tmp_name"])) {
                                            $branch_cover = uniqid() . $new_file_extention;
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
                            $branchData = Database::search("SELECT * FROM `branches` WHERE `branch_id`='" . $branch_id . "'")->fetch_assoc();

                            $branch_update_query = "`branch`='" . $branch_name . "'";

                            if (!empty($branch_cover)) {
                                $branch_update_query .= ", `branch_cover`='" . $branch_cover . "'";
                            }

                            Database::iud("UPDATE `branches` SET " . $branch_update_query . " WHERE `branch_id`='" . $branch_id . "'");

                            if (isset($_FILES["branch_cover"])) {
                                $cover_path = "../assets/imgs/branches-covers/";
                                $qimg_path = $cover_path . $branch_cover;
                                move_uploaded_file($_FILES["branch_cover"]["tmp_name"], $qimg_path);

                                if (!empty($branchData['branch_cover']) && $branchData['branch_cover'] != 'empty-thumbnail.webp' && file_exists($cover_path . $branchData['branch_cover'])) {
                                    unlink($cover_path . $branchData['branch_cover']);
                                }
                            }
                            $status->done =  "The branch has been successfully updated";
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
