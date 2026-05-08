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
            WHERE `assignment_id` = '" . $id . "'");
            if ($assignment->num_rows == 0) {
                header("Location: ./subject-assignments");
            } else {
                $assignmentData = $assignment->fetch_assoc();

                $isLoginUserSubjectLecturer = Database::search("SELECT `lecturers_id`,`user_fname`,`user_lname`,`profile_img`,`title`,`per_assignment_fee` FROM `lecturers`
                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $assignmentData['subjects_subject_id'] . "'");
                if ($isLoginUserSubjectLecturer->num_rows == 0) {
                    header("Location: ./assignment-students-performance?assignment-id=" . $id);
                } else {
                    $lecturerData = $isLoginUserSubjectLecturer->fetch_assoc();
                    $hasRecorrectionFee = Database::search("SELECT * FROM `assignment_recorrection_fee` WHERE `assignments_assignment_id` = '" . $id . "'");

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    $cardData = Database::search("
                            WITH 
                            assignment_stats AS (
                                SELECT 
                                    COUNT(*) AS total_assignments,
                                    COALESCE(SUM(CASE WHEN `marks` IS NOT NULL THEN 1 ELSE 0 END), 0) AS marked,
                                    COALESCE(SUM(CASE WHEN `marks` IS NULL THEN 1 ELSE 0 END), 0) AS pending
                                FROM `lecturers_has_students_has_assignments`
                                WHERE `lecturers_lecturers_id` = '" . $lecturerData['lecturers_id'] . "'
                                AND `students_has_assignments_students_has_assignments_id` IN (
                                    SELECT `students_has_assignments_id` 
                                    FROM `students_has_assignments` 
                                    WHERE `assignments_assignment_id` = '" . $id . "'
                                )
                            ),
                            assignment_recorrection AS (
                                SELECT 
                                    COUNT(*) AS total_recorrection,
                                    COALESCE(SUM(CASE WHEN `marks` IS NOT NULL THEN 1 ELSE 0 END), 0) AS recorrection_marked,
                                    COALESCE(SUM(CASE WHEN `marks` IS NULL THEN 1 ELSE 0 END), 0) AS recorrection_pending
                                FROM `assignment_recorrection`
                                WHERE `lecturers_lecturers_id` = '" . $lecturerData['lecturers_id'] . "'
                                AND `students_has_assignments_students_has_assignments_id` IN (
                                    SELECT `students_has_assignments_id` 
                                    FROM `students_has_assignments` 
                                    WHERE `assignments_assignment_id` = '" . $id . "'
                                )
                            )
                            SELECT
                                a.total_assignments,
                                a.marked,
                                a.pending,
                                r.total_recorrection,
                                r.recorrection_marked,
                                r.recorrection_pending,
                                (a.marked * " . $lecturerData['per_assignment_fee'] . ") AS total_income_from_marked,
                                (r.recorrection_marked * " . $lecturerData['per_assignment_fee'] . ") AS total_income_from_recorrection_marked,
                                CASE
                                    WHEN (a.marked * " . $lecturerData['per_assignment_fee'] . ") >= 1000000 THEN CONCAT(ROUND((a.marked * " . $lecturerData['per_assignment_fee'] . ") / 1000000, 1), 'M')
                                    WHEN (a.marked * " . $lecturerData['per_assignment_fee'] . ") >= 1000 THEN CONCAT(ROUND((a.marked * " . $lecturerData['per_assignment_fee'] . ") / 1000, 1), 'K')
                                    ELSE CAST((a.marked * " . $lecturerData['per_assignment_fee'] . ") AS CHAR)
                                END AS formatted_income_from_marked,
                                CASE
                                    WHEN (r.recorrection_marked * " . $lecturerData['per_assignment_fee'] . ") >= 1000000 THEN CONCAT(ROUND((r.recorrection_marked * " . $lecturerData['per_assignment_fee'] . ") / 1000000, 1), 'M')
                                    WHEN (r.recorrection_marked * " . $lecturerData['per_assignment_fee'] . ") >= 1000 THEN CONCAT(ROUND((r.recorrection_marked * " . $lecturerData['per_assignment_fee'] . ") / 1000, 1), 'K')
                                    ELSE CAST((r.recorrection_marked * " . $lecturerData['per_assignment_fee'] . ") AS CHAR)
                                END AS formatted_income_from_recorrection_marked
                            FROM assignment_stats a
                            CROSS JOIN assignment_recorrection r
                        ");

                    $cardData = $cardData->fetch_assoc();
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Lecturer Assignment Marking Panel - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

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
                                            include "./includes/components/skeleton-dashboards.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0" id="accordionParent">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?= $lecturerData['title'] . "." . $lecturerData['user_fname'] . " " . $lecturerData['user_lname'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $assignmentData['faculty_id'] ?>"><?= $assignmentData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $assignmentData['programme_id'] ?>"><?= $assignmentData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?subject-id=<?= $assignmentData['subjects_subject_id'] ?>"><?= $assignmentData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-assignments?subject-id=<?= $assignmentData['subjects_subject_id'] ?>">Assignments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Marking Panel</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="container p-0 m-0 g-0 mb-4">
                                                    <div class="row g-4 d-flex">
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Total Direct Marking</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['total_assignments'] ?></h2>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-success d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Marked</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['marked'] ?></h2>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Pending Marking</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['pending'] ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-pink d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-pink);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Direct Marking Income</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['formatted_income_from_marked'] ?></h2>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-green-blue d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-green-blue);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Recorrection Marking</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['total_recorrection'] ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Recorrection Marked</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['recorrection_marked'] ?></h2>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-orange d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-orange);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Re. Pending Marking</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['recorrection_pending'] ?></h2>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-pink d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline-rounded" width="32" height="32" style="color: var(--dark-pink);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Recorrection Income</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['formatted_income_from_recorrection_marked'] ?></h2>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- On-Time Submissions => start -->
                                                <div class="col-12 px-4 py-3 bg-light-green mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10 py-2" data-bs-toggle="collapse" data-bs-target="#levelsCollapse" aria-expanded="false" aria-controls="levelsCollapse">
                                                            <div class="row">
                                                                <span class="lh-lg fw-semibold text-18 sub-topic">Direct Assignment Marking</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 mb-4 component-theme collapse" id="levelsCollapse" data-bs-parent="#accordionParent">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="col-12 mb-4">
                                                            <div class="row gy-3 gx-3 align-content-center justify-content-start">
                                                                <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $submit_assignments = Database::search("SELECT COUNT(DISTINCT(`students_has_assignments_students_has_assignments_id`)) as count FROM `lecturers_has_students_has_assignments` WHERE `students_has_assignments_students_has_assignments_id` 
                                                                        IN (SELECT `students_has_assignments_id` FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $id . "')");
                                                                        $submit_assignments_count = $submit_assignments->fetch_assoc();
                                                                        ?>
                                                                        <span class="text-14 dark-theme fw-medium"><?= $submit_assignments_count["count"]; ?> Marking Assigned </span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-8 col-md-8 col-lg-5 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleSLPAssignmentsPagination('<?= $id; ?>','filter')">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" id="filter-selector" aria-label="Default select example" onchange="handleSLPAssignmentsPagination('<?= $id; ?>','filter')">
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
                                                                <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" id="sort-selector" aria-label="Default select example" onchange="handleSLPAssignmentsPagination('<?= $id; ?>','filter')">
                                                                        <option selected value="0">- Filter -</option>
                                                                        <option value="1">Recently Submit</option>
                                                                        <option value="2">Early Submit</option>
                                                                        <option value="3">Mark Submitted</option>
                                                                        <option value="4">Mark Not Submitted</option>
                                                                        <option value="5">Pending Approval</option>
                                                                        <option value="6">Mark Released</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button">Search</button>
                                                                </div>
                                                                <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn" onclick="window.location.href = 'api/exportStudentsOnTimeSubmittedAssignments.php?assignment_id=<?= $id; ?>'">Download All Assignment</button>
                                                                </div>
                                                                <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn" onclick="openMarksBulkUploadModal(<?= $id; ?>)">Upload Marks CSV File</button>
                                                                </div>

                                                                <div class="col-5 d-none d-lg-block"></div>
                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleSLPAssignmentsPagination('<?= $id; ?>','filter')">
                                                                        <option selected value="10">10</option>
                                                                        <option value="25">25</option>
                                                                        <option value="50">50</option>
                                                                        <option value="100">100</option>
                                                                        <option value="250">250</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="students-assignments-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium">Student</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Upload Date & Time</th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>
                                                                            <th scope="col-2" class="px-4 py-3 bg-table-header">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-14 main-text fw-medium">Submission Status</span>
                                                                                    <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php

                                                                        $query = "SELECT DISTINCT(`students_has_assignments_id`),`students_has_assignments_id`,`title`,`first_name`,`last_name`,`profile_img`,`students_has_intakes`.`student_id`,`uploaded_at`,`upload`,`submission_id`,`submission`,`marks`,`assignment_marks_status`,`assignment_marks_status_id` FROM `students_has_assignments` 
                                                                    INNER JOIN `students` ON `students_has_assignments`.`students_student_id`=`students`.`student_id`
                                                                    INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                                                                    INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
                                                                    INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                    INNER JOIN `students_has_intakes` ON (`students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                    AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                                                                    INNER JOIN `assignment_submission` ON `students_has_assignments`.`submission_submission_id`=`assignment_submission`.`submission_id`
                                                                    INNER JOIN `lecturers_has_students_has_assignments` ON `lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`=`students_has_assignments`.`students_has_assignments_id`
                                                                    INNER JOIN `assignment_marks_status` ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = `assignment_marks_status`.`assignment_marks_status_id`
                                                                    WHERE `students_has_assignments`.`assignments_assignment_id`='" . $id . "'
                                                                    AND `lecturers_has_students_has_assignments`.`lecturers_lecturers_id`='" . $lecturerData['lecturers_id'] . "'
                                                                    ORDER BY `students_has_assignments`.`uploaded_at` DESC";

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

                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" disabled>

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
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                                    <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["uploaded_at"]; ?></span></td>

                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <button class="table-btn table-btn-dark-theme" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <?php
                                                                                            if ($data['marks'] == null) {
                                                                                            ?>
                                                                                                <button class="table-btn table-btn-purple">Pending</button>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <span class="text-14 main-text"><?= $data['marks']; ?></span>
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
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <?php
                                                                                        $studentName = $data["first_name"] . " " . $data["last_name"];

                                                                                        if ($data["marks"] == "") {
                                                                                        ?>
                                                                                            <button class="table-btn table-btn-purple" onclick="loadLecturerAddMarkModel('<?= $id ?>','<?= $assignmentData['assignment_code'] ?>','<?= $assignmentData['assignment'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['students_has_assignments_id']; ?>')">Add Mark</button>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <button class="table-btn table-btn-danger" onclick="loadLecturerEditMarkModel('<?= $id ?>','<?= $assignmentData['assignment_code'] ?>','<?= $assignmentData['assignment'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['marks'] ?>','<?= $data['students_has_assignments_id']; ?>')">Edit Mark</button>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </td>

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
                                                <!-- On-Time Submissions => end -->

                                                <!-- Recorection Request Submissions => start -->

                                                <div class="col-12 px-4 py-3 bg-light-orange mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10 py-2" data-bs-toggle="collapse" data-bs-target="#recorrectionRequest" aria-expanded="false" aria-controls="recorrectionRequest">
                                                            <div class="row">
                                                                <span class="lh-lg fw-semibold text-18 sub-topic">Re-Correction Assignment Marking</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme collapse" id="recorrectionRequest" data-bs-parent="#accordionParent">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="col-12 mb-4">
                                                            <div class="row gy-3 gx-3 align-content-center justify-content-start">
                                                                <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $recorrection_applied = Database::search("SELECT COUNT(*) as count FROM `assignment_recorrection` 
                                                                        WHERE `lecturers_lecturers_id`='" . $lecturerData['lecturers_id'] . "' AND `students_has_assignments_students_has_assignments_id` IN (SELECT `students_has_assignments_id` FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $id . "') ")->fetch_assoc()["count"];

                                                                        ?>
                                                                        <span class="text-14 dark-theme fw-medium"><?= $recorrection_applied ?> Marking Assigned</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-8 col-md-8 col-lg-5 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="recorrection-search-text" onkeyup="handleSLPRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" id="recorrection-filter-selector" aria-label="Default select example" onchange="handleSLPAssignmentsPagination('<?= $id; ?>','filter')">
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

                                                                <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" id="recorrection-sort-selector" aria-label="Default select example" onchange="handleSLPRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                        <option selected value="0">- Filter -</option>
                                                                        <option value="1">Mark Submit</option>
                                                                        <option value="2">Mark Not Submit</option>
                                                                        <option value="3">Pending Approval</option>
                                                                        <option value="4">Mark Released</option>

                                                                    </select>
                                                                </div>

                                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button">Search</button>
                                                                </div>
                                                                <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn" onclick="window.location.href = 'api/exportStudentsRecorrectionAssignments.php?assignment_id=<?= $id; ?>'">Download All Assignment</button>
                                                                </div>
                                                                <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn" onclick="openMarksBulkUploadModal(<?= $id; ?>,2)">Upload Marks CSV File</button>
                                                                </div>

                                                                <div class="col-5 d-none d-lg-block"></div>
                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" id="recorrection-show-result-selector" onchange="handleSLPRecorrectionAssignmentsPagination('<?= $id; ?>','filter')">
                                                                        <option selected value="10">10</option>
                                                                        <option value="25">25</option>
                                                                        <option value="50">50</option>
                                                                        <option value="100">100</option>
                                                                        <option value="250">250</option>
                                                                    </select>
                                                                </div>

                                                            </div>
                                                        </div>

                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="students-recorrection-assignments-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="students-recorrection-assignments-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium">Student</span>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Upload Date & Time</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>
                                                                            <th scope="col-2" class="py-3 bg-table-header">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-14 main-text fw-medium">Submission Status</span>
                                                                                    <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </div>
                                                                            </th>

                                                                            <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php

                                                                        $query = "SELECT `students_has_assignments_id`,`title`,`first_name`,`last_name`,`profile_img`,`students_has_intakes`.`student_id`,`uploaded_at`,`upload`,`submission_id`,`submission`,`marks`,`assignment_marks_status`,`assignment_marks_status_id` FROM `students_has_assignments` 
                                                                    INNER JOIN `students` ON `students_has_assignments`.`students_student_id`=`students`.`student_id`
                                                                    INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                                                                    INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN `students_has_intakes` ON `students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                    INNER JOIN `assignment_submission` ON `students_has_assignments`.`submission_submission_id`=`assignment_submission`.`submission_id`
                                                                    INNER JOIN `assignment_recorrection` ON `assignment_recorrection`.`students_has_assignments_students_has_assignments_id`=`students_has_assignments`.`students_has_assignments_id`
                                                                    INNER JOIN `assignment_marks_status` ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = `assignment_marks_status`.`assignment_marks_status_id`
                                                                    WHERE `students_has_assignments`.`assignments_assignment_id`='" . $id . "'
                                                                    AND `lecturers_lecturers_id`='" . $lecturerData['lecturers_id'] . "'
                                                                    AND `students_has_intakes`.`intakes_intake_id`='" . $assignmentData['intakes_intake_id'] . "'
                                                                    AND (`assignment_marks_status_assignment_marks_status_id`='5' OR `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `assignment_recorrection`)) 
                                                                    ORDER BY `students_has_assignments`.`uploaded_at` DESC";

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
                                                                                    <td class="pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                                    <td class="pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["uploaded_at"]; ?></span></td>


                                                                                    <td class="pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <button class="table-btn table-btn-primary active" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <?php
                                                                                            if ($data['marks'] == null) {
                                                                                            ?>
                                                                                                <button class="table-btn table-btn-purple">Pending</button>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <span class="text-14 main-text"><?= $data['marks']; ?></span>
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


                                                                                    <td class="py-2 align-middle">
                                                                                        <?php
                                                                                        $studentName = $data["first_name"] . " " . $data["last_name"];

                                                                                        if ($data["marks"] == "") {
                                                                                        ?>
                                                                                            <button class="table-btn table-btn-purple" onclick="loadLecturerAddMarkModel('<?= $id ?>','<?= $assignmentData['assignment_code'] ?>','<?= $assignmentData['assignment'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['students_has_assignments_id']; ?>','2')">Add Mark</button>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <button class="table-btn table-btn-danger" onclick="loadLecturerEditMarkModel('<?= $id ?>','<?= $assignmentData['assignment_code'] ?>','<?= $assignmentData['assignment'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['marks'] ?>','<?= $data['students_has_assignments_id']; ?>','2')">Edit Mark</button>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </td>

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

                                            <!-- Recorection Request Submissions => end -->

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

                        <!-- Mark Add Model => Start -->
                        <div class="modal fade br-0" id="addMark" aria-hidden="true" aria-labelledby="addMarkLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Add Assignment Mark</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-2 bl-solid">
                                            <div class="row p-2 gy-3 align-content-center">
                                                <div class="d-flex">
                                                    <span class="col-2 text-14 sub-text">Code</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="code"></span>
                                                </div>
                                                <div class="d-flex">
                                                    <span class="col-2 text-14 sub-text">Topic</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="topic"></span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <span class="col-2 text-14 sub-text">Student</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="student-name"></span>
                                                </div>
                                                <div class="d-flex">
                                                    <div class="col-2 text-14 sub-text">Student Id</div>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="student-id"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-3">
                                            <div class="row gy-3 align-content-center">
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Add Mark</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="mark">
                                                    <span id="mark-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 mt-4 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0" id="saveMark">Save Mark</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Mark Add Model => End -->

                        <!-- Mark Edit Model => Start -->
                        <div class="modal fade br-0" id="editMark" aria-hidden="true" aria-labelledby="addMarkLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Edit Assignment Mark</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-2 bl-solid">
                                            <div class="row p-2 gy-3 align-content-center">
                                                <div class="d-flex">
                                                    <span class="col-2 text-14 sub-text">Code</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-code"></span>
                                                </div>
                                                <div class="d-flex">
                                                    <span class="col-2 text-14 sub-text">Topic</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-topic"></span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <span class="col-2 text-14 sub-text">Student</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-student-name"></span>
                                                </div>
                                                <div class="d-flex">
                                                    <div class="col-2 text-14 sub-text">Student Id</div>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-student-id"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-3">
                                            <div class="row gy-3 align-content-center">
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Already Assigned Mark</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="edit-already-mark" disabled>
                                                </div>
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">New Mark</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="edit-mark">
                                                    <span id="mark-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 mt-4 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0" id="edit-saveMark">Save Mark</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Mark Edit Model => End -->


                        <!-- Student Bulk Upload Model => Start -->
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
                                                            <button class="custom-btn d-flex align-items-center justify-content-center bg-light-theme mx-2" onclick="downloadMarksUploadCSV('<?= $assignmentData['assignment_code']; ?>')">
                                                                <span class="sub-topic mx-4 fw-medium">Download CSV Format</span>
                                                            </button>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-5" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" accept=".csv">
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="text-12 text-dark-red" id="file-feedback"></span>
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
                        <!-- Student Bulk Upload Model => End -->

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