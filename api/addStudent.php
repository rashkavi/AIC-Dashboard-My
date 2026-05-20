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
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
                $status->error = 'You are not authorized to add student';
            } else {
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
                $faculty = isset($_POST['faculty']) ? trim($_POST['faculty']) : 0;
                $programme = isset($_POST['programme']) ? trim($_POST['programme']) : 0;
                $intake = isset($_POST['intake']) ? trim($_POST['intake']) : 0;
                $gtitle = isset($_POST['gtitle']) ? trim($_POST['gtitle']) : 0;
                $grelationship = isset($_POST['grelationship']) ? trim($_POST['grelationship']) : 0;
                $gfname = isset($_POST['gfname']) ? trim($_POST['gfname']) : '';
                $glname = isset($_POST['glname']) ? trim($_POST['glname']) : '';
                $gemail = isset($_POST['gemail']) ? trim($_POST['gemail']) : '';
                $gmobile = isset($_POST['gmobile']) ? trim($_POST['gmobile']) : '';
                $gwhatsapp = isset($_POST['gwhatsapp']) ? trim($_POST['gwhatsapp']) : '';
                $image = '';

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

                if (!empty($faculty) ||  !empty($programme) || !empty($intake) || !empty($id)) {
                    if (!Limitation::availableStudents()) {
                        $status->id = "You have reached the maximum number of intake students";
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

                        if (empty($faculty)) {
                            $status->faculty =  "Please select student faculty";
                        }

                        if (empty($programme)) {
                            $status->programme =  "Please select student programme";
                        }
                        if (empty($intake)) {
                            $status->intake =  "Please select student intake";
                        } else {
                            $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id` = '" . $intake . "'");
                            $row = $isValidIntake->fetch_assoc();
                            if ($row['count'] == 0) {
                                $status->intake =  "Intake does not exist";
                            }
                        }
                    }
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

                $studentDocuments = validateStudentDocuments();

                foreach ($studentDocuments["errors"] as $key => $message) {
                    $status->$key = $message;
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
                    'faculty',
                    'programme',
                    'intake',
                    'gtitle',
                    'grelationship',
                    'gfname',
                    'glname',
                    'gemail',
                    'gmobile',
                    'gwhatsapp',
                    'entry_type'
                ];

                $allPropertiesExist = true;
                foreach ($requiredProperties as $property) {
                    if (property_exists($status, $property)) {
                        $allPropertiesExist = false;
                        break; // Exit loop on the first missing property
                    }
                }
                foreach ($studentDocuments["errors"] as $key => $message) {
                    $allPropertiesExist = false;
                    break; // Exit loop on the first missing property
                }
                if ($allPropertiesExist) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $registerTime = $dateTime->format('Y-m-d H:i:s');

                    $timestamp = strtotime($dob);
                    $dob = date("Y-m-d", $timestamp);

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

                    if (!empty($faculty) && Limitation::availableStudents()) {
                        $shi_table_insert_columns = "`students_student_id`, `student_id`,`addmission_date`,`status_status_id`,`users_user_id`,`intakes_intake_id`";
                        $shi_table_insert_values = "'" . $studentId . "', '" . $id . "','" . $registerTime . "','1','" . $sessionUserObject['user_id'] . "','" . $intake . "'";

                        Database::iud("INSERT INTO `students_has_intakes` (" . $shi_table_insert_columns . ") VALUES (" . $shi_table_insert_values . ")");
                    }

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

function validateStudentDocuments()
{
    $maxSize = 2 * 1024 * 1024; // 2MB

    $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

    $documentTypes = getStudentDocumentTypeConfig();

    $validFiles = [];
    $errors = [];

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
                $errors[$fieldName] = "File size must be less than 2MB";
                continue;
            }

            $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions)) {
                $errors[$fieldName] = "Invalid file type";
                continue;
            }

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
    $baseDbPath = "assets/uploads/student-identity-documents/";

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
