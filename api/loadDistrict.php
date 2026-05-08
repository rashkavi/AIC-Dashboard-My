<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
     
        $province = isset($_GET['id']) ? trim($_GET['id']) : '';

        if ($province == '' || $province == 0) {
            $status->error = "Please select province";
        }

        if (!property_exists($status, 'error')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $district = Database::search("SELECT * FROM `district` WHERE `province_province_id`='" . $province . "'");
            while ($data = $district->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["district_id"]; ?>"><?php echo $data["district"]; ?></option>
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

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
