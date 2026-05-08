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

            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $image;

            if (empty($content)) {
                $status->content = "Content is required";
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
                        if ($_FILES["image"]["size"] <= 5 * 1024 * 1024) {
                            if (getimagesize($_FILES["image"]["tmp_name"])) {
                                $image = uniqid() . $new_file_extention;
                            } else {
                                $status->image = "Invalid image file!";
                            }
                        } else {
                            $status->image = "Maximum allowed file size is 5MB";
                        }
                    } else {
                        $status->image = "There is an error with the selected image";
                    }
                }
            } else {
                $status->image = "Image is required";
            }

            $requiredProperties = [
                'title',
                'content',
                'image'
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
                $addTime = $dateTime->format('Y-m-d H:i:s');

                $news_table_insert_columns = "`desc`,`status_status_id`,`likes`,`added_at`,`added_by`";
                $news_table_insert_values = "'" . $content . "','1','0','" . $addTime . "','" . $sessionUserObject['user_id'] . "'";

                Database::iud("INSERT INTO `news` (" . $news_table_insert_columns . ") VALUES (" . $news_table_insert_values . ")");
                $newsId = Database::$connection->insert_id;

                if (isset($_FILES["image"])) {

                    Database::iud("INSERT INTO `news_imgs`(`news_img`,`news_news_id`) VALUES('" . $image . "', '" . $newsId . "')");

                    move_uploaded_file($_FILES["image"]["tmp_name"], "../assets/imgs/news-imges/" . $image);
                }

                $status->done = "News Added Successfully";
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
