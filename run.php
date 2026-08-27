<!-- IMPROVED PAYMENT PLANS ACCORDION VIEW -->
<!-- Better looking accordion headers with icons and professional styling -->

<?php
require_once './includes/connection.php';
require_once './includes/UserClass.php';
require_once './includes/Project.php';


Database::iud("UPDATE `course_payments` SET `payment_status_payment_status_id`='1' 
    WHERE `course_payments_id`='10286'");
