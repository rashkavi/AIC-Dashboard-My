<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/Limitation.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/student-mail-body.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $intake_id = isset($_POST['intake_id']) ? trim($_POST['intake_id']) : '';
            $id = isset($_POST['id']) ? trim($_POST['id']) : '';
            $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
            $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
            $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
            $nwi = isset($_POST['nwi']) ? trim($_POST['nwi']) : '';
            $title = isset($_POST['title']) ? trim($_POST['title']) : 0;
            $gender = isset($_POST['gender']) ? trim($_POST['gender']) : 0;
            $marital_status = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : 0;
            $nationality = isset($_POST['nationality']) ? trim($_POST['nationality']) : '';
            $nic = isset($_POST['nic']) ? trim($_POST['nic']) : '';
            $passport = isset($_POST['passport']) ? trim($_POST['passport']) : '';
            $cob = isset($_POST['cob']) ? trim($_POST['cob']) : '';
            $cor = isset($_POST['cor']) ? trim($_POST['cor']) : '';
            $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
            $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : '';
            $address2 = isset($_POST['address2']) ? trim($_POST['address2']) : '';
            $city = isset($_POST['city']) ? trim($_POST['city']) : '';
            $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';
            $gtitle = isset($_POST['gtitle']) ? trim($_POST['gtitle']) : 0;
            $grelationship = isset($_POST['grelationship']) ? trim($_POST['grelationship']) : 0;
            $gfname = isset($_POST['gfname']) ? trim($_POST['gfname']) : '';
            $glname = isset($_POST['glname']) ? trim($_POST['glname']) : '';
            $gemail = isset($_POST['gemail']) ? trim($_POST['gemail']) : '';
            $gmobile = isset($_POST['gmobile']) ? trim($_POST['gmobile']) : '';
            $gwhatsapp = isset($_POST['gwhatsapp']) ? trim($_POST['gwhatsapp']) : '';
            $image;

            if (empty($intake_id)) {
                $status->error =  "Please select an Intake";
            } else {
                $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id` = '" . $intake_id . "'");
                $row = $isValidIntake->fetch_assoc();
                if ($row['count'] == 0) {
                    $status->error =  "Intake does not exist";
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $intake_id))) {
                        $status->error = 'You do not have permission to add student to this intake';
                    } else {
                        if (!Limitation::availableStudents()) {
                            $status->error = 'You have reached the maximum number of students';
                        } else {
                            if (empty($id)) {
                                $status->id =  "Please enter student id";
                            } else {
                                $isValidStudentId = Database::search("SELECT COUNT(*) as 'count' FROM `students_has_intakes` WHERE `student_id` = '" . $id . "'");
                                $row = $isValidStudentId->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->id =  "Student id already exists";
                                }
                            }

                            if (empty($fname)) {
                                $status->fname =  "Please enter student first name";
                            } else if (strlen($fname) > 45) {
                                $status->fname =  "First Name should be less than 45 characters";
                            }
                            if (empty($lname)) {
                                $status->lname =  "Please enter student last name";
                            } else if (strlen($lname) > 45) {
                                $status->lname =  "Last Name should be less than 45 characters";
                            }
                            if (empty($fullname)) {
                                $status->fullname =  "Please enter student full name";
                            } else if (strlen($fullname) > 150) {
                                $status->fullname =  "Full Name should be less than 150 characters";
                            }
                            if (empty($nwi)) {
                                $status->nwi =  "Please enter student name with initials";
                            } else if (strlen($nwi) > 45) {
                                $status->nwi =  "Name with initials should be less than 45 characters";
                            }
                            if ($title == 0) {
                                $status->title =  "Please select student title";
                            }
                            if ($gender == 0) {
                                $status->gender =  "Please select student gender";
                            }
                            if (!empty($nationality)) {
                                if (strlen($nationality) > 45) {
                                    $status->nationality =  "Nationality should be less than 45 characters";
                                }
                            }
                            if (!empty($nic)) {
                                if (strlen($nic) > 45) {
                                    $status->nic =  "NIC should be less than 45 characters";
                                } else if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                                    $status->nic =  "Please enter a valid NIC number";
                                } else {
                                    $isValidNic = Database::search("SELECT * FROM `students` WHERE `nic` = '" . $nic . "'");
                                    if ($isValidNic->num_rows > 0) {
                                        $status->nic =  "NIC already exists";
                                    }
                                }
                            }
                            if (!empty($passport)) {
                                if (strlen($passport) > 20) {
                                    $status->passport = "Passport number should be less than 20 characters";
                                } else if (!preg_match('/^[A-Z]{1,2}[0-9]{6,8}$/', strtoupper($passport))) {
                                    $status->passport = "Please enter a valid passport number";
                                } else {
                                    $isValidPassport = Database::search(
                                        "SELECT * FROM `students` WHERE `passport` = '" . $passport . "'"
                                    );

                                    if ($isValidPassport->num_rows > 0) {
                                        $status->passport = "Passport number already exists";
                                    }
                                }
                            }
                            if (empty($cob)) {
                                $status->cob =  "Please enter student country of birth";
                            } else if (strlen($cob) > 45) {
                                $status->cob =  "Country of Birth should be less than 45 characters";
                            }
                            if (empty($cor)) {
                                $status->cor =  "Please enter student country of residence";
                            } else if (strlen($cor) > 45) {
                                $status->cor =  "Country of Residence should be less than 45 characters";
                            }

                            if (empty($email)) {
                                $status->email =  "Please enter student email";
                            } else if (strlen($email) > 100) {
                                $status->email =  "Email should be less than 100 characters";
                            } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $status->test = $email;
                                $status->email =  "Please enter a valid email";
                            } else {
                                $isValidEmail = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE `email` = '" . $email . "'");
                                $row = $isValidEmail->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->email =  "Email already exists";
                                }
                            }
                            if (empty($mobile)) {
                                $status->mobile =  "Please enter student mobile";
                            } else if (!preg_match('/^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$/', $mobile)) {
                                $status->mobile =  "Please enter a valid contact number";
                            } else {
                                $isValidMobile = Database::search("SELECT COUNT(*) as 'count' FROM `students` WHERE `mobile` = '" . $mobile . "'");
                                $row = $isValidMobile->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->mobile =  "Contact number already exists";
                                }
                            }
                            if (empty($whatsapp)) {
                                $status->whatsapp =  "Please enter student whatsapp number";
                            } else if (!preg_match('/^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$/', $whatsapp)) {
                                $status->whatsapp =  "Please enter a valid whatsapp number";
                            } else {
                                $isValidWhatsapp = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE `whatsapp` = '" . $whatsapp . "'");
                                $row = $isValidWhatsapp->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->whatsapp =  "Whatsapp number already exists";
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
                                    $status->city =  "Please select student city";
                                }

                                if (empty($pcode)) {
                                    $status->pcode =  "Please enter student city postal code";
                                }
                                if (empty($address)) {
                                    $status->address =  "Please enter student address";
                                }
                            }

                            if (!empty($pcode) && !strlen($pcode) > 5) {
                                $status->pcode =  "Postcode should be less than 5 characters";
                            }


                            if ($gtitle != 0 || $grelationship != 0 || !empty($gfname) || !empty($glname) || !empty($gemail) || !empty($gmobile) || !empty($gwhatsapp)) {
                                if ($gtitle == 0) {
                                    $status->gtitle =  "Please select student guardian title";
                                }
                                if ($grelationship == 0) {
                                    $status->grelationship =  "Please select student guardian relationship";
                                }
                                if (empty($gfname)) {
                                    $status->gfname =  "Please enter student guardian first name";
                                } else if (strlen($gfname) > 45) {
                                    $status->gfname =  "First Name should be less than 45 characters";
                                }
                                if (empty($glname)) {
                                    $status->glname =  "Please enter student guardian last name";
                                } else if (strlen($glname) > 45) {
                                    $status->glname =  "Last Name should be less than 45 characters";
                                }
                                if (!empty($gemail)) {
                                    if (strlen($gemail) > 100) {
                                        $status->gemail =  "Email should be less than 100 characters";
                                    } else if (!filter_var($gemail, FILTER_VALIDATE_EMAIL)) {
                                        $status->gemail =  "Please enter a valid email";
                                    }
                                }

                                if (empty($gmobile)) {
                                    $status->gmobile =  "Please enter student guardian mobile";
                                } else if (!preg_match('/^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$/', $gmobile)) {
                                    $status->gmobile =  "Please enter a valid contact number";
                                }

                                if (!empty($gwhatsapp)) {
                                    if (!preg_match('/^[0]{1}[7]{1}[01245678]{1}[0-9]{7}$/', $gwhatsapp)) {
                                        $status->gwhatsapp =  "Please enter a valid contact number";
                                    }
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

                            $requiredProperties = [
                                'id',
                                'fname',
                                'lname',
                                'fullname',
                                'nwi',
                                'title',
                                'gender',
                                'marital_status',
                                'nationality',
                                'nic',
                                'cob',
                                'cor',
                                'dob',
                                'email',
                                'mobile',
                                'whatsapp',
                                'address',
                                'address2',
                                'city',
                                'pcode',
                                'image',
                                'gtitle',
                                'grelationship',
                                'gfname',
                                'glname',
                                'gemail',
                                'gmobile',
                                'gwhatsapp',
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

                                $login_details_columns = " `first_name`, `last_name`, `email`, `whatsapp`, `password`, `date_registered`,  `status_status_id`,`verification`";
                                $login_details_values = "'" . $fname . "','" . $lname . "','" . $email . "','" . $whatsapp . "','" . $hashPassword . "','" . $registerTime . "','1','" . $token . "'";

                                Database::iud("INSERT INTO `login_details` (" . $login_details_columns . ") VALUES (" . $login_details_values . ")");
                                $login_details_id = Database::$connection->insert_id;

                                $student_table_insert_columns = "`full_name`,`name_with_initials`,`title_title_id`,`mobile`,`date_added`,`gender_gender_id`,`users_user_id`,`login_details_login_details_id`";
                                $student_table_insert_values = "'" . $fullname . "','" . $nwi . "','" . $title . "','" . $mobile . "','" . $registerTime . "','" . $gender . "','" . $sessionUserObject['user_id'] . "','" . $login_details_id . "'";

                                if (isset($_FILES["image"])) {
                                    $student_table_insert_columns .= ", `profile_img`";
                                    $student_table_insert_values .= ",'" . $image . "'";
                                }

                                if ($marital_status != 0) {
                                    $student_table_insert_columns .= ",`marital_status_marital_status_id`";
                                    $student_table_insert_values .= ",'" . $marital_status . "'";
                                }

                                if (!empty($dob)) {
                                    $student_table_insert_columns .= ",`date_of_birth`";
                                    $student_table_insert_values .= ",'" . $dob . "'";
                                }

                                if (!empty($nationality)) {
                                    $student_table_insert_columns .= ", `nationality`";
                                    $student_table_insert_values .= ",'" . $nationality . "'";
                                }
                                if (!empty($cob)) {
                                    $student_table_insert_columns .= ", `country_of_birth`";
                                    $student_table_insert_values .= ",'" . $cob . "'";
                                }

                                if (!empty($cor)) {
                                    $student_table_insert_columns .= ", `country_of_residence`";
                                    $student_table_insert_values .= ",'" . $cor . "'";
                                }

                                if (!empty($nic)) {
                                    $student_table_insert_columns .= ",`nic`";
                                    $student_table_insert_values .= ",'" . $nic . "'";
                                }

                                if (!empty($passport)) {
                                    $student_table_insert_columns .= ",`passport`";
                                    $student_table_insert_values .= ",'" . $passport . "'";
                                }

                                Database::iud("INSERT INTO `students` (" . $student_table_insert_columns . ") VALUES (" . $student_table_insert_values . ")");
                                $studentId = Database::$connection->insert_id;

                                if (!empty($address)) {
                                    $address_table_insert_columns = "`address_line_1`,`city_city_id`,`postal_code`,`students_student_id`";
                                    $address_table_insert_values = "'" . $address . "','" . $city . "','" . $pcode . "','" . $studentId . "'";

                                    if (!empty($address2)) {
                                        $address_table_insert_columns .= ", `address_line_2`";
                                        $address_table_insert_values .= ",'" . $address2 . "'";
                                    }

                                    Database::iud("INSERT INTO `student_has_address` (" . $address_table_insert_columns . ") VALUES (" . $address_table_insert_values . ")");
                                }


                                $shi_table_insert_columns = "`students_student_id`, `student_id`,`addmission_date`,`status_status_id`,`users_user_id`,`intakes_intake_id`";
                                $shi_table_insert_values = "'" . $studentId . "', '" . $id . "','" . $registerTime . "','1','" . $sessionUserObject['user_id'] . "','" . $intake_id . "'";

                                Database::iud("INSERT INTO `students_has_intakes` (" . $shi_table_insert_columns . ") VALUES (" . $shi_table_insert_values . ")");

                                if ($gtitle != 0) {
                                    $guardian_table_insert_columns = "`guardian_relation_guardian_relation_id`,`first_name`,`last_name`,`mobile`,`title_title_id`,`students_student_id`";
                                    $guardian_table_insert_values = "'" . $grelationship . "','" . $gfname . "','" . $glname . "','" . $gmobile . "','" . $gtitle . "','" . $studentId . "'";

                                    if (!empty($gemail)) {
                                        $guardian_table_insert_columns .= ",`email`";
                                        $guardian_table_insert_values .= ",'" . $gemail . "'";
                                    }

                                    if (!empty($gwhatsapp)) {
                                        $guardian_table_insert_columns .= ",`whatsapp`";
                                        $guardian_table_insert_values .= ",'" . $gwhatsapp . "'";
                                    }

                                    Database::iud("INSERT INTO `guardian_details` (" . $guardian_table_insert_columns . ") VALUES (" . $guardian_table_insert_values . ")");
                                }

                                if (isset($_FILES["image"])) {
                                    $qimg_path = "../assets/imgs/students/" . $image;
                                    move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
                                }

                                $url = "setup-password?token=" . $token;
                                $formattedWhatsapp = normalizePhoneNumber($whatsapp);

                                $curl = curl_init();
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => 'https://graph.facebook.com/v22.0/1046956908493510/messages',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_POST => true,
                                    CURLOPT_HTTPHEADER => array(
                                        "Authorization: Bearer EAAWijtYusPgBRMOqgf1ZChSWZBhrXDDKn2XZBl3mavZCkZC1mKfvDaZAfrOKTeaOJce5bMB6fJmK3qsvG545CkUJclqxBJc8aDIc2mFI4pGb1shsAW0z35XLCvugiuZCtyWhUerzoMg5lWI1Ky5hbNQs0iZC3NuoTaavKFJ8iLazqvMVPELLZAKG6FgA8kdDDgJG7zwZDZD",
                                        "Content-Type: application/json"
                                    ),
                                    CURLOPT_POSTFIELDS => json_encode([
                                        "messaging_product" => "whatsapp",
                                        "recipient_type" => "individual",
                                        "to" => $formattedWhatsapp,
                                        "type" => "template",
                                        "template" => [
                                            "name" => "welcome_message",
                                            "language" => [
                                                "code" => "en_US"
                                            ],
                                            "components" => [
                                                [
                                                    "type" => "body",
                                                    "parameters" => [
                                                        [
                                                            "type" => "text",
                                                            "text" => $fname . " " . $lname
                                                        ]
                                                    ]
                                                ],
                                                [
                                                    "type" => "button",
                                                    "sub_type" => "url",
                                                    "index" => "0",
                                                    "parameters" => [
                                                        [
                                                            "type" => "text",
                                                            "text" => $url,

                                                        ]
                                                    ]
                                                ]

                                            ]
                                        ]
                                    ])
                                ));
                                $response = curl_exec($curl);
                                curl_close($curl);

                                $curlData = json_decode($response, true);
                                if (isset($curlData['error'])) {
                                    $status->error =  "Student Added Successfully. But failed to send registration link";
                                } else {
                                    $status->done =  "Student Added Successfully";
                                }
                            }
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
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        if ($number[0] !== '+') {
            return '+' . $number;
        }
        return $number;
    }

    return false;
}
