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

            $bank = isset($_POST['bank']) ? $_POST['bank'] : '';
            $acc_no = isset($_POST['acc_no']) ? $_POST['acc_no'] : '';
            $acc_name = isset($_POST['acc_name']) ? $_POST['acc_name'] : '';
            $branch = isset($_POST['branch']) ? $_POST['branch'] : '';
            $bank_logo;

            if (empty($bank)) {
                $status->bank = "Bank name is required";
            }

            if (empty($acc_no) || !is_numeric($acc_no)) {
                $status->acc_no = "Account number is required";
            } else {
                $isValidAccNo = Database::search("SELECT * FROM `bank` WHERE `acc_no`='" . $acc_no . "'");
                if ($isValidAccNo->num_rows > 0) {
                    $status->acc_no = "Account number already exists";
                }
            }

            if (empty($acc_name)) {
                $status->acc_name = "Account name is required";
            }

            if (empty($branch)) {
                $status->branch = "Branch is required";
            }

            if (isset($_FILES["image"])) {
                $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                $file_ex = $_FILES["image"]["type"];
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
                    if ($_FILES["image"]["error"] === 0) {
                        if ($_FILES["image"]["size"] < 2 * 1024 * 1024) {
                            if (getimagesize($_FILES["image"]["tmp_name"])) {
                                $bank_logo = uniqid() . $new_file_extention;
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
                $status->cover = "Please select level cover";
            }

            $requiredProperties = [
                'bank',
                'acc_no',
                'acc_name',
                'branch',
                'cover'
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
                $addDateTime = $dateTime->format('Y-m-d H:i:s');

                $bank_insert_columns = "`bank_name`, `acc_no`, `acc_name`, `branch`,`logo`,`users_user_id`,`date_added`";
                $bank_insert_values = "'" . $bank . "', '" . $acc_no . "', '" . $acc_name . "', '" . $branch . "', '" . $bank_logo . "', '" . $sessionUserObject['user_id'] . "', '" . $addDateTime . "'";

                Database::iud("INSERT INTO `bank` (" . $bank_insert_columns . ") VALUES (" . $bank_insert_values . ")");

                if (isset($_FILES["image"])) {
                    $qimg_path = "../assets/imgs/banks-logo/" . $bank_logo;
                    move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
                }
                $status->done =  "The bank has been successfully added";
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
