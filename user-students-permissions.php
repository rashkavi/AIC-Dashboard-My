<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['user-id']) && !empty($_GET['user-id']))) {
            header("Location: ./students");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) ||  UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))) {
                header("Location: ./students");
            } else {

                $user_id = $_GET['user-id'];

                $validUser = Database::search("SELECT `u`.*, `t`.`title` FROM `users_has_permission_to_other` AS `uhpto`
                INNER JOIN `users` AS `u` ON `uhpto`.`users_user_id` = `u`.`user_id`
                INNER JOIN `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
                INNER JOIN `permission_to` AS `pt` ON `uhpto`.`permission_to_permission_to_id` = `pt`.`permission_to_id`
                WHERE `u`.`user_id` = '" . $user_id . "'
                AND `pt`.`permission_type_permission_type_id` = '2'");

                if ($validUser->num_rows == 0) {
                    header("Location: ./students-permissions");
                } else {
                    $userData = $validUser->fetch_assoc();

                    $userHasPermissions = Database::search("
                    SELECT 
                        `p`.`permission` AS `permission`,
                        `uhpto`.`users_has_permission_to_id` AS `user_other_per_id`,
                        `uhpto`.`status_status_id` AS `status_status_id`,
                        `uhpto`.`added_at` AS `added_at`,
                        `add_user`.`user_id` AS `added_by_user_id`,
                        `add_user`.`user_fname` AS `added_by_fname`,
                        `add_user`.`user_lname` AS `added_by_lname`,
                        `add_user`.`profile_img` AS `added_by_profile_img`
                    FROM 
                        `users_has_permission_to_other` AS `uhpto`
                    INNER JOIN `permission_to` AS `pt` ON `uhpto`.`permission_to_permission_to_id` = `pt`.`permission_to_id`
                    INNER JOIN `permissions` AS `p` ON `pt`.`permissions_permission_id` = `p`.`permission_id`
                    LEFT JOIN `users` AS `add_user` ON `uhpto`.`added_by` = `add_user`.`user_id`
                    WHERE 
                        `uhpto`.`users_user_id` = '" . $user_id . "'
                        AND `pt`.`permission_type_permission_type_id` = '2'
                    ORDER BY 
                        `p`.`permission_id` ASC
                    ");

                    if ($userHasPermissions->num_rows == 0) {
                        header("Location: ./students-permissions");
                    } else {


                        $userHasntPermissions = Database::search("
                SELECT 
                  
                    `p`.`permission` AS `permission`,
                    `pt`.`permission_to_id` AS `permission_to_id`

                FROM 
                    `permission_to` AS `pt` 
                INNER JOIN `permissions` AS `p` ON `pt`.`permissions_permission_id` = `p`.`permission_id`
                WHERE 
                  NOT EXISTS (
                        SELECT 1 
                        FROM `users_has_permission_to_other` AS `uhpto`
                        WHERE `uhpto`.`permission_to_permission_to_id` = `pt`.`permission_to_id`
                        AND `uhpto`.`users_user_id` = '" . $user_id . "'
                    )
                    AND `pt`.`permission_type_permission_type_id` = '2'
                ORDER BY 
                    `p`.`permission_id` ASC
                ");

?>
                        <!DOCTYPE html>
                        <html lang="en">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>User Students Permissions - Admin Dashboard | <?= Project::getBaseName() ?></title>

                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        </head>

                        <body class="overflow-hidden">
                            <div class="notifications"></div>
                            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                                <div id="appContainer" class="row p-0">
                                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                        <?php
                                        include "includes/side-menu.php";
                                        load_side_menu::load("Students");
                                        ?>
                                    </div>
                                    <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                                        <div class="row p-0 m-0 g-0 h-100">
                                            <div class="col-12 p-0 m-0 g-0">
                                                <?php
                                                include "./includes/header.php";
                                                selectItem::setSelected("Students");
                                                ?>
                                            </div>

                                            <div class="container p-4 m-0" id="skeleton-list">
                                                <?php
                                                include "./includes/components/skeleton-header.php";
                                                include "./includes/components/skeleton-table.php"; ?>
                                            </div>

                                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                                <div class="row p-4 m-0 g-0" id="display-area">
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row align-items-center p-0 m-0 g-0">
                                                            <div class="col-12 col-lg-8">
                                                                <div class="row">
                                                                    <span class="main-topic lh-lg" data-i18n="students-permissions">Students Permissions</span>
                                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                        <ol class="breadcrumb m-0">
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="students" href="./users">Students</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="students-permissions" href="./students-permissions">Students Permissions</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $userData["title"] . "." . $userData["user_fname"] . " " . $userData["user_lname"] ?></a></li>
                                                                        </ol>
                                                                    </nav>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php

                                                    if ($userHasntPermissions->num_rows > 0) {
                                                    ?>
                                                        <div class="col-12 p-4 component-theme mb-3" id="user-permission-template">
                                                            <div class="row p-0 m-0 g-0">
                                                                <div class="col-12 d-flex flex-column m-0 p-0">
                                                                    <span class="text-14 main-text mb-3" data-i18n="available-user-permissions">Available User Permissions</span>
                                                                    <div class="chip-group d-flex flex-wrap gap-3">
                                                                        <?php
                                                                        while ($permission = $userHasntPermissions->fetch_assoc()) {
                                                                        ?>
                                                                            <div class="chip d-flex align-content-center" data-user-id="<?= $userData['user_id'] ?>" data-value="<?= $permission['permission_to_id'] ?>" onclick="chipBoxAction(this);">
                                                                                <span class="chip-label"><?= $permission['permission'] ?></span>
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
                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("users", 5)) {
                                                                    ?>
                                                                        <div class="col-12 col-md-4 col-lg-2 mt-3">
                                                                            <button class="custom-btn w-100" data-i18n="save-permissions" onclick="addUsersOtherPermissions(this,2);">Save Permissions</button>
                                                                        </div>
                                                                    <?php
                                                                    }

                                                                    ?>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }

                                                    ?>
                                                    <div class="col-12 p-4 component-theme">
                                                        <div class="row p-0 gy-3 gx-3 align-content-center justify-content-start">
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("users", 6)) {
                                                            ?>
                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="user-permission-update-selector">
                                                                        <option selected value="0">- Update Permission -</option>
                                                                        <option value="1">Active Permission</option>
                                                                        <option value="2">Inactive Permission</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" data-i18n="apply" onclick="updateUsersOtherPermission(this,<?= $userData['user_id'] ?>,2)">Apply</button>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 component-theme">
                                                        <div class="row p-4 m-0 g-0 align-content-center">
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="users-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Permission</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Added By</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Added Date</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <span class="text-14 main-text fw-medium">Status</span>
                                                                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                    </div>
                                                                                </th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php
                                                                            while ($permission = $userHasPermissions->fetch_assoc()) {
                                                                            ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-permission<?php echo $permission["user_other_per_id"]; ?>" onchange="checkRowEvents(<?php echo $permission['user_other_per_id']; ?>);">
                                                                                            <span class="text-14 main-text mx-2 cursor-pointer" onclick="togglePermissionCheckbox(<?php echo $permission['user_other_per_id']; ?>)"><?php echo $permission["permission"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <?php
                                                                                            if (!empty($permission["added_by_profile_img"]) && file_exists('./assets/imgs/users/' . $permission["added_by_profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?php echo $permission["added_by_profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($permission["added_by_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($permission["added_by_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?php echo $userData["title"] . "." . $permission["added_by_fname"] . " " . $permission["added_by_lname"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $permission["added_at"] ?></span></td>
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "Active"     => "table-btn-success",
                                                                                            "Inactive"  => "table-btn-danger",
                                                                                        ];
                                                                                        $sbutton_class = $status_classes[$permission["status_status_id"] == 1 ? "Active" : "Inactive"];
                                                                                        ?>
                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-75"> <?php echo $permission["status_status_id"] == 1 ? "Active" : "Inactive" ?></button>
                                                                                    </td>

                                                                                </tr>
                                                                            <?php
                                                                            }

                                                                            ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>


                                                </div>
                                                <div class="flex-grow-1"></div>
                                                <footer>
                                                    <?php include './includes/footer.php' ?>
                                                </footer>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        </body>

                        </html>
<?php
                    }
                }
            }
        }
    }
}
?>