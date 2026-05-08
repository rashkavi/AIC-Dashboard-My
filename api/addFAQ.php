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
                    $question = isset($decoded['question']) ? $decoded['question'] : '';
                    $answer = isset($decoded['answer']) ? $decoded['answer'] : '';
                    $videoLink = isset($decoded['video_link']) ? $decoded['video_link'] : '';

                    if (empty($question)) {
                        $status->question = 'Question is required';
                    } else {
                        $isValidQuestion = Database::search("SELECT COUNT(*) as 'count' FROM `faq` WHERE `question` = '" . $question . "'");
                        $isValidQuestionData = $isValidQuestion->fetch_assoc();
                        if ($isValidQuestionData['count'] > 0) {
                            $status->question = 'Question already exist';
                        }
                    }

                    if (empty($answer)) {
                        $status->answer = 'Answer is required';
                    }

                    if (!empty($videoLink)) {
                        if (!preg_match('/<iframe.*src="https:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]+.*".*<\/iframe>/i', $videoLink)) {
                            $status->link = "Link must be a valid YouTube iframe embed code";
                        }
                    }

                    $requiredProperties = [
                        'question',
                        'answer',
                        'link'
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
                        $addTime = $dateTime->format('Y-m-d H:i:s');

                        $faq_table_insert_columns = "`question`, `answer`, `date_added`,`users_user_id`,`faq_order`";
                        $faq_table_insert_values = "'" . $question . "','" . $answer . "','" . $addTime . "','" . $sessionUserObject['user_id'] . "','0'";

                        if (!empty($videoLink)) {
                            $faq_table_insert_columns .= ",`video_link`";
                            $faq_table_insert_values .= ",'" . $videoLink . "'";
                        }

                        Database::iud("INSERT INTO `faq` (" . $faq_table_insert_columns . ") VALUES (" . $faq_table_insert_values . ")");
                        $status->done = 'FAQ added successfully';
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
