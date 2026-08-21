<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('students', 0))) {
            $status->error = "You are not authorized to this action.";
        } else {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                    $content = trim(file_get_contents('php://input'));
                    $decoded = json_decode($content, true);
                    if (json_last_error() == JSON_ERROR_NONE) {

                        $id = isset($decoded['id']) ? $decoded['id'] : 0;
                        $siid = isset($decoded['siid']) ? $decoded['siid'] : 0;
                        $note = isset($decoded['note']) ? $decoded['note'] : '';

                        if (empty($id)) {
                            $status->error = 'Invalid student id';
                        } else {
                            $isValidId = Database::search("SELECT `students_has_intakes_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`whatsapp`,`email`,`programmes`.`programme`,`intakes`.`intake` FROM `students_has_intakes`
                                INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id`=`intakes`.`intake_id` 
                                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                INNER JOIN `students` ON `students_has_intakes`.`students_student_id`=`students`.`student_id`
                                INNER JOIN `login_details` ON `login_details`.`login_details_id`=`students`.`login_details_login_details_id`                            
                                WHERE `students_has_intakes_id`='" . $id . "'");

                            if ($isValidId->num_rows == 0) {
                                $status->error = 'Invalid student id';
                            } else {
                                $studentIntakeData = $isValidId->fetch_assoc();

                                if (empty($siid)) {
                                    $status->siid = "Please enter Registration Number";
                                } elseif (strlen($siid) > 45) {
                                    $status->siid = "Registration Number should be less than 45 characters";
                                } else {
                                    $isValidId = Database::search("SELECT * FROM `students_has_intakes` WHERE `student_id`='" . $siid . "' AND `students_has_intakes_id` != '" . $id . "'");
                                    if ($isValidId->num_rows > 0) {
                                        $status->siid = "Registration Number already exists";
                                    } else {
                                        Database::iud("UPDATE `students_has_intakes` SET `student_id` ='" . Database::escape($siid) . "' WHERE `students_has_intakes_id`='" . $id . "'");

                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $addTime = $dateTime->format('Y-m-d H:i:s');


                                        $log = "Registration Number changed " . Database::escape($studentIntakeData['student_id']) . " → " . Database::escape($siid);

                                        $columns = "`students_has_intakes_students_has_intakes_id`,`users_user_id`,`date_updated`,`log`";
                                        $values = "'" . $id . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "','" . $log . "'";

                                        if (!empty($note)) {
                                            $columns .= ",`note`";
                                            $values .= ",'" . Database::escape($note) . "'";
                                        }

                                        Database::iud("INSERT INTO `students_has_intakes_logs`(" . $columns . ") VALUES(" . $values . ")");

                                        $status->done = "Registration Number updated successfully";
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
            } else {
                http_response_code(400);
                $status->error = 'Invalid request method';
            }
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
