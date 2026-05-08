<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
       
        $intake = isset($_GET['id']) ? trim($_GET['id']) : '';
        if ($intake == 0) {
            $status->intake = "Please select intake";
        }

        if (!property_exists($status, 'intake')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $query = "SELECT * FROM `levels` WHERE `intakes_intake_id`='" . $intake . "'";

            $levels = Database::search($query);
            while ($data = $levels->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["level_id"]; ?>"><?php echo $data["level"]; ?></option>
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

if (property_exists($status, 'error') || property_exists($status, 'intake')) {
    echo (json_encode($status));
}
