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
                   
                    $programmeId = isset($decoded['programmeId']) ? intval($decoded['programmeId']) : 0;
                    $searchText = isset($decoded['searchText']) ? trim($decoded['searchText']) : '';
                    $selectedUsers = isset($decoded['selectedUsers']) ? $decoded['selectedUsers'] : [];
                    $newPermissionUsers = isset($decoded['newPermissionUsers']) ? $decoded['newPermissionUsers'] : [];

                    $query = "
                        SELECT 
                            users.user_id, 
                            users.user_fname,
                            users.user_lname, 
                            users.profile_img,
                            users.user_email, 
                            title.title
                         
                        FROM `users`
                        INNER JOIN `user_roles` 
                            ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                        INNER JOIN `title` 
                            ON `users`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `status` 
                            ON `users`.`status_status_id` = `status`.`status_id`
                        WHERE 
                            (`user_id` LIKE '%" . $searchText . "%' 
                            OR `user_email` LIKE '%" . $searchText . "%' 
                            OR `user_fname` LIKE '%" . $searchText . "%') 
                            AND `status_status_id` = '1' 
                            AND `user_id` != '" . $sessionUserObject["user_id"] . "'
                            AND `user_id` NOT IN (SELECT `users_user_id` FROM `users_has_permission_to_programmes`)
                            AND `user_roles_user_role_id` NOT IN (1,2)
                            ORDER BY `user_id` DESC
                    ";
                    $users = Database::search($query);
?>
                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                    <div class="d-flex flex-row align-items-center">
                                        <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">User Id</th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Email Address</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php

                            if ($users->num_rows > 0) {
                                while ($data = $users->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <?php
                                                if (count($newPermissionUsers) > 0 && in_array($data['user_id'], $newPermissionUsers)) {
                                                ?>
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check bg-warning border-warning" checked disabled>
                                                    <?php
                                                } else {
                                                    if (in_array($data['user_id'], $selectedUsers)) {
                                                    ?>
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" checked id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                <?php
                                                    }
                                                }

                                                ?>
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/users/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_id"]; ?></span></td>
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_email"]; ?></span></td>
                                    </tr>
                                <?php
                                }
                            } else {
                                ?>
                                <tr class="justify-content-center align-middle">
                                    <td colspan="13" class="align-middle justify-content-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data"/>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
<?php
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
