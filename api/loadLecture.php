<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $subject = isset($decoded["subject"]) ? $decoded["subject"] : 0;
                    $lecturer = isset($decoded["lecturer"]) ? $decoded["lecturer"] : 0;
                    $date = isset($decoded["date"]) ? $decoded["date"] : 0;

                    if ($subject != 0 && $lecturer != 0 && $date != 0) {

                        require_once "../includes/connection.php";

                        $lectures = Database::search("SELECT * FROM `time_table`
                        INNER JOIN `lecture_type` ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id` 
                        WHERE `subjects_subject_id`='" . $subject . "' AND `lecturers_lecturers_id`='" . $lecturer . "' AND `date`='" . $date . "' 
                        AND `time_table`.`lecture_type_lecture_type_id` != '3'");


                        if ($lectures->num_rows > 0) {
                            while ($data = $lectures->fetch_assoc()) {
                                $start_time = date("h:i A", strtotime($data["start_time"]));
                                $end_time = date("h:i A", strtotime($data["end_time"]));
?>
                                <option value="<?php echo $data["time_table_id"]; ?>"><?php echo $data["lecture_type"] . "Lecture ( Time Slot : " . $start_time . " - " . $end_time . " )"; ?></option>
                            <?php
                            }
                        } else {
                            ?>
                            <option value="0">Not Found Lectures</option>
<?php
                        }
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}
if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
