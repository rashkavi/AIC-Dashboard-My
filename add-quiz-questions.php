<?php
require_once './includes/UserClass.php';
require_once "./includes/UserPermissionsClass.php";
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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['quiz-id']) && !empty($_GET['quiz-id']))) {
            header("Location: ./subjects");
        } else {
            $id = $_GET['quiz-id'];

            $quiz = Database::search("
            SELECT 
                `q`.`quiz_id`,
                `e`.`exam_id`,
                `s`.`subject_id`,
                `f`.`faculty`,
                `f`.`faculty_id`,
                `p`.`programme`,
                `p`.`programme_id`,
                `i`.`intake`,
                `i`.`intake_id`,
                `l`.`level`,
                `l`.`level_id`,
                `s`.`subject`
            FROM `quiz` AS `q`
            INNER JOIN `exams` AS `e` ON `q`.`exams_exam_id` = `e`.`exam_id`
            INNER JOIN `subjects` AS `s` ON `e`.`subjects_subject_id` = `s`.`subject_id`
            INNER JOIN `levels` AS `l` ON `s`.`levels_level_id` = `l`.`level_id`
            INNER JOIN `intakes` AS `i` ON `l`.`intakes_intake_id` = `i`.`intake_id`
            INNER JOIN `programmes` AS `p` ON `i`.`programmes_programme_id` = `p`.`programme_id`
            INNER JOIN `faculties` AS `f` ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
            WHERE `q`.`quiz_id` = '" . $id . "'
        ");

            if ($quiz->num_rows == 0) {
                header("Location: ./subjects");
            } else {
                $quizData = $quiz->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 2, $quizData['subjects_subject_id']))) {
                    header("Location: ./subject-quizes?subject-id=" . $quizData['subjects_subject_id']);
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Quiz Questions - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                        <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
                        <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeAddQuizQuestion();">
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
                                            include './includes/components/skeleton-header.php';
                                            include './includes/components/skeleton-add-edit.php'; ?>
                                        </div>
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-12 col-lg-6">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Add Quiz Questions</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $quizData['faculty_id'] ?>"><?= $quizData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $quizData['subject_id'] ?>"><?= $quizData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-examinations?subject-id=<?= $quizData['subject_id'] ?>">Examinations</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Quiz Questions</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 col-lg-6 mt-3 mt-lg-0">
                                                            <div class="row gx-3">
                                                                <div class="col-6 col-md-4 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" data-bs-target="#quizBulkUpload" data-bs-toggle="modal">Add Questions CSV File</button>
                                                                </div>
                                                                <div class="col-6 col-md-4 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" id="add-quiz-btn">Add New Question</button>
                                                                </div>
                                                                <div class="col-12 col-md-4 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" id="save-quiz-btn" onclick="saveQuizQuestions(<?= $quizData['quiz_id'] ?>,<?= $quizData['subject_id'] ?>)">Save Questions</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12" id="empty-question-display-area">
                                                    <div class="row p-0 m-0 g-0 component-theme justify-content-center p-5">
                                                        <img src="./assets/imgs/common/questions-concept.png" class="w-25 h-auto" alt="Select User JPG" />
                                                        <div class="col-12 d-flex align-items-center justify-content-center">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme d-none" id="quiz-questions-container">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 m-0 g-0">
                                                            <div class="row p-0 m-0" id="quiz-container">
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

                        <!-- Quiz Bulk Upload Model => Start -->
                        <div class="modal fade br-0" id="quizBulkUpload" aria-hidden="true" aria-labelledby="quizBulkUploadLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="QuizBulkUploadLabel">Add Quiz Bulk</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0 component-theme mb-4">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Upload Quiz CSV</label>
                                                    <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                        <span>Drag & Drop your files here</span>
                                                        <span>OR</span>
                                                        <div class="col-12 mt-3">
                                                            <div class="row gap-3 align-items-center justify-content-center">
                                                                <button class="custom-btn px-4 col-4" onclick="downloadQuizCSVFormat()">Download CSV Format</button>
                                                                <button class="custom-btn px-4 col-4" id="file-selector">Browse Files</button>
                                                            </div>
                                                        </div>


                                                        <input type="file" class="d-none" id="file-selector-input" accept=".csv">
                                                        <div class="col-12 px-1 mt-4">
                                                            <div class="row" id="list-section">

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="file-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 m-0 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0">Upload</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Quiz Bulk Upload Model => End -->

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>