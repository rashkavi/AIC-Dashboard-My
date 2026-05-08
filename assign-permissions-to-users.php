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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5))) {
            header("Location: ./users");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Assign Permissions to Users - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            load_side_menu::load("Users");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Users");
                                    ?>
                                </div>

                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-add-edit.php"; ?>
                                </div>

                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0" id="display-area">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-lg-10">
                                                    <div class="row">
                                                        <div class="d-flex align-items-center"><span class="main-topic lh-lg" data-i18n="assign-permissions">Assign New Permissions</span>
                                                            <span class="permission-info-icon mx-3 me-0 d-flex align-items-center"
                                                                data-tooltip="Select users, search by name, email, or ID.&#10;Check available users and assign permissions individually.&#10;Confirm selections and save—all in a seamless process!">
                                                                <iconify-icon icon="bxs:info-square" width="24" height="24" class="dark-theme"></iconify-icon>
                                                            </span>
                                                        </div>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="users" href="./users">Users</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="users-permissions" href="./users-permissions">Users Permissions</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="assign-permissions">Assign New Permissions</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 component-theme" id="user-detailss-display-area">
                                        </div>
                                        <div class="col-12" id="empty-users-display-area">
                                            <div class="d-flex flex-column p-0 m-0 g-0 component-theme justify-content-center align-items-center p-5">
                                                <img src="./assets/imgs/common/select-user.png" class="no-data-icon h-auto" alt="Select User" />
                                                <button class="custom-btn px-5" data-bs-toggle="modal" data-bs-target="#addNewUserModal" data-i18n="select-users">Select Users</button>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme d-none" id="save-permission-btn-area">
                                            <div class="row justify-content-end p-0 m-0 g-0">
                                                <div class="col-4 m-md-0 d-flex justify-content-end align-items-center">
                                                    <button class="custom-btn w-100" data-i18n="save-permissions" onclick="addUsersOtherPermissions(this);">Save Permissions</button>
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

                <div class="modal modal-lg fade" id="addNewUserModal" tabindex="-1" aria-labelledby="addNewUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h1 class="modal-title fs-5" data-i18n="search-user" id="addNewUserModalLabel">Search User</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body ">
                                <input type="text" list="userlistOptions" class="form-control custom-input" id="search-text" placeholder="Search user using ID, name, or email" data-i18n="[placeholder]permissions-search-user" onkeyup="UsersPermissionsSearchUsers();">
                                <span id="search-text-feedback" class="text-danger"></span>
                                <div class="col-12 mt-4">
                                    <div class="row p-0 m-0 g-0 align-content-center">
                                        <div class="overflow-x-auto p-0 m-0 g-0" id="users-table-container">
                                        </div>
                                    </div>
                                </div>
                                <span class="text-12 text-dark-red  email-feedback"></span>
                            </div>
                            <div class="modal-footer">
                                <div class="col-12 col-md-4">
                                    <button type="button" class="custom-btn w-100" data-i18n="confirm-select-users" onclick="loadAvailableUserUsersPermission(this);">Confirm Select User</button>
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
?>