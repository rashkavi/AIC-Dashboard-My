<?php
// api/getExchangeRate.php
// Returns the latest exchange rate for a given base/target currency and date

$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

$baseId   = isset($_GET['base'])   ? (int)$_GET['base']   : 0;
$targetId = isset($_GET['target']) ? (int)$_GET['target'] : 0;
$date     = isset($_GET['date'])   ? $_GET['date']         : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

if ($baseId === $targetId) {
    // Same currency — rate is always 1
    echo json_encode([
        'rate'                      => '1.000000',
        'effective_date'            => $date,
        'currency_exchange_rate_id' => null,
    ]);
    exit;
}

// 1. Try exact date first
$result = Database::search("
    SELECT
        `currency_exchange_rate_id`,
        `rate`,
        `effective_date`,
        `effective_time`
    FROM `currency_exchange_rates`
    WHERE `base_currency_id`   = '" . $baseId   . "'
      AND `target_currency_id` = '" . $targetId . "'
      AND `effective_date`     = '" . $date      . "'
    ORDER BY `effective_time` DESC
    LIMIT 1
");

// 2. Fall back to latest rate on or before the given date
if ($result->num_rows === 0) {
    $result = Database::search("
        SELECT
            `currency_exchange_rate_id`,
            `rate`,
            `effective_date`,
            `effective_time`
        FROM `currency_exchange_rates`
        WHERE `base_currency_id`   = '" . $baseId   . "'
          AND `target_currency_id` = '" . $targetId . "'
          AND `effective_date`    <= '" . $date      . "'
        ORDER BY `effective_date` DESC, `effective_time` DESC
        LIMIT 1
    ");
}

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'rate'                      => $row['rate'],
        'effective_date'            => $row['effective_date'],
        'currency_exchange_rate_id' => $row['currency_exchange_rate_id'],
    ]);
} else {
    echo json_encode(['error' => 'No rate found', 'rate' => null]);
}
