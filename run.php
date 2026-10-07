<!-- IMPROVED PAYMENT PLANS ACCORDION VIEW -->
<!-- Better looking accordion headers with icons and professional styling -->

<?php
require_once './includes/connection.php';
require_once './includes/UserClass.php';
require_once './includes/Project.php';

$query = "SELECT 
            `student_id`,
            `entry_type`,
            `payment_option`,
            `lead_student_status_lead_student_status_id`
          FROM `students`
          WHERE 
            `entry_type` IS NOT NULL
            OR `payment_option` IS NOT NULL
            OR `lead_student_status_lead_student_status_id` IS NOT NULL";

$result = Database::search($query);

$updatedCount = 0;
$skippedCount = 0;

while ($student = $result->fetch_assoc()) {

    $studentId = $student['student_id'];

    $entryType = $student['entry_type'];
    $paymentOption = $student['payment_option'];
    $leadStatusId = $student['lead_student_status_lead_student_status_id'];

    // Escape values
    $entryType = $entryType !== null ? "'" . Database::$connection->real_escape_string($entryType) . "'" : "NULL";
    $paymentOption = $paymentOption !== null ? "'" . Database::$connection->real_escape_string($paymentOption) . "'" : "NULL";
    $leadStatusId = $leadStatusId !== null ? "'" . Database::$connection->real_escape_string($leadStatusId) . "'" : "NULL";

    // Check whether the student has intake records
    $intakeResult = Database::search(
        "SELECT `students_has_intakes_id`
         FROM `students_has_intakes`
         WHERE `students_student_id` = '" . $studentId . "'"
    );

    if ($intakeResult->num_rows > 0) {

        while ($intake = $intakeResult->fetch_assoc()) {

            $updateQuery = "UPDATE `students_has_intakes`
                            SET
                                `entry_type` = $entryType,
                                `payment_option` = $paymentOption,
                                `lead_student_status_lead_student_status_id` = $leadStatusId
                            WHERE `students_has_intakes_id` = '" . $intake['students_has_intakes_id'] . "'";

            Database::iud($updateQuery);

            $updatedCount++;
        }
    } else {
        $skippedCount++;
    }
}

echo "Completed successfully.<br>";
echo "Updated intake records: " . $updatedCount . "<br>";
echo "Students without intake records: " . $skippedCount . "<br>";
