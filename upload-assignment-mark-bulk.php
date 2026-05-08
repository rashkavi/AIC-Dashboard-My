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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['assignment-id']) && !empty($_GET['assignment-id']) && isset($_GET['status-id']) && !empty($_GET['status-id']) && ($_GET['status-id'] == 1 || $_GET['status-id'] == 2))) {
            header("Location: ./dashboard");
        } else {
            $id = $_GET['assignment-id'];
            $status_id = $_GET['status-id'];
            $assignment = Database::search("SELECT * FROM `assignments`
            INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `assignment_id` = '" . $id . "'");
            if ($assignment->num_rows == 0) {
                header("Location: ./subject-assignments");
            } else {
                $assignmentData = $assignment->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('assignments', 20, $assignmentData['subjects_subject_id']))) {
                    header("Location: ./assignment");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title><?= $status_id == 1 ? "Upload Assignment Marks Bulk" : "Upload Assignment Recorrection Marks Bulk" ?> - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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

                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0" id="accordionParent">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?= $status_id == 1 ? "Upload Assignment Marks Bulk" : "Upload Assignment Recorrection Marks Bulk" ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $assignmentData['faculties_faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programe-id=<?= $assignmentData['programme_id'] ?>'><?= $assignmentData["programme"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $id ?>"><?= $assignmentData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $status_id == 1 ? "Upload Assignment Marks Bulk" : "Upload Assignment Recorrection Marks Bulk" ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 component-theme p-4">
                                                    <div class="row p-0 m-0">
                                                        <div class="col-12 bl-solid p-0 m-0 d-flex flex-column">
                                                            <div class="p-2 d-flex flex-column align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <div class="col-12 mt-3">
                                                                    <div class="row gap-3 align-items-center justify-content-center">
                                                                        <button class="custom-btn px-4 col-4" onclick="downloadMarksUploadCSV('<?= $assignmentData['assignment_code']; ?>')">Download CSV Format</button>
                                                                        <button class="custom-btn px-4 col-4" id="file-selector">Browse Files</button>
                                                                    </div>
                                                                </div>
                                                                <input type="file" class="d-none" id="file-selector-input" accept=".csv">
                                                            </div>
                                                            <div class="col-12 p-4 d-none" id="list-section-container">
                                                                <div class="row">
                                                                    <div class="col-12">
                                                                        <div class="row" id="list-section">

                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12 text-end">
                                                                        <?php
                                                                        if ($status_id == 1) {
                                                                        ?>
                                                                            <button class="custom-btn px-5" type="button" onclick="uploadAssignmentsMarksBulk(this,<?= $id ?>);">
                                                                                Upload Marks Bulk
                                                                            </button>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <button class="custom-btn px-5" type="button" onclick="uploadRecorrectionAssignmentsMarksBulk(this,<?= $id ?>);">
                                                                                Upload Marks Bulk
                                                                            </button>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="text-12 text-dark-red p-2" id="csv-file-feedback"></span>
                                                        </div>

                                                        <div class="container p-0 mt-4">
                                                            <div class="bulk-instructions-header">
                                                                <h4 class="sub-topic-color">Marks Bulk Upload Instructions</h4>
                                                                <p class="bulk-instructions-subtitle">Follow these guidelines to ensure successful marks data import</p>
                                                            </div>

                                                            <div class="bulk-instructions-note p-3">
                                                                <div class="bulk-instructions-note-title text-16 fw-semibold mb-1">
                                                                    Important Upload Guidelines
                                                                </div>

                                                                <p class="text-14 mt-1">
                                                                    <strong class="text-danger">All fields must be completed accurately.</strong></br>
                                                                    Ensure your CSV file adheres to the required field structure, value types, and date formats provided in the instructions.
                                                                </p>

                                                                <p class="text-14 mt-2">
                                                                    During validation, the system will automatically detect missing information, invalid formats, and improperly structured records.
                                                                    Rows containing errors will not be processed, so please review your file thoroughly before uploading.
                                                                </p>
                                                            </div>

                                                            <div class="bulk-instructions-section">
                                                                <h2 class="bulk-instructions-section-title">Student ID Requirements</h2>
                                                                <p class="bulk-instructions-section-content">
                                                                    Please ensure the Student ID follows the required format and character limits:
                                                                </p>

                                                                <ul class="bulk-instructions-list">
                                                                    <li><strong>Maximum Length:</strong> 45 characters</li>
                                                                    <li><strong>Allowed Characters:</strong> Letters (A–Z), Numbers (0–9), Forward Slashes (/), and Hyphens (-)</li>
                                                                    <li><strong>Important:</strong> The Student ID you provide will be used to <strong>Student Intake ID</strong> in the system.</li>
                                                                </ul>

                                                                <p class="bulk-instructions-section-content text-danger">
                                                                    <strong>Notes:</strong><br>
                                                                    - Student ID must be unique for each student.<br>
                                                                    - Exceeding character limits or using invalid characters will generate a row-level validation error.<br>
                                                                    - Incorrect Student ID format may result in incorrect or failed Student Intake ID creation.
                                                                </p>
                                                            </div>

                                                            <div class="bulk-instructions-section">
                                                                <h2 class="bulk-instructions-section-title">Marks Column Requirements</h2>
                                                                <p class="bulk-instructions-section-content">
                                                                    Please ensure the <strong>Marks</strong> column follows the correct input format to avoid validation errors:
                                                                </p>

                                                                <ul class="bulk-instructions-list">
                                                                    <li><strong>Required Field:</strong> Marks must be provided for every student.</li>
                                                                    <li><strong>Value Type:</strong> Only numeric values are allowed (0–100).</li>
                                                                    <li><strong>Valid Range:</strong> Marks must be between <strong>0 and 100</strong> inclusive.</li>
                                                                    <li><strong>No Symbols or Letters:</strong> Do not include %, commas, decimals, or text.</li>
                                                                    <li><strong>Maximum Length:</strong> 3 digits (e.g., 0, 25, 100).</li>
                                                                </ul>

                                                                <p class="bulk-instructions-section-content text-danger">
                                                                    <strong>Notes:</strong><br>
                                                                    - Marks outside the valid range will generate a row-level validation error.<br>
                                                                    - Empty or non-numeric marks values will cause the row to be rejected.<br>
                                                                    - If marks are not yet available, use a placeholder such as <strong>0</strong> (depending on system rules).
                                                                </p>
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
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/bulkfileChooser.js?v=<?= Project::getVersion() ?>"></script>

                    </body>

                    </html>
<?php
                }
            }
        }
    }
} ?>