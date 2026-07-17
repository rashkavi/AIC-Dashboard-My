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
            $subject_name = isset($_POST['subject_name']) ? $_POST['subject_name'] : '';
            $subject_hours = isset($_POST['subject_hours']) ? $_POST['subject_hours'] : '';
            $subject_id = isset($_POST['subject_id']) ? $_POST['subject_id'] : '';
            $subject_type = isset($_POST['subject_type']) ? $_POST['subject_type'] : 0;
            $subject_group = isset($_POST['subject_group']) ? $_POST['subject_group'] : 0;
            $subject_cover;

            if (empty($subject_id)) {
                $status->error = "Invalid subject Id";
            } else if (!is_numeric($subject_id)) {
                $status->error = "Invalid subject Id";
            } else {

                $isValidSubject = Database::search("SELECT * FROM `subjects` WHERE `subject_id`='" . $subject_id . "'");
                if ($isValidSubject->num_rows == 0) {
                    $status->error = "Invalid subject Id";
                } else {
                    $subjectData = $isValidSubject->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('level', 10, $subjectData['levels_level_id']))) {
                        $status->error = "You don't have permission to update subjects";
                    } else {
                        if (empty($subject_name)) {
                            $status->name =  "Please enter subject name";
                        } else if (strlen($subject_name) > 100) {
                            $status->name =  "Subject name should be less than 100 characters";
                        } else {
                            $isValidSubject  = Database::search("SELECT COUNT(*) as 'count' FROM `subjects` WHERE `subject`='" . $subject_name . "' AND `subject_id` != '" . $subject_id . "' AND `levels_level_id` IN (SELECT `levels_level_id` FROM `subjects` WHERE `subject_id` = '" . $subject_id . "')");
                            $row = $isValidSubject->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->name =  "Subject name already exists in this level";
                            }
                        }

                        if (empty($subject_type) || $subject_type == 0) {
                            $status->type =  "Please select subject type";
                        }

                        if (empty($subject_group) || $subject_group == 0) {
                            $status->group =  "Please select subject group";
                        }

                        if (empty($subject_hours)) {
                            $status->hours =  "Please enter subject hours";
                        } else if (!is_numeric($subject_hours)) {
                            $status->hours =  "Invalid subject hours";
                        } else if ($subject_hours < 0) {
                            $status->hours =  "Invalid subject hours";
                        }

                        if (isset($_FILES["subject_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["subject_cover"]["type"];
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
                                if ($_FILES["subject_cover"]["error"] === 0) {
                                    if ($_FILES["subject_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["subject_cover"]["tmp_name"])) {
                                            $subject_cover = uniqid() . $new_file_extention;
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

                        $requiredProperties = [
                            'name',
                            'hours',
                            'cover',
                            'type',
                        ];
                        $allPropertiesExist = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allPropertiesExist = false;
                                break;
                            }
                        }

                        if ($allPropertiesExist) {

                            $subject_update_query = "`subject`='" . $subject_name . "',`max_course_hours`='" . $subject_hours . "',`subject_type_subject_type_id`='" . $subject_type . "'";

                            if ($subject_type == 2) {
                                $subject_update_query .= ",`subject_group_subject_group_id`='" . $subject_group . "'";
                            } else {
                                $subject_update_query .= ",`subject_group_subject_group_id`= NULL";
                            }

                            if (!empty($subject_cover)) {
                                $subject_update_query .= ", `subject_cover`='" . $subject_cover . "'";
                            }



                            Database::iud("UPDATE `subjects` SET " . $subject_update_query . " WHERE `subject_id`='" . $subject_id . "'");

                            if (isset($_FILES["subject_cover"])) {
                                $cover_path = "../assets/imgs/subjects-covers/";
                                $qimg_path = $cover_path . $subject_cover;
                                move_uploaded_file($_FILES["subject_cover"]["tmp_name"], $qimg_path);

                                if (!empty($subjectData['subject_cover']) && $subjectData['branch_cover'] != 'empty-thumbnail.webp' && file_exists($cover_path . $subjectData['subject_cover'])) {
                                    unlink($cover_path . $subjectData['subject_cover']);
                                }
                            }
                            $status->done =  "The subject has been successfully updated";
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
