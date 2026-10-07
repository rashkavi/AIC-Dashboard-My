<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $programme = isset($_GET['id']) ? trim($_GET['id']) : 0;
        $type = isset($_GET['type']) ? trim($_GET['type']) : 0;
        if ($programme == 0) {
            $status->programme = "Please select programme";
        }

        if (!property_exists($status, 'programme')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $query = "SELECT * FROM `intakes` WHERE `programmes_programme_id`='" . $programme . "'
            ";

            if ($type == 2) {
                $query .= " AND `intake_status` = 'ONGOING'";
            }

            $intakes = Database::search($query);
            while ($data = $intakes->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["intake_id"]; ?>"
                    data-name="<?= $data["intake"]; ?>" data-start="<?= $data['date_added']; ?>" data-end="<?= $data['closed_date'] ?>"><?php echo $data["intake"]; ?></option>
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
