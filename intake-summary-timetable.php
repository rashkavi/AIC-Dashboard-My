<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/IntakeTimetableManager.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        $id = isset($_GET['intake-id']) ? (int) $_GET['intake-id'] : 0;
        $intakeData = $id > 0 ? IntakeTimetableManager::getIntake($id) : null;

        if (!$intakeData) {
            header("Location: ./intakes");
        } else if (!IntakeTimetableManager::canView($sessionUserRole, $id)) {
            header("Location: ./intake-details?intake-id=" . $id);
        } else {
            $canManage = IntakeTimetableManager::canManage($sessionUserRole, $id);
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Summary Timetable - Admin Dashboard | <?= Project::getBaseName(); ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/intake-summary-timetable.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializeIntakeSummaryTimetables();">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Course Overview");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Course Overview");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">

                                        <!-- Header -->
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-lg-9">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Summary Timetable</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./programes?faculty-id=<?= (int) $intakeData['faculty_id'] ?>"><?= htmlspecialchars($intakeData['faculty']) ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./intakes?programe-id=<?= (int) $intakeData['programme_id'] ?>">...</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./intake-details?intake-id=<?= $id ?>"><?= htmlspecialchars($intakeData['intake']) ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Summary Timetable</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <?php
                                                if ($canManage) {
                                                ?>
                                                    <div class="col-12 col-md-12 col-lg-3 mt-3 mt-lg-0">
                                                        <div class="row align-items-center">
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="window.location.href='add-intake-summary-timetable?intake-id=<?= $id ?>'">
                                                                    Create Timetable
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <!-- Active timetable -->
                                        <div class="col-12 px-4 py-4 component-theme mb-4">
                                            <div class="row gx-4 gy-3 align-items-center p-0 m-0" id="tt-active-card">
                                                <span class="text-14 sub-text p-0">Loading…</span>
                                            </div>
                                        </div>

                                        <!-- All timetables -->
                                        <div class="col-12 p-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 component-theme mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <span class="text-14 dark-theme fw-medium" id="tt-count">0 Timetables</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <span class="text-14 dark-theme fw-medium" id="tt-student-count">0 Students</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-8 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="tt-search-text" onkeyup="filterIntakeSummaryTimetables()">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive overflow-visible">
                                                        <table class="table bl-solid m-0 p-0 g-0">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center w-25">Timetable</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Modules</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Created Date</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Created By</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Last Sent</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="tt-list-body">
                                                                <tr>
                                                                    <td colspan="7" class="py-4 text-center text-14 sub-text">Loading…</td>
                                                                </tr>
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

                <!-- Preview modal => Start -->
                <div class="modal fade br-0" id="ttPreviewModal" aria-hidden="true" aria-labelledby="ttPreviewLabel" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="ttPreviewLabel">Timetable</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3" id="tt-preview-body"></div>
                            <div class="modal-footer p-0 mt-4 border-0">
                                <div class="col-12">
                                    <div class="row gx-3 gy-2 justify-content-end">
                                        <div class="col-12 col-md-4 col-lg-3">
                                            <button type="button" class="custom-btn d-flex align-items-center justify-content-center w-100" id="tt-preview-download">
                                                Download PDF
                                                <iconify-icon icon="line-md:download-loop" width="22" height="22" class="text-white mx-2"></iconify-icon>
                                            </button>
                                        </div>
                                        <?php
                                        if ($canManage) {
                                        ?>
                                            <div class="col-12 col-md-4 col-lg-3">
                                                <button type="button" class="custom-btn d-flex align-items-center justify-content-center w-100" id="tt-preview-notify">
                                                    Notify Students
                                                    <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="text-white mx-2"></iconify-icon>
                                                </button>
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
                <!-- Preview modal => End -->

                <?php
                if ($canManage) {
                ?>
                    <!-- Notify modal => Start -->
                    <div class="modal fade br-0" id="ttNotifyModal" aria-hidden="true" aria-labelledby="ttNotifyLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="ttNotifyLabel">Notify Students</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-0">
                                        <div class="row p-0 gy-3 gx-3">
                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text">Timetable</label>
                                                <input type="text" class="form-control custom-input mt-2" id="tt-notify-title" disabled>
                                            </div>
                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text">Recipients</label>
                                                <input type="text" class="form-control custom-input mt-2" id="tt-notify-count" disabled>
                                            </div>
                                            <div class="col-12">
                                                <p class="text-14 sub-text m-0">The timetable PDF is attached to the email. Students are added in BCC, so they cannot see each other's email addresses.</p>
                                                <p class="text-14 text-dark-red m-0 mt-2 d-none" id="tt-notify-warning"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer p-0 mt-4 border-0">
                                    <div class="col-12 col-md-4">
                                        <button type="button" class="custom-btn m-0 w-100" id="tt-notify-confirm">Send Email</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Notify modal => End -->

                    <!-- Status confirm modal => Start -->
                    <div class="modal fade br-0" id="ttStatusModal" aria-hidden="true" aria-labelledby="ttStatusLabel" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="ttStatusLabel">Change Status</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <p class="text-14 main-text m-0" id="tt-status-text"></p>
                                </div>
                                <div class="modal-footer p-0 mt-4 border-0">
                                    <div class="col-12 col-md-5">
                                        <button type="button" class="custom-btn m-0 w-100" id="tt-status-confirm">Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Status confirm modal => End -->
                <?php
                }
                ?>

                <script>
                    window.TT_CONFIG = {
                        intakeId: <?= (int) $id ?>,
                        timetableId: 0,
                        canManage: <?= $canManage ? 'true' : 'false' ?>,
                        instituteName: <?= json_encode(Project::getBaseName()) ?>,
                        logoUrl: './assets/imgs/common/logo.png',
                        programme: <?= json_encode($intakeData['programme']) ?>,
                        intake: <?= json_encode($intakeData['intake']) ?>
                    };
                </script>
                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/intake-summary-timetable.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}
?>