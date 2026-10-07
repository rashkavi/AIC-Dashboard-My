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
            $id = isset($_POST['id']) ? $_POST['id'] : '';

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 10) || $sessionUserObject['user_id'] == $id)) {
                $status->error = 'You are not authorized to update user';
            } else {


                if (empty($id)) {
                    $status->error = "Invalid user Id";
                } else if (!is_numeric($id)) {
                    $status->error = "Invalid user Id";
                } else {
                    $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_id`='" . $id . "'");
                    $row = $isValidUser->fetch_assoc();
                    if ($row['count'] == 0) {
                        $status->error = "Invalid user Id";
                    } else {

                        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
                        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
                        $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
                        $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
                        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                        $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
                        $nic = isset($_POST['nic']) ? trim($_POST['nic']) : '';
                        $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
                        $branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
                        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                        $address2 = isset($_POST['address2']) ? trim($_POST['address2']) : '';
                        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
                        $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';
                        $about = isset($_POST['about']) ? $_POST['about'] : '';
                        $bio = isset($_POST['bio']) ? $_POST['bio'] : '';
                        $achivements = isset($_POST['achivements']) ? $_POST['achivements'] : '';
                        $experience = isset($_POST['experience']) ? $_POST['experience'] : '';
                        $image = "";
                        $cv_file_name;

                        if (empty($fname)) {
                            $status->fname =  "Please enter user first name";
                        } else if (strlen($fname) > 45) {
                            $status->fname =  "First Name should be less than 45 characters";
                        }
                        if (empty($lname)) {
                            $status->lname =  "Please enter user last name";
                        } else if (strlen($lname) > 45) {
                            $status->lname =  "Last Name should be less than 45 characters";
                        }
                        if ($title == 0) {
                            $status->title =  "Please select user title";
                        }
                        if (empty($email)) {
                            $status->email =  "Please enter user email";
                        } else if (strlen($email) > 100) {
                            $status->email =  "Email should be less than 45 characters";
                        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $status->test = $email;
                            $status->email =  "Please enter a valid email";
                        } else {
                            $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_email`='" . $email . "' AND `user_id` != '" . $id . "'");
                            $row = $isValidUser->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->email =  "User email already exists";
                            }
                        }
                        if (empty($contact)) {
                            $status->contact =  "Please enter user Contact Number";
                        } else if (!normalizePhoneNumber($contact)) {
                            $status->contact =  "Please enter a valid contact number";
                        } else {
                            $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE (`user_mobile`='" . $contact . "' OR `user_mobile`='" . normalizePhoneNumber($contact) . "') AND `user_id` != '" . $id . "'");
                            $row = $isValidUser->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->contact =  "User contact number already exists";
                            }
                        }
                        if ($role == 0) {
                            $status->role =  "Please select user role";
                        } else {
                            if ($role == 1 && $sessionUserRole != "Developer") {
                                $status->role =  "You can't add this user role";
                            }

                            if ($role == 2 && SessionUser::ifUserIsPro($sessionUserRole) == false) {
                                $status->role =  "You can't add this user role";
                            }
                            if ($role == 9 && $branch == 0) {
                                $status->branch = "Please Select Branch";
                            }
                        }
                        if (!empty($nic)) {
                            if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                                $status->nic =  "Please enter a valid NIC number";
                            }
                        }

                        if (!empty($address) || !empty($address2) || !$city == 0 || !empty($pcode)) {
                            if (empty($address)) {
                                $status->address =  "Please enter user address";
                            }

                            if (!empty($address)) {
                                if (!strlen($address) > 100) {
                                    $status->address1 =  "Address should be less than 100 characters";
                                }
                            }
                            if (!empty($address2)) {
                                if (!strlen($address2) > 100) {
                                    $status->address2 =  "Address should be less than 100 characters";
                                }
                            }

                            if ($city == 0) {
                                $status->city =  "Please select user city";
                            }
                            if (empty($pcode)) {
                                $status->pcode =  "Please enter user city postal code";
                            }
                            if (!empty($pcode) && !strlen($pcode) > 5) {
                                $status->pcode =  "Postcode should be less than 5 characters";
                            }
                        }

                        if (isset($_FILES["image"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["image"]["type"];
                            if (!in_array($file_ex, $allowed_image_extentions)) {
                                $status->image = "Only jpg,jpeg,png and svg allowed";
                            } else {
                                $new_file_extention;
                                switch ($file_ex) {
                                    case "image/jpg":
                                        $new_file_extention = ".jpg";
                                        break;
                                    case "image/jpeg":
                                        $new_file_extention = ".jpeg";
                                        break;
                                    case "image/png":
                                        $new_file_extention = ".png";
                                        break;
                                    case "image/svg+xml":
                                        $new_file_extention = ".svg";
                                        break;
                                }

                                if ($_FILES["image"]["error"] === 0) {
                                    if ($_FILES["image"]["size"] <= 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["image"]["tmp_name"])) {
                                            $image = uniqid() . $new_file_extention;
                                        } else {
                                            $status->image = "Invalid image file!";
                                        }
                                    } else {
                                        $status->image = "Maximum allowed file size is 2MB";
                                    }
                                } else {
                                    $status->image = "There is an error with the selected image";
                                }
                            }
                        }
                        if (isset($_FILES["cv"])) {
                            $allowed_cv_extensions = array("application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
                            $cv_ex = $_FILES["cv"]["type"];
                            if (!in_array($cv_ex, $allowed_cv_extensions)) {
                                $status->cv = "Only PDF, DOC, and DOCX files are allowed";
                            } else {
                                $new_cv_extension = '';
                                switch ($cv_ex) {
                                    case "application/pdf":
                                        $new_cv_extension = ".pdf";
                                        break;
                                    case "application/msword":
                                        $new_cv_extension = ".doc";
                                        break;
                                    case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                        $new_cv_extension = ".docx";
                                        break;
                                }

                                if ($_FILES["cv"]["error"] === 0) {
                                    if ($_FILES["cv"]["size"] <= 2 * 1024 * 1024) {
                                        $cv_file_name = uniqid() . $new_cv_extension;
                                    } else {
                                        $status->cv = "Maximum allowed file size is 2MB";
                                    }
                                } else {
                                    $status->cv = "There is an error with the selected CV file";
                                }
                            }
                        }

                        if (empty(get_object_vars($status))) {

                            $formattedMobile = normalizePhoneNumber($contact);


                            $user = Database::search("SELECT * FROM `users` WHERE `user_id` = '" . $id . "'");
                            $user_data = $user->fetch_assoc();

                            $users_table_update_query = "`title_title_id`='" . $title . "',`user_fname`='" . $fname . "',`user_lname`='" . $lname . "',`user_email`='" . $email . "',`user_mobile`='" . $formattedMobile . "',`user_roles_user_role_id`='" . $role . "'";

                            if (!empty($image)) {
                                $users_table_update_query .= ",`profile_img`='" . $image . "'";
                            }
                            if (!empty($nic)) {
                                $users_table_update_query .= ",`nic`='" . $nic . "'";
                            }
                            if (!empty($dob)) {
                                $timestamp = strtotime($dob);
                                $dob = date("Y-m-d", $timestamp);
                                $users_table_update_query .= ",`dob`='" . $dob . "'";
                            }

                            if ($role == 9 && $branch != 0) {
                                $users_table_update_query .= ",`branches_branch_id`='" . $branch . "'";
                            } else {
                                $users_table_update_query .= ",`branches_branch_id`=NULL";
                            }

                            Database::iud("UPDATE `users` SET " . $users_table_update_query . " WHERE `user_id` = '" . $id . "'");

                            if (!empty($address)) {
                                $uAddress = Database::search("SELECT * FROM `user_has_address` WHERE `users_user_id` = '" . $id . "'");
                                $uAddress_data = $uAddress->fetch_assoc();
                                if ($uAddress_data != null) {
                                    $address_table_update_query = "`address_line_1`='" . $address . "',`city_city_id`='" . $city . "',`postal_code`='" . $pcode . "'";
                                    if (!empty($address2)) {
                                        $address_table_update_query .= ",`address_line_2`='" . $address2 . "'";
                                    }
                                    Database::iud("UPDATE `user_has_address` SET " . $address_table_update_query . " WHERE `users_user_id` = '" . $id . "' AND `user_has_address_id` = '" . $uAddress_data["user_has_address_id"] . "'");
                                } else {
                                    $address_table_insert_columns = "`users_user_id`, `address_line_1`, `city_city_id`, `postal_code`";
                                    $address_table_insert_values = "'" . $id . "','" . $address . "','" . $city . "','" . $pcode . "'";

                                    if (!empty($address2)) {
                                        $address_table_insert_columns .= ", address_line_2";
                                        $address_table_insert_values .= ",'" . $address2 . "'";
                                    }

                                    Database::iud("INSERT INTO `user_has_address` (" . $address_table_insert_columns . ") VALUES (" . $address_table_insert_values . ")");
                                }
                            }

                            if (!empty($cv_file_name) || !empty($about) || !empty($bio) || !empty($achievements) || !empty($experience)) {
                                $uOptional = Database::search("SELECT * FROM `optional_details` WHERE `users_user_id` = '" . $id . "'");
                                $uOptional_data = $uOptional->fetch_assoc();
                                if ($uOptional_data != null) {
                                    $optional_details_table_update_query = "`about`='" . $about . "',`bio`='" . $bio . "',`achivements`='" . $achivements . "',`experience`='" . $experience . "'";
                                    if (!empty($cv_file_name)) {
                                        $optional_details_table_update_query .= ",`cv`='" . $cv_file_name . "'";
                                    }
                                    Database::iud("UPDATE `optional_details` SET " . $optional_details_table_update_query . " WHERE `users_user_id` = '" . $id . "' AND `optional_details_id` = '" . $uOptional_data["optional_details_id"] . "'");
                                } else {
                                    $optional_details_table_insert_columns = "`users_user_id`, `about`, `bio`, `experience`, `achivements`";
                                    $optional_details_table_insert_values = "'" . $id . "','" . $about . "','" . $bio . "','" . $experience . "','" . $achivements . "'";

                                    if (!empty($cv_file_name)) {
                                        $optional_details_table_insert_columns .= ", `cv`";
                                        $optional_details_table_insert_values .= ",'" . $cv_file_name . "'";
                                    }

                                    Database::iud("INSERT INTO `optional_details` (" . $optional_details_table_insert_columns . ") VALUES (" . $optional_details_table_insert_values . ")");
                                }
                            }

                            if (isset($_FILES["image"])) {
                                $image_path = "../assets/imgs/users/";
                                $qimg_path =  $image_path . $image;
                                move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);

                                if (!empty($user_data["profile_img"])) {
                                    if (file_exists($image_path . $user_data["profile_img"])) {
                                        unlink($image_path . $user_data["profile_img"]);
                                    }
                                }

                                if ($id == $sessionUserObject['user_id']) {
                                    $sessionUserObject['user_image'] = $image;
                                }
                            }
                            if (isset($_FILES["cv"])) {
                                $cv_path = "../assets/uploads/cv/";
                                $qcv_path = $cv_path . $cv_file_name;
                                move_uploaded_file($_FILES["cv"]["tmp_name"], $qcv_path);

                                if (!empty($uOptional_data["cv"]) && file_exists($cv_path . $uOptional_data["cv"])) {
                                    if (file_exists($cv_path . $uOptional_data["cv"])) {
                                        unlink($cv_path . $uOptional_data["cv"]);
                                    }
                                }
                            }

                            if ($sessionUserObject["user_id"] == $id) {
                                $userData = [
                                    "user_id" => $sessionUserObject["user_id"],
                                    "user_fname" => $fname,
                                    "user_lname" => $lname,
                                    "user_email" => $email,
                                    "user_image" => $sessionUserObject["user_image"],
                                    "user_roles_user_role_id" => $role,
                                    "user_permissions" => $sessionUserObject["user_permissions"]
                                ];

                                $role = Database::search("SELECT * FROM `user_roles` WHERE `user_role_id` = '" . $role . "'");
                                $role = $role->fetch_assoc();
                                SessionUser::destroySession();
                                SessionUser::startSession($role["user_role"], $userData);
                            }
                            $status->done =  "User Updated Successfully";
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

function normalizePhoneNumber($number)
{
    // Remove spaces, dashes, brackets, etc. (keep +)
    $number = trim($number);
    $number = preg_replace('/[^\d+]/', '', $number);

    // Already in international format
    if (preg_match('/^\+\d{8,15}$/', $number)) {
        return $number;
    }

    // Sri Lanka: +947XXXXXXXX
    if (preg_match('/^\+947\d{8}$/', $number)) {
        return $number;
    }

    // Sri Lanka: 947XXXXXXXX
    if (preg_match('/^947\d{8}$/', $number)) {
        return '+' . $number;
    }

    // Sri Lanka: 07XXXXXXXX
    if (preg_match('/^07\d{8}$/', $number)) {
        return '+94' . substr($number, 1);
    }

    // Sri Lanka: 7XXXXXXXX
    if (preg_match('/^7\d{8}$/', $number)) {
        return '+94' . $number;
    }

    // Any other international number (8-15 digits)
    if (preg_match('/^\d{8,15}$/', $number)) {
        return '+' . $number;
    }

    // Invalid phone number
    return false;
}
