<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole   = SessionUser::getCurrentUserRole();

    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $input = json_decode(file_get_contents("php://input"), true);

            $taxRateId   = isset($input['taxRateId'])   ? (int)trim($input['taxRateId'])   : 0;
            $taxRateName = isset($input['taxRateName'])  ? trim($input['taxRateName'])       : '';
            $taxRate     = isset($input['taxRate'])      ? trim($input['taxRate'])           : '';
            $note        = isset($input['note'])         ? trim($input['note'])              : '';

            // ── Validate tax rate record exists ──
            if ($taxRateId <= 0) {
                $status->error = "Invalid tax rate ID.";
            } else {
                $existing = Database::search("SELECT * FROM `tax_rates` WHERE `tax_rate_id` = '$taxRateId'");
                if ($existing->num_rows == 0) {
                    $status->error = "Tax rate not found.";
                } else {

                    // ── Permission check ──
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 1))) {
                        $status->error = "You don't have permission to update tax rates.";
                    } else {

                        // ── Field validation ──
                        if (empty($taxRateName)) {
                            $status->taxRateName = "Please enter a tax rate name.";
                        } elseif (strlen($taxRateName) > 50) {
                            $status->taxRateName = "Tax rate name must be less than 50 characters.";
                        }

                        if ($taxRate === '') {
                            $status->taxRate = "Please enter a tax rate.";
                        } elseif (!is_numeric($taxRate) || (float)$taxRate < 0) {
                            $status->taxRate = "Please enter a valid tax rate (0 or above).";
                        } elseif ((float)$taxRate > 100) {
                            $status->taxRate = "Tax rate cannot exceed 100%.";
                        }

                        if (!property_exists($status, 'taxRateName') && !property_exists($status, 'taxRate')) {

                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date     = $dateTime->format('Y-m-d H:i:s');

                            $oldData = $existing->fetch_assoc();

                            // ── Save old value to logs ──
                            Database::iud("
                                INSERT INTO `tax_rates`
                                    ( `tax_rate_name`, `tax_rate`, `users_user_id`, `date_added`, `note`)
                                VALUES
                                    (
                                       
                                        '" . addslashes($oldData['tax_rate_name']) . "',
                                        '" . addslashes($oldData['tax_rate']) . "',
                                        '" . $sessionUserObject['user_id'] . "',
                                        '$date',
                                        '" . addslashes($note) . "'
                                    )
                            ");

                            $status->done = "Tax rate has been successfully updated.";
                        }
                    }
                }
            }
        } else {
            http_response_code(400);
            $status->error = "Invalid request method.";
        }
    } else {
        $status->error = "Authentication Failed!";
    }
} else {
    $status->error = "Authentication Failed!";
}

echo json_encode($status);
