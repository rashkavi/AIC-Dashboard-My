<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $news_id = isset($decoded["news_id"]) ? $decoded["news_id"] : 0;
                    if (empty($news_id) || $news_id == 0 || !is_numeric($news_id)) {
                        $status->error = 'Invalid News ID';
                    } else {
                        $isValidNews = Database::search("SELECT * FROM `news` WHERE `news_id`='" . $news_id . "'");
                        if ($isValidNews->num_rows == 0) {
                            $status->error = 'Invalid News ID';
                        } else {
                            Database::iud("DELETE FROM `news_imgs` WHERE `news_news_id`='" . $news_id . "'");
                            Database::iud("DELETE FROM `news` WHERE `news_id`='" . $news_id . "'");

                            $status->done = 'News Removed Successfully';
                        }
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
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
