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
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faculties', 2))) {
                $status->error = "You don't have permission to update faculty";
            } else {
                $faculty_id = isset($_POST['faculty_id']) ? $_POST['faculty_id'] : '';
                if (empty($faculty_id)) {
                    $status->error = "Invalid faculty Id";
                } else if (!is_numeric($faculty_id)) {
                    $status->error = "Invalid faculty Id";
                } else {
                    $isValidFaculty = Database::search("SELECT * FROM `faculties` WHERE `faculty_id`='" . $faculty_id . "'");

                    if ($isValidFaculty->num_rows == 0) {
                        $status->error = "Invalid faculty Id";
                    } else {
                        $facultyData = $isValidFaculty->fetch_assoc();
                        $faculty_name = isset($_POST['faculty_name']) ? trim($_POST['faculty_name']) : '';
                        $faculty_cover;
                        if (empty($faculty_name)) {
                            $status->name =  "Please enter faculty Name";
                        } else if (strlen($faculty_name) > 100) {
                            $status->name =  "Faculty name should be less than 100 characters";
                        } else {
                            $isValidFaculty = Database::search("SELECT COUNT(*) as 'count' FROM `faculties` 
                            WHERE `faculty`='" . $faculty_name . "' AND `faculty_id` != '" . $faculty_id . "' AND `branches_branch_id`='" . $facultyData['branches_branch_id'] . "'");
                            $row = $isValidFaculty->fetch_assoc();
                            if ($row['count'] > 0) {
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
                        }
                        if (!property_exists($status, 'name') &&  !property_exists($status, 'cover')) {
                            require_once("../includes/connection.php");

                            $faculty_update_query = "`faculty`='" . $faculty_name . "'";

                            if (!empty($faculty_cover)) {
                                $faculty_update_query .= ", `faculty_cover`='" . $faculty_cover . "'";
                            }

                            Database::iud("UPDATE `faculties` SET " . $faculty_update_query . " WHERE `faculty_id`='" . $faculty_id . "'");

                            if (isset($_FILES["faculty_cover"])) {
                                $cover_path = "../assets/imgs/faculties-covers/";
                                $qimg_path = $cover_path . $faculty_cover;
                                move_uploaded_file($_FILES["faculty_cover"]["tmp_name"], $qimg_path);

                                if (!empty($facultyData['faculty_cover']) && $facultyData['branch_cover'] != 'empty-thumbnail.webp' && file_exists($cover_path . $facultyData['faculty_cover'])) {
                                    unlink($cover_path . $facultyData['faculty_cover']);
                                }
                            }
                            $status->done =  "The faculty has been successfully updated";
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
