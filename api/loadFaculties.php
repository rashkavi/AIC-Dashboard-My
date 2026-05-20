<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $branch = isset($_GET['id']) ? trim($_GET['id']) : 0;
        if ($branch == 0) {
            $status->branch = "Please select branch";
        }

        if (!property_exists($status, 'branch')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $query = "SELECT * FROM `faculties` WHERE `branches_branch_id`='" . $branch . "'";

            $faculties = Database::search($query);
            while ($data = $faculties->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
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

if (property_exists($status, 'error') || property_exists($status, 'programme')) {
    echo (json_encode($status));
}
