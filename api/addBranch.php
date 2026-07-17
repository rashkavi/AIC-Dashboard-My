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
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 2))) {
                $status->error = "You don't have permission to add branch";
            } else {
                $branch_name = isset($_POST['branch_name']) ? $_POST['branch_name'] : '';
                $branch_cover = '';
                if (empty($branch_name)) {
                    $status->name =  "Please enter branch name";
                } else if (strlen($branch_name) > 100) {
                    $status->name =  "Branch Name should be less than 100 characters";
                } else {
                    require_once("../includes/connection.php");
                    $isValidFaculty  = Database::search("SELECT COUNT(*) as 'count' FROM `branches` WHERE `branch`='" . $branch_name . "'");
                    $row = $isValidFaculty->fetch_array();
                    if ($row["count"] > 0) {
                        $status->name =  "Branch already exists";
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
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $date = $dateTime->format('Y-m-d H:i:s');

                    $columns = "`branch`,`date_added`,`users_user_id`,`branch_order`";
                    $values = "'" . $branch_name . "','" . $date . "','" . $sessionUserObject['user_id'] . "','0'";

                    if (!empty($branch_cover)) {
                        $columns .= ", `branch_cover`";
                        $values .= ",'" . $branch_cover . "'";
                    } else {
                        $columns .= ", `branch_cover`";
                        $values .= ",'empty-thumbnail.webp'";
                    }
                    Database::iud("INSERT INTO `branches` (" . $columns . ") VALUES (" . $values . ")");

                    if (isset($_FILES["branch_cover"])) {
                        $qimg_path = "../assets/imgs/branches-covers/" . $branch_cover;
                        move_uploaded_file($_FILES["branch_cover"]["tmp_name"], $qimg_path);
                    }
                    $status->done =  "The branch has been successfully added";
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
