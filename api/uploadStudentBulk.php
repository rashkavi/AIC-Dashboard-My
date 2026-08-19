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
            if (!(SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
                $status->error = 'You are not authorized to add student';
            } else {
                if (!isset($_FILES["file"])) {
                    $status->file = "Please select a file";
                } else {
                    $csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');
                    if (empty($_FILES['file']['name']) && !in_array($_FILES['file']['type'], $csvMimes)) {
                        $status->file = "Please select a valid CSV file";
                    } else {
                        if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
                            $status->file = "Please select a valid CSV file";
                        } else {
                            $csvFile = fopen($_FILES['file']['tmp_name'], 'r');
                            fgetcsv($csvFile);

                            $approvedStudebtCount = 0;

                            $csvData = [];

                            while (($line = fgetcsv($csvFile)) !== FALSE) {
                                if (count(array_filter($line)) != 0) {
                                    $csvData[] = $line;
                                }
                            }
                            fclose($csvFile);
                            if (count($csvData) == 0) {
                                $status->file = "Please select a valid CSV file";
                            }

                            if (!property_exists($status, 'file') && !property_exists($status, 'error')) {
                                $rowNumber = 1;

                                $linesErrors = [];
                                $mobileNumbers = [];
                                $emailAddresses = [];
                                $whatsappNumbers = [];
                                $nicNumbers = [];
                                $passportNumbers = [];

                                foreach ($csvData as $data) {

                                    $fname = $data[0];
                                    $lname = $data[1];
                                    $fullname = $data[2];
                                    $nwi = $data[3];
                                    $title = $data[4];
                                    $gender = $data[5];
                                    $marital_status = $data[6];
                                    $nic = $data[7];
                                    $passport = $data[8];
                                    $dob = $data[9];
                                    $email = $data[10];
                                    $mobile = $data[11];
                                    $whatsapp = $data[12];

                                    $lineErrors = [];

                                    if (empty($fname)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": student first name cannot be empty";
                                    } else if (strlen($fname) > 45) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": First Name should be less than 45 characters";
                                    }
                                    if (empty($lname)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": student last name cannot be empty";
                                    } else if (strlen($lname) > 45) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Last Name should be less than 45 characters";
                                    }
                                    if (empty($fullname)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": student Full Name (As it appears on the Certificate) cannot be empty";
                                    } else if (strlen($fullname) > 150) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Full Name (As it appears on the Certificate) should be less than 150 characters";
                                    }
                                    if (empty($nwi)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter student name with initials";
                                    } else if (strlen($nwi) > 45) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Name with initials should be less than 45 characters";
                                    }
                                    if ($title == 0) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please add student title";
                                    } else {
                                        $isValidTitle = Database::search("SELECT * FROM `title` WHERE `title_id` = '" . $title . "'");
                                        if ($isValidTitle->num_rows == 0) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Please add valid student title";
                                        }
                                    }
                                    if ($gender == 0) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please add student gender";
                                    } else {
                                        $isValidGender = Database::search("SELECT * FROM `gender` WHERE `gender_id` = '" . $gender . "'");
                                        if ($isValidGender->num_rows == 0) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Please add valid student gender";
                                        }
                                    }
                                    if ($marital_status != 0) {
                                        $isValidMaritalStatus = Database::search("SELECT COUNT(*) as 'count' FROM `marital_status` WHERE `marital_status_id` = '" . $marital_status . "'");
                                        $row = $isValidMaritalStatus->fetch_assoc();
                                        if ($row['count'] == 0) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Please add valid student marital status";
                                        }
                                    }

                                    if (!empty($nic)) {
                                        if (strlen($nic) > 12) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": NIC/Passport should be less than 12 characters";
                                            $status->nic = "NIC/Passport should be less than 45 characters";
                                        } else if (
                                            !preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic) && // NIC format
                                            !preg_match('/^[A-Za-z][A-Za-z0-9]{5,11}$/', $nic)     // Passport format
                                        ) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter a valid NIC or Passport number";
                                        } else if (in_array($nic, $nicNumbers)) {
                                            $duplicateIndex = array_search($nic, $nicNumbers);
                                            $lineErrors[] = "Data Row " . $rowNumber . ": NIC already has been used in this CSV at line " . ($duplicateIndex + 1) . " student";
                                        } else {
                                            $isValidNic = Database::search("SELECT * FROM `students` WHERE `nic` = '" . $nic . "'");
                                            if ($isValidNic->num_rows > 0) {
                                                $lineErrors[] =  "Data Row " . $rowNumber . ": NIC already exists";
                                            }
                                        }
                                    }

                                    if (!empty($passport)) {
                                        if (strlen($passport) > 20) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Passport number should be less than 20 characters";
                                        } else if (!preg_match('/^[A-Z]{1,2}[0-9]{6,8}$/', strtoupper($passport))) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter a valid passport number";
                                        } else if (in_array($passport, $passportNumbers)) {
                                            $duplicateIndex = array_search($passport, $passportNumbers);
                                            $lineErrors[] = "Data Row " . $rowNumber . ": Passport already has been used in this CSV at line " . ($duplicateIndex + 1) . " student";
                                        } else {
                                            $isValidPassport = Database::search("SELECT * FROM `students` WHERE `passport` = '" . $passport . "'");
                                            if ($isValidPassport->num_rows > 0) {
                                                $lineErrors[] =  "Data Row " . $rowNumber . ": Passport number already exists";
                                            }
                                        }
                                    }

                                    if (!empty($dob)) {
                                        $result = validateDateFlexible($dob, $rowNumber);

                                        if (is_string($result)) {
                                            $lineErrors[] = $result;
                                        }
                                    }
                                    if (empty($email)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter student email";
                                    } else if (strlen($email) > 100) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Email should be less than 100 characters";
                                    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter a valid email";
                                    } else if (in_array($email, $emailAddresses)) {
                                        $duplicateIndex = array_search($email, $emailAddresses);
                                        $lineErrors[] = "Data Row " . $rowNumber . ": Email already has been used in this CSV at line " . ($duplicateIndex + 1) . " student";
                                    } else {
                                        $emailHasStudent = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE `email` = '" . $email . "'");
                                        $row = $emailHasStudent->fetch_assoc();
                                        if ($row['count'] > 0) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Email already exists";
                                        }
                                    }

                                    $formattedMobile = normalizePhoneNumber($mobile);
                                    if (empty($mobile)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter student mobile";
                                    } else if (!$formattedMobile) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter a valid mobile number";
                                    } else if (in_array($formattedMobile, $mobileNumbers)) {
                                        $duplicateIndex = array_search($formattedMobile, $mobileNumbers);
                                        $lineErrors[] = "Data Row " . $rowNumber . ": Mobile number already has been used in this CSV at line " . ($duplicateIndex + 1) . " student";
                                    } else {
                                        $mobileHasStudent = Database::search("SELECT COUNT(*) as 'count' FROM `students` WHERE `mobile` = '" . $formattedMobile . "'");
                                        $row = $mobileHasStudent->fetch_assoc();
                                        if ($row['count'] > 0) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Mobile number already exists";
                                        }
                                    }

                                    $formattedWhatsapp = normalizePhoneNumber($whatsapp);
                                    if (empty($whatsapp)) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter student whatsapp number";
                                    } else if (!$formattedWhatsapp) {
                                        $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter a valid whatsapp number";
                                    } else if (in_array($formattedWhatsapp, $whatsappNumbers)) {
                                        $duplicateIndex = array_search($formattedWhatsapp, $whatsappNumbers);
                                        $lineErrors[] = "Data Row " . $rowNumber . ": Whatsapp number already has been used in this CSV at line " . ($duplicateIndex + 1) . " student";
                                    } else {
                                        $whatsappHasStudent = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE `whatsapp` = '" . $formattedWhatsapp . "'");
                                        $row = $whatsappHasStudent->fetch_assoc();
                                        if ($row['count'] > 0) {
                                            $lineErrors[] =  "Data Row " . $rowNumber . ": Whatsapp number already exists";
                                        }
                                    }

                                    $rowNumber++;
                                    if (count($lineErrors) > 0) {
                                        $linesErrors[] = $lineErrors;
                                    }

                                    $nicNumbers[] = $nic;
                                    $emailAddresses[] = $email;
                                    $mobileNumbers[] = $formattedMobile;
                                    $whatsappNumbers[] = $formattedWhatsapp;
                                }
                                if (!empty($linesErrors)) {
                                    $status->dataErrors = $linesErrors;
                                } else {

                                    foreach ($csvData as $data) {
                                        $fname = $data[0];
                                        $lname = $data[1];
                                        $fullname = $data[2];
                                        $nwi = $data[3];
                                        $title = $data[4];
                                        $gender = $data[5];
                                        $marital_status = $data[6];
                                        $nic = $data[7];
                                        $passport = $data[8];
                                        $dob = $data[9];
                                        $email = $data[10];
                                        $mobile = $data[11];
                                        $whatsapp = $data[12];

                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $registerTime = $dateTime->format('Y-m-d H:i:s');

                                        $timestamp = strtotime($dob);
                                        $dob = date("Y-m-d", $timestamp);

                                        $hashPassword = password_hash($mobile, PASSWORD_BCRYPT);

                                        $formattedMobile = normalizePhoneNumber($mobile);
                                        $formattedWhatsapp = normalizePhoneNumber($whatsapp);

                                        $login_details_columns = " `first_name`, `last_name`, `email`, `whatsapp`, `password`, `date_registered`,  `status_status_id`";
                                        $login_details_values = "'" . $fname . "','" . $lname . "','" . $email . "','" . $formattedWhatsapp . "','" . $hashPassword . "','" . $registerTime . "','1'";

                                        Database::iud("INSERT INTO `login_details` (" . $login_details_columns . ") VALUES (" . $login_details_values . ")");
                                        $login_details_id = Database::$connection->insert_id;

                                        $student_table_insert_columns = "`full_name`,`name_with_initials`,`title_title_id`,`mobile`,`date_added`,`gender_gender_id`,`users_user_id`,`login_details_login_details_id`";
                                        $student_table_insert_values = "'" . $fullname . "','" . $nwi . "','" . $title . "','" . $formattedMobile . "','" . $registerTime . "','" . $gender . "','" . $sessionUserObject['user_id'] . "','" . $login_details_id . "'";

                                        $student_table_insert_columns .= ", `nationality`, `country_of_birth`, `country_of_residence`";
                                        $student_table_insert_values .= ",'69','69','69'";

                                        if ($marital_status != 0) {
                                            $student_table_insert_columns .= ",`marital_status_marital_status_id`";
                                            $student_table_insert_values .= ",'" . $marital_status . "'";
                                        }

                                        if (!empty($dob)) {
                                            $student_table_insert_columns .= ",`date_of_birth`";
                                            $student_table_insert_values .= ",'" . $dob . "'";
                                        }

                                        if (!empty($nic)) {
                                            $student_table_insert_columns .= ",`nic`";
                                            $student_table_insert_values .= ",'" . $nic . "'";
                                        }

                                        if (!empty($passport)) {
                                            $student_table_insert_columns .= ",`passport`";
                                            $student_table_insert_values .= ",'" . $passport . "'";
                                        }

                                        try {
                                            Database::iud("INSERT INTO `students` (" . $student_table_insert_columns . ") VALUES (" . $student_table_insert_values . ")");
                                        } catch (Exception $e) {
                                            $status->error = $e->getMessage();
                                        }
                                    }

                                    $status->done = "All students added successfully";
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

function validateDateFlexible($dateString, $rowNumber)
{
    $timezone = new DateTimeZone("Asia/Colombo");

    $formats = [
        'Y/m/d',   // 2025/11/02
        'Y/m/j',   // 2025/11/2
        'd/m/Y',   // 02/11/2025
        'm/d/Y',   // 11/02/2025
        'j/m/Y',   // 2/11/2025 or 24/10/2025
    ];

    foreach ($formats as $format) {

        $date = DateTime::createFromFormat($format, $dateString);
        $errors = DateTime::getLastErrors();

        if ($date && empty($errors['warning_count']) && empty($errors['error_count'])) {
            return new DateTime($date->format('Y-m-d'), $timezone);
        }
    }

    return "Data Row $rowNumber: Invalid date of birth format";
}
