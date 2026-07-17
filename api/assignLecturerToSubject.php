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
                    $subject_id = isset($decoded['subject']) ? $decoded['subject'] : 0;
                    $lecturer_id = isset($decoded['lecturer']) ? $decoded['lecturer'] : 0;
                    $hourly_rate = isset($decoded['hourlyRate']) ? $decoded['hourlyRate'] : 0;
                    $assignment_fee = isset($decoded['assignmentFee']) ? $decoded['assignmentFee'] : 0;
                    $examination_fee = isset($decoded['examinationFee']) ? $decoded['examinationFee'] : 0;

                    if ($subject_id == 0) {
                        $status->error = "Please select subject";
                    } else if (!is_numeric($subject_id)) {
                        $status->error = "Invalid subject Id";
                    } else {
                        $isValidSubject = Database::search("SELECT COUNT(*) as 'count' FROM `subjects` WHERE `subject_id`='" . $subject_id . "'");
                        $row = $isValidSubject->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->error = "Invalid subject Id";
                        } else {
                            if ($lecturer_id == 0) {
                                $status->lecturer = "Please select lecturer";
                            } else if (!is_numeric($lecturer_id)) {
                                $status->lecturer = "Invalid lecturer Id";
                            }

                            if ($subject_id != 0 && $lecturer_id != 0) {
                                $isValidLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `lecturers` WHERE `subjects_subject_id`='" . $subject_id . "' AND `users_user_id`='" . $lecturer_id . "'");
                                $row = $isValidLecturer->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $status->lecturer = "This lecturer already assigned to this subject";
                                }
                            }

                            if (empty($hourly_rate)) {
                                $status->hourly_rate = "Please enter hourly rate";
                            } else if (!is_numeric($hourly_rate)) {
                                $status->hourly_rate = "Invalid hourly rate";
                            }
                          

                            $requiredProperties = [
                                'level',
                                'subject',
                                'lecturer',
                                'error',
                                'hourly_rate',
                                'assignment_fee',
                                'examination_fee'
                            ];
                            $allFieldsPresent = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allFieldsPresent = false;
                                    break;
                                }
                            }

                            if ($allFieldsPresent) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $addTime = $dateTime->format('Y-m-d H:i:s');

                                $users_has_intakes_table_columns = "`users_user_id`,`subjects_subject_id`,`added_at`,`added_by`,`status_status_id`";
                                $users_has_intakes_table_values = "'" . $lecturer_id . "','" . $subject_id . "','" . $addTime . "','" . $sessionUserObject["user_id"] . "','1'";

                                if (empty($hourly_rate)) {
                                    $users_has_intakes_table_columns .= ",`hourly_rate`";
                                    $users_has_intakes_table_values .= ",'0'";
                                } else {
                                    $users_has_intakes_table_columns .= ",`hourly_rate`";
                                    $users_has_intakes_table_values .= ",'" . $hourly_rate . "'";
                                }

                                if (empty($assignment_fee)) {
                                    $users_has_intakes_table_columns .= ",`per_assignment_fee`";
                                    $users_has_intakes_table_values .= ",'0'";
                                } else {
                                    $users_has_intakes_table_columns .= ",`per_assignment_fee`";
                                    $users_has_intakes_table_values .= ",'" . $assignment_fee . "'";
                                }

                                if (empty($examination_fee)) {
                                    $users_has_intakes_table_columns .= ",`per_examination_fee`";
                                    $users_has_intakes_table_values .= ",'0'";
                                } else {
                                    $users_has_intakes_table_columns .= ",`per_examination_fee`";
                                    $users_has_intakes_table_values .= ",'" . $examination_fee . "'";
                                }

                                Database::iud("INSERT INTO `lecturers` (" . $users_has_intakes_table_columns . ") VALUES (" . $users_has_intakes_table_values . ")");

                                $status->done =  "The subject has been successfully assigned to the lecturer";
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
