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
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(
            SessionUser::ifUserIsPro($sessionUserRole) ||
            SessionUser::ifUserIsAcademic($sessionUserRole) ||
            SessionUser::ifUserIsCounsiler($sessionUserRole) ||
            SessionUser::ifUserIsLecturer($sessionUserRole) ||
            UserPermissions::validPermisisonType('super_lecture_schedule') ||
            UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))
        ) {
            header("Location: ./dashboard");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Attendance - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Attendance");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Attendance");
                                    ?>
                                </div>

                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>

                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-xxl-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="">Attendance</span>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="attendance-desc">Effortlessly manage students, access profiles, and maintain oversight</span>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent:live-16-filled" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Ongoing Lecture Attendance</span>
                                                        <span class="text-14 main-text">Manage student accounts, details, and activities in one place.</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <button class="custom-btn w-100" onclick="window.location = 'ongoing-lectures'">View</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:student" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Students Attendance Breakdown</span>
                                                        <span class="text-14 main-text">Manage student accounts, details, and activities in one place.</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <button class="custom-btn w-100" onclick="window.location = 'students-attendance-breakdown'">View</button>
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
                <script src="./assets/js/fileChooser.js"></script>


            </body>

            </html>
<?php
        }
    }
}
?>