<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $plan = isset($_GET['id']) ? trim($_GET['id']) : 0;
        if ($plan == 0) {
            $status->plan = "Please select plan";
        }

        if (!property_exists($status, 'plan')) {

            $query = "SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='" . $plan . "' OR `payment_plan_payment_plan_id` IS NULL";

            $result = Database::search($query);
            $types = [];
            while ($row = $result->fetch_assoc()) {
                $types[] = $row;
            }

            $status->types = $types;
            $status->done = 'Success';
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
