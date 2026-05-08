<?php
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserObject = SessionUser::getCurrentUserObject();
    require '../vendor/autoload.php';

    $agent = new Jenssegers\Agent\Agent();
    $timezone = new DateTimeZone("Asia/Colombo");
    $dateTime = new DateTime("now", $timezone);

    $deviceType = $agent->isDesktop() ? 'Desktop' : ($agent->isPhone() ? 'Mobile' : 'Tablet');
    $browser = $agent->browser();
    $deviceName = $agent->device();
    $os = $agent->platform();
    $ip = $_SERVER['REMOTE_ADDR'];
    $loginTime = $dateTime->format('Y-m-d H:i:s');

    $deviceCheck = Database::search("
            SELECT * FROM users_devices 
            WHERE users_user_id = '" . $sessionUserObject["user_id"] . "'
            AND device_name = '" . $deviceName . "'
            AND device_type = '" . $deviceType . "'
            AND browser = '" . $browser . "'
            AND os = '" . $os . "'
        ");


    if ($deviceCheck->num_rows > 0) {
        $deviceData = $deviceCheck->fetch_assoc();

        Database::search("UPDATE `users_devices` SET `is_active`='0' WHERE `users_devices_id`='" . $deviceData["users_devices_id"] . "'");
    }

    SessionUser::destroySession();
    header("Location: ../index.php");
} else {
    header("Location: ../index.php");
}
