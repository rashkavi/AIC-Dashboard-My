<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
       
        $subject = isset($_GET['id']) ? trim($_GET['id']) : '';
        if ($subject == 0) {
            $status->subject = "Please select subject";
        }

        if (!property_exists($status, 'subject')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $query = "SELECT `lecturers_id`,`user_fname`,`user_lname`,`title` FROM `lecturers` 
                                    INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                    WHERE `lecturers`.`status_status_id`='1' AND `users`.`status_status_id`='1'
                                    AND `subjects_subject_id`='" . $subject . "'";

            $subjects = Database::search($query);
            while ($data = $subjects->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["lecturers_id"]; ?>"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></option>
<?php
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error') || property_exists($status, 'level')) {
    echo (json_encode($status));
}
