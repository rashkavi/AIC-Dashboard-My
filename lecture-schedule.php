<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once './includes/Project.php';
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Lecture Schedule - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/calender.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden" onload="initializeLecturerSchedule();">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("Lecture Schedule");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Lecture Schedule");
                                ?>
                            </div>
                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include './includes/components/skeleton-header.php';
                                include './includes/components/skeleton-calender.php'; ?>
                            </div>
                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-12 col-lg-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="lecture-schedule">Lecture Schedule</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="lec-schedule-desc">Manage and schedule academic lectures with ease and flexibility.</span>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                <div class="row gx-3 justify-content-end">
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                    ?>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="bg-transparent border-0 px-3" onclick="window.location = 'lecture-schedule-permissions'">
                                                                <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2)) {
                                                    ?>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" data-i18n="schedule-lecture" onclick="window.location = 'schedule-lecture'">Schedule a Lecture</button>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 gy-4 gx-3">
                                            <div class="container">
                                                <div class="col-12 d-flex flex-column flex-lg-row justify-content-center mb-4 mt-2">
                                                    <div class="col-2 col-lg-1 d-none d-lg-block">
                                                        <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-lg-10 d-flex justify-content-center align-items-center gap-2">
                                                        <h4 class="text-center" data-i18n="lecture-schedule">Lecture Schedule</h4>
                                                        <h4 class="text-center">-</h4>
                                                        <h4 class="text-center" id="month"></h4>
                                                        <h4 class="text-center" id="year"></h4>
                                                    </div>
                                                    <div class="col-12 col-lg-1 mt-3 d-block d-lg-none">
                                                        <div class="row">
                                                            <div class="col-6 d-flex justify-content-start">
                                                                <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                                                </button>
                                                            </div>
                                                            <div class="col-6 d-flex justify-content-end">
                                                                <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule()">
                                                                    <span class="text-14 main-text fw-semibold">Next</span>
                                                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-lg-1 text-end d-none d-lg-block">
                                                        <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule()">
                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div id="calendar" class="calendar"></div>
                                            </div>

                                            <!-- Event Modal -->
                                            <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="eventModalLabel">Events on <span id="selectedDate"></span></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body z-1" id="eventDetails"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal fade" id="lectureDetailsModal" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-start-0 modal-lg">
                                                    <div class="modal-content lecture-modal-content">
                                                        <div class="modal-header lecture-modal-header">
                                                            <h5 class="modal-title" id="lectureDetailsTitle">Lecture Details</h5>
                                                            <button type="button" class="btn-close text-dark-color" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body" id="lectureDetailsBody"></div>
                                                        <div class="modal-footer" id="lectureDetailsFooter">

                                                        </div>
                                                    </div>
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
            <script src="./assets/js/eventCalender.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
    }
}
