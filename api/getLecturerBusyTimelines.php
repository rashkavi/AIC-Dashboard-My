<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {

            $today = new DateTime();
            $startOfWeek = clone $today->modify(('Monday' == $today->format('l')) ? 'this week' : 'last monday');
            $endOfWeek = clone $startOfWeek;
            $endOfWeek->modify('+6 days');

            $weekTimeTable = Database::search("SELECT `start_time`,`end_time`,`date` FROM `time_table` 
                    WHERE  `lecturers_lecturers_id` IN (SELECT `lecturers_id` FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "')
                    AND `time_table`.`date` BETWEEN '" . $startOfWeek->format('Y-m-d') . "' AND '" . $endOfWeek->format('Y-m-d') . "'");

            $dataPoints = [];

            $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            if ($weekTimeTable->num_rows > 0) {
                while ($row = $weekTimeTable->fetch_assoc()) {
                    $date = new DateTime($row['date']);
                    $dayName = $daysOfWeek[$date->format('w')]; // 0 (Sun) to 6 (Sat)

                    if (!in_array($dayName, $daysOfWeek)) continue;

                    $startTime = explode(":", $row["start_time"]);
                    $endTime = explode(":", $row["end_time"]);

                    $startDecimal = intval($startTime[0]) + intval($startTime[1]) / 60;
                    $endDecimal = intval($endTime[0]) + intval($endTime[1]) / 60;

                    $startDateTime = DateTime::createFromFormat('H:i:s', $row["start_time"]);
                    $endDateTime = DateTime::createFromFormat('H:i:s', $row["end_time"]);

                    $startLabel = $startDateTime ? $startDateTime->format('h:i A') : $row["start_time"];
                    $endLabel = $endDateTime ? $endDateTime->format('h:i A') : $row["end_time"];

                    $dataPoints[] = [
                        "x" => [$startDecimal, $endDecimal],
                        "y" => $dayName,
                        "label" => "{$startLabel} - {$endLabel}"
                    ];
                }
            }

            $status->done = true;
            $status->labels = array_values(array_filter($daysOfWeek, fn($d) => $d != 'Saturday' && $d != 'Sunday')); // Optional
            $status->data = $dataPoints;
        } else {
            http_response_code(400);
            $status->error = 'Invalid JSON';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
