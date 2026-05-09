<?php

$status = new stdClass();

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);

                if (json_last_error() == JSON_ERROR_NONE) {

                    $fromDate = isset($decoded["from_date"]) && !empty($decoded["from_date"])
                        ? $decoded["from_date"]
                        : date("Y-m-01");

                    $toDate = isset($decoded["to_date"]) && !empty($decoded["to_date"])
                        ? $decoded["to_date"]
                        : date("Y-m-d");

                    $query = "
                        SELECT 
                            cer.currency_exchange_rate_id,
                            cer.base_currency_id,
                            cer.target_currency_id,

                            cerl.currency_exchange_rate_log_id,
                            cerl.effective_date,
                            cerl.old_rate,
                            cerl.new_rate,
                            cerl.action_type,
                            cerl.date_added,

                            bc.code AS base_code,
                            bc.symbol AS base_symbol,

                            tc.code AS target_code,
                            tc.symbol AS target_symbol

                        FROM currency_exchange_rate_logs cerl

                        INNER JOIN currency_exchange_rates cer
                            ON cerl.currency_exchange_rates_currency_exchange_rate_id = cer.currency_exchange_rate_id

                        INNER JOIN currencies bc 
                            ON cer.base_currency_id = bc.currency_id

                        INNER JOIN currencies tc 
                            ON cer.target_currency_id = tc.currency_id

                        WHERE bc.is_active = 1
                        AND tc.code = 'LKR'
                        AND cerl.effective_date BETWEEN '" . $fromDate . "' AND '" . $toDate . "'

                        ORDER BY cerl.effective_date ASC, cerl.date_added ASC, bc.code ASC
                    ";

                    

                    $result = Database::search($query);

                    $labels = [];
                    $currencyData = [];

                    while ($row = $result->fetch_assoc()) {

                        $date = $row["effective_date"];
                        $code = $row["base_code"];

                        if (!in_array($date, $labels)) {
                            $labels[] = $date;
                        }

                        if (!isset($currencyData[$code])) {
                            $currencyData[$code] = [
                                "label" => $code . " to LKR",
                                "data" => []
                            ];
                        }

                        /*
                            If same currency updated 2 times same day,
                            chart will show latest updated rate of that date.
                        */
                        $currencyData[$code]["data"][$date] = (float)$row["new_rate"];
                    }

                    $datasets = [];

                    foreach ($currencyData as $code => $currency) {

                        $dataPoints = [];

                        foreach ($labels as $date) {
                            $dataPoints[] = $currency["data"][$date] ?? null;
                        }

                        $datasets[] = [
                            "label" => $currency["label"],
                            "data" => $dataPoints,
                            "borderWidth" => 2,
                            "tension" => 0.35,
                            "pointRadius" => 3,
                            "pointHoverRadius" => 6,
                            "fill" => false
                        ];
                    }

                    $status->labels = $labels;
                    $status->datasets = $datasets;
                    $status->done = 'Success';
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
