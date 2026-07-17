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

            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {

                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);

                if (json_last_error() == JSON_ERROR_NONE) {

                    $exchangeRateId = isset($decoded['exchangeRateId']) ? $decoded['exchangeRateId'] : '';
                    $rate = isset($decoded['rate']) ? $decoded['rate'] : '';
                    $effectiveDate = isset($decoded['effectiveDate']) ? $decoded['effectiveDate'] : '';
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if (empty($exchangeRateId)) {
                        $status->error = 'Invalid exchange rate.';
                    }

                    if (empty($rate)) {
                        $status->rate = 'Exchange rate is required.';
                    } else if (!is_numeric($rate)) {
                        $status->rate = 'Exchange rate must be a valid number.';
                    } else if ($rate <= 0) {
                        $status->rate = 'Exchange rate must be greater than zero.';
                    }

                    if (empty($effectiveDate)) {
                        $status->effectiveDate = 'Effective date is required.';
                    } else {
                        $dateCheck = DateTime::createFromFormat('Y-m-d', $effectiveDate);

                        if (!$dateCheck || $dateCheck->format('Y-m-d') != $effectiveDate) {
                            $status->effectiveDate = 'Invalid effective date.';
                        }
                    }

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1))) {
                        $status->error = 'You do not have permission to update exchange rates.';
                    }

                    $requiredProperties = [
                        'rate',
                        'effectiveDate',
                        'error'
                    ];

                    $allPropertiesExist = true;

                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allPropertiesExist = false;
                            break;
                        }
                    }

                    if ($allPropertiesExist) {

                        $isExistingRate = Database::search("
                            SELECT 
                                `currency_exchange_rate_id`,
                                `base_currency_id`,
                                `target_currency_id`,
                                `effective_date`,
                                `rate`
                            FROM `currency_exchange_rates`
                            WHERE `currency_exchange_rate_id` = '" . $exchangeRateId . "'
                            LIMIT 1
                        ");

                        if ($isExistingRate->num_rows == 0) {

                            $status->error = 'Exchange rate record not found.';
                        } else {

                            $existingData = $isExistingRate->fetch_assoc();

                            $currentMainDate = $existingData['effective_date'];
                            $oldRate = $existingData['rate'];
                            $baseCurrencyId = $existingData['base_currency_id'];
                            $targetCurrencyId = $existingData['target_currency_id'];

                            $note = addslashes($note);

                            if ($effectiveDate < $currentMainDate) {

                                $duplicatePastLog = Database::search("
                                    SELECT `currency_exchange_rate_log_id`
                                    FROM `currency_exchange_rate_logs`
                                    WHERE `currency_exchange_rates_currency_exchange_rate_id` = '" . $exchangeRateId . "'
                                    AND `effective_date` = '" . $effectiveDate . "'
                                    AND `new_rate` = '" . $rate . "'
                                    LIMIT 1
                                ");

                                if ($duplicatePastLog->num_rows > 0) {
                                    $status->effectiveDate = 'Exchange rate history already exists for this past effective date.';
                                } else {

                                    Database::iud("
                                        INSERT INTO `currency_exchange_rate_logs`
                                        (
                                            `currency_exchange_rates_currency_exchange_rate_id`,
                                            `effective_date`,
                                            `old_rate`,
                                            `new_rate`,
                                            `action_type`,
                                            `note`,
                                            `updated_by`
                                        )
                                        VALUES
                                        (
                                            '" . $exchangeRateId . "',
                                            '" . $effectiveDate . "',
                                            NULL,
                                            '" . $rate . "',
                                            'UPDATE',
                                            '" . $note . "',
                                            '" . $sessionUserObject['user_id'] . "'
                                        )
                                    ");

                                    $status->done = 'Past exchange rate history added successfully. Latest exchange rate was not changed.';
                                }
                            } else {

                                $duplicateMainRate = Database::search("
                                    SELECT `currency_exchange_rate_id`
                                    FROM `currency_exchange_rates`
                                    WHERE `base_currency_id` = '" . $baseCurrencyId . "'
                                    AND `target_currency_id` = '" . $targetCurrencyId . "'
                                    AND `effective_date` = '" . $effectiveDate . "'
                                    AND `currency_exchange_rate_id` != '" . $exchangeRateId . "'
                                    AND `rate` = '" . $rate . "'
                                    LIMIT 1
                                ");

                                if ($duplicateMainRate->num_rows > 0) {

                                    $status->effectiveDate = 'Exchange rate already exists for this effective date.';
                                } else {

                                    Database::iud("
                                        UPDATE `currency_exchange_rates` SET
                                            `effective_date` = '" . $effectiveDate . "',
                                            `rate` = '" . $rate . "',
                                            `note` = '" . $note . "',
                                            `updated_by` = '" . $sessionUserObject['user_id'] . "'
                                        WHERE `currency_exchange_rate_id` = '" . $exchangeRateId . "'
                                    ");

                                    Database::iud("
                                        INSERT INTO `currency_exchange_rate_logs`
                                        (
                                            `currency_exchange_rates_currency_exchange_rate_id`,
                                            `effective_date`,
                                            `old_rate`,
                                            `new_rate`,
                                            `action_type`,
                                            `note`,
                                            `updated_by`
                                        )
                                        VALUES
                                        (
                                            '" . $exchangeRateId . "',
                                            '" . $effectiveDate . "',
                                            '" . $oldRate . "',
                                            '" . $rate . "',
                                            'UPDATE',
                                            '" . $note . "',
                                            '" . $sessionUserObject['user_id'] . "'
                                        )
                                    ");

                                    $status->done = 'Exchange rate updated successfully.';
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
