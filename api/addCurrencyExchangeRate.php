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

                    $baseCurrencyId = isset($decoded['baseCurrencyId']) ? $decoded['baseCurrencyId'] : '';
                    $targetCurrencyId = isset($decoded['targetCurrencyId']) ? $decoded['targetCurrencyId'] : '';
                    $rate = isset($decoded['rate']) ? $decoded['rate'] : '';
                    $effectiveDate = isset($decoded['effectiveDate']) ? $decoded['effectiveDate'] : '';
                    $effectiveTime = isset($decoded['effectiveTime']) ? $decoded['effectiveTime'] : '';
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if (empty($baseCurrencyId)) {
                        $status->baseCurrency = 'Base currency is required.';
                    } else {
                        $isValidBaseCurrency = Database::search("
                            SELECT * FROM `currencies` 
                            WHERE `currency_id` = '" . $baseCurrencyId . "'
                            AND `is_active` = '1'
                        ");

                        if ($isValidBaseCurrency->num_rows == 0) {
                            $status->baseCurrency = 'Invalid base currency.';
                        }
                    }

                    if (empty($targetCurrencyId)) {
                        $status->targetCurrency = 'Target currency is required.';
                    } else {
                        $isValidTargetCurrency = Database::search("
                            SELECT * FROM `currencies` 
                            WHERE `currency_id` = '" . $targetCurrencyId . "'
                            AND `is_active` = '1'
                        ");

                        if ($isValidTargetCurrency->num_rows == 0) {
                            $status->targetCurrency = 'Invalid target currency.';
                        }
                    }

                    if (!empty($baseCurrencyId) && !empty($targetCurrencyId) && $baseCurrencyId == $targetCurrencyId) {
                        $status->targetCurrency = 'Base currency and target currency cannot be same.';
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

                    if (empty($effectiveTime)) {
                        $status->effectiveTime = 'Effective time is required.';
                    }

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1))) {
                        $status->error = 'You do not have permission to add exchange rates.';
                    }

                    $requiredProperties = [
                        'baseCurrency',
                        'targetCurrency',
                        'rate',
                        'effectiveDate',
                        'effectiveTime',
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

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $addTime = $dateTime->format('Y-m-d H:i:s');

                        $note = addslashes($note);

                        Database::iud("
                                INSERT INTO `currency_exchange_rates`
                                (
                                    `base_currency_id`,
                                    `target_currency_id`,
                                    `effective_date`,
                                    `effective_time`,
                                    `rate`,
                                    `note`,
                                    `updated_by`,
                                    `date_added`
                                )
                                VALUES
                                (
                                    '" . $baseCurrencyId . "',
                                    '" . $targetCurrencyId . "',
                                    '" . $effectiveDate . "',
                                    '" . $effectiveTime . "',
                                    '" . $rate . "',
                                    '" . $note . "',
                                    '" . $sessionUserObject['user_id'] . "',
                                    '" . $addTime . "'
                                )
                            ");

                        $currencyExchangeRateId = Database::$connection->insert_id;

                        Database::iud("
                                INSERT INTO `currency_exchange_rate_logs`
                                (
                                    `currency_exchange_rates_currency_exchange_rate_id`,
                                    `effective_date`,
                                    `old_rate`,
                                    `new_rate`,
                                    `action_type`,
                                    `note`,
                                    `updated_by`,
                                    `date_added`
                                )
                                VALUES
                                (
                                    '" . $currencyExchangeRateId . "',
                                    '" . $effectiveDate . "',
                                    NULL,
                                    '" . $rate . "',
                                    'ADD',
                                    '" . $note . "',
                                    '" . $sessionUserObject['user_id'] . "',
                                     '" . $addTime . "'
                                )
                            ");

                        $status->done = 'Exchange rate added successfully.';
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
