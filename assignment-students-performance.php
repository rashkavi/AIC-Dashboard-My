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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['assignment-id']) && !empty($_GET['assignment-id']))) {
            header("Location: ./selected-subject");
        } else {

            $id = $_GET['assignment-id'];
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
                $hasRecorrectionFee = Database::search("SELECT * FROM `assignment_recorrection_fee` WHERE `assignments_assignment_id` = '" . $id . "'");

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $assignmentData['subjects_subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                $allowedPermissions = [15, 16, 17, 18, 19, 20];
                $haveAnyPermission = false;

                foreach ($allowedPermissions as $permission) {
                    if (UserPermissions::hasPermission('assignments', $permission, $assignmentData['subjects_subject_id'])) {
                        $haveAnyPermission = true;
                    }
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                    header("Location: ./subjects");
                } else {

                    $cardData = Database::search("
                WITH payment_totals_cte AS (
                    SELECT 
                        SUM(CASE WHEN `payment_status_payment_status_id` = 8 THEN amount ELSE 0 END) AS total_payments,
                        SUM(CASE WHEN `payment_status_payment_status_id` IN (2,3,5,6,8) THEN amount ELSE 0 END) AS pending_approval,
                        SUM(CASE WHEN `payment_status_payment_status_id` IN (4,7) THEN amount ELSE 0 END) AS reject_payments
                    FROM assignment_recorrection_payment 
                    WHERE `students_has_assignments_students_has_assignments_id` 
                    IN (
                        SELECT `students_has_assignments_id` 
                        FROM `students_has_assignments` 
                        WHERE `assignments_assignment_id` = '" . $id . "'
                    )
                ),
                assignment_stats AS (
                    SELECT 
                        COUNT(*) AS total_assignments,
                        SUM(CASE WHEN `submission_submission_id` = 1 THEN 1 ELSE 0 END) AS direct_submit_count,
                        SUM(CASE WHEN `submission_submission_id` = 2 THEN 1 ELSE 0 END) AS late_submit_count,
                        SUM(CASE WHEN `submission_submission_id` = 3 THEN 1 ELSE 0 END) AS repeat_submit_count
                    FROM `students_has_assignments`
                    WHERE `assignments_assignment_id` = '" . $id . "'
                ),
                    assignment_recorrection AS (
                    SELECT 
                        COUNT(*) AS total_recorrection
                       
                    FROM `students_has_assignments`
                    WHERE `assignments_assignment_id`='" . $id . "' AND (`students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = '5' 
                    OR `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `assignment_recorrection`))
                )
                SELECT
                    a.total_assignments,
                    a.direct_submit_count,
                    a.late_submit_count,
                    a.repeat_submit_count,
                    r.total_recorrection,
                    CASE
                        WHEN p.total_payments >= 1000000 THEN CONCAT(ROUND(p.total_payments / 1000000, 1), 'M')
                        WHEN p.total_payments >= 1000 THEN CONCAT(ROUND(p.total_payments / 1000, 1), 'K')
                        ELSE CAST(p.total_payments AS CHAR)
                    END AS total_payments_formatted,
                    CASE
                        WHEN p.pending_approval >= 1000000 THEN CONCAT(ROUND(p.pending_approval / 1000000, 1), 'M')
                        WHEN p.pending_approval >= 1000 THEN CONCAT(ROUND(p.pending_approval / 1000, 1), 'K')
                        ELSE CAST(p.pending_approval AS CHAR)
                    END AS pending_approval_formatted,
                    CASE
                        WHEN p.reject_payments >= 1000000 THEN CONCAT(ROUND(p.reject_payments / 1000000, 1), 'M')
                        WHEN p.reject_payments >= 1000 THEN CONCAT(ROUND(p.reject_payments / 1000, 1), 'K')
                        ELSE CAST(p.reject_payments AS CHAR)
                    END AS reject_payments_formatted
                FROM payment_totals_cte p, assignment_stats a, assignment_recorrection r
            ");

                    $cardData = $cardData->fetch_assoc();
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Assignment Performance - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeSelectedSubjectAssignment(<?= $id ?>);">
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
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0" id="accordionParent">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="assignment-performance">Assignment Performance - #<?= $assignmentData['assignment_code'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $assignmentData['faculty_id'] ?>"><?= $assignmentData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $assignmentData['programme_id'] ?>"><?= $assignmentData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?subject-id=<?= $assignmentData['subjects_subject_id'] ?>"><?= $assignmentData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-assignments?subject-id=<?= $assignmentData['subjects_subject_id'] ?>">Assignments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Students Performance</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        $isLoginUserSubjectLecturer = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $assignmentData['subjects_subject_id'] . "'");
                                                        if ($isLoginUserSubjectLecturer->num_rows > 0) {
                                                        ?>
                                                            <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0">
                                                                <button class="custom-btn w-100" type="button" onclick="window.location.href='lecturer-assignment-marking-panel?assignment-id=<?= $assignmentData['assignment_id'] ?>'">
                                                                    Open My Marking Panel
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="container p-0 m-0 g-0 mb-4">
                                                    <div class="row g-4 d-flex">
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Total Assignments</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['total_assignments'] != null ? $cardData['total_assignments'] : 0 ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-success d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Direct Submission</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['direct_submit_count'] != null ? $cardData['direct_submit_count'] : 0 ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Late Submission</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['late_submit_count'] != null ? $cardData['late_submit_count'] : 0 ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-danger d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-red);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Repeat Submission</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['repeat_submit_count'] != null ? $cardData['repeat_submit_count'] : 0 ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-green-blue d-flex align-items-center justify-content-center"> <iconify-icon icon="icon-park-twotone:flash-payment" width="32" height="32" style="color: var(--dark-green-blue);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Recorrection Request</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['total_recorrection'] != null ? $cardData['total_recorrection'] : 0 ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="carbon:condition-wait-point" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Recorrection Payments</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['total_payments_formatted'] != null ? $cardData['total_payments_formatted'] : "0.00" ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-orange d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:pending" width="32" height="32" style="color: var(--dark-orange);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Pending Approval</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['pending_approval_formatted'] != null ? $cardData['pending_approval_formatted'] : "0.00" ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-danger d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent:text-change-reject-20-filled" width="32" height="32" style="color: var(--dark-red);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Rejected Payments</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['reject_payments_formatted'] != null ? $cardData['reject_payments_formatted'] : "0.00" ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 mb-4">
                                                    <div class="row g-0 g-md-4 g-lg-4 gy-4 d-flex">
                                                        <div class="col-12 col-lg-6 d-flex">
                                                            <div class="d-flex flex-column component-theme p-4 w-100">
                                                                <div class="d-flex flex-row">
                                                                    <span class="sub-topic">Assignment Submit</span>
                                                                </div>
                                                                <div class="d-flex flex-column flex-xxl-row flex-grow-1 align-items-center justify-content-center p-0 w-100">
                                                                    <div class="h-auto p-0 m-0" id="chart-container">
                                                                        <canvas id="assignmentChart"></canvas>
                                                                    </div>
                                                                    <div class="col-12 col-xxl-5 px-xxl-4 mt-3 mt-xxl-0 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                        <div class="row w-100 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                            <div class="col-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-start justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-2);"></span> Not-Submitted
                                                                            </div>
                                                                            <div class="col-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-green);"></span>Direct Submitted
                                                                            </div>
                                                                            <div class="col-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-yellow);"></span>Late Submitted
                                                                            </div>
                                                                            <div class="col-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-red);"></span> Repeat-Submitted
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 d-flex">
                                                            <div class="d-flex flex-column component-theme p-4 w-100">
                                                                <div class="d-flex flex-row">
                                                                    <span class="sub-topic">Assignment Marking Process</span>
                                                                </div>
                                                                <div class="d-flex flex-column flex-xxl-row align-items-center justify-content-center p-4 w-100">
                                                                    <div class="h-auto p-0 m-0" id="chart-container">
                                                                        <canvas id="assignmentMarkChart"></canvas>
                                                                    </div>
                                                                    <div class="col-12 col-xxl-5 px-xxl-4 pe-0 mt-3 mt-xxl-0 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                        <div class="row w-100 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                            <div class="col-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-1);"></span> Pending Assign
                                                                            </div>
                                                                            <div class="col-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-purple);"></span> Marking Assign
                                                                            </div>
                                                                            <div class="col-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-pink);"></span> Mark Submit
                                                                            </div>
                                                                            <div class="col-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-yellow-green);"></span> Mark Released
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="accordion accordion-flush" id="accordionFush">
                                                    <div class="accordion-item mb-4 border-0">
                                                        <h2 class="accordion-header">
                                                            <button class="accordion-button collapsed shadow-none border-0 p-3" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                                                                <iconify-icon icon="carbon:ibm-cloud-direct-link-1-connect" width="30" height="30" class="dark-theme me-3 bg-light-theme p-4"></iconify-icon>
                                                                Assignments Submissions
                                                            </button>
                                                        </h2>
                                                        <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFush">
                                                            <div class="accordion-body">
                                                                <div class="col-12 component-theme">
                                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                                        <div class="col-12 mb-4">
                                                                            <div class="row gy-3 gx-3 align-content-center justify-content-end">
                                                                                <div class="col-4 col-md-4 col-lg-2 d-flex flex-column justify-content-start">
                                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                        <?php
                                                                                        $submit_assignments = Database::search("SELECT COUNT(*) as count FROM `students_has_assignments` WHERE `assignments_assignment_id` = '" . $id . "'");
                                                                                        $submit_assignments_count = $submit_assignments->fetch_assoc()["count"];
                                                                                        ?>
                                                                                        <span class="text-14 dark-theme fw-medium"><?= $submit_assignments_count ?> Submit Assignments</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-8 col-md-8 <?= $submit_assignments_count > 0 ? "col-lg-7" : "col-lg-9"; ?> d-flex flex-column">
                                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleStudentsAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 col-md-6 col-lg-1 d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#usersFilter" aria-expanded="false" aria-controls="usersFilter">
                                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <?php
                                                                                if ($submit_assignments_count > 0) {
                                                                                ?>
                                                                                    <div class="col-12 col-md-6 col-lg-2 mt-3 m-md-0 d-flex flex-column justify-content-end align-items-center">
                                                                                        <div class="dropdown w-100">
                                                                                            <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                                Actions
                                                                                            </button>
                                                                                            <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                                                <?php
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 16, $assignmentData['subjects_subject_id'])) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item" href="#" data-bs-toggle="collapse" data-bs-target="#assignFilter" aria-expanded="false" aria-controls="assignFilter">Assign Lecturer</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 20, $assignmentData['subjects_subject_id'])) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item" href="./upload-assignment-mark-bulk?assignment-id=<?= $id ?>&status-id=1">Upload Marks CSV</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 17, $assignmentData['subjects_subject_id'])) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item" onclick="openMarksReleasedModal(<?= $id; ?>);">Release Marks</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <li><a class="dropdown-item" href="assignment-payments?assignment-id=<?= $id ?>">Confirm Lecturers Payments</a></li>
                                                                                            </ul>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php
                                                                                }
                                                                                ?>

                                                                                <div class="col-12 col-lg-4 collapse" id="assignFilter">
                                                                                    <div class="card card-body border-0 p-0">
                                                                                        <div class="row gy-3 justify-content-end">
                                                                                            <?php
                                                                                            if ($submit_assignments_count > 0) {
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 16, $assignmentData['subjects_subject_id'])) {
                                                                                            ?>
                                                                                                    <div class="col-12 col-md-6 d-flex align-items-center justify-content-end">
                                                                                                        <select class="form-select custom-select" id="lecturer-selector" onchange="handleStudentsAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                                            <option value="0">- Select Lecturer -</option>
                                                                                                            <?php
                                                                                                            $subjectLecturers = Database::search("SELECT * FROM `lecturers` INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` WHERE `subjects_subject_id` = '" . $assignmentData["subjects_subject_id"] . "' ");
                                                                                                            while ($data = $subjectLecturers->fetch_assoc()) {
                                                                                                            ?>
                                                                                                                <option value="<?= $data["lecturers_id"]; ?>"><?= $data["user_fname"] . " " . $data["user_lname"]; ?></option>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                        </select>
                                                                                                    </div>
                                                                                                    <div class="col-12 col-md-6 d-flex flex-column justify-content-end">
                                                                                                        <button class="custom-btn w-100" onclick="assignLecturerToStudentsAssignments(this,'<?= $id; ?>')">Assign Lecturer</button>
                                                                                                    </div>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 col-lg-8 collapse" id="usersFilter">
                                                                                    <div class="card card-body border-0 p-0">
                                                                                        <div class="row gy-3 justify-content-end">
                                                                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                                                                <select class="form-select custom-select" id="filter-selector" aria-label="Default select example" onchange="handleStudentsAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                                    <option selected value="0">- Submission Status -</option>
                                                                                                    <?php
                                                                                                    $status = Database::search("SELECT * FROM `assignment_submission`");
                                                                                                    if ($status->num_rows > 0) {
                                                                                                        while ($data = $status->fetch_assoc()) {
                                                                                                    ?>
                                                                                                            <option value="<?= $data["submission_id"]; ?>"><?= $data["submission"]; ?> Submission</option>
                                                                                                    <?php
                                                                                                        }
                                                                                                    }
                                                                                                    ?>
                                                                                                </select>
                                                                                            </div>
                                                                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                                                                <select class="form-select custom-select" id="sort-selector" aria-label="Default select example" onchange="handleStudentsAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                                    <option selected value="0">- Filter -</option>
                                                                                                    <option value="1">Recently Submit</option>
                                                                                                    <option value="2">Early Submit</option>
                                                                                                    <option value="3">Pending Marking Assign</option>
                                                                                                    <option value="4">Marking Assigned</option>
                                                                                                    <option value="5">Mark Submitted</option>
                                                                                                    <option value="6">Mark Not Submitted</option>
                                                                                                    <option value="7">Pending Approval</option>
                                                                                                    <option value="8">Mark Released</option>
                                                                                                </select>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 offest-0 offset-md-8 offset-lg-9 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                                    <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleStudentsAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                        <option selected value="10">10</option>
                                                                                        <option value="25">25</option>
                                                                                        <option value="50">50</option>
                                                                                        <option value="100">100</option>
                                                                                        <option value="250">250</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="overflow-x-auto bg-table-header p-0 m-0 g-0" id="students-assignments-table-container">
                                                                            <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsAssignmentsPagination('<?= $id; ?>','sortByName')">
                                                                                                    <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                </div>
                                                                                            </div>
                                                                                        </th>
                                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">
                                                                                            <div class="d-flex justify-content-between align-items-center cursor-pointer" onclick="handleStudentsAssignmentsPagination('<?= $id; ?>','sortByDate')">
                                                                                                <span class="text-14 main-text fw-medium me-2">Uploaded At</span>
                                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                            </div>
                                                                                        </th>

                                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assigned Lecturer</th>
                                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>
                                                                                        <th scope="col-2" class="px-4 py-3 bg-table-header">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <span class="text-14 main-text fw-medium">Submission Status</span>
                                                                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                            </div>
                                                                                        </th>

                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php

                                                                                    $query = "SELECT
                                                                        `students_has_assignments_id`,
                                                                        `title`,
                                                                        `first_name`,
                                                                        `last_name`,
                                                                        `profile_img`,
                                                                        `students_has_intakes`.`student_id`,
                                                                        `uploaded_at`,
                                                                        `upload`,
                                                                        `submission_id`,
                                                                        `submission`,
                                                                        `assignment_marks_status`,
                                                                        `assignment_marks_status_id`,
                                                                        `uploaded_at`
                                                                    FROM `students_has_assignments`
                                                                    INNER JOIN `students` 
                                                                        ON `students_has_assignments`.`students_student_id` = `students`.`student_id`
                                                                    INNER JOIN `login_details`
                                                                            ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                                                                    INNER JOIN `title` 
                                                                        ON `students`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN `assignments` 
                                                                        ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
                                                                    INNER JOIN `subjects` 
                                                                        ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                    INNER JOIN `levels` 
                                                                        ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                    INNER JOIN `students_has_intakes` 
                                                                        ON (
                                                                            `students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id` 
                                                                            AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`
                                                                        )
                                                                    INNER JOIN `assignment_submission` 
                                                                        ON `students_has_assignments`.`submission_submission_id` = `assignment_submission`.`submission_id`
                                                                    INNER JOIN `assignment_marks_status` 
                                                                        ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = `assignment_marks_status`.`assignment_marks_status_id`
                                                                    WHERE `students_has_assignments`.`assignments_assignment_id` = '" . $id . "'
                                                                    ORDER BY `students_has_assignments`.`uploaded_at` DESC
                                                                    ";

                                                                                    $results = Database::search($query);
                                                                                    $pageno = 1;
                                                                                    $results_count = $results->num_rows;
                                                                                    $result_per_page = 10;  // Consistent variable name

                                                                                    if ($results_count > $result_per_page) {
                                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                                    } else {
                                                                                        $number_of_pages = 1;
                                                                                    }

                                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                                    $assignments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
                                                                                    if ($assignments->num_rows > 0) {
                                                                                        while ($data = $assignments->fetch_assoc()) {

                                                                                            $assignmentAssignLecturer = Database::search("SELECT `title`,`user_fname`,`user_lname`,`profile_img`,`marks` FROM `lecturers_has_students_has_assignments`
                                                                                                INNER JOIN `lecturers` ON `lecturers_has_students_has_assignments`.`lecturers_lecturers_id`=`lecturers`.`lecturers_id`
                                                                                                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` 
                                                                                                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                                WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                                                                            $assignmentAssignLecturerData = $assignmentAssignLecturer->fetch_assoc();

                                                                                    ?>
                                                                                            <tr class="br-t-5">
                                                                                                <td scope="row" class="px-4 pt-3 pb-3">
                                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                        <?php
                                                                                                        $checkboxIsEnable = true;
                                                                                                        if ($assignmentAssignLecturer->num_rows > 0) {
                                                                                                            if ($assignmentAssignLecturerData['marks'] != null) {
                                                                                                                $checkboxIsEnable = false;
                                                                                                        ?>
                                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" disabled>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["students_has_assignments_id"]; ?>" onchange="checkRowEvents(<?= $data['students_has_assignments_id']; ?>);">
                                                                                                            <?php
                                                                                                            }
                                                                                                        } else {
                                                                                                            ?>
                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["students_has_assignments_id"]; ?>" onchange="checkRowEvents(<?= $data['students_has_assignments_id']; ?>);">
                                                                                                        <?php
                                                                                                        }

                                                                                                        if ($checkboxIsEnable) {
                                                                                                        ?>
                                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?= $data['students_has_assignments_id']; ?>);">
                                                                                                                <?php
                                                                                                                if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                                                                                ?>
                                                                                                                    <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                                <?php
                                                                                                                } else {
                                                                                                                    $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                                    $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                                ?>
                                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                                <?php
                                                                                                                }
                                                                                                                ?>
                                                                                                                <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                            </div>
                                                                                                        <?php
                                                                                                        } else {
                                                                                                        ?>
                                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                                                <?php
                                                                                                                if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                                ?>
                                                                                                                    <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                                <?php
                                                                                                                } else {
                                                                                                                    $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                                    $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                                ?>
                                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                                <?php
                                                                                                                }
                                                                                                                ?>
                                                                                                                <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                            </div>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["uploaded_at"])) ?></span></td>
                                                                                                <?php
                                                                                                if ($assignmentAssignLecturer->num_rows > 0) {
                                                                                                ?>
                                                                                                    <td scope="row" class="px-4 pt-3 pb-3">
                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                            <?php
                                                                                                            if (!empty($assignmentAssignLecturerData["profile_img"]) && file_exists('./assets/imgs/users/' . $assignmentAssignLecturerData["profile_img"])) {
                                                                                                            ?>
                                                                                                                <img src="./assets/imgs/users/<?= $assignmentAssignLecturerData["profile_img"]; ?>" class="hwr-40" alt="lecturer image">
                                                                                                            <?php
                                                                                                            } else {
                                                                                                                $firstNameInitial = strtoupper(substr($assignmentAssignLecturerData["user_fname"], 0, 1));
                                                                                                                $lastNameInitial = strtoupper(substr($assignmentAssignLecturerData["user_lname"], 0, 1));
                                                                                                            ?>
                                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                            <span class="text-14 main-text mx-2"><?= $assignmentAssignLecturerData["title"] . "." . $assignmentAssignLecturerData["user_fname"] . " " . $assignmentAssignLecturerData["user_lname"]; ?></span>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <button class="table-btn table-btn-dark-theme" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <?php
                                                                                                            if ($assignmentAssignLecturerData['marks'] == null) {
                                                                                                            ?>
                                                                                                                <button class="table-btn table-btn-purple">Pending</button>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <span class="text-14 main-text"><?= $assignmentAssignLecturerData['marks']; ?></span>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>

                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-yellow",
                                                                                                            "2"  => "table-btn-pink",
                                                                                                            "3"  => "table-btn-orange",
                                                                                                            "4"  => "table-btn-success"
                                                                                                        ];
                                                                                                        if ($data["assignment_marks_status_id"] == 5) {
                                                                                                            $sbutton_class = $status_classes[4];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> Mark Released</button>
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $sbutton_class = $status_classes[$data["assignment_marks_status_id"]];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["assignment_marks_status"]; ?></button>
                                                                                                        <?php
                                                                                                        }

                                                                                                        ?>

                                                                                                    </td>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-success",
                                                                                                            "2"  => "table-btn-yellow",
                                                                                                            "3"  => "table-btn-danger",
                                                                                                        ];
                                                                                                        $sbutton_class = $status_classes[$data["submission_id"]];
                                                                                                        ?>
                                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["submission"]; ?> Submission</button>
                                                                                                    </td>


                                                                                                <?php
                                                                                                } else {
                                                                                                ?>
                                                                                                    <td scope="row" class="px-4 pt-3 pb-3 align-middle">
                                                                                                        <span class="text-14 main-text">Lecturer Not Assigned</span>
                                                                                                    </td>
                                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <button class="table-btn table-btn-dark-theme" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <button class="table-btn table-btn-purple">Pending</button>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-yellow",
                                                                                                            "2"  => "table-btn-pink",
                                                                                                            "3"  => "table-btn-orange",
                                                                                                            "4"  => "table-btn-success"
                                                                                                        ];
                                                                                                        if ($data["assignment_marks_status_id"] == 5) {
                                                                                                            $sbutton_class = $status_classes[4];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> Mark Released</button>
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $sbutton_class = $status_classes[$data["assignment_marks_status_id"]];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["assignment_marks_status"]; ?></button>
                                                                                                        <?php
                                                                                                        }

                                                                                                        ?>

                                                                                                    </td>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-success",
                                                                                                            "2"  => "table-btn-yellow",
                                                                                                            "3"  => "table-btn-danger",
                                                                                                        ];
                                                                                                        $sbutton_class = $status_classes[$data["submission_id"]];
                                                                                                        ?>
                                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["submission"]; ?> Submission</button>
                                                                                                    </td>

                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </tr>
                                                                                        <?php
                                                                                        }
                                                                                    } else {
                                                                                        ?>
                                                                                        <tr class="justify-content-center align-middle">
                                                                                            <td colspan="13" class="align-middle justify-content-center">
                                                                                                <div class="d-flex flex-column align-items-center">
                                                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    <?php
                                                                                    }

                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                                    <div class="row p-4 align-items-center">
                                                                                        <!-- Previous Button (col-1) -->
                                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersPagination(<?= $id ?>,'previousPage')">
                                                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                        </button>
                                                                                        <!-- Page Numbers (col-10, centered) -->
                                                                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                            <ul class="pagination-list d-flex mb-0">
                                                                                                <?php
                                                                                                $shudderPages1 = false;
                                                                                                $shudderPages2 = false;

                                                                                                function renderPageButton2($id, $i, $isActive = false)
                                                                                                {
                                                                                                    $activeClass = $isActive ? 'active' : '';
                                                                                                    $action = $isActive ? '' : "onclick=\"handleLecturersPagination('{$id}','goToPage', '{$i}')\"";
                                                                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                                }

                                                                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                                        renderPageButton2($id, $i, $pageno == $i);
                                                                                                    } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                                        $shudderPages1 = true;
                                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                    } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                                        $shudderPages2 = true;
                                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                    }
                                                                                                }
                                                                                                ?>
                                                                                            </ul>
                                                                                        </div>
                                                                                        <!-- Next Button (col-1) -->
                                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                        </button>
                                                                                    </div>
                                                                                </nav>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="accordion-item">
                                                        <h2 class="accordion-header">
                                                            <button class="accordion-button collapsed shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                                                                <iconify-icon icon="solar:reel-broken" width="30" height="30" class="dark-theme me-3 bg-light-theme p-4"></iconify-icon>
                                                                Re-Correction Request
                                                            </button>
                                                        </h2>
                                                        <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFush">
                                                            <div class="accordion-body">
                                                                <div class="col-12 component-theme">
                                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                                        <div class="col-12 mb-4">
                                                                            <div class="row gy-3 gx-3 align-content-center justify-content-end">
                                                                                <div class="col-4 col-md-4 col-lg-2 d-flex flex-column justify-content-start">
                                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                        <?php
                                                                                        $recorrection_applied = Database::search("SELECT COUNT(*) as count FROM `students_has_assignments` 
                                                                                        WHERE `assignments_assignment_id`='" . $id . "' AND 
                                                                                        (`assignment_marks_status_assignment_marks_status_id` = '5' OR `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `assignment_recorrection`))")->fetch_assoc()["count"];
                                                                                        ?>
                                                                                        <span class="text-14 dark-theme fw-medium"><?= $recorrection_applied ?> Request</span>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-8 col-md-8 <?= $recorrection_applied > 0 ? "col-lg-7" : "col-lg-9"; ?> d-flex flex-column">
                                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="recorrection-search-text" onkeyup="handleStudentsRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 col-md-6 col-lg-1 d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter2" aria-expanded="false" aria-controls="filter2">
                                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <?php
                                                                                if ($recorrection_applied > 0) {
                                                                                ?>
                                                                                    <div class="col-12 col-md-6 col-lg-2 mt-3 m-md-0 d-flex flex-column justify-content-end align-items-center">
                                                                                        <div class="dropdown w-100">
                                                                                            <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                                Actions
                                                                                            </button>
                                                                                            <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                                                <?php
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 16, $assignmentData['subjects_subject_id'])) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item" href="#" data-bs-toggle="collapse" data-bs-target="#assignFilter2" aria-expanded="false" aria-controls="assignFilter2">Assign Lecturer</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 20, $assignmentData['subjects_subject_id'])) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item" href="./upload-assignment-mark-bulk?assignment-id=<?= $id ?>&status-id=2">Upload Marks CSV</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 17, $assignmentData['subjects_subject_id'])) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item" onclick="openRecorrectionMarksReleasedModal(<?= $id; ?>);">Release Marks</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <li><a class="dropdown-item" href="assignment-recorrection-payments?assignment-id=<?= $id ?>">Confirm Lecturers Payments</a></li>
                                                                                            </ul>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                                <div class="col-12 col-lg-4 collapse" id="assignFilter2">
                                                                                    <div class="card card-body border-0 p-0">
                                                                                        <div class="row gy-3 justify-content-end">
                                                                                            <?php
                                                                                            if ($recorrection_applied > 0) {
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 16, $assignmentData['subjects_subject_id'])) {
                                                                                            ?>
                                                                                                    <div class="col-6 col-md-6 d-flex align-items-center justify-content-end">
                                                                                                        <select class="form-select custom-select" id="recorrection-lecturer-selector" onchange="handleStudentsRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                                            <option value="0">- Select Lecturer -</option>
                                                                                                            <?php
                                                                                                            $subjectLecturers = Database::search("SELECT * FROM `lecturers` INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` WHERE `subjects_subject_id` = '" . $assignmentData["subjects_subject_id"] . "' ");
                                                                                                            while ($data = $subjectLecturers->fetch_assoc()) {
                                                                                                            ?>
                                                                                                                <option value="<?= $data["lecturers_id"]; ?>"><?= $data["user_fname"] . " " . $data["user_lname"]; ?></option>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                        </select>
                                                                                                    </div>
                                                                                                    <div class="col-12 col-md-6 d-flex flex-column justify-content-end">
                                                                                                        <button class="custom-btn w-100" onclick="assignLecturerToStudentsReCorrectionAssignments(this,'<?= $id; ?>')">Assign Lecturer</button>
                                                                                                    </div>

                                                                                            <?php
                                                                                                }
                                                                                            }
                                                                                            ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 col-lg-8 collapse" id="filter2">
                                                                                    <div class="card card-body border-0 p-0">
                                                                                        <div class="row gy-3 justify-content-end">
                                                                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                                                                <select class="form-select custom-select" id="recorrection-filter-selector" aria-label="Default select example" onchange="handleStudentsRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                                    <option selected value="0">- Submission Status -</option>
                                                                                                    <?php
                                                                                                    $status = Database::search("SELECT * FROM `assignment_submission`");
                                                                                                    if ($status->num_rows > 0) {
                                                                                                        while ($data = $status->fetch_assoc()) {
                                                                                                    ?>
                                                                                                            <option value="<?= $data["submission_id"]; ?>"><?= $data["submission"]; ?> Submission</option>
                                                                                                    <?php
                                                                                                        }
                                                                                                    }
                                                                                                    ?>
                                                                                                </select>
                                                                                            </div>
                                                                                            <?php
                                                                                            if ($hasRecorrectionFee->num_rows > 0) {
                                                                                            ?>
                                                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                                                                    <select class="form-select custom-select" id="recorrection-sort-selector" aria-label="Default select example" onchange="handleStudentsRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                                        <option selected value="0">- Filter -</option>
                                                                                                        <?php
                                                                                                        $paymentStatus = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` != 1");
                                                                                                        if ($paymentStatus->num_rows > 0) {
                                                                                                            while ($data = $paymentStatus->fetch_assoc()) {
                                                                                                        ?>
                                                                                                                <option value="<?= $data["payment_status_id"]; ?>"><?= $data["payment_status"]; ?></option>
                                                                                                        <?php
                                                                                                            }
                                                                                                        }
                                                                                                        ?>
                                                                                                    </select>
                                                                                                </div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-6 offset-0 offset-8 offset-lg-9 col-md-4  col-lg-3 d-flex align-items-center justify-content-end">
                                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                                    <select class="form-select custom-select w-25" id="recorrection-show-result-selector" onchange="handleStudentsRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                                        <option selected value="10">10</option>
                                                                                        <option value="25">25</option>
                                                                                        <option value="50">50</option>
                                                                                        <option value="100">100</option>
                                                                                        <option value="250">250</option>
                                                                                    </select>
                                                                                </div>

                                                                            </div>
                                                                        </div>
                                                                        <div class="overflow-x-auto p-0 m-0 g-0" id="students-recorrection-assignments-table-container">
                                                                            <table class="table bl-solid m-0 p-0 g-0" id="students-recorrection-assignments-table">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsRecorrectionAssignmentsPagination('<?= $id; ?>','sortByName')">
                                                                                                    <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                </div>
                                                                                            </div>
                                                                                        </th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">
                                                                                            <div class="d-flex justify-content-between align-items-center cursor-pointer" onclick="handleStudentsRecorrectionAssignmentsPagination('<?= $id; ?>','sortByDate')">
                                                                                                <span class="text-14 main-text fw-medium me-2">Uploaded At</span>
                                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                            </div>
                                                                                        </th>

                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Assign Lecturer</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>
                                                                                        <th scope="col-2" class="py-3 bg-table-header">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <span class="text-14 main-text fw-medium">Submission Status</span>
                                                                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                            </div>
                                                                                        </th>
                                                                                        <?php

                                                                                        if ($hasRecorrectionFee->num_rows > 0) {
                                                                                        ?>
                                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Payment Status</th>
                                                                                            <?php
                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 15, $assignmentData['subjects_subject_id'])) {
                                                                                            ?>
                                                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                        <?php
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php
                                                                                    $query = "SELECT
                                                                        DISTINCT(`students_has_assignments_id`),
                                                                            `students_has_assignments_id`,
                                                                            `title`,
                                                                            `first_name`,
                                                                            `last_name`,
                                                                            `profile_img`,
                                                                            `students_has_intakes`.`student_id`,
                                                                            `uploaded_at`,
                                                                            `upload`,
                                                                            `submission_id`,
                                                                            `submission`,
                                                                            `assignment_marks_status`,
                                                                            `assignment_marks_status_id`,
                                                                            `uploaded_at`
                                                                        FROM `students_has_assignments`
                                                                        INNER JOIN `students` 
                                                                            ON `students_has_assignments`.`students_student_id` = `students`.`student_id`
                                                                        INNER JOIN `login_details`
                                                                            ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                        INNER JOIN `title` 
                                                                            ON `students`.`title_title_id` = `title`.`title_id`
                                                                        INNER JOIN `students_has_intakes` 
                                                                            ON `students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                        INNER JOIN `assignment_submission` 
                                                                            ON `students_has_assignments`.`submission_submission_id` = `assignment_submission`.`submission_id`
                                                                        INNER JOIN `assignment_marks_status` 
                                                                            ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = `assignment_marks_status`.`assignment_marks_status_id`
                                                                        WHERE `students_has_assignments`.`assignments_assignment_id` = '" . $id . "'
                                                                           
                                                                            AND (
                                                                                `assignment_marks_status_assignment_marks_status_id` = '5' 
                                                                                OR `students_has_assignments_id` IN (
                                                                                    SELECT `students_has_assignments_students_has_assignments_id` 
                                                                                    FROM `assignment_recorrection`
                                                                                )
                                                                            )
                                                                        ORDER BY `students_has_assignments`.`uploaded_at` DESC
                                                                        ";

                                                                                    $results = Database::search($query);
                                                                                    $pageno = 1;
                                                                                    $results_count = $results->num_rows;
                                                                                    $result_per_page = 10;  // Consistent variable name

                                                                                    if ($results_count > $result_per_page) {
                                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                                    } else {
                                                                                        $number_of_pages = 1;
                                                                                    }

                                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                                    $assignments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
                                                                                    if ($assignments->num_rows > 0) {
                                                                                        while ($data = $assignments->fetch_assoc()) {
                                                                                    ?>
                                                                                            <tr class="br-t-5">
                                                                                                <td scope="row" class="px-4 pt-3 pb-3">
                                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["students_has_assignments_id"]; ?>" onchange="checkRowEvents(<?= $data['students_has_assignments_id']; ?>);">
                                                                                                        <?php
                                                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                        ?>
                                                                                                            <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                            $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                        ?>
                                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                        <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["uploaded_at"])) ?></span></td>
                                                                                                <?php

                                                                                                $assignmentAssignLecturer = Database::search("SELECT `title`,`user_fname`,`user_lname`,`profile_img`,`marks` FROM `assignment_recorrection`
                                                                                                    INNER JOIN `lecturers` ON `assignment_recorrection`.`lecturers_lecturers_id`=`lecturers`.`lecturers_id`
                                                                                                    INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` 
                                                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                                    WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");

                                                                                                if ($assignmentAssignLecturer->num_rows > 0) {
                                                                                                    $assignmentAssignLecturerData = $assignmentAssignLecturer->fetch_assoc();
                                                                                                ?>
                                                                                                    <td scope="row" class="pt-3 pb-3">
                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                            <?php
                                                                                                            if (!empty($assignmentAssignLecturerData["profile_img"]) && file_exists('./assets/imgs/users/' . $assignmentAssignLecturerData["profile_img"])) {
                                                                                                            ?>
                                                                                                                <img src="./assets/imgs/users/<?= $assignmentAssignLecturerData["profile_img"]; ?>" class="hwr-40" alt="lecturer image">
                                                                                                            <?php
                                                                                                            } else {
                                                                                                                $firstNameInitial = strtoupper(substr($assignmentAssignLecturerData["user_fname"], 0, 1));
                                                                                                                $lastNameInitial = strtoupper(substr($assignmentAssignLecturerData["user_lname"], 0, 1));
                                                                                                            ?>
                                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                            <span class="text-14 main-text mx-2"><?= $assignmentAssignLecturerData["title"] . "." . $assignmentAssignLecturerData["user_fname"] . " " . $assignmentAssignLecturerData["user_lname"]; ?></span>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <button class="table-btn table-btn-dark-theme" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <?php
                                                                                                            if ($assignmentAssignLecturerData['marks'] == null) {
                                                                                                            ?>
                                                                                                                <button class="table-btn table-btn-purple">Pending</button>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <span class="text-14 main-text"><?= $assignmentAssignLecturerData['marks']; ?></span>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>

                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-yellow",
                                                                                                            "2"  => "table-btn-pink",
                                                                                                            "3"  => "table-btn-orange",
                                                                                                            "4"  => "table-btn-success"
                                                                                                        ];
                                                                                                        if ($data["assignment_marks_status_id"] == 5) {
                                                                                                            $sbutton_class = $status_classes[1];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100">Pending</button>
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $sbutton_class = $status_classes[$data["assignment_marks_status_id"]];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["assignment_marks_status"]; ?></button>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </td>
                                                                                                    <td class="py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-success",
                                                                                                            "2"  => "table-btn-yellow",
                                                                                                            "3"  => "table-btn-danger",
                                                                                                        ];
                                                                                                        $sbutton_class = $status_classes[$data["submission_id"]];
                                                                                                        ?>
                                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["submission"]; ?> Submission</button>
                                                                                                    </td>

                                                                                                    <?php
                                                                                                    $payment_status_id = 1;
                                                                                                    $payment_id = 0;
                                                                                                    if ($hasRecorrectionFee->num_rows > 0) {
                                                                                                        $studentRecorrectionPayment = Database::search("SELECT * FROM `assignment_recorrection_payment`
                                                                                    INNER JOIN `payment_status` ON `assignment_recorrection_payment`.`payment_status_payment_status_id` = `payment_status`.`payment_status_id` 
                                                                                    WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                                                                                        if ($studentRecorrectionPayment->num_rows > 0) {
                                                                                                            $psdata =  $studentRecorrectionPayment->fetch_assoc();
                                                                                                            $payment_status_id = $psdata['payment_status_payment_status_id'];
                                                                                                            $payment_id = $psdata['assignment_recorrection_payment_id'];

                                                                                                    ?>
                                                                                                            <td class="py-2 align-middle">
                                                                                                                <?php
                                                                                                                $status_classes = [
                                                                                                                    "0"  => "table-btn-purple",
                                                                                                                    "1"  => "table-btn-yellow",
                                                                                                                    "2"  => "table-btn-orange",
                                                                                                                    "3"  => "table-btn-success",
                                                                                                                    "4"  => "table-btn-danger",
                                                                                                                    "5"  => "table-btn-orange",
                                                                                                                    "6"  => "table-btn-success",
                                                                                                                    "7"  => "table-btn-danger",
                                                                                                                    "8"  => "table-btn-success",
                                                                                                                ];
                                                                                                                $sbutton_class = $status_classes[$psdata['payment_status_payment_status_id']];
                                                                                                                ?>
                                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $psdata['payment_status']; ?></button>
                                                                                                            </td>
                                                                                                            <?php

                                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 15, $assignmentData['subjects_subject_id'])) {
                                                                                                            ?>
                                                                                                                <td class="px-4 py-3 align-middle">
                                                                                                                    <div class="dropdown">
                                                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-assignment-payment.php?payment-id=<?= $payment_id; ?>">
                                                                                                                                    <iconify-icon icon="carbon:view" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    View Payment</a></li>
                                                                                                                        </ul>
                                                                                                                    </div>
                                                                                                                </td>
                                                                                                    <?php
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                } else {
                                                                                                    ?>
                                                                                                    <td scope="row" class="pt-3 pb-3 align-middle">
                                                                                                        <span class="text-14 main-text">Lecturer Not Assigned</span>
                                                                                                    </td>
                                                                                                    <td class="pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <button class="table-btn table-btn-dark-theme" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="pt-3 pb-3 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                            <button class="table-btn table-btn-purple">Pending</button>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-yellow",
                                                                                                            "2"  => "table-btn-pink",
                                                                                                            "3"  => "table-btn-orange",
                                                                                                            "4"  => "table-btn-success"
                                                                                                        ];
                                                                                                        if ($data["assignment_marks_status_id"] == 5) {
                                                                                                            $sbutton_class = $status_classes[1];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100">Pending</button>
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $sbutton_class = $status_classes[$data["assignment_marks_status_id"]];
                                                                                                        ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["assignment_marks_status"]; ?></button>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </td>
                                                                                                    <td class="py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-success",
                                                                                                            "2"  => "table-btn-yellow",
                                                                                                            "3"  => "table-btn-danger",
                                                                                                        ];
                                                                                                        $sbutton_class = $status_classes[$data["submission_id"]];
                                                                                                        ?>
                                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["submission"]; ?> Submission</button>
                                                                                                    </td>
                                                                                                    <?php

                                                                                                    if ($hasRecorrectionFee->num_rows > 0) {
                                                                                                        $studentRecorrectionPayment = Database::search("SELECT * FROM `assignment_recorrection_payment` 
                                                                                    INNER JOIN `payment_status` ON `assignment_recorrection_payment`.`payment_status_payment_status_id` = `payment_status`.`payment_status_id`
                                                                                    WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                                                                                        if ($studentRecorrectionPayment->num_rows > 0) {
                                                                                                            $psdata =  $studentRecorrectionPayment->fetch_assoc();
                                                                                                    ?>
                                                                                                            <td class="py-2 align-middle">
                                                                                                                <?php
                                                                                                                $status_classes = [
                                                                                                                    "0"  => "table-btn-purple",
                                                                                                                    "1"  => "table-btn-yellow",
                                                                                                                    "2"  => "table-btn-orange",
                                                                                                                    "3"  => "table-btn-success",
                                                                                                                    "4"  => "table-btn-danger",
                                                                                                                    "5"  => "table-btn-orange",
                                                                                                                    "6"  => "table-btn-success",
                                                                                                                    "7"  => "table-btn-danger",
                                                                                                                    "8"  => "table-btn-success",
                                                                                                                ];
                                                                                                                $sbutton_class = $status_classes[$psdata['payment_status_payment_status_id']];
                                                                                                                ?>
                                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $psdata['payment_status']; ?></button>
                                                                                                            </td>
                                                                                                            <?php

                                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 15, $assignmentData['subjects_subject_id'])) {

                                                                                                            ?>
                                                                                                                <td class="px-4 py-3 align-middle">
                                                                                                                    <div class="dropdown">
                                                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-assignment-payment.php?payment-id=<?= $payment_id; ?>">
                                                                                                                                    <iconify-icon icon="carbon:view" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    View Payment</a></li>
                                                                                                                        </ul>
                                                                                                                    </div>
                                                                                                                </td>
                                                                                                    <?php

                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                    ?>

                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </tr>
                                                                                        <?php
                                                                                        }
                                                                                    } else {
                                                                                        ?>
                                                                                        <tr class="justify-content-center align-middle">
                                                                                            <td colspan="13" class="align-middle justify-content-center">
                                                                                                <div class="d-flex flex-column align-items-center">
                                                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </tbody>
                                                                            </table>
                                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container3">
                                                                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                                    <div class="row p-4 align-items-center">
                                                                                        <!-- Previous Button (col-1) -->
                                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersPagination(<?= $id ?>,'previousPage')">
                                                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                        </button>
                                                                                        <!-- Page Numbers (col-10, centered) -->
                                                                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                            <ul class="pagination-list d-flex mb-0">
                                                                                                <?php
                                                                                                $shudderPages1 = false;
                                                                                                $shudderPages2 = false;

                                                                                                function renderPageButton3($id, $i, $isActive = false)
                                                                                                {
                                                                                                    $activeClass = $isActive ? 'active' : '';
                                                                                                    $action = $isActive ? '' : "onclick=\"handleLecturersPagination('{$id}','goToPage', '{$i}')\"";
                                                                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                                }

                                                                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                                        renderPageButton3($id, $i, $pageno == $i);
                                                                                                    } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                                        $shudderPages1 = true;
                                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                    } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                                        $shudderPages2 = true;
                                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                    }
                                                                                                }
                                                                                                ?>
                                                                                            </ul>
                                                                                        </div>
                                                                                        <!-- Next Button (col-1) -->
                                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                        </button>
                                                                                    </div>
                                                                                </nav>
                                                                            </div>
                                                                        </div>
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

                        <?php
                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 20, $assignmentData['subjects_subject_id'])) {
                        ?>
                            <!-- Student Mark Bulk Upload Model => Start -->
                            <div class="modal fade br-0" id="marksBulkUpload" aria-hidden="true" aria-labelledby="marksBulkUploadLabel" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                    <div class="modal-content p-4">
                                        <div class="modal-header p-0 border-0">
                                            <h1 class="modal-title text-16 text-dark-color" id="MarksBulkUploadLabel">Add Assignment Marks Bulk</h1>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-0 mt-3">
                                            <div class="col-12 container-fluid">
                                                <div class="row">
                                                    <div class="col-12 p-0 component-theme mb-4">
                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                            <div class="col-12 d-flex align-items-center">
                                                                <span class="text-16 sub-text flex-grow-1">Marks CSV Format File</span>

                                                            </div>

                                                            <div class="col-12 d-flex flex-column">
                                                                <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
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
                                                                    <div class="col-12 px-1 mt-4">
                                                                        <div class="row" id="list-section">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <span class="text-12 text-dark-red" id="csv-file-feedback"></span>
                                                            </div>
                                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer p-0 m-0 border-0">
                                            <button class="col-8 col-md-4 custom-btn m-0" id="markBulkUploadBtn">Upload</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Student Mark Bulk Upload Model => End -->
                            <?php
                        }

                        if ($submit_assignments_count > 0) {
                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 20, $assignmentData['subjects_subject_id'])) {
                            ?>
                                <!-- Mark Released Model => Start -->
                                <div class="modal fade" id="markReleasedModal" tabindex="-1" role="dialog" aria-labelledby="markReleasedModalTitle" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLongTitle">Assignment Mark Release</h5>

                                            </div>
                                            <div class="modal-body" id="markReleasedModalBody">

                                            </div>
                                            <div class="modal-footer" id="markReleasedModalFooter">

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Mark Released Model => End -->
                        <?php
                            }
                        }
                        ?>

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