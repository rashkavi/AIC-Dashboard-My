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
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $faq = isset($decoded['faq']) ? $decoded['faq'] : '';

                    if (empty($faq)) {
                        $status->error = 'Invalid FAQ ID';
                    } else {
                        $isValidFaq = Database::search("SELECT COUNT(*) as 'count' FROM `faq` WHERE `faq_id` = '" . $faq . "'");
                        $isValidFaqData = $isValidFaq->fetch_assoc();
                        if ($isValidFaqData['count'] > 0) {
                            Database::iud("DELETE FROM `faq` WHERE `faq_id` = '" . $faq . "'");
                            $status->done = 'FAQ removed successfully';
                        } else {
                            $status->error = 'Invalid FAQ ID';
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
