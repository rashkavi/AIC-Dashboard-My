<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $level = isset($_GET['id']) ? trim($_GET['id']) : '';
        if ($level == 0) {
            $status->level = "Please select level";
        }

        if (!property_exists($status, 'level')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $query = "SELECT * FROM `subjects` WHERE `levels_level_id`='" . $level . "'";

            $subjects = Database::search($query);
            while ($data = $subjects->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["subject_id"]; ?>"><?php echo $data["subject"]; ?></option>
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
