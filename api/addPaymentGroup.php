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

                    $intake_id = isset($decoded['intake_id']) ? $decoded['intake_id'] : 0;
                    $name = isset($decoded['name']) ? $decoded['name'] : '';

                    if (empty($intake_id)) {
                        http_response_code(400);
                        $status->error = 'Intake id is required';
                    } else {
                        $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id`='" . $intake_id . "'");
                        if ($isValidIntake->num_rows == 0) {
                            http_response_code(400);
                            $status->error = 'Invalid intake id';
                        } else {
                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $intake_id))) {
                                $status->error = 'You are not authorized to add payment group';
                            } else {

                                if (empty($name)) {
                                    $status->name = "Type name is required";
                                } else {
                                    $isValidName = Database::search("SELECT * FROM `intakes_has_payment_groups` WHERE `group_name`='" . $name . "' AND `intakes_intake_id`='" . $intake_id . "'");
                                    if ($isValidName->num_rows > 0) {
                                        $status->name = "This type is already added";
                                    }
                                }

                                $requiredProperties = [
                                    'name',
                                ];
                                $allGood = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allGood = false;
                                        break;
                                    }
                                }

                                if ($allGood) {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $addTime = $dateTime->format('Y-m-d H:i:s');

                                    $columns = "`group_name`,`intakes_intake_id`,`added_by`,`added_at`";
                                    $values = "'" . $name . "','" . $intake_id . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "'";

                                    Database::iud("INSERT INTO `intakes_has_payment_groups` (" . $columns . ") VALUES(" . $values . ")");
                                    $status->done = "Payment group added successfully";
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
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo json_encode($status);
