<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $selectedUsers = isset($decoded['selectedUsers']) ? $decoded['selectedUsers'] : 0;
                    (int) $userCount = isset($decoded['userCount']) ? $decoded['userCount'] : 0;

                    $user = Database::search("
                    SELECT
                        `user_id`, 
                        `title`, 
                        `user_fname`, 
                        `user_lname`, 
                        `user_email`, 
                        `status_status_id`, 
                        `profile_img` 
                    FROM `users`
                    INNER JOIN `title` 
                        ON `users`.`title_title_id` = `title`.`title_id`
                    WHERE `user_id` IN (" . implode(",", $selectedUsers) . ")
                ");


                    if ($user->num_rows > 0) {
                        while ($userData = $user->fetch_assoc()) {
                            $userHasAvailableSubjectePermissions = Database::search("
                            SELECT 
                                `permission_to`.`permission_to_id`, 
                                `permissions`.`permission` 
                            FROM `permission_to`
                            INNER JOIN `permissions` 
                                ON `permission_to`.`permissions_permission_id` = `permissions`.`permission_id`
                            WHERE 
                                `permission_type_permission_type_id` = '2' 
                                AND `permission_to_id` NOT IN (
                                    SELECT `permission_to_permission_to_id` 
                                    FROM `users_has_permission_to_other`
                                    WHERE `users_user_id` = '" . $userData['user_id'] . "'
                                    AND `permission_to_permission_to_id` IN (SELECT `permission_to_id` FROM `permission_to` WHERE `permission_type_permission_type_id` = 2)
                                )
                        ");
                            if ($userHasAvailableSubjectePermissions->num_rows > 0) {
?>
                                <div class="col-12 p-4 component-theme mb-3" id="user-permission-template">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 d-flex">
                                            <div class="col-6">
                                                <span class="main-text m-0 p-0">User - <?= $userCount += 1 ?></span>
                                            </div>
                                            <div class="col-6 d-flex justify-content-end">
                                                <iconify-icon icon="typcn:delete" width="30" height="30" class="sub-text" onclick="removePermissionToAddUser(<?= $userData['user_id'] ?>,this)"></iconify-icon>
                                            </div>
                                        </div>

                                        <div class="col-12 bl-solid mt-3 p-3">
                                            <div class="row">
                                                <div class="col-12 col-lg-6 col-xl-4">
                                                    <div class="d-flex flex-column p-0 m-0">
                                                        <span class="text-14 main-text mb-3">Selected User Information</span>
                                                        <div class="d-flex flex-row align-content-center align-items-center bl-solid p-3 m-0">
                                                            <?php
                                                            if (!empty($userData["profile_img"]) && file_exists('../assets/imgs/users/' . $userData["profile_img"])) {
                                                            ?>
                                                                <img src="./assets/imgs/users/<?php echo $userData["profile_img"]; ?>" class="hwr-50" alt="User image">
                                                            <?php
                                                            } else {
                                                                $firstNameInitial = strtoupper(substr($userData["user_fname"], 0, 1));
                                                                $lastNameInitial = strtoupper(substr($userData["user_lname"], 0, 1));
                                                            ?>
                                                                <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                            <?php
                                                            }
                                                            ?>
                                                            <div class="d-flex flex-column mx-4">
                                                                <span class="sub-text"><?= $userData['title'] . "." . $userData['user_fname'] . " " . $userData['user_lname'] ?></span>
                                                                <span class="sub-text">#<?= $userData['user_id'] ?></span>
                                                                <span class="sub-text"><?= $userData['user_email'] ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-6 col-xl-8 d-flex flex-column mt-3 mt-lg-0">
                                                    <span class="text-14 main-text mb-3 px-3">Available User Permissions</span>
                                                    <div class="chip-group d-flex flex-wrap gap-3 px-3">
                                                        <?php
                                                        while ($userHasAvailableSubjectPermission = $userHasAvailableSubjectePermissions->fetch_assoc()) {
                                                        ?>
                                                            <div class="chip d-flex align-content-center" data-user-id="<?= $userData['user_id'] ?>" data-value="<?= $userHasAvailableSubjectPermission['permission_to_id'] ?>" onclick="chipBoxAction(this);">
                                                                <span class="chip-label"><?= $userHasAvailableSubjectPermission['permission'] ?></span>
                                                                <span class="chip-check-icon">
                                                                    <iconify-icon icon="mdi:check"></iconify-icon>
                                                                </span>
                                                                <span class="chip-info-icon mx-3 me-0 d-flex align-items-center" data-tooltip="This permission allows users to view content without making changes.">
                                                                    <iconify-icon icon="mdi:information-outline"></iconify-icon>
                                                                </span>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

<?php
                            } else {
                                $status->msg = "The user already has all permissions in this Subject.";
                            }
                        }
                    } else {
                        $status->msg = 'User ID is not valid';
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

if (property_exists($status, 'error') || property_exists($status, 'msg')) {
    echo (json_encode($status));
}
