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
                    $recording = isset($decoded["recording"]) ? $decoded["recording"] : 0;

                    if (empty($recording) || $recording == 0) {
                        $status->error = 'Invalid recording id';
                    } else {

                        $recording = Database::search("SELECT * FROM `lecture_recording`  WHERE `lecture_recording_id`='" . $recording . "'");
                        if ($recording->num_rows == 0) {
                            $status->error = 'Invalid recording id';
                        } else {
                            $recordingData = $recording->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0) || UserPermissions::validPermisisonType('course_overview', 0))) {
                                $status->error = 'You are not authorized to this action.';
                            } else {
                                Database::iud("DELETE FROM `lecture_recording_has_lectures` WHERE `lecture_recording_lecture_recording_id`='" . $recordingData['lecture_recording_id'] . "'");
                                Database::iud("DELETE FROM `lecture_recording` WHERE `lecture_recording_id`='" . $recordingData['lecture_recording_id'] . "'");
                                $status->done = 'Lecture recording removed successfully';
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
