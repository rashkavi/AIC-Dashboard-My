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
            $subject = Database::search("
            SELECT 
                `subjects`.`subject_id`,
                `faculties`.`faculty`,
                `faculties`.`faculty_id`,
                `programmes`.`programme`,
                `programmes`.`programme_id`,
                `intakes`.`intake`,
                `intakes`.`intake_id`,
                `levels`.`level`,
                `levels`.`level_id`,
                `subjects`.`subject`,
                `subjects`.`subject_id`
            FROM `subjects`
            INNER JOIN `levels` 
                ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` 
                ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `subjects`.`subject_id` = '" . $id . "'
        ");
        
            if ($subject->num_rows == 0) {
                header("Location: ./selected-subject");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('exams', 2, $id))) {
                    header("Location: ./subjects");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Examination - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg" data-i18n="add-examination"></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $subjectData['faculty_id'] ?>"><?= $subjectData['faculty'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programe-id=<?= $subjectData['programme_id'] ?>"><?= $subjectData['programme'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-examinations?subject-id=<?= $subjectData['subject_id'] ?>">Examinations</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Examination</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                                <div class="col-12 py-4 px-4 component-theme">
                                                    <div class="row gy-3 align-content-center">
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 sub-topic">Examination Basic Information</label>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Examination Code</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="exam-code">
                                                            <span class="text-12 text-dark-red" id="exam-code-feedback"></span>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Examination Name</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="exam-name">
                                                            <span class="text-12 text-dark-red" id="exam-code-feedback"></span>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Examination Date</label>
                                                            <input type="date" class="col-6 form-control custom-input mt-2" id="exam-date">
                                                            <span class="text-12 text-dark-red" id="exam-date-feedback"></span>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Examination Type</label>
                                                            <select name="" class="form-select custom-select mt-2" id="exam-type" onchange="loadExaminationAdvancedDetails();">
                                                                <option value="0">- Select Examination Type -</option>
                                                                <?php
                                                                $exam_type = Database::search("SELECT * FROM `exam_types` WHERE `exam_type_id` != 3");
                                                                while ($data = $exam_type->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["exam_type_id"]; ?>"><?php echo $data["exam_type"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="w-100 custom-btn" onclick="addExamination('<?php echo $id; ?>');">Add Examination</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 pb-4 px-4 component-theme d-none" id="physical-examination">
                                                    <div class="row gy-3 align-content-center">
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 sub-topic">Examination Advanced Information</label>
                                                        </div>
                                                        <div class="col-6 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Examination Start Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="physical-start-time">
                                                            <span class="text-12 text-dark-red" id="physical-start-time-feedback"></span>
                                                        </div>
                                                        <div class="col-6 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Examination End Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="physical-end-time">
                                                            <span class="text-12 text-dark-red" id="physical-end-time-feedback"></span>
                                                        </div>
                                                        <div class="col-6 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Examination Duration</label>
                                                            <input type="number" class="form-control custom-input mt-2" id="physical-duration">
                                                            <span class="text-12 text-dark-red" id="physical-duration-feedback"></span>
                                                        </div>

                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Examination Location</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="physical-location">
                                                            <span class="text-12 text-dark-red" id="physical-location-feedback"></span>
                                                        </div>

                                                        <div class="col-12 d-flex align-items-center">
                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                            <span class="text-14 main-text">Release Admission</span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Admission Closing Date & Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex">
                                                                        <input type="date" class="col-6 form-control custom-input">
                                                                    </div>
                                                                    <div class="col-6 d-flex">
                                                                        <input type="time" class="col-6 form-control custom-input">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="w-100 custom-btn" onclick="scheduleExamination('<?php echo $id; ?>');">Schedule Examination</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 pb-4 px-4 component-theme d-none" id="online-examination">
                                                    <div class="row gy-3 align-content-center">
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 sub-topic">Examination Advanced Information</label>
                                                        </div>
                                                        <div class="col-6 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Examination Start Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="online-start-time">
                                                            <span class="text-12 text-dark-red" id="online-start-time-feedback"></span>
                                                        </div>
                                                        <div class="col-6 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Examination End Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="online-end-time">
                                                            <span class="text-12 text-dark-red" id="online-end-time-feedback"></span>
                                                        </div>
                                                        <div class="col-6 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Examination Duration</label>
                                                            <input type="number" class="form-control custom-input mt-2" id="online-duration">
                                                            <span class="text-12 text-dark-red" id="online-duration-feedback"></span>
                                                        </div>
                                                        <div class="col-6 col-lg-4 d-flex flex-column d-none" id="quiz-type-section">
                                                            <label for="" class="text-14 main-text">Examination Question Display type</label>
                                                            <select name="" id="quiz-type" class="form-select custom-select mt-2">
                                                                <option value="0">Select Question Display Type</option>
                                                                <?php
                                                                $quiz_types = Database::search("SELECT * FROM `quiz_type`");
                                                                foreach ($quiz_types as $quiz_type) {
                                                                ?>
                                                                    <option value="<?php echo $quiz_type["quiz_type_id"]; ?>"><?php echo $quiz_type["quiz_type"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="quiz-type-feedback"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Questions CSV</label>
                                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <div class="col-12">
                                                                    <div class="row g-2 justify-content-center align-items-center">
                                                                        <button class="custom-btn bg-light-theme dark-theme px-4 col-5 col-lg-2" onclick="downloadOnlineExaminationCSVFormat();">Download CSV Format</button>
                                                                        <button class="custom-btn px-4 mx-4 col-5 col-lg-2" id="file-selector">Browse Files</button>
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

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="w-100 custom-btn" onclick="scheduleExamination('<?php echo $id; ?>');">Schedule Examination</button>
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
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>