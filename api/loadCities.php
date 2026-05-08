<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $district = isset($_GET['id']) ? trim($_GET['id']) : '';
        if ($district == '' || $district == 0) {
            $status->error = "Please select district";
        }

        if (!property_exists($status, 'error')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $city = Database::search("SELECT * FROM `city` WHERE `district_district_id`='" . $district . "'");
            while ($data = $city->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["city_id"]; ?>"><?php echo $data["city"]; ?></option>
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
