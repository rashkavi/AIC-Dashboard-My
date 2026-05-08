<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $deleteProgramme = isset($_GET['id']) ? trim($_GET['id']) : 0;
        if ($deleteProgramme != 0) {
            $ProgrammeHasIntakes = Database::search("SELECT `intake_id` FROM `intakes` WHERE `programmes_Programme_id`='" . $deleteProgramme . "'");
            $intakes[] = array();
            while ($data = $ProgrammeHasIntakes->fetch_assoc()) {
                $intakes[] = $data["intake_id"];
            }
            $status->intakes = $intakes;
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
