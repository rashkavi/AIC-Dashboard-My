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

            $branch_id = isset($_POST['branch_id']) ? $_POST['branch_id'] : '';
            $faculty_name = isset($_POST['faculty_name']) ? $_POST['faculty_name'] : '';
            $faculty_cover;

            if (empty($branch_id)) {
                $status->error = "Invalid branch Id";
            } else {
                $branch = Database::search("SELECT * FROM `branches` WHERE `branch_id` = '" . $branch_id . "'");
                if ($branch->num_rows == 0) {
                    $status->error = "Invalid branch Id";
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('branch', 2, $branch_id))) {
                        $status->error = "You don't have permission to add faculty";
                    } else {

                        if (empty($faculty_name)) {
                            $status->name =  "Please enter faculty Name";
                        } else if (strlen($faculty_name) > 100) {
                            $status->name =  "Faculty Name should be less than 100 characters";
                        } else {
                            require_once("../includes/connection.php");
                            $isValidFaculty  = Database::search("SELECT COUNT(*) as 'count' FROM `faculties` 
                            WHERE `faculty`='" . $faculty_name . "' AND `branches_branch_id`='" . $branch_id . "'");
                            $row = $isValidFaculty->fetch_array();
                            if ($row["count"] > 0) {
                                $status->name =  "Faculty already exists";
                            }
                        }

                        if (isset($_FILES["faculty_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["faculty_cover"]["type"];
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
                                if ($_FILES["faculty_cover"]["error"] === 0) {
                                    if ($_FILES["faculty_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["faculty_cover"]["tmp_name"])) {
                                            $faculty_cover = uniqid() . $new_file_extention;
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
                            $status->cover = "Please select faculty cover";
                        }

                        if (!property_exists($status, 'name') &&  !property_exists($status, 'cover')) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');

                            $faculty_insert_columns = "`faculty`,`date_added`,`users_user_id`,`faculty_order`,`branches_branch_id`";
                            $faculty_insert_values = "'" . $faculty_name . "','" . $date . "','" . $sessionUserObject['user_id'] . "','0','" . $branch_id . "'";

                            if (!empty($faculty_cover)) {
                                $faculty_insert_columns .= ", `faculty_cover`";
                                $faculty_insert_values .= ",'" . $faculty_cover . "'";
                            }
                            Database::iud("INSERT INTO `faculties` (" . $faculty_insert_columns . ") VALUES (" . $faculty_insert_values . ")");

                            if (isset($_FILES["faculty_cover"])) {
                                $qimg_path = "../assets/imgs/faculties-covers/" . $faculty_cover;
                                move_uploaded_file($_FILES["faculty_cover"]["tmp_name"], $qimg_path);
                            }
                            $status->done =  "The faculty has been successfully added";
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
