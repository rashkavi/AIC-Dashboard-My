<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $inquiryId = isset($decoded['inquiryId']) ? $decoded['inquiryId'] : 0;
                    $inquiryStatus = isset($decoded['inquiryStatus']) ? $decoded['inquiryStatus'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if ($inquiryId == 0) {
                        $status->error = "Invalid inquiry id";
                    } else {
                        $isValidInquiryId = Database::search("SELECT * FROM `students_has_help_desk` WHERE `students_has_help_desk_id` = '" .  $inquiryId . "'");
                        if ($isValidInquiryId->num_rows == 0) {
                            $status->error = "Invalid inquiry id";
                        } else {
                            $courseInquiryData = $isValidInquiryId->fetch_assoc();
                            $isValidInquiryStatus = Database::search("SELECT * FROM `help_desk_status` WHERE `help_desk_status_id` = '" . $inquiryStatus . "'");
                            if ($isValidInquiryStatus->num_rows == 0) {
                                $status->status = "Invalid inquiry status";
                            } else {
                                $inquiryCurrentStatus = Database::search("SELECT `help_desk_status_help_desk_status_id` FROM `students_has_help_desk` WHERE `students_has_help_desk_id` = '" . $inquiryId . "'");
                                $row = $inquiryCurrentStatus->fetch_assoc();
                                if ($row["help_desk_status_help_desk_status_id"] == $inquiryStatus) {
                                    $status->status = "This inquiry status already updated";
                                }
                            }

                            if (empty($note)) {
                                $status->note = "Note is required";
                            } else if (strlen($note) > 100) {
                                $status->note = "Note is too long";
                            }

                            if (!property_exists($status, "status") && !property_exists($status, "note")) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');


                                Database::iud("UPDATE `students_has_help_desk` SET `help_desk_status_help_desk_status_id` = '" . $inquiryStatus . "' WHERE `students_has_help_desk_id` = '" . $inquiryId . "'");

                                Database::iud("INSERT INTO `help_desk_logs` (`students_has_help_desk_students_has_help_desk_id`,`users_user_id`,`added_at`,`log`) VALUES ('" . $inquiryId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . $note . "')");
                            
                                $status->done = "Inquiry status updated successfully";
                            
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
