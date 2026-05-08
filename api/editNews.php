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
            $id = isset($_POST['id']) ? trim($_POST['id']) : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $image;

            if (empty($id) || $id == 0) {
                $status->error = "Something went wrong";
            } else {
                $isValidId = Database::search("SELECT * FROM `news` WHERE `news_id`='" . $id . "'");
                if ($isValidId->num_rows == 0) {
                    $status->error = "Something went wrong";
                } else {

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

                        Database::iud("UPDATE `news` SET `desc`='" . $content . "' WHERE `news_id` = '" . $id . "'");

                        if (isset($_FILES["image"])) {

                            $newsImg = Database::search("SELECT * FROM `news_imgs` WHERE `news_news_id`='" . $id . "'");
                            if ($newsImg->num_rows > 0) {
                                $newsImgData = $newsImg->fetch_assoc();
                                unlink("../assets/imgs/news-imges/" . $newsImgData['news_img']);
                            }

                            Database::iud("UPDATE `news_imgs` SET `news_img`='" . $image . "' WHERE `news_news_id` = '" . $id . "'");
                            move_uploaded_file($_FILES["image"]["tmp_name"], "../assets/imgs/news-imges/" . $image);
                        }

                        $status->done = "News updated successfully";
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
