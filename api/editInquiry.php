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

            $id = isset($_POST['id']) ? trim($_POST['id']) : '';

            if (empty($id)) {
                $status->error = "Invalid student Id";
            } else {
                $isHasId = Database::search("SELECT `student_id`,`profile_img`,`login_details_login_details_id` FROM `students` WHERE `student_id`='" . $id . "'");

                if ($isHasId->num_rows == 0) {
                    $status->error = "Invalid student Id";
                } else {

                    $student = $isHasId->fetch_assoc();

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
                    $branch = isset($_POST['branch']) ? trim($_POST['branch']) : 0;
                    $faculty = isset($_POST['faculty']) ? trim($_POST['faculty']) : 0;
                    $programme = isset($_POST['programme']) ? trim($_POST['programme']) : 0;
                    $intake = isset($_POST['intake']) ? trim($_POST['intake']) : 0;
                    $entry_type = isset($_POST['entry_type']) ? trim($_POST['entry_type']) : 0;
                    $payment_option = isset($_POST['payment_option']) ? trim($_POST['payment_option']) : 0;
                    $image = '';

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
                    if (empty($fullname)) {
                        $status->fullname =  "Please enter user full name";
                    } else if (strlen($fullname) > 150) {
                        $status->fullname =  "Full Name should be less than 150 characters";
                    }
                    if (empty($nwi)) {
                        $status->nwi =  "Please enter user name with initials";
                    } else if (strlen($nwi) > 45) {
                        $status->nwi =  "Name with initials should be less than 45 characters";
                    }
                    if ($title == 0) {
                        $status->title =  "Please select user title";
                    }
                    if ($gender == 0) {
                        $status->gender =  "Please select user gender";
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
                            $isValidNic = Database::search("SELECT * FROM `students` WHERE `nic` = '" . $nic . "' AND `student_id` != '" . $id . "'");
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
                                "SELECT * FROM `students` WHERE `passport` = '" . $passport . "' AND `student_id` != '" . $id . "'"
                            );

                            if ($isValidPassport->num_rows > 0) {
                                $status->passport = "Passport number already exists";
                            }
                        }
                    }
                    if (empty($cob)) {
                        $status->cob =  "Please enter user country of birth";
                    } else if (strlen($cob) > 45) {
                        $status->cob =  "Country of Birth should be less than 45 characters";
                    }
                    if (empty($cor)) {
                        $status->cor =  "Please enter user country of residence";
                    } else if (strlen($cor) > 45) {
                        $status->cor =  "Country of Residence should be less than 45 characters";
                    }

                    if (empty($email)) {
                        $status->email =  "Please enter user email";
                    } else if (strlen($email) > 100) {
                        $status->email =  "Email should be less than 100 characters";
                    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $status->test = $email;
                        $status->email =  "Please enter a valid email";
                    } else {
                        $emailHasStudent = Database::search("SELECT `email` FROM `login_details`
                        INNER JOIN `students` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                         WHERE `email` = '" . $email . "' AND `student_id` != '" . $student["student_id"] . "'");
                        if ($emailHasStudent->num_rows > 0) {
                            $status->email =  "Email already exists";
                        }
                    }
                    if (empty($mobile)) {
                        $status->mobile =  "Please enter user mobile";
                    } else if (!normalizePhoneNumber($mobile)) {
                        $status->mobile =  "Please enter a valid contact number";
                    } else {
                        $mobileHasStudent = Database::search("SELECT `mobile` FROM `students` WHERE `mobile` = '" . $mobile . "' AND `student_id` != '" . $student["student_id"] . "'");
                        if ($mobileHasStudent->num_rows > 0) {
                            $status->mobile =  "Contact number already exists";
                        }
                    }
                    if (empty($whatsapp)) {
                        $status->whatsapp =  "Please enter user mobile";
                    } else if (!normalizePhoneNumber($whatsapp)) {
                        $status->whatsapp =  "Please enter a valid whatsapp number";
                    } else {
                        $whatsappHasStudent = Database::search("SELECT `whatsapp` FROM `login_details`
                        INNER JOIN `students` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id` 
                        WHERE `whatsapp` = '" . $whatsapp . "' AND `student_id` != '" . $student["student_id"] . "'");
                        if ($whatsappHasStudent->num_rows > 0) {
                            $status->whatsapp =  "Whatsapp number already exists";
                        }
                    }

                    $invalid_chars = '/[\'\";\-]/';
                    if (!empty($address) || !empty($address2) || !$city == 0 || !empty($pcode)) {
                        if (empty($address)) {
                            $status->address =  "Please enter user address";
                        }

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

                    if (empty($branch)) {
                        $status->branch =  "Please select student branch";
                    }

                    if (empty($faculty)) {
                        $status->faculty =  "Please select student faculty";
                    }

                    if (empty($programme)) {
                        $status->programme =  "Please select student programme";
                    }

                    if (empty($intake)) {
                        $status->intake =  "Please select student intake";
                    }
                    if (empty($entry_type)) {
                        $status->entry_type = "Please select student entry type";
                    }

                    if ($gtitle != 0 || $grelationship != 0 || !empty($gfname) || !empty($glname) || !empty($gemail) || !empty($gmobile) || !empty($gwhatsapp)) {
                        if ($gtitle == 0) {
                            $status->gtitle =  "Please select user guardian title";
                        }
                        if ($grelationship == 0) {
                            $status->grelationship =  "Please select user guardian relationship";
                        }
                        if (empty($gfname)) {
                            $status->gfname =  "Please enter user guardian first name";
                        } else if (strlen($gfname) > 45) {
                            $status->gfname =  "First Name should be less than 45 characters";
                        }
                        if (empty($glname)) {
                            $status->glname =  "Please enter user guardian last name";
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

                    $studentDocuments = validateStudentDocuments($id);

                    foreach ($studentDocuments["errors"] as $key => $message) {
                        $status->$key = $message;
                    }

                    $requiredProperties = [
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
                        'branch',
                        'faculty',
                        'programme',
                        'intake',
                        'entry_type',
                        'gtitle',
                        'grelationship',
                        'gfname',
                        'glname',
                        'gemail',
                        'gmobile',
                    ];
                    $allFieldsPresent = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allFieldsPresent = false;
                            break; // Exit loop on the first missing property
                        }
                    }
                    foreach ($studentDocuments["errors"] as $key => $message) {
                        $allPropertiesExist = false;
                        break; // Exit loop on the first missing property
                    }
                    if ($allFieldsPresent) {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $resetTime = $dateTime->format('Y-m-d H:i:s');

                        $timestamp = strtotime($dob);
                        $dob = date("Y-m-d", $timestamp);

                        $login_table_update = "`first_name`='" . $fname . "',`last_name`='" . $lname . "',`whatsapp`='" . $whatsapp . "',`email`='" . $email . "'";

                        $student_table_update = "`full_name`='" . $fullname . "',`name_with_initials`='" . $nwi . "'
                        ,`title_title_id`='" . $title . "'
                        ,`mobile`='" . $mobile . "',`gender_gender_id`='" . $gender . "'
                        ,`country_of_birth`='" . $cob . "',`country_of_residence`='" . $cor . "',
                        `entry_type`='" . $entry_type . "',`payment_option`='" . $payment_option . "'
                        ";

                        if ($marital_status != 0) {
                            $student_table_update .= ",`marital_status_marital_status_id`='" . $marital_status . "'";
                        }

                        if (!empty($dob)) {
                            $student_table_update .= ",`date_of_birth`='" . $dob . "'";
                        }

                        if (!empty($nic)) {
                            $student_table_update .= ",`nic`='" . $nic . "'";
                        } else {
                            $student_table_update .= ",`nic`=''";
                        }

                        if (!empty($passport)) {
                            $student_table_update .= ",`passport`='" . $passport . "'";
                        } else {
                            $student_table_update .= ",`passport`=''";
                        }

                        if (isset($_FILES["image"])) {
                            $student_table_update .= ",`profile_img`='" . $image . "'";
                        }

                        if (!empty($nationality)) {
                            $student_table_update .= ",`nationality`='" . $nationality . "'";
                        } else {
                            $student_table_update .= ",`nationality`='69'";
                        }


                        Database::iud("UPDATE `login_details` SET " . $login_table_update . " WHERE `login_details_id` = '" . $student["login_details_login_details_id"] . "'");
                        Database::iud("UPDATE `students` SET " . $student_table_update . " WHERE `student_id` = '" . $student["student_id"] . "'");

                        Database::iud("UPDATE `students_has_intakes` SET `intakes_intake_id`='" . $intake . "' WHERE `students_student_id`='" . $student["student_id"] . "'");
                        if (!empty($address)) {
                            $studentHasAddress = Database::search("SELECT * FROM `student_has_address` WHERE `students_student_id`='" . $student["student_id"] . "'");
                            if ($studentHasAddress->num_rows == 0) {

                                $address_table_columns = "`students_student_id`,`address_line_1`,`city_city_id`,`postal_code`";
                                $address_table_values = "'" . $student["student_id"] . "','" . $address . "','" . $city . "','" . $pcode . "'";

                                if (!empty($address2)) {
                                    $address_table_columns .= ",`address_line_2`";
                                    $address_table_values .= ",'" . $address2 . "'";
                                }

                                Database::iud("INSERT INTO `student_has_address` (" . $address_table_columns . ") VALUES (" . $address_table_values . ")");
                            } else {
                                $address_table_update = "`address_line_1`='" . $address . "',`city_city_id`='" . $city . "',`postal_code`='" . $pcode . "'";

                                if (!empty($address2)) {
                                    $address_table_update .= ",`address_line_2`='" . $address2 . "'";
                                }

                                Database::iud("UPDATE `student_has_address` SET " . $address_table_update . " WHERE `students_student_id` = '" . $student["student_id"] . "'");
                            }
                        }


                        if ($gtitle != 0) {
                            $studnetHasGuardian = Database::search("SELECT * FROM `guardian_details` WHERE `students_student_id`='" . $student["student_id"] . "'");
                            if ($studnetHasGuardian->num_rows == 0) {
                                $guardian_table_columns = "`students_student_id`,`first_name`,`last_name`,`guardian_relation_guardian_relation_id`,`title_title_id`,`mobile`";
                                $guardian_table_values = "'" . $student["student_id"] . "','" . $gfname . "','" . $glname . "','" . $grelationship . "','" . $gtitle . "','" . $gmobile . "'";

                                if (!empty($gemail)) {
                                    $guardian_table_columns .= ",`email`";
                                    $guardian_table_values .= ",'" . $gemail . "'";
                                }

                                if (!empty($gwhatsapp)) {
                                    $guardian_table_columns .= ",`whatsapp`";
                                    $guardian_table_values .= ",'" . $gwhatsapp . "'";
                                }


                                Database::iud("INSERT INTO `guardian_details` (" . $guardian_table_columns . ") VALUES (" . $guardian_table_values . ")");
                            } else {

                                $guardian_table_update = "`first_name`='" . $gfname . "',
                                `last_name`='" . $glname . "',`guardian_relation_guardian_relation_id`='" . $grelationship . "',
                                `title_title_id`='" . $gtitle . "',`email`='" . $gemail . "',
                                `mobile`='" . $gmobile . "',`whatsapp`='" . $gwhatsapp . "'";
                                Database::iud("UPDATE `guardian_details` SET " . $guardian_table_update . " WHERE `students_student_id` = '" . $student["student_id"] . "'");
                            }
                        }
                        if (isset($_FILES["image"])) {
                            $image_path = "../assets/imgs/students/" . $image;
                            move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);

                            if (!empty($student["profile_img"])) {
                                if (file_exists("../assets/imgs/students/" . $student["profile_img"])) {
                                    unlink("../assets/imgs/students/" . $student["profile_img"]);
                                }
                            }
                        }

                        if (isset($_POST["removed_documents"]) && is_array($_POST["removed_documents"])) {

                            $docFolderMap = [
                                1 => "aic-applications",
                                2 => "university-applications",
                                3 => "signed-agreements",
                                4 => "signed-program-briefs",
                                5 => "passport-nic-documents",
                                6 => "academic-documents",
                                7 => "other-entry-documents",
                                8 => "payment-slips",
                                9 => "manager-checklists",
                                10 => "passport-size-photos",
                                11 => "discount-approved-documents"
                            ];

                            foreach ($_POST["removed_documents"] as $docId) {
                                $docId = (int)$docId;

                                if ($docId <= 0) {
                                    continue;
                                }

                                $docRs = Database::search("
                                    SELECT 
                                        `students_identity_document_id`,
                                        `students_identity_documents_type_id`,
                                        `document`
                                    FROM `students_identity_documents`
                                    WHERE `students_identity_document_id` = '" . $docId . "'
                                      AND `students_student_id` = '" . $id . "'
                                    LIMIT 1
                                ");

                                if ($docRs->num_rows == 0) {
                                    continue;
                                }

                                $doc = $docRs->fetch_assoc();

                                $typeId = (int)$doc["students_identity_documents_type_id"];
                                $fileName = basename($doc["document"]);

                                if (isset($docFolderMap[$typeId])) {
                                    $filePath = "../assets/uploads/student-identity-documents/" . $docFolderMap[$typeId] . "/" . $fileName;

                                    if (is_file($filePath)) {
                                        unlink($filePath);
                                    }
                                }

                                Database::iud("
                                    DELETE FROM `students_identity_documents`
                                    WHERE `students_identity_document_id` = '" . $docId . "'
                                      AND `students_student_id` = '" . $id . "'
                                ");
                            }
                        }

                        uploadStudentDocuments($id, $studentDocuments);

                        Database::iud("INSERT INTO `students_logs` 
                            (`students_student_id`, `date_updated`, `users_user_id`,`log`)
                             VALUES ('" . $id . "','" . $resetTime . "', '" . $sessionUserObject['user_id'] . "','Update Student Details')");

                        $status->done =  "Student Update Successfully";
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

function validateStudentDocuments($id)
{
    $maxSize = 8 * 1024 * 1024; // 8MB same as JS

    $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

    $requiredDocuments = [
        "passport_nic",
        "payment_slip",
        "discount_approved_document",
        "signed_fee_structure",
    ];

    $removeIds = [];

    if (isset($_POST["removed_documents"]) && is_array($_POST["removed_documents"])) {
        foreach ($_POST["removed_documents"] as $docId) {
            $docId = (int)$docId;

            if ($docId > 0) {
                $removeIds[] = $docId;
            }
        }
    }

    $notInQuery = "";

    if (!empty($removeIds)) {
        $notInQuery = " AND `students_identity_document_id` NOT IN (" . implode(",", $removeIds) . ")";
    }

    $isUploadDocument = Database::search("
        SELECT *
        FROM `students_identity_documents`
        WHERE `students_student_id` = '" . $id . "'
        AND `students_identity_documents_type_id` IN (5,8)
        $notInQuery
    ");

    if ($isUploadDocument->num_rows > 0) {

        while ($data = $isUploadDocument->fetch_assoc()) {

            if ($data['students_identity_documents_type_id'] == 5) {

                $key = array_search("passport_nic", $requiredDocuments);

                if ($key !== false) {
                    unset($requiredDocuments[$key]);
                }
            }

            if ($data['students_identity_documents_type_id'] == 8) {

                $key = array_search("payment_slip", $requiredDocuments);

                if ($key !== false) {
                    unset($requiredDocuments[$key]);
                }
            }
        }
    }

    $requiredDocuments = array_values($requiredDocuments);
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
