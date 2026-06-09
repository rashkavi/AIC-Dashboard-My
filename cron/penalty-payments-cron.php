<?php

// declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

require_once(__DIR__ . "/../includes/connection.php");
require_once(__DIR__ . "/../includes/UserClass.php");
require_once(__DIR__ . "/../includes/Project.php");

// if (php_sapi_name() !== 'cli') {
//     exit("Access denied");
// }

// Optional log file
$logFile = __DIR__ . '/logs/penalty-payments-cron.txt';

function writeLog(string $message, string $logFile): void
{
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    $query = "
    SELECT 
        `cp`.`course_payments_id`,
        `cp`.`amount`,
        `cp`.`panelty_payment_option_panelty_payment_option_id`,
        `cp`.`due_date`
    FROM `course_payments` AS `cp`
    WHERE `cp`.`payment_status_payment_status_id` = '1'
    AND `cp`.`panelty_payment_option_panelty_payment_option_id` != '1'
    AND `cp`.`due_date` < '" . date("Y-m-d") . "'
    ";

    $results = Database::search($query);
    if ($results->num_rows > 0) {
        while ($data = $results->fetch_assoc()) {
            if ($data['panelty_payment_option_panelty_payment_option_id'] == 2) {
                $ifAddedPanelty = Database::search("SELECT * FROM `panelty_payments` WHERE 
                `course_payments_course_payments_id` = '" . $data['course_payments_id'] . "' AND (`date_added` = '" . date("Y-m-d") . "' OR `date_added` < '" . date("Y-m-d") . "')");

                if ($ifAddedPanelty->num_rows == 0) {
                    $panelty = Database::search("SELECT * FROM `course_payments_penalties` WHERE `course_payments_course_payments_id` = '" . $data['course_payments_id'] . "'");
                    if ($panelty->num_rows > 0) {
                        $paneltyData = $panelty->fetch_assoc();
                        $amount = 0;

                        if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 1) {
                            $amount = $paneltyData['amount'];
                        } else if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 2) {
                            $amount = $data['amount'] / 100 * $paneltyData['amount'];
                        }

                        $columns = "`amount`,`date_added`,`course_payments_course_payments_id`";
                        $values  = "'" . $amount . "','" . date("Y-m-d") . "','" . $data['course_payments_id'] . "'";

                        Database::iud("INSERT INTO `panelty_payments` (" . $columns . ") VALUES (" . $values . ")");
                        writeLog("Panelty payment added for course_payments_id: " . $data['course_payments_id'] . " amount: " . $amount . " date_added: " . date("Y-m-d"), $logFile);
                    }
                }
            }

            if ($data['panelty_payment_option_panelty_payment_option_id'] == 3) {
                $ifAddedPanelty = Database::search("SELECT * FROM `panelty_payments` WHERE 
                `course_payments_course_payments_id` = '" . $data['course_payments_id'] . "' AND `date_added` = '" . date("Y-m-d") . "'");

                if ($ifAddedPanelty->num_rows == 0) {
                    $panelty = Database::search("SELECT * FROM `course_payments_penalties` WHERE `course_payments_course_payments_id` = '" . $data['course_payments_id'] . "'");
                    if ($panelty->num_rows > 0) {
                        $paneltyData = $panelty->fetch_assoc();
                        $amount = 0;

                        if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 1) {
                            $amount = $paneltyData['amount'];
                        } else if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 2) {
                            $amount = $data['amount'] / 100 * $paneltyData['amount'];
                        }

                        $columns = "`amount`,`date_added`,`course_payments_course_payments_id`";
                        $values  = "'" . $amount . "','" . date("Y-m-d") . "','" . $data['course_payments_id'] . "'";

                        Database::iud("INSERT INTO `panelty_payments` (" . $columns . ") VALUES (" . $values . ")");
                        writeLog("Panelty payment added for course_payments_id: " . $data['course_payments_id'] . " amount: " . $amount . " date_added: " . date("Y-m-d"), $logFile);
                    }
                }
            }

            if ($data['panelty_payment_option_panelty_payment_option_id'] == 4) {
                $ifAddedPanelty = Database::search("SELECT * FROM `panelty_payments` WHERE 
                `course_payments_course_payments_id` = '" . $data['course_payments_id'] . "' AND `date_added` = '" . date("Y-m-d") . "'");

                if ($ifAddedPanelty->num_rows == 0) {
                    $panelty = Database::search("SELECT * FROM `course_payments_penalties` 
                    WHERE `course_payments_course_payments_id` = '" . $data['course_payments_id'] . "'");
                    if ($panelty->num_rows > 0) {
                        $amount = 0;

                        while ($paneltyData = $panelty->fetch_assoc()) {
                            if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 1) {
                                $amount = $paneltyData['amount'];
                            } else if ($paneltyData['panelty_payment_type_panelty_payment_type_id'] == 2) {
                                $amount = $data['amount'] / 100 * $paneltyData['amount'];
                            }

                            $dueDate = new DateTime($data['due_date']);
                            $modifyDate = $dueDate->modify('+' . $paneltyData['days'] . ' day');

                            if ($modifyDate->format('Y-m-d') == date("Y-m-d")) {
                                $amount = $paneltyData['amount'];

                                $columns = "`amount`,`date_added`,`course_payments_course_payments_id`";
                                $values  = "'" . $amount . "','" . date("Y-m-d") . "','" . $data['course_payments_id'] . "'";

                                Database::iud("INSERT INTO `panelty_payments` (" . $columns . ") VALUES (" . $values . ")");
                                writeLog("Panelty payment added for course_payments_id: " . $data['course_payments_id'] . " amount: " . $amount . " date_added: " . date("Y-m-d"), $logFile);
                            } else if ($modifyDate->format('Y-m-d') < date("Y-m-d")) {
                                $addedPenalty = Database::search("SELECT * FROM `panelty_payments` WHERE 
                                `course_payments_course_payments_id` = '" . $data['course_payments_id'] . "' AND `date_added` = '" . $modifyDate->format('Y-m-d') . "'");

                                if ($addedPenalty->num_rows == 0) {
                                    $columns = "`amount`,`date_added`,`course_payments_course_payments_id`";
                                    $values  = "'" . $amount . "','" . $modifyDate->format('Y-m-d') . "','" . $data['course_payments_id'] . "'";

                                    Database::iud("INSERT INTO `panelty_payments` (" . $columns . ") VALUES (" . $values . ")");
                                    writeLog("Panelty payment added for course_payments_id: " . $data['course_payments_id'] . " amount: " . $amount . " date_added: " . date("Y-m-d"), $logFile);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
