<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $subjects = Database::search("SELECT * FROM `subjects` WHERE `subject`='CPRH52 - Leadership and Quality in the Early Years'");

    while ($subject = $subjects->fetch_assoc()) {
        print_r($subject);
        echo "<br>";
        try {
            Database::iud("DELETE FROM `time_table` WHERE `subjects_subject_id`='" . $subject['subject_id'] . "' 
        AND `date`>='2026-10-20'");
        } catch (Exception $e) {
            echo $e;
        }
    }
}
