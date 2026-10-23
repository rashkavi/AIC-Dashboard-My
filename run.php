<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    Database::iud("ALTER TABLE `students_logs` MODIFY COLUMN `log` TEXT NOT NULL;");
}
