<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('course_overview', 0))) {
                $status->error = "You are not authorized to this action.";
            } else {
                if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                    $content = trim(file_get_contents('php://input'));
                    $decoded = json_decode($content, true);
                    if (json_last_error() == JSON_ERROR_NONE) {

                        $studentId = isset($decoded['studentId']) ? $decoded['studentId'] : 0;
                        $intakeId = isset($decoded['intakeId']) ? $decoded['intakeId'] : 0;
                        $intakeStudentId = isset($decoded['intakeStudentId']) ? $decoded['intakeStudentId'] : 0;
                        $user = isset($decoded['user']) ? $decoded['user'] : 0;

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $addTime = $dateTime->format('Y-m-d H:i:s');
                        $addDate = $dateTime->format('Y-m-d');
                        $date = $dateTime->format('Y-m-d H:i:s');

                        if (empty($intakeId)) {
                            $status->error = 'Invalid intake id';
                        } else {
                            $sValidIntake = Database::search("SELECT * FROM `intakes` 
                            INNER JOIN `programmes` ON  `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
                            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id`=`faculties`.`faculty_id`
                        WHERE `intake_id`='" . $intakeId . "'");

                            if ($sValidIntake->num_rows == 0) {
                                $status->error = 'Invalid intake id or intake is closed';
                            } else {
                                $intakeData = $sValidIntake->fetch_assoc();

                                if ($studentId == 0) {
                                    $status->error = 'Invalid student id';
                                } else {
                                    require_once '../includes/connection.php';
                                    $isValidStudent = Database::search("SELECT * FROM `students` 
                                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                    WHERE `student_id`='" . $studentId . "' AND `student_id` NOT IN (SELECT `students_student_id` FROM `students_has_intakes` WHERE `intakes_intake_id`='" . $intakeId . "')");

                                    if ($isValidStudent->num_rows == 0) {
                                        $status->error = 'Invalid student id';
                                    } else {
                                        $studentData = $isValidStudent->fetch_assoc();

                                        if (!empty($intakeStudentId)) {
                                            $isValidIntakeStudentId = Database::search("SELECT COUNT(*) as 'count' FROM `students_has_intakes` WHERE `student_id`='" . $intakeStudentId . "'");
                                            $row = $isValidIntakeStudentId->fetch_assoc();
                                            if ($row['count'] > 0) {
                                                $status->sIId = 'Intake student id already exists';
                                            }
                                        }

                                        if (!empty($user)) {
                                            $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_id` = '" . $user . "' 
                                            AND `user_roles_user_role_id` IN (3,9,10) AND `status_status_id`='1'");

                                            $row = $isValidUser->fetch_assoc();
                                            if ($row['count'] == 0) {
                                                $status->user = 'User does not exist';
                                            }
                                        }

                                        if (empty(get_object_vars($status))) {

                                            $insert_columns = "`students_student_id`,`intakes_intake_id`,`addmission_date`,`status_status_id`,`users_user_id`";
                                            $insert_values = "'" . $studentId . "','" . $intakeId . "','" . $addTime . "','1','" . $sessionUserObject['user_id'] . "'";

                                            if (!empty($intakeStudentId)) {
                                                $insert_columns .= ",`student_id`";
                                                $insert_values .= ",'" . $intakeStudentId . "'";
                                            }

                                            if (!empty($user)) {
                                                $insert_columns .= ",`counselor_user_id`";
                                                $insert_values .= ",'" . $user . "'";
                                            }

                                            Database::iud("INSERT INTO `students_has_intakes` (" . $insert_columns . ") VALUES (" . $insert_values . ")");

                                            $studentIntakeId = Database::$connection->insert_id;

                                            $shil_columns = "`students_has_intakes_students_has_intakes_id`,`users_user_id`,`date_updated`,`log`";
                                            $shil_values = "'" . $studentIntakeId . "', '" . $sessionUserObject['user_id'] . "', '" . $addTime . "', 'Student assigned to intake'";

                                            Database::iud("INSERT INTO `students_has_intakes_logs`(" . $shil_columns . ") VALUES(" . $shil_values . ")");

                                            $status->done = 'Student added successfully';
                                        }
                                    }
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
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}

function normalizePhoneNumber(string $number, string $defaultCountryCode = '94')
{
    $number = preg_replace('/[^\d+]/', '', $number);

    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    if ($defaultCountryCode === '94' && preg_match('/^0[7][0-9]{8}$/', $number)) {
        return '+94' . substr($number, 1);
    }

    if (preg_match('/^[1-9][0-9]{7,14}$/', $number)) {
        return '+' . $number;
    }
    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    return false;
}
