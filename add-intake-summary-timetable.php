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

        $id          = isset($_GET['intake-id']) ? (int) $_GET['intake-id'] : 0;
        $timetableId = isset($_GET['timetable-id']) ? (int) $_GET['timetable-id'] : 0;
        $intakeData  = $id > 0 ? IntakeTimetableManager::getIntake($id) : null;

        // Editing: the timetable must belong to this intake
        $timetable = null;
        if ($intakeData && $timetableId > 0) {
            $timetable = IntakeTimetableManager::getHeader($timetableId);
            if (!$timetable || (int) $timetable['intake_id'] !== $id) {
                $timetable = null;
                $timetableId = -1;
            }
        }

        if (!$intakeData) {
            header("Location: ./intakes");
        } else if (!IntakeTimetableManager::canManage($sessionUserRole, $id) || $timetableId === -1) {
            header("Location: ./intake-summary-timetable?intake-id=" . $id);
        } else {
            $isEdit   = $timetableId > 0;
            $pageName = $isEdit ? 'Edit Timetable' : 'Create Timetable';
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title><?= $pageName ?> - Admin Dashboard | <?= Project::getBaseName(); ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/intake-summary-timetable.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializeAddIntakeSummaryTimetable();">
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
                                    include "./includes/components/skeleton-add-edit.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">

                                        <!-- Header -->
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg"><?= $pageName ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./intakes?programe-id=<?= (int) $intakeData['programme_id'] ?>"><?= htmlspecialchars($intakeData['programme']) ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./intake-details?intake-id=<?= $id ?>"><?= htmlspecialchars($intakeData['intake']) ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./intake-summary-timetable?intake-id=<?= $id ?>">Summary Timetable</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $pageName ?></a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row h-100 justify-content-end align-items-center gx-3">
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" type="button" onclick="previewTimetableDraft()">Preview</button>
                                                        </div>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" type="button" onclick="saveIntakeSummaryTimetable(this)">Save Timetable</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">

                                                <!-- Replace notice (create only, when an active timetable exists) -->
                                                <div class="col-12 p-0 g-0 mb-4 d-none" id="tt-replace-note">
                                                    <div class="tt-notice d-flex align-items-start p-3">
                                                        <iconify-icon icon="iwwa:info" width="22" height="22" class="me-2 flex-shrink-0"></iconify-icon>
                                                        <div class="flex-grow-1">
                                                            <span class="text-14 main-text" id="tt-replace-text"></span>
                                                        </div>
                                                        <button type="button" class="custom-btn px-3 py-2 text-14 ms-3 flex-shrink-0" onclick="copyActiveTimetableIntoForm()">Start From Active</button>
                                                    </div>
                                                </div>

                                                <!-- Server error -->
                                                <div class="col-12 p-0 g-0 mb-4 d-none" id="tt-errors"></div>

                                                <!-- Timetable details -->
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <span class="sub-topic">Timetable Details</span>
                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="tt-title">Timetable Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="tt-title" maxlength="150" placeholder="e.g. Semester 1 Timetable">
                                                            <span id="tt-title-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                            <label class="text-14 main-text not-required" for="tt-intake">Intake</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="tt-intake" value="<?= htmlspecialchars($intakeData['programme'] . ' - ' . $intakeData['intake']) ?>" disabled>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text not-required" for="tt-note">Note (shown at the bottom of the PDF)</label>
                                                            <textarea class="form-control custom-input mt-2" id="tt-note" rows="3" maxlength="2000"></textarea>
                                                            <span id="tt-note-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Sections are rendered here -->
                                                <div class="col-12 p-0 g-0" id="tt-sections"></div>

                                                <div class="col-12 p-0 g-0 mt-4">
                                                    <div class="row p-0 m-0 gx-4 gy-3">
                                                        <div class="col-12 col-md-6 col-lg-4 p-0 m-0">
                                                            <button type="button" class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="addTimetableSection(true)">
                                                                Add New Section
                                                                <iconify-icon icon="mdi:table-plus" width="20" height="20" class="text-white mx-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-5">
                                                    <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                        <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                            <button class="custom-btn w-100" type="button" onclick="saveIntakeSummaryTimetable(this)">Save Timetable</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <datalist id="tt-day-options">
                                                    <option value="Every Monday">
                                                    <option value="Every Tuesday">
                                                    <option value="Every Wednesday">
                                                    <option value="Every Thursday">
                                                    <option value="Every Friday">
                                                    <option value="Every Saturday">
                                                    <option value="Every Sunday">
                                                    <option value="Every Weekday">
                                                    <option value="Every Weekend">
                                                    <option value="Saturday">
                                                    <option value="Sunday">
                                                </datalist>
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
                                <h1 class="modal-title text-16 text-dark-color" id="ttPreviewLabel">Preview</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3" id="tt-preview-body"></div>
                            <div class="modal-footer p-0 mt-4 border-0">
                                <div class="col-12 col-md-4 col-lg-3">
                                    <button type="button" class="custom-btn d-flex align-items-center justify-content-center w-100" id="tt-preview-download">
                                        Download PDF
                                        <iconify-icon icon="line-md:download-loop" width="22" height="22" class="text-white mx-2"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Preview modal => End -->

                <script>
                    window.TT_CONFIG = {
                        intakeId: <?= (int) $id ?>,
                        timetableId: <?= (int) $timetableId ?>,
                        canManage: true,
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