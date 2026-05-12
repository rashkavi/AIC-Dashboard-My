<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("users", 2))) {
                $status->error = 'You are not authorized to add user';
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
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                $address2 = isset($_POST['address2']) ? trim($_POST['address2']) : '';
                $city = isset($_POST['city']) ? trim($_POST['city']) : '';
                $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';
                $about = isset($_POST['about']) ? $_POST['about'] : '';
                $bio = isset($_POST['bio']) ? $_POST['bio'] : '';
                $achivements = isset($_POST['achivements']) ? $_POST['achivements'] : '';
                $experience = isset($_POST['experience']) ? $_POST['experience'] : '';
                $image = "";
                $cv_file_name = "";

                if ($title == 0) {
                    $status->title =  "Please select user title";
                }
                if ($gender == 0) {
                    $status->gender =  "Please select user gender";
                }
                if ($role == 0) {
                    $status->role =  "Please select user role";
                }
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
                if (empty($email)) {
                    $status->email =  "Please enter user email";
                } else if (strlen($email) > 100) {
                    $status->email =  "Email should be less than 45 characters";
                } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $status->test = $email;
                    $status->email =  "Please enter a valid email";
                } else {
                    $user = Database::search("SELECT * FROM `users` WHERE `user_email`='" . $email . "'");
                    if ($user->num_rows > 0) {
                        $status->email =  "User already exists";
                    }
                }
                if (empty($contact)) {
                    $status->contact =  "Please enter user Contact Number";
                } else if (!preg_match('/^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$/', $contact)) {
                    $status->contact =  "Please enter a valid contact number";
                } else {
                    $user = Database::search("SELECT * FROM `users` WHERE `user_mobile`='" . $contact . "'");
                    if ($user->num_rows > 0) {
                        $status->contact =  "User already exists";
                    }
                }
                if (!empty($nic)) {
                    if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                        $status->nic =  "Please enter a valid NIC number";
                    }
                }
                $invalid_chars = '/[\'\";\-]/';
                if (!empty($address)) {
                    if (!strlen($address) > 100) {
                        $status->address1 =  "Address should be less than 100 characters";
                    } else if (preg_match($invalid_chars, $address)) {
                        $status->address1 =  "Address lines contain invalid characters.";
                    }
                }
                if (!empty($address2)) {
                    if (!strlen($address2) > 100) {
                        $status->address2 =  "Address should be less than 100 characters";
                    } else if (preg_match($invalid_chars, $address2)) {
                        $status->address2 =  "Address lines contain invalid characters.";
                    }
                }
                if (!empty($address) || !$city == 0 || !empty($pcode)) {
                    if ($city == 0) {
                        $status->city =  "Please select user city";
                    }
                    if (empty($pcode)) {
                        $status->pcode =  "Please enter user city postal code";
                    }
                    if (empty($address)) {
                        $status->address =  "Please enter user address";
                    }
                }

                if (!empty($pcode) && !strlen($pcode) > 5) {
                    $status->pcode =  "Postcode should be less than 5 characters";
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


                $requiredProperties = [
                    'fname',
                    'lname',
                    'email',
                    'contact',
                    'title',
                    'role',
                    'gender',
                    'image',
                    'address',
                    'address2',
                    'city',
                    'pcode',
                    'cv',
                    'nic',
                    'dob'
                ];
                $allPropertiesExist = true;
                foreach ($requiredProperties as $property) {
                    if (property_exists($status, $property)) {
                        $allPropertiesExist = false;
                        break; // Exit loop on the first missing property
                    }
                }
                if ($allPropertiesExist) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $registerTime = $dateTime->format('Y-m-d H:i:s');

                    $bytes = random_bytes(75);
                    $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                    $password = rand(1000, 9999);
                    $hashPassword = password_hash($password, PASSWORD_BCRYPT);

                    $users_table_insert_columns = "`title_title_id`, `user_fname`, `user_lname`, `user_email`, `user_mobile`, `user_password`, `user_reg_date`, `status_status_id`, `user_roles_user_role_id`,`gender_gender_id`, `user_verification`, `user_reset_time`,`added_by`";
                    $users_table_insert_values = "'" . $title . "','" . $fname . "','" . $lname . "','" . $email . "','" . $contact . "','" . $hashPassword . "','" . $registerTime . "','1','" . $role . "','" . $gender . "','" . $token . "','" . $registerTime . "','" . $sessionUserObject['user_id'] . "'";

                    if (isset($_FILES["image"])) {
                        $users_table_insert_columns .= ", `profile_img`";
                        $users_table_insert_values .= ",'" . $image . "'";
                    }

                    if (!empty($nic)) {
                        $users_table_insert_columns .= ", `nic`";
                        $users_table_insert_values .= ",'" . $nic . "'";
                    }

                    if (!empty($dob)) {
                        $timestamp = strtotime($dob);
                        $dob = date("Y-m-d", $timestamp);

                        $users_table_insert_columns .= ", `dob`";
                        $users_table_insert_values .= ",'" . $dob . "'";
                    }


                    Database::iud("INSERT INTO `users` (" . $users_table_insert_columns . ") VALUES (" . $users_table_insert_values . ")");

                    $added_user_id = Database::$connection->insert_id;

                    if (!empty($address)) {
                        $address_table_insert_columns = "`users_user_id`, `address_line_1`, `city_city_id`, `postal_code`";
                        $address_table_insert_values = "'" . $added_user_id . "','" . $address . "','" . $city . "','" . $pcode . "'";

                        if (!empty($address2)) {
                            $address_table_insert_columns .= ", `address_line_2`";
                            $address_table_insert_values .= ",'" . $address2 . "'";
                        }

                        Database::iud("INSERT INTO `user_has_address` (" . $address_table_insert_columns . ") VALUES (" . $address_table_insert_values . ")");
                    }

                    if (!empty($cv_file_name) || !empty($about) || !empty($bio) || !empty($achivements) || !empty($experience)) {
                        $optional_details_table_insert_columns = "`users_user_id`, `about`, `bio`, `experience`, `achivements`";
                        $optional_details_table_insert_values = "'" . $added_user_id . "','" . $about . "','" . $bio . "','" . $experience . "','" . $achivements . "'";

                        if (!empty($cv_file_name)) {
                            $optional_details_table_insert_columns .= ", `cv`";
                            $optional_details_table_insert_values .= ",'" . $cv_file_name . "'";
                        }
                        Database::iud("INSERT INTO `optional_details` (" . $optional_details_table_insert_columns . ") VALUES (" . $optional_details_table_insert_values . ")");
                    }

                    if (isset($_FILES["image"])) {
                        $qimg_path = "../assets/imgs/users/" . $image;
                        move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
                    }
                    if (isset($_FILES["cv"])) {
                        $qcv_path = "../assets/uploads/cv/" . $cv_file_name;
                        move_uploaded_file($_FILES["cv"]["tmp_name"], $qcv_path);
                    }

                    $mail = EmailNotificationService::build(
                        EmailTemplateContent::PASSWORD_SETUP,
                        [
                            'action_url' => Project::getBaseUrl() . "/setup-password?token=" . $token
                        ]
                    );

                    $titleName = Database::search("SELECT `title` FROM `title` WHERE `title_id`='" . $title . "'")->fetch_assoc()["title"];
                    $userName = $titleName . "." . $fname . " " . $lname;

                    sendMail($email, $userName, $mail['subject'], $mail['body']);

                    $status->done =  "User Added Successfully";
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
