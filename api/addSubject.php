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
            $subject_type = isset($_POST['subject_type']) ? $_POST['subject_type'] : 0;
            $subject_group = isset($_POST['subject_group']) ? $_POST['subject_group'] : 0;
            $new_subject_group = isset($_POST['new_subject_group']) ? $_POST['new_subject_group'] : 0;
            $level_id = isset($_POST['level_id']) ? $_POST['level_id'] : '';
            $subject_cover;

            if (empty($level_id)) {
                $status->error = "Invalid level Id";
            } else if (!is_numeric($level_id)) {
                $status->error = "Invalid level Id";
            } else {
                $isValidLevel = Database::search("SELECT COUNT(*) as 'count' FROM `levels` WHERE `level_id`='" . $level_id . "'");
                $row = $isValidLevel->fetch_array();
                if ($row['count'] == 0) {
                    $status->error = "Invalid level Id";
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('level', 2, $level_id))) {
                        $status->error = "You don't have permission to add subjects";
                    } else {
                        if (empty($subject_name)) {
                            $status->name =  "Please enter subject name";
                        } else if (strlen($subject_name) > 100) {
                            $status->name =  "Subject name should be less than 100 characters";
                        } else {
                            $isValidSubject  = Database::search("SELECT COUNT(*) as 'count' FROM `subjects` WHERE `subject`='" . $subject_name . "' AND `levels_level_id` = '" . $level_id . "'");
                            $row = $isValidSubject->fetch_array();
                            if ($row['count'] > 0) {
                                $status->name =  "Subject name already exists in this level";
                            }
                        }
                        if (empty($subject_hours)) {
                            $status->hours =  "Please enter subject hours";
                        } else if (!is_numeric($subject_hours)) {
                            $status->hours =  "Invalid subject hours";
                        } else if ($subject_hours < 0) {
                            $status->hours =  "Invalid subject hours";
                        }
                        if (empty($subject_type) || $subject_type == 0) {
                            $status->type =  "Please select subject type";
                        } else {
                            $isValidSubjectType = Database::search("SELECT COUNT(*) as 'count' FROM `subject_type` WHERE `subject_type_id`='" . $subject_type . "'");
                            $row = $isValidSubjectType->fetch_array();
                            if ($row['count'] == 0) {
                                $status->type =  "Invalid subject type";
                            }
                        }
                        if (!empty($subject_type) && $subject_type == 2 && (empty($subject_group) || $subject_group == 0)) {
                            if (empty($new_subject_group)) {
                                $status->group =  "Please select subject group or create a new one";
                            }
                        } else {
                            if ($subject_group != 0) {
                                $isValidSubjectGroup = Database::search("SELECT COUNT(*) as 'count' FROM `subject_group` WHERE `subject_group_id`='" . $subject_group . "' AND `levels_level_id`='" . $level_id . "'");
                                $row = $isValidSubjectGroup->fetch_array();
                                if ($row['count'] == 0) {
                                    $status->group =  "Invalid subject group";
                                }
                            } else {
                                $isValidSubjectGroup = Database::search("SELECT COUNT(*) as 'count' FROM `subject_group` WHERE `subject_group`='" . $new_subject_group . "' AND `levels_level_id`='" . $level_id . "'");
                                $row = $isValidSubjectGroup->fetch_array();
                                if ($row['count'] > 0) {
                                    $status->group =  "subject group already exists in this level";
                                }
                            }
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
                        } else {
                            $status->cover = "Please select subject cover";
                        }
                        $requiredProperties = [
                            'name',
                            'hours',
                            'type',
                            'cover',
                            'group'
                        ];
                        $allPropertiesExist = true;
                        foreach ($requiredProperties as $property) {
                            if (property_exists($status, $property)) {
                                $allPropertiesExist = false;
                                break;
                            }
                        }
                        if ($allPropertiesExist) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');

                            $subject_insert_columns = "`subject`,`date_added`,`users_user_id`,`levels_level_id`,`max_course_hours`,`subject_type_subject_type_id`,`subjects_order`";
                            $subject_insert_values = "'" . $subject_name . "','" . $date . "','" . $sessionUserObject['user_id'] . "','" . $level_id . "','" . $subject_hours . "','" . $subject_type . "','0'";

                            if ($subject_type == 2) {
                                if ($subject_group == 0) {
                                    $subject_group_insert_columns = "`subject_group`,`levels_level_id`,`users_user_id`,`date_added`";
                                    $subject_group_insert_values = "'" . $new_subject_group . "','" . $level_id . "','" . $sessionUserObject['user_id'] . "','" . $date . "'";
                                    Database::iud("INSERT INTO `subject_group` (" . $subject_group_insert_columns . ") VALUES (" . $subject_group_insert_values . ")");

                                    $subjectGroupId = Database::$connection->insert_id;
                                    $subject_insert_columns .= ", `subject_group_subject_group_id`";
                                    $subject_insert_values .= ",'" . $subjectGroupId . "'";
                                } else {
                                    $subject_insert_columns .= ", `subject_group_subject_group_id`";
                                    $subject_insert_values .= ",'" . $subject_group . "'";
                                }
                            }

                            if (!empty($subject_cover)) {
                                $subject_insert_columns .= ", `subject_cover`";
                                $subject_insert_values .= ",'" . $subject_cover . "'";
                            }

                            Database::iud("INSERT INTO `subjects` (" . $subject_insert_columns . ") VALUES (" . $subject_insert_values . ")");

                            if (isset($_FILES["subject_cover"])) {
                                $qimg_path = "../assets/imgs/subjects-covers/" . $subject_cover;
                                move_uploaded_file($_FILES["subject_cover"]["tmp_name"], $qimg_path);
                            }
                            $status->done =  "The subject has been successfully added";
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
