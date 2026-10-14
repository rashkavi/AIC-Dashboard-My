<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $payments = Database::search("SELECT * FROM `course_payments`
    WHERE `panelty_payment_option_panelty_payment_option_id` != 1
    AND `course_payments_id` NOT IN (SELECT `course_payments_course_payments_id` FROM `course_payments_penalties`)");
    while ($payment = $payments->fetch_assoc()) {

        $penalties = Database::search("SELECT * FROM `intake_has_payments_has_penalties`
    WHERE `intake_payments_plan_has_payment_id` = '" . $payment['intake_payments_plan_has_payment_id'] . "'");
        if ($penalties->num_rows > 0) {
            while ($p = $penalties->fetch_assoc()) {
                Database::iud("INSERT INTO `course_payments_penalties`(`course_payments_course_payments_id`,`amount`,`days`,`panelty_payment_type_panelty_payment_type_id`)
                VALUES ('" . $payment['course_payments_id'] . "',
                '" . $p['amount'] . "',
                '" . $p['days'] . "',
                '" . $p['panelty_payment_type_panelty_payment_type_id'] . "')");

                echo "Panelty payment added for course_payments_id: " . $payment['course_payments_id'] . " amount: " . $p['amount'] . " date_added: " . date("Y-m-d") . "<br>";
            }
        }
    }
}
