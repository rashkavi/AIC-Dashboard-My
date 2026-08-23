<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/Limitation.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
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
            $counselor = isset($_POST['counselor']) ? trim($_POST['counselor']) : 0;
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
            $certificate_name = isset($_POST['certificate_name']) ? trim($_POST['certificate_name']) : '';
            $school = isset($_POST['school']) ? trim($_POST['school']) : '';
            $ol_results = isset($_POST['ol_results']) ? trim($_POST['ol_results']) : '';
            $al_results = isset($_POST['al_results']) ? trim($_POST['al_results']) : '';
            $work_experience = isset($_POST['work_experience']) ? trim($_POST['work_experience']) : '';
            $team_username = isset($_POST['team_username']) ? trim($_POST['team_username']) : '';
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
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $intake_id))) {
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
                                $status->fullname =  "Please enter student Full Name (As it appears on the Certificate)";
                            } else if (strlen($fullname) > 150) {
                                $status->fullname =  "Full Name (As it appears on the Certificate) should be less than 150 characters";
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

                            if (empty($counselor)) {
                                $status->counselor =  "Please select student counselor";
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
                            } else if (!normalizePhoneNumber($mobile)) {
                                $status->mobile =  "Please enter a valid contact number";
                            } else {
                                $isValidMobile = Database::search("SELECT COUNT(*) as 'count' FROM `students` WHERE (`mobile` = '" . $mobile . "' OR `mobile` = '" . normalizePhoneNumber($mobile) . "')");
                                $row = $isValidMobile->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->mobile =  "Contact number already exists";
                                }
                            }
                            if (empty($whatsapp)) {
                                $status->whatsapp =  "Please enter student whatsapp number";
                            } else if (!normalizePhoneNumber($whatsapp)) {
                                $status->whatsapp =  "Please enter a valid whatsapp number";
                            } else {
                                $isValidWhatsapp = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE (`whatsapp` = '" . $whatsapp . "' OR `whatsapp` = '" . normalizePhoneNumber($whatsapp) . "')");
                                $row = $isValidWhatsapp->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->whatsapp =  "Whatsapp number already exists";
                                }
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
                            if (!empty($address) || !$city == 0 || !empty($pcode)) {
                                if ($city == 0) {
                                    $status->city =  "Please select student city";
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
                                } else if (!normalizePhoneNumber($gmobile)) {
                                    $status->gmobile =  "Please enter a valid contact number";
                                }

                                if (!empty($gwhatsapp)) {
                                    if (!normalizePhoneNumber($gwhatsapp)) {
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

                            if (!empty($certificate_name) && strlen($certificate_name) > 100) {
                                $status->certificate_name = "Certificate name should be less than 100 characters";
                            }

                            if (!empty($school) && strlen($school) > 150) {
                                $status->school = "School name should be less than 150 characters";
                            }

                            if (!empty($ol_results) && strlen($ol_results) > 100) {
                                $status->ol_results = "O/L results should be less than 100 characters";
                            }

                            if (!empty($al_results) && strlen($al_results) > 100) {
                                $status->al_results = "A/L results should be less than 100 characters";
                            }

                            if (!empty($work_experience) && strlen($work_experience) > 1000) {
                                $status->work_experience = "Work experience is too long";
                            }

                            if (!empty($team_username) && strlen($team_username) > 100) {
                                $status->team_username = "Team username too long";
                            }

                            $studentDocuments = validateStudentDocuments();

                            foreach ($studentDocuments["errors"] as $key => $message) {
                                $status->$key = $message;
                            }

                            if (empty(get_object_vars($status))) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $registerTime = $dateTime->format('Y-m-d H:i:s');

                                $bytes = random_bytes(75);
                                $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                                $password = rand(1000, 9999);
                                $hashPassword = password_hash($password, PASSWORD_BCRYPT);

                                $login_details_columns = " `first_name`, `last_name`, `email`, `whatsapp`, `password`, `date_registered`,  `status_status_id`,`verification`";
                                $login_details_values = "'" . $fname . "','" . $lname . "','" . $email . "','" . normalizePhoneNumber($whatsapp) . "','" . $hashPassword . "','" . $registerTime . "','1','" . $token . "'";

                                Database::iud("INSERT INTO `login_details` (" . $login_details_columns . ") VALUES (" . $login_details_values . ")");
                                $login_details_id = Database::$connection->insert_id;

                                $student_table_insert_columns = "`full_name`,`name_with_initials`,`title_title_id`,`mobile`,`date_added`,`gender_gender_id`,`users_user_id`,`login_details_login_details_id`";
                                $student_table_insert_values = "'" . $fullname . "','" . $nwi . "','" . $title . "','" . normalizePhoneNumber($mobile) . "','" . $registerTime . "','" . $gender . "','" . $sessionUserObject['user_id'] . "','" . $login_details_id . "'";

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
                                } else {
                                    $student_table_insert_columns .= ", `nationality`";
                                    $student_table_insert_values .= ",'69'";
                                }
                                if (!empty($cob)) {
                                    $student_table_insert_columns .= ", `country_of_birth`";
                                    $student_table_insert_values .= ",'" . $cob . "'";
                                } else {
                                    $student_table_insert_columns .= ", `country_of_birth`";
                                    $student_table_insert_values .= ",'69'";
                                }
                                if (!empty($cor)) {
                                    $student_table_insert_columns .= ", `country_of_residence`";
                                    $student_table_insert_values .= ",'" . $cor . "'";
                                } else {
                                    $student_table_insert_columns .= ", `country_of_residence`";
                                    $student_table_insert_values .= ",'69'";
                                }

                                if (!empty($nic)) {
                                    $student_table_insert_columns .= ",`nic`";
                                    $student_table_insert_values .= ",'" . $nic . "'";
                                }

                                if (!empty($passport)) {
                                    $student_table_insert_columns .= ",`passport`";
                                    $student_table_insert_values .= ",'" . $passport . "'";
                                }

                                if (!empty($certificate_name)) {
                                    $student_table_insert_columns .= ",`certificate_name`";
                                    $student_table_insert_values .= ",'" . $certificate_name . "'";
                                }

                                if (!empty($school)) {
                                    $student_table_insert_columns .= ",`school_attended`";
                                    $student_table_insert_values .= ",'" . $school . "'";
                                }

                                if (!empty($ol_results)) {
                                    $student_table_insert_columns .= ",`ol_results`";
                                    $student_table_insert_values .= ",'" . $ol_results . "'";
                                }

                                if (!empty($al_results)) {
                                    $student_table_insert_columns .= ",`al_results`";
                                    $student_table_insert_values .= ",'" . $al_results . "'";
                                }

                                if (!empty($work_experience)) {
                                    $student_table_insert_columns .= ",`work_experience`";
                                    $student_table_insert_values .= ",'" . $work_experience . "'";
                                }

                                if (!empty($team_username)) {
                                    $student_table_insert_columns .= ",`ms_team_username`";
                                    $student_table_insert_values .= ",'" . $team_username . "'";
                                }

                                Database::iud("INSERT INTO `students` (" . $student_table_insert_columns . ") VALUES (" . $student_table_insert_values . ")");
                                $studentId = Database::$connection->insert_id;

                                if (!empty($address)) {
                                    $address_table_insert_columns = "`address_line_1`,`students_student_id`";
                                    $address_table_insert_values = "'" . $address . "','" . $studentId . "'";

                                    if (!empty($address2)) {
                                        $address_table_insert_columns .= ", `address_line_2`";
                                        $address_table_insert_values .= ",'" . $address2 . "'";
                                    }

                                    if (!empty($city) && $city != 0) {
                                        $address_table_insert_columns .= ", `city_city_id`";
                                        $address_table_insert_values .= ",'" . $city . "'";
                                    }

                                    if (!empty($pcode) && $pcode != 0) {
                                        $address_table_insert_columns .= ", `postal_code`";
                                        $address_table_insert_values .= ",'" . $pcode . "'";
                                    }

                                    Database::iud("INSERT INTO `student_has_address` (" . $address_table_insert_columns . ") VALUES (" . $address_table_insert_values . ")");
                                }

                                $shi_table_insert_columns = "`students_student_id`, `student_id`,`addmission_date`,`status_status_id`,`users_user_id`,`intakes_intake_id`,`counselor_user_id`";
                                $shi_table_insert_values = "'" . $studentId . "', '" . $id . "','" . $registerTime . "','1','" . $sessionUserObject['user_id'] . "','" . $intake_id . "','" . $counselor . "'";

                                Database::iud("INSERT INTO `students_has_intakes` (" . $shi_table_insert_columns . ") VALUES (" . $shi_table_insert_values . ")");

                                if ($gtitle != 0) {
                                    $guardian_table_insert_columns = "`guardian_relation_guardian_relation_id`,`first_name`,`last_name`,`mobile`,`title_title_id`,`students_student_id`";
                                    $guardian_table_insert_values = "'" . $grelationship . "','" . $gfname . "','" . $glname . "','" . normalizePhoneNumber($gmobile) . "','" . $gtitle . "','" . $studentId . "'";

                                    if (!empty($gemail)) {
                                        $guardian_table_insert_columns .= ",`email`";
                                        $guardian_table_insert_values .= ",'" . $gemail . "'";
                                    }

                                    if (!empty($gwhatsapp)) {
                                        $guardian_table_insert_columns .= ",`whatsapp`";
                                        $guardian_table_insert_values .= ",'" . normalizePhoneNumber($gwhatsapp) . "'";
                                    }

                                    Database::iud("INSERT INTO `guardian_details` (" . $guardian_table_insert_columns . ") VALUES (" . $guardian_table_insert_values . ")");
                                }

                                uploadStudentDocuments($studentId, $studentDocuments);

                                if (isset($_FILES["image"])) {
                                    $qimg_path = "../assets/imgs/students/" . $image;
                                    move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
                                }

                                $mail = EmailNotificationService::build(
                                    EmailTemplateContent::PASSWORD_SETUP,
                                    [
                                        'action_url' => Project::getBasePortalUrl() . "/setup-password?token=" . $token
                                    ]
                                );

                                $titleName = Database::search("SELECT `title` FROM `title` WHERE `title_id`='" . $title . "'")->fetch_assoc()["title"];
                                $userName = $titleName . "." . $fname . " " . $lname;

                                sendMail($email, $userName, $mail['subject'], $mail['body']);
                                $status->done =  "Student Added Successfully";
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


function validateStudentDocuments()
{
    $maxSize = 8 * 1024 * 1024; // 8MB same as JS

    $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

    $requiredDocuments = [
        "passport_nic",
        "payment_slip",
        "signed_fee_structure",
        "discount_approved_document",
        "aic_application"
    ];

    $documentTypes = getStudentDocumentTypeConfig();

    $validFiles = [];
    $errors = [];
    $uploadedDocuments = [];

    foreach ($documentTypes as $fieldName => $config) {

        if (!isset($_FILES[$fieldName])) {
            continue;
        }

        $files = normalizeFilesArray($_FILES[$fieldName]);

        foreach ($files as $file) {

            if ($file["error"] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $errors[$fieldName] = "Upload error occurred";
                continue;
            }

            if ($file["size"] > $maxSize) {
                $errors[$fieldName] = "File size must be less than 8MB";
                continue;
            }

            $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions)) {
                $errors[$fieldName] = "Invalid file type";
                continue;
            }

            $uploadedDocuments[$fieldName] = true;

            $validFiles[] = [
                "field_name" => $fieldName,
                "type_id" => $config["type_id"],
                "folder" => $config["folder"],
                "prefix" => $config["prefix"],
                "file" => $file,
                "extension" => $extension
            ];
        }
    }

    foreach ($requiredDocuments as $requiredDoc) {
        if (!isset($uploadedDocuments[$requiredDoc])) {
            $errors[$requiredDoc] = "This document is required";
        }
    }

    return [
        "files" => $validFiles,
        "errors" => $errors
    ];
}
function getStudentDocumentTypeConfig()
{
    return [
        "aic_application" => [
            "type_id" => 1,
            "folder" => "aic-applications",
            "prefix" => "AIC_APPLICATION"
        ],
        "university_application" => [
            "type_id" => 2,
            "folder" => "university-applications",
            "prefix" => "UNIVERSITY_APPLICATION"
        ],
        "signed_agreement" => [
            "type_id" => 3,
            "folder" => "signed-agreements",
            "prefix" => "SIGNED_AGREEMENT"
        ],
        "signed_program_brief" => [
            "type_id" => 4,
            "folder" => "signed-program-briefs",
            "prefix" => "SIGNED_PROGRAM_BRIEF"
        ],
        "passport_nic" => [
            "type_id" => 5,
            "folder" => "nic-documents",
            "prefix" => "PASSPORT_NIC"
        ],
        "academic_documents" => [
            "type_id" => 6,
            "folder" => "academic-documents",
            "prefix" => "ACADEMIC_DOCUMENT"
        ],
        "other_entry_documents" => [
            "type_id" => 7,
            "folder" => "other-entry-documents",
            "prefix" => "OTHER_ENTRY_DOCUMENT"
        ],
        "payment_slip" => [
            "type_id" => 8,
            "folder" => "payment-slips",
            "prefix" => "PAYMENT_SLIP"
        ],
        "manager_checklist" => [
            "type_id" => 9,
            "folder" => "manager-checklists",
            "prefix" => "MANAGER_CHECKLIST"
        ],
        "passport_size_photo" => [
            "type_id" => 10,
            "folder" => "passport-size-photo",
            "prefix" => "PASSPORT_SIZE_PHOTO"
        ],
        "discount_approved_document" => [
            "type_id" => 11,
            "folder" => "discount-approved-documents",
            "prefix" => "DISCOUNT_APPROVED_DOCUMENT"
        ],
        "signed_fee_structure" => [
            "type_id" => 12,
            "folder" => "signed-fee-structure",
            "prefix" => "SIGNED_FEE_STRUCTURE"
        ]
    ];
}

function normalizeFilesArray($fileInput)
{
    $files = [];

    if (is_array($fileInput["name"])) {
        foreach ($fileInput["name"] as $index => $name) {
            $files[] = [
                "name" => $fileInput["name"][$index],
                "type" => $fileInput["type"][$index],
                "tmp_name" => $fileInput["tmp_name"][$index],
                "error" => $fileInput["error"][$index],
                "size" => $fileInput["size"][$index],
            ];
        }
    } else {
        $files[] = $fileInput;
    }

    return $files;
}

function uploadStudentDocuments($studentId, $studentDocuments)
{
    $baseUploadDir = "../assets/uploads/student-identity-documents/";

    foreach ($studentDocuments["files"] as $doc) {

        $typeId = $doc["type_id"];
        $folder = $doc["folder"];
        $prefix = $doc["prefix"];
        $file = $doc["file"];
        $extension = $doc["extension"];

        $uploadDir = $baseUploadDir . $folder . "/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = $prefix . "_" . uniqid() . "_" . rand(1000, 9999) . "." . $extension;

        $serverPath = $uploadDir . $newFileName;

        if (move_uploaded_file($file["tmp_name"], $serverPath)) {

            $dbPath = $newFileName;

            Database::iud("
                INSERT INTO `students_identity_documents`
                (
                    `students_student_id`,
                    `students_identity_documents_type_id`,
                    `document`,
                    `date_added`
                )
                VALUES
                (
                    '" . $studentId . "',
                    '" . $typeId . "',
                    '" . $dbPath . "',
                    NOW()
                )
            ");
        }
    }
}
