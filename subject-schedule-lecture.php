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
            header("Location: ./level-details");
        } else {
            $id = $_GET['subject-id'];
            $subject = Database::search("SELECT `subject_id`,`faculty`,`faculty_id`,`programme`,`programme_id`,`intake`,`intake_id`,`level`,`level_id`,`subject` FROM `subjects`
            INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`  
            WHERE `subject_id` = '" . $id . "'");
            if ($subject->num_rows == 0) {
                header("Location: ./level-details");
            } else {
                $subjectData = $subject->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('lecture_schedule', 2, $id))) {
                    header("Location: ./subject-lecture-schedule?subejct-id=" . $subjectData['subject_id']);
                } else {
                    $selectedDate = '';
                    if (isset($_GET['date']) && !empty($_GET['date'])) {
                        $selectedDate = $_GET['date'];
                    }
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Lectures Schedule - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                        <div class="col-12 col-md-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="schedule-lecture-title">Schedule Lecture</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./programes?faculty_id=<?php echo $subjectData['faculty_id'] ?>'><?= $subjectData["programme"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programme_id=<?php echo $subjectData['programme_id'] ?>'><?= $subjectData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./level?level_id=<?php echo $subjectData['level_id'] ?>'><?= $subjectData["level"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $subjectData["subject"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./subject-lecture-schedule?subject-id=<?= $subjectData['subject_id'] ?>'>Lecture Schedule</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Schedule a Lecture</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 g-0">
                                                            <div class="row gx-4 gy-3">
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Select Lecturer</label>
                                                                    <select name="" id="lecturer-selector" class="form-select custom-select mt-2">
                                                                        <option value="0" selected>- Select Lecturer -</option>
                                                                        <?php
                                                                        $subjectLeccturers = Database::search("
                                                                    SELECT `lecturers`.`lecturers_id`,`title`.`title`,`user_fname`,' ',`user_lname` FROM `lecturers`
                                                                    INNER JOIN `users`
                                                                    ON `lecturers`.`users_user_id`=`users`.`user_id`
                                                                    INNER JOIN `title`
                                                                    ON `users`.`title_title_id`=`title`.`title_id`
                                                                    WHERE `lecturers`.`status_status_id`='1' AND `users`.`status_status_id`='1' AND `lecturers`.`subjects_subject_id`='" . $id . "'");

                                                                        if ($subjectLeccturers->num_rows > 0) {
                                                                            while ($lecturer = $subjectLeccturers->fetch_assoc()) {
                                                                        ?>
                                                                                <option value="<?= $lecturer['lecturers_id'] ?>"><?= $lecturer['title'] . ' . ' . $lecturer['user_fname'] . ' ' . $lecturer['user_lname'] ?></option>
                                                                        <?php
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="lecturer-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Date</label>
                                                                    <input type="date" class="form-control custom-input mt-2" id="lecture-date" value="<?= $selectedDate ?>">
                                                                    <span id="date-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Pick Start Time</label>
                                                                    <input type="time" class="form-control custom-input mt-2" id="start-time">
                                                                    <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Pick End Time</label>
                                                                    <input type="time" class="form-control custom-input mt-2" id="end-time">
                                                                    <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Lecture Title</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="title">
                                                                    <span id="title-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Lecture Attachment</label>
                                                                    <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                        <span>Drag & Drop your files here</span>
                                                                        <span>OR</span>
                                                                        <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                        <input type="file" class="d-none" id="file-selector-input" multiple accept=".pdf">
                                                                        <div class="col-12 px-1 mt-4">
                                                                            <div class="row" id="list-section">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div id="attachment-feedback" class="text-dark-red text-12"></div>
                                                                    <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                                </div>

                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Lecture Type</label>
                                                                    <div class="col-12 d-flex mt-3">
                                                                        <div class="col-6 col-md-3 d-flex flex-column">
                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                <input type="checkbox"
                                                                                    id="physical-checkbox"
                                                                                    name="lecture_type[]"
                                                                                    value="physical"
                                                                                    class="form-check-input text-20 mt-0 mb-0 me-2 lecture-type-checkbox"
                                                                                    checked
                                                                                    data-dependent-field="lecture-link-darea"
                                                                                    data-dependent-action="hide">

                                                                                <label class="text-14 sub-text mx-2">Physical</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6 col-md-3 d-flex flex-column">
                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                <input type="checkbox"
                                                                                    id="online-checkbox"
                                                                                    name="lecture_type[]"
                                                                                    value="online"
                                                                                    class="form-check-input text-20 mt-0 mb-0 me-2 lecture-type-checkbox"
                                                                                    data-dependent-field="lecture-link-darea"
                                                                                    data-dependent-action="show">
                                                                                <label class="text-14 sub-text mx-2">Online</label>
                                                                            </div>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                                <div class="col-12 d-flex flex-column d-none" id="lecture-link-darea">
                                                                    <label class="text-14 main-text required" for="">Lecture Link</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="link-input">
                                                                    <span id="link-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                                    <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                        <button class="custom-btn w-100" onclick="scheduleLectureToSubject(this,<?= $id ?>);">Save Lecture</button>
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
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
} ?>