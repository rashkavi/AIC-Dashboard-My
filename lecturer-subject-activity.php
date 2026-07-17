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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecturer-id']) && !empty($_GET['lecturer-id']))) {
            header("Location: ./intake-details");
        } else {
            $id = $_GET['lecturer-id'];
            $lecturer = Database::search("
            SELECT 
                `lecturers`.`lecturers_id`,
                `users`.`user_fname`,
                `users`.`user_lname`,
                `title`.`title`,
                `intakes`.`intake`,
                `intakes`.`intake_id`,
                `levels`.`level`,
                `levels`.`level_id`,
                `programmes`.`programme`,
                `faculties`.`faculty`,
                `programmes`.`faculties_faculty_id`,
                `subjects`.`subject`,
                `subjects`.`subject_id`,
                `subjects`.`max_course_hours`
            FROM `lecturers`
            INNER JOIN `users` 
                ON `lecturers`.`users_user_id` = `users`.`user_id`
            INNER JOIN `title` 
                ON `users`.`title_title_id` = `title`.`title_id`
            INNER JOIN `subjects` 
                ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
            INNER JOIN `levels` 
                ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` 
                ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `lecturers`.`lecturers_id` = '" . $id . "'
        ");

            if ($lecturer->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $lecturerData = $lecturer->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $timezone = new DateTimeZone("Asia/Colombo");
                $dateTime = new DateTime("now", $timezone);
                $todayDate = $dateTime->format('Y-m-d');
                $nowTime = $dateTime->format('H:i:s');

                $result = Database::search("
                SELECT 
                    CONCAT(
                        LPAD(FLOOR(SUM(`duration`) / 60), 2, '0'), 
                        ' hours ',
                        LPAD(MOD(SUM(`duration`), 60), 2, '0'),
                        ' min'
                    ) AS total_completed_hours
                FROM `time_table`
                WHERE `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'
                    AND `lecturers_lecturers_id` = '" . $id . "'
                    AND (
                        (`date` = '" . $todayDate . "' 
                            AND `start_time` <= '" . $nowTime . "' 
                            AND `end_time` <= '" . $nowTime . "')
                        OR `date` < '" . $todayDate . "'
                    )
                    AND `time_table`.`time_table_status_time_table_status_id` = '1'
            ");

                $totalCompletedHourse = $result->fetch_assoc()['total_completed_hours'];
                $totalCompletedHourse = floatval($totalCompletedHourse);
                $maxCourseHours = floatval($lecturerData['max_course_hours']);

                $completeProgress = $maxCourseHours > 0
                    ? ($totalCompletedHourse / $maxCourseHours) * 100
                    : 0;

                $assignements = Database::search("SELECT 
                COUNT(*) AS total_count,
                IFNULL(SUM(CASE WHEN lecturers_has_students_has_assignments.submission_sts_submission_sts_id = 2 THEN 1 ELSE 0 END), 0) AS submitted_count,
                IFNULL(SUM(CASE WHEN lecturers_has_students_has_assignments.submission_sts_submission_sts_id = 1 THEN 1 ELSE 0 END), 0) AS not_submitted_count
            FROM 
                lecturers_has_students_has_assignments
            INNER JOIN 
                students_has_assignments 
                ON lecturers_has_students_has_assignments.students_has_assignments_students_has_assignments_id = students_has_assignments.students_has_assignments_id
            INNER JOIN
                assignments 
                ON students_has_assignments.assignments_assignment_id = assignments.assignment_id
            WHERE 
                lecturers_has_students_has_assignments.lecturers_lecturers_id = '" . $id . "'
                AND assignments.subjects_subject_id = '" . $lecturerData['subject_id'] . "'
            ");

                $assignementsData = $assignements->fetch_assoc();
                $assignementsProgress = 0;
                if ($assignementsData['total_count'] > 0) {
                    $assignementsProgress = ($assignementsData['submitted_count'] / $assignementsData['total_count']) * 100;
                }

                $totalIncome = 0;
                $pendingPayments = 0;
                $rejectedPayments = 0;

                $assignmentsPayments = Database::search("SELECT * FROM `assignment_payments`
                INNER JOIN `assignments` ON `assignment_payments`.`assignments_assignment_id` = `assignments`.`assignment_id` 
                WHERE `assignment_payments`.`lecturers_lecturers_id` = '" . $id . "' AND `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'");

                if ($assignmentsPayments->num_rows > 0) {
                    while ($assignmentPayment = $assignmentsPayments->fetch_assoc()) {
                        if ($assignmentPayment['earning_status_earning_status_id'] == 1) {
                            $pendingPayments += $assignmentPayment['amount'];
                        } else if ($assignmentPayment['earning_status_earning_status_id'] == 2) {
                            $totalIncome += $assignmentPayment['amount'];
                        } else if ($assignmentPayment['earning_status_earning_status_id'] == 3) {
                            $rejectedPayments += $assignmentPayment['amount'];
                        }
                    }
                }

                $examPayments = Database::search("SELECT * FROM `exam_payments`
                INNER JOIN `exams` ON `exam_payments`.`exams_exam_id` = `exams`.`exam_id` 
                WHERE `exam_payments`.`lecturers_lecturers_id` = '" . $id . "' AND `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'");

                if ($examPayments->num_rows > 0) {
                    while ($examPayment = $examPayments->fetch_assoc()) {
                        if ($examPayment['earning_status_earning_status_id'] == 1) {
                            $pendingPayments += $examPayment['amount'];
                        } else if ($examPayment['earning_status_earning_status_id'] == 2) {
                            $totalIncome += $examPayment['amount'];
                        } else if ($examPayment['earning_status_earning_status_id'] == 3) {
                            $rejectedPayments += $examPayment['amount'];
                        }
                    }
                }

                $lecturePayments = Database::search("SELECT * FROM `lecturer_payments`
                INNER JOIN `time_table` ON `lecturer_payments`.`time_table_time_table_id` = `time_table`.`time_table_id` 
                WHERE `lecturer_payments`.`lecturers_lecturers_id` = '" . $id . "' AND `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'");

                if ($lecturePayments->num_rows > 0) {
                    while ($lecturePayment = $lecturePayments->fetch_assoc()) {
                        if ($lecturePayment['earning_status_earning_status_id'] == 1) {
                            $pendingPayments += $lecturePayment['amount'];
                        } else if ($lecturePayment['earning_status_earning_status_id'] == 2) {
                            $totalIncome += $lecturePayment['amount'];
                        } else if ($lecturePayment['earning_status_earning_status_id'] == 3) {
                            $rejectedPayments += $lecturePayment['amount'];
                        }
                    }
                }

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Lecturer Subject Activity - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/calender.css">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body class="overflow-hidden" onload="getSelectedLecturerScheduleData(<?= $id ?>);">
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
                                        include "./includes/components/skeleton-dashboards.php"; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0" id="accordionParent">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-8 col-lg-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg"><?= $lecturerData["title"] . " . " . $lecturerData["user_fname"] . " " . $lecturerData["user_lname"] . " - Subject : " . $lecturerData["subject"] ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties?id=<?= $lecturerData['faculties_faculty_id'] ?>'>...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $lecturerData['intake_id'] ?>"><?= $lecturerData["intake"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id=<?= $lecturerData['level_id'] ?>"><?= $lecturerData["level"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $lecturerData['subject_id'] ?>"><?= $lecturerData["subject"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-lecturers?subject-id=<?= $lecturerData['subject_id'] ?>">Lecturers</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Lecturer Activity</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="container p-0 m-0 g-0 mb-4">
                                                <div class="row g-4 d-flex">
                                                    <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:student" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                                                Completed Subject Hours</div>
                                                                            <div class="stat-value mb-0 text-gray-800"><?= $totalCompletedHourse == null ? '0' : $totalCompletedHourse ?></div>
                                                                            <div class="text-xs text-muted">of <?= $lecturerData["max_course_hours"] ?> hours (<?= $completeProgress ?>%)</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-2">
                                                                        <div class="progress progress-sm" style="height: 0.5rem;">
                                                                            <div class="progress-bar" role="progressbar" style="width: <?= $completeProgress ?>%" aria-valuenow="<?= $completeProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="ic:baseline-hourglass-bottom" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                                                Completed Assignment Marking</div>
                                                                            <div class="stat-value mb-0 text-gray-800"><?= $assignementsData['submitted_count'] ?></div>
                                                                            <div class="text-xs text-muted">of <?= $assignementsData['not_submitted_count'] ?> hours (<?= $assignementsProgress ?>%)</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-2">
                                                                        <div class="progress progress-sm" style="height: 0.5rem;">
                                                                            <div class="progress-bar" role="progressbar" style="width:<?= $assignementsProgress ?>%" aria-valuenow="<?= $assignementsProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="carbon:hourglass" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                                                Completed Exam Marking</div>
                                                                            <div class="stat-value mb-0 text-gray-800">0</div>
                                                                            <div class="text-xs text-muted">of 0 hours (0%)</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-2">
                                                                        <div class="progress progress-sm" style="height: 0.5rem;">
                                                                            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 table-btn-green-blue d-flex align-items-center justify-content-center"> <iconify-icon icon="icon-park-twotone:flash-payment" width="32" height="32" style="color: var(--dark-green-blue);"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                                                TOTAL SUBJECT INCOME</div>
                                                                            <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($totalIncome) ?></div>
                                                                            <div class="text-xs text-muted">Available for withdrawal</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-3">
                                                                        <a href="#" class="text-xs text-success">Income History <i class="fas fa-arrow-right ms-1"></i></a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="carbon:condition-wait-point" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                                                PENDING APPROVAL</div>
                                                                            <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($pendingPayments) ?></div>
                                                                            <div class="text-xs text-muted">Next payment cycle</div>
                                                                        </div>

                                                                    </div>
                                                                    <div class="mt-3">
                                                                        <a href="#" class="text-xs text-primary">Payment Schedule <i class="fas fa-arrow-right ms-1"></i></a>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 table-btn-orange d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:pending" width="32" height="32" style="color: var(--dark-orange);"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                                                REJECTED Payments</div>
                                                                            <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($rejectedPayments) ?></div>
                                                                            <div class="text-xs text-muted">Rejected payments</div>
                                                                        </div>

                                                                    </div>
                                                                    <div class="mt-3">
                                                                        <a href="#" class="text-xs text-danger">Send Reminders <i class="fas fa-arrow-right ms-1"></i></a>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                            <div class="container p-4 component-theme">
                                                <div class="col-12 d-flex justify-content-center mb-4 mt-2">
                                                    <div class="col-2 col-lg-1">
                                                        <button class="w-100 p-2 bl-solid  d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                            <span class="text-14 main-text fw-semibold">previous</span>
                                                        </button>
                                                    </div>
                                                    <div class="col-8 col-lg-10 d-flex gap-2 justify-content-center align-items-center">
                                                        <h4 class="text-center" data-i18n="lecture-schedule">Lecture Schedule</h4>
                                                        <h4 class="text-center">-</h4>
                                                        <h4 class="text-center" id="month"></h4>
                                                        <h4 class="text-center" id="year"></h4>
                                                    </div>
                                                    <div class="col-2 col-lg-1 text-end">
                                                        <button class="w-100 p-2 bl-solid  d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule()">
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
                                                        <div class="modal-body" id="eventDetails"></div>
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
                    <script src="./assets/js/fileChooser.js"></script>
                    <script src="./assets/js/eventCalender.js"></script>
                </body>

                </html>
<?php
            }
        }
    }
} ?>