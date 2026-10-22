<?php
$status = new stdClass();
require_once '../includes/UserClass.php';
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $lecturer_id = isset($decoded['lecturerId']) ? $decoded['lecturerId'] : 0;
                    $hourly_rate = isset($decoded['hourlyRate']) ? $decoded['hourlyRate'] : 0;
                    $assignment_fee = isset($decoded['assignmentFee']) ? $decoded['assignmentFee'] : 0;
                    $examination_fee = isset($decoded['examinationFee']) ? $decoded['examinationFee'] : 0;

                    if ($lecturer_id == 0) {
                        $status->error = "Invalid lecturer Id";
                    } else if (!is_numeric($lecturer_id)) {
                        $status->error = "Invalid lecturer Id";
                    } else {
                        $isValidLecturer = Database::search("SELECT COUNT(*) FROM `lecturers` WHERE `lecturers_id`='" . $lecturer_id . "'");
                        $row = $isValidLecturer->fetch_assoc();
                        if ($row['COUNT(*)'] == 0) {
                            $status->error = "Invalid lecturer Id";
                        } else {

                            if (!is_numeric($hourly_rate)) {
                                $status->hourly_rate = "Invalid hourly rate";
                            }

                            $requiredProperties = [
                                'hourly_rate',
                                'assignment_fee',
                                'examination_fee'
                            ];
                            $allFieldsPresent = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allFieldsPresent = false;
                                    break; // Exit loop on the first missing property
                                }
                            }

                            if ($allFieldsPresent) {
                                $update_query = "";
                                if (empty($hourly_rate)) {
                                    $update_query .= "`hourly_rate`='0'";
                                } else {
                                    $update_query .= "`hourly_rate`='" . $hourly_rate . "'";
                                }
                                if (empty($assignment_fee)) {
                                    $update_query .= ",`per_assignment_fee`='0'";
                                } else {
                                    $update_query .= ",`per_assignment_fee`='" . $assignment_fee . "'";
                                }

                                if (empty($examination_fee)) {
                                    $update_query .= ",`per_examination_fee`='0'";
                                } else {
                                    $update_query .= ",`per_examination_fee`='" . $examination_fee . "'";
                                }
                                Database::iud("UPDATE `lecturers` SET " . $update_query . " WHERE `lecturers_id`='" . $lecturer_id . "'");

                                $status->done =  "Lecturer updated successfully";
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
