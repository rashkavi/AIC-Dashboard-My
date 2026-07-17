<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

// TODO: confirm this against your `earning_status` table — this assumes 1 = Pending
// (matches the convention already used elsewhere, e.g. AdminDashboardSummary's
// pending_total_payments_amount query filters earning_status_earning_status_id = 1).
define('EARNING_STATUS_PENDING', 1);

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $lectureId = isset($decoded["id"]) ? $decoded["id"] : 0;

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0))) {
                        $status->error = 'You are not authorized to this action.';
                    } else {
                        if (empty($lectureId) || $lectureId == 0) {
                            $status->error = 'Invalid attachment id';
                        } else {

                            $lecture = Database::search("SELECT * FROM `time_table` WHERE `time_table_id`='" . $lectureId . "'");
                            if ($lecture->num_rows == 0) {
                                $status->error = 'Invalid attachment id';
                            } else {
                                $lectureData = $lecture->fetch_assoc();

                                $actingUserId = isset($sessionUserObject['user_id']) ? (int)$sessionUserObject['user_id'] : 0;

                                $attachment = Database::search("SELECT * FROM `attachments`  WHERE `time_table_time_table_id`='" . $lectureId . "'");
                                if ($attachment->num_rows > 0) {
                                    while ($attachmentData = $attachment->fetch_assoc()) {
                                        Database::iud("DELETE FROM `attachments` WHERE `attachment_id`='" . $attachmentData["attachment_id"] . "'");
                                        if (file_exists("../assets/uploads/lectures-attachments/" . $attachmentData["attachment"])) {
                                            $isUsedAnother = Database::search("SELECT * FROM `attachments` WHERE `attachment`='" . $attachmentData['attachment'] . "' AND `time_table_time_table_id` != '" . $lectureId . "'");
                                            if ($isUsedAnother->num_rows == 0) {
                                                $attachment_path = "../assets/uploads/lectures-attachments/" . $attachmentData["attachment"];
                                                if (file_exists($attachment_path)) {
                                                    unlink($attachment_path);
                                                }
                                            }
                                        }
                                    }
                                }

                                $lectureHasRecordings = Database::search("SELECT * FROM `lecture_recording` WHERE `time_table_time_table_id`='" . $lectureId . "'");
                                if ($lectureHasRecordings->num_rows > 0) {
                                    while ($lectureHasRecordingData = $lectureHasRecordings->fetch_assoc()) {
                                        Database::iud("DELETE FROM `lecture_recording` WHERE `recording_id`='" . $lectureHasRecordingData["recording_id"] . "'");
                                        if (file_exists("../assets/imgs/recording-thumbnails/" . $lectureHasRecordingData["recording_thumbnail"])) {
                                            $isUsedAnother = Database::search("SELECT * FROM `lecture_recording` WHERE `recording_thumbnail`='" . $lectureHasRecordingData['recording_thumbnail'] . "' AND `time_table_time_table_id` != '" . $lectureId . "'");
                                            if ($isUsedAnother->num_rows == 0) {
                                                $recording_thumbnail_path = "../assets/imgs/recording-thumbnails/" . $lectureHasRecordingData["recording_thumbnail"];
                                                if (file_exists($recording_thumbnail_path)) {
                                                    unlink($recording_thumbnail_path);
                                                }
                                            }
                                        }
                                    }
                                }

                                Database::iud("DELETE FROM `attendance` WHERE `time_table_time_table_id`='" . $lectureId . "'");
                                Database::iud("DELETE FROM `time_table` WHERE `time_table_id`='" . $lectureId . "'");

                                // If $hasConcurrentBatches is true, lecturer_payments is
                                // deliberately left untouched — it still covers the remaining
                                // concurrent batches in that slot.

                                $status->done = 'Lecture removed successfully';
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
