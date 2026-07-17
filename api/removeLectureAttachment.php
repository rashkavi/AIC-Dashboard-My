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
                    $attachmentId = isset($decoded["id"]) ? $decoded["id"] : 0;

                    if (empty($attachmentId) || $attachmentId == 0) {
                        $status->error = 'Invalid attachment id';
                    } else {

                        $attachment = Database::search("SELECT * FROM `attachments` INNER JOIN `time_table` ON `time_table`.`time_table_id`=`attachments`.`time_table_time_table_id` WHERE `attachment_id`='" . $attachmentId . "'");
                        if ($attachment->num_rows == 0) {
                            $status->error = 'Invalid attachment id';
                        } else {
                            $attachmentData = $attachment->fetch_assoc();
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('lecture_schedule', 11, $attachmentData['subjects_subject_id']) || UserPermissions::hasPermission('super_lecture_schedule', 11))) {
                                $status->error = 'You are not authorized to remove lecture attachment';
                            } else {
                                Database::iud("DELETE FROM `attachments` WHERE `attachment_id`='" . $attachmentId . "'");
                                if (file_exists("../assets/uploads/lectures-attachments/" . $attachmentData["attachment"])) {
                                    $attachment_path = "../assets/uploads/lectures-attachments/" . $attachmentData["attachment"];
                                    if (file_exists($attachment_path)) {
                                        unlink($attachment_path);
                                    }
                                }

                                $status->done = 'Lecture attachment removed successfully';
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
