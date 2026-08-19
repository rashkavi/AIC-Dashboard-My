<?php

// declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

require_once(__DIR__ . "/../includes/connection.php");
require_once(__DIR__ . "/../includes/UserClass.php");
require_once(__DIR__ . "/../includes/Project.php");

// if (php_sapi_name() !== 'cli') {
//     exit("Access denied");
// }

// ========================================================
// CONFIG
// ========================================================
// Current active status on students_has_intakes
const STATUS_ACTIVE = 1;
// Status to set when an overdue unpaid balance is found
// ASSUMPTION: confirm this is the correct status_status_id for "Overdue"
const STATUS_OVERDUE = 2;
// payment_status_payment_status_id on course_payments that represents "awaiting payment"
const PAYMENT_STATUS_AWAITING = 1;

$logFile = __DIR__ . '/logs/checkOverduePaymentsAndUpdateStudentStatus.txt';

function writeLog(string $message, string $logFile): void
{
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    $today = date("Y-m-d");

    // ========================================================
    // 1. Get all students_has_intakes currently ACTIVE
    // ========================================================
    $shiRs = Database::search("
        SELECT
            `shi`.`students_has_intakes_id`,
            `shi`.`students_student_id`,
            `shi`.`intakes_intake_id`
        FROM `students_has_intakes` AS `shi`
        WHERE `shi`.`status_status_id` = '" . STATUS_ACTIVE . "'
    ");

    $totalChecked = 0;
    $totalFlaggedOverdue = 0;

    while ($shi = $shiRs->fetch_assoc()) {

        $totalChecked++;

        $studentsHasIntakesId = (int)$shi["students_has_intakes_id"];
        $studentId = (int)$shi["students_student_id"];
        $intakeId = (int)$shi["intakes_intake_id"];

        // ========================================================
        // 2. Pull awaiting-payment rows past their due date for
        //    THIS specific students_has_intakes enrollment.
        //    Same balance formula used elsewhere in the system.
        // ========================================================
        $query = "
            SELECT
                `cp`.`course_payments_id`,
                `cp`.`amount`,
                `cp`.`discount`,
                `cp`.`due_date`,
                `cp`.`discount_type_discount_type_id`,
                IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                IFNULL(cpp.paid_amount, 0) AS paid_amount,
                IFNULL(cpp.total_discount, 0) AS total_discount,
                IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
            FROM `course_payments` AS `cp`
            INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi`
                ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
            LEFT JOIN (
                SELECT
                    course_payments_course_payments_id,
                    SUM(amount) AS panelty_amount
                FROM panelty_payments
                GROUP BY course_payments_course_payments_id
            ) pp
                ON cp.course_payments_id = pp.course_payments_course_payments_id
            LEFT JOIN (
                SELECT
                    course_payments_course_payments_id,
                    SUM(paid_amount) AS paid_amount,
                    SUM(discount_amount) AS total_discount,
                    SUM(panelty_amount) AS total_sub_panelty_amount
                FROM course_payment_has_payments
                WHERE payment_status_payment_status_id IN (2,3)
                GROUP BY course_payments_course_payments_id
            ) cpp
                ON cp.course_payments_id = cpp.course_payments_course_payments_id
            WHERE `pphsi`.`students_has_intakes_students_has_intakes_id` = '$studentsHasIntakesId'
                AND `cp`.`payment_status_payment_status_id` = '" . PAYMENT_STATUS_AWAITING . "'
                AND `cp`.`due_date` < '$today'
            GROUP BY `cp`.`course_payments_id`
        ";

        $payments = Database::search($query);

        $isOverdueWithBalance = false;
        $overdueDetails = [];

        if ($payments->num_rows > 0) {
            while ($payment = $payments->fetch_assoc()) {

                $discountAmount = $payment["discount"];
                if ($payment["discount_type_discount_type_id"] == 3) {
                    $discountAmount = $payment["amount"] / 100 * $payment["discount"];
                }

                $payableAmount = $payment["amount"]
                    - $discountAmount
                    - $payment["total_discount"]
                    + $payment["panelty_amount"]
                    + $payment["total_sub_panelty_amount"];

                $balance = $payableAmount - $payment["paid_amount"];

                if ($balance > 0) {
                    $isOverdueWithBalance = true;
                    $overdueDetails[] = "course_payments_id=" . $payment["course_payments_id"]
                        . " due_date=" . $payment["due_date"]
                        . " balance=" . number_format($balance, 2, '.', '');
                }
            }
        }

        // ========================================================
        // 3. Flag as overdue if any unpaid balance found
        // ========================================================
        if ($isOverdueWithBalance) {

            Database::iud("
                UPDATE `students_has_intakes`
                SET `status_status_id` = '" . STATUS_OVERDUE . "'
                WHERE `students_has_intakes_id` = '$studentsHasIntakesId'
            ");

            $totalFlaggedOverdue++;

            writeLog(
                "Flagged OVERDUE | students_has_intakes_id: $studentsHasIntakesId"
                    . " | student_id: $studentId"
                    . " | intake_id: $intakeId"
                    . " | " . implode(" ; ", $overdueDetails),
                $logFile
            );
        }
    }

    writeLog(
        "Run complete | total_checked: $totalChecked | total_flagged_overdue: $totalFlaggedOverdue | date: " . date("Y-m-d H:i:s"),
        $logFile
    );

    echo json_encode([
        "status" => "success",
        "total_checked" => $totalChecked,
        "total_flagged_overdue" => $totalFlaggedOverdue
    ]);
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
