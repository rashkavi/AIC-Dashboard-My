<?php

require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once './includes/Project.php';
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    $onloadAttr = '';
    if (SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole != "Lecturer") {
        $onloadAttr = 'onload="initializeDashboard();"';
    } else if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
        $onloadAttr = 'onload = "initializeLecturerDashboard()"';
    }

    $alertDisplay = false;
    if (isset($_COOKIE['times_academy_lms__user_login'])) {
        setcookie('times_academy_lms__user_login', '', time() - 3600, '/');
        unset($_COOKIE['times_academy_lms__user_login']);
        $alertDisplay = true;
    }

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Dashboard - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

    </head>

    <body class="overflow-hidden" <?= $onloadAttr ?>>
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Dashboard");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "./includes/header.php";
                        selectItem::setSelected("Dashboard");
                        ?>
                    </div>

                    <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                        <div class="row g-4">
                            <?php
                            for ($i = 0; $i < 8; $i++) {
                            ?>
                                <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                    <div class="post-card-skeleton skeleton w-100 px-5 py-5"></div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                        <div class="row g-4 mt-2 mb-4">
                            <?php
                            for ($i = 0; $i < 2; $i++) {
                            ?>
                                <div class="col-12 col-md-6 d-flex align-items-stretch">
                                    <?php include "./includes/components/skeleton-details-card.php"; ?>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                        <?php
                        include "./includes/components/skeleton-table.php";
                        ?>
                    </div>

                    <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                        <div class="row m-0 p-4 g-4">
                            <?php
                            if ($alertDisplay) {
                            ?>
                                <div class="alert-container p-0 m-0 g-0">
                                    <div class="alert alert-dismissible component-theme mb-4 br-0 custom-alert" role="alert" id="welcomeAlert">
                                        <div class="inner">
                                            <div class="app-card-body p-3 p-lg-4">
                                                <h3 class="mb-3">Welcome, <?= $sessionUserRole . " " . $sessionUserObject['user_fname'] . " " . $sessionUserObject['user_lname'] ?>!</h3>
                                                <div class="row gx-5 gy-3">
                                                    <div class="col-12 col-lg-9">
                                                        <p>Welcome back! Access academic tools, manage student records, monitor course progress, and stay informed with the latest updates from the university.</p>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="hideAlert()"></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                            <?php
                            if (SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole != "Lecturer") {
                                require_once "./includes/Dashboards/superAdminDashboard.php";
                                SuperAdminDashboard::loadContent();
                            } else if ($sessionUserRole == "Lecturer") {
                                require_once "./includes/Dashboards/lecturerDashboard.php";
                                LecturerDashboard::loadContent();
                            }
                            ?>

                        </div>
                        <footer>
                            <?php include './includes/footer.php' ?>
                        </footer>
                    </div>

                </div>
            </div>
        </div>

        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

    </body>

    </html>
<?php
}
?>