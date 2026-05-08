<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
               $deleteFaculty = isset($_GET['id']) ? trim($_GET['id']) : 0;
        if ($deleteFaculty != 0) {
            $facultyHasProgrammes = Database::search("SELECT `programme_id` FROM `programmes` WHERE `faculties_faculty_id`='" . $deleteFaculty . "'");
            $programmes[] = array();
            while ($data = $facultyHasProgrammes->fetch_assoc()) {
                $programmes[] = $data["programme_id"];
            }
            $status->programmes = $programmes;
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

    echo (json_encode($status));

