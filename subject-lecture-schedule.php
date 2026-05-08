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
            $subject = Database::search("SELECT `subject_id`,`faculty`,`faculty_id`,`programme`,`programme_id`,`intake`,`intake_id`,`level`,`level_id`,`subject`,`subject_id` FROM `subjects`
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

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subjectData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', $id) || $validLecturer)) {
                    header("Location: ./level-details");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Lecture Schedule - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/calender.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeSubjectLecturerSchedule(<?= $subjectData['subject_id'] ?>);">

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
                                            include './includes/components/skeleton-calender.php'; ?>
                                        </div>

                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="subject-lecture-schedule">Subject Lecture Schedule</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $subjectData['programme_id'] ?>"><?= $subjectData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./level-details?level-id=<?= $subjectData['level_id'] ?>"><?= $subjectData['level'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Lecture Schedule</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('lecture_schedule', 2, $id)) {
                                                        ?>
                                                            <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row align-items-center">
                                                                    <div class="col-12 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" onclick="window.location.href='subject-schedule-lecture?subject-id=<?= $id ?>'" data-i18n="schedule-lecture">Schedule Lecture</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-20 px-4 pe-4 component-theme">
                                                    <div class="row p-0 m-0 gy-4 gx-3">
                                                        <div class="container">
                                                            <div class="col-12 d-flex flex-column flex-lg-row justify-content-center mb-4 mt-2">
                                                                <div class="col-2 col-lg-1 d-none d-lg-block">
                                                                    <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Privious</span>
                                                                    </button>
                                                                </div>
                                                                <div class="col-12 col-lg-10 d-flex justify-content-center align-items-center gap-2">
                                                                    <h4 data-i18n="lecture-schedule">Lecture schedule</h4>
                                                                    <h4>-</h4>
                                                                    <h4 id="month"></h4>
                                                                    <h4 id="year"></h4>
                                                                </div>
                                                                <div class="col-12 col-lg-1 mt-3 d-block d-lg-none">
                                                                    <div class="row">
                                                                        <div class="col-6 d-flex justify-content-start">
                                                                            <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                                <span class="text-14 main-text fw-semibold">Privious</span>
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
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/eventCalender.js?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
} ?>