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
                    $question = isset($decoded['question']) ? $decoded['question'] : '';
                    $answer = isset($decoded['answer']) ? $decoded['answer'] : '';
                    $videoLink = isset($decoded['video_link']) ? $decoded['video_link'] : '';


                    if (empty($faq)) {
                        $status->error = 'Invalid FAQ ID';
                    } else {
                        $isValidFaq = Database::search("SELECT COUNT(*) as 'count' FROM `faq` WHERE `faq_id` = '" . $faq . "'");
                        $isValidFaqData = $isValidFaq->fetch_assoc();
                        if ($isValidFaqData['count'] == 0) {
                            $status->error = 'Invalid FAQ ID';
                        } else {
                            if (empty($question)) {
                                $status->question = 'Question is required';
                            } else {
                                $isValidQuestion = Database::search("SELECT COUNT(*) as 'count' FROM `faq` WHERE `faq_id` != '" . $faq . "' AND `question` LIKE  '%" . $question . "%'");
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
                                $update_data = "`question`='" . $question . "',`answer`='" . $answer . "'";

                                if (!empty($videoLink)) {
                                    $update_data .= ",`video_link`='" . $videoLink . "'";
                                }

                                Database::iud("UPDATE `faq` SET " . $update_data . " WHERE `faq_id` = '" . $faq . "'");

                                $status->done = "FAQ has been updated successfully";
                            }
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
