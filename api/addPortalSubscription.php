<?php
$status = new stdClass();
require_once '../includes/UserClass.php';
require_once '../includes/connection.php';
require_once '../includes/connection.php';
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole == 'Developer') {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $studentCount = isset($decoded["studentCount"]) ? $decoded["studentCount"] : 0;
                    $amount = isset($decoded["amount"]) ? $decoded["amount"] : 0;
                    $note = isset($decoded["note"]) ? $decoded["note"] : "";

                    if (empty($studentCount) || $studentCount == 0 || !is_numeric($studentCount)) {
                        $status->student = "Please enter a valid student count";
                    }

                    if (empty($amount) || $amount == 0) {
                        $status->amount = "Please enter a valid amount";
                    }

                    if (empty($note)) {
                        $status->note = "Please enter a note";
                    }

                    $requiredProperties = [
                        'student',
                        'amount',
                        'note'
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

                        $subscription_insert_columns = "`students_count`,`total_amount`,`note`,`date_paid`,`approved_user`";
                        $subscription_insert_values = "'" . $studentCount . "','" . $amount . "','" . $note . "','" . $addTime . "','" . $sessionUserObject["user_id"] . "'";

                        Database::iud("INSERT INTO `portal_subscription_payments`(" . $subscription_insert_columns . ") VALUES(" . $subscription_insert_values . ")");

                        $status->done = "Portal subscription added successfully";
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
