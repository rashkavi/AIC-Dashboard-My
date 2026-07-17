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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['subject-id']) && !empty($_GET['subject-id']))) {
            header("Location: ./selected-subject");
        } else {

            $id = $_GET['subject-id'];
            $subject = Database::search("SELECT `subject_id`,`faculty`,`faculty_id`,`programme`,`programme_id`,`intake`,`intake_id`,`level`,`level_id`,`subject`,`subject_id` FROM `subjects`
            INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`  
            WHERE `subject_id` = '" . $id . "'");

            if ($subject->num_rows == 0) {
                header("Location: ./selected-subject");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 2, $id))) {
                    header("Location: ./subject-assignments?subject-id=" . $id);
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Add Assignments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

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
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg" data-i18n="assignments">Assignments</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $subjectData['faculty_id'] ?>"><?= $subjectData['faculty'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id=<?= $subjectData['level_id'] ?>"><?= $subjectData['level'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-assignments?subject-id=<?= $subjectData['subject_id'] ?>">Assignments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Assignments</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row gy-3 align-content-center">
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Assignment Code</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="code">
                                                            <span class="text-12 text-dark-red" id="code-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-8 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Assignment Topic</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="topic">
                                                            <span class="text-12 text-dark-red" id="topic-feedback"></span>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Start Date & Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="start-date">
                                                                        <span class="text-12 text-dark-red" id="start-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="start-time">
                                                                        <span class="text-12 text-dark-red" id="start-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">End Date & Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="end-date" onchange="acceptFutureDate('end-date');">
                                                                        <span class="text-12 text-dark-red" id="end-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="end-time">
                                                                        <span class="text-12 text-dark-red" id="end-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Assigment Document</label>
                                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" accept=".pdf">
                                                                <div class="col-6 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="text-12 text-dark-red" id="file-feedback"></span>
                                                        </div>

                                                        <div class="col-12 col-lg-4 d-flex flex-column" id="recorection-fee-add-area">
                                                            <label for="" class="text-14 main-text required">Plagiarism Report</label>
                                                            <select name="" class="form-select custom-select mt-2" disabled id="plagiarism-report">
                                                                <?php
                                                                $plagiarismStatus = Database::search("SELECT * FROM `plagiarism_status`");
                                                                while ($data = $plagiarismStatus->fetch_assoc()) {
                                                                ?>
                                                                    <option <?= $data['plagiarism_status_id'] == 2 ? 'selected' : '' ?> value="<?= $data['plagiarism_status_id'] ?>"><?= $data['plagiarism_status'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="recorrection-fee-feedback"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="w-100 custom-btn" onclick="addAssignment(this,'<?php echo $id; ?>');">Save Assgnment</button>
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
                        <script>
                            const today = new Date().toISOString().split("T")[0];
                            document.getElementById('start-date').setAttribute("min", today);
                            document.getElementById('end-date').setAttribute("min", today);

                            const now = new Date();
                            const currentTime = now.toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            //console.log(currentTime);

                            document.getElementById('start-time').setAttribute("min", currentTime);
                            document.getElementById('end-time').setAttribute("min", currentTime);
                        </script>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js"></script>
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
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