<?php
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['examination-id']) && !empty($_GET['examination-id']))) {
            header("Location: ./subject-examinations");
        } else {

            $id = $_GET['examination-id'];
            $examination = Database::search("SELECT * FROM `exams`
            INNER JOIN `subjects` ON `exams`.`subjects_subject_id`=`subjects`.`subject_id`
            INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`  
            WHERE `exam_id` = '" . $id . "' AND `exam_types_exam_type_id` != '3'");
            if ($examination->num_rows == 0) {
                header("Location: ./subject-examinations");
            } else {
                $examinationData = $examination->fetch_assoc();
                $examType = $examinationData["exam_types_exam_type_id"];
                $isScheduledExams = false;
                if ($examType == 1) {
                    $physical_exam = Database::search("SELECT `start_time`,`end_time` FROM `physical_exam` WHERE `exams_exam_id`='" . $examinationData["exam_id"] . "'");
                    if ($physical_exam->num_rows > 0) {
                        $isScheduledExams = true;
                    }
                } else if ($examType == 2) {
                    $online_exam = Database::search("SELECT `start_time`,`end_time` FROM `online_exam` WHERE `exams_exam_id`='" . $examinationData["exam_id"] . "'");
                    if ($online_exam->num_rows > 0) {
                        $isScheduledExams = true;
                    }
                }

                if (!$isScheduledExams) {
                    header("Location: ./subject-examinations?subject_id=" . $examinationData['subject_id']);
                } else {

                    $datetime = new DateTime('now', new DateTimeZone('Asia/Colombo'));
                    $formatedDateTime = $datetime->format('Y-m-d');

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $validLecturer = false;

                    $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $examinationData['subject_id'] . "'");
                    if ($lecturerSubject->num_rows > 0) {
                        $validLecturer = true;
                    }

                    $allowedPermissions = [1, 17, 20];
                    $haveAnyPermission = false;

                    foreach ($allowedPermissions as $permission) {
                        if (UserPermissions::hasPermission('exams', $permission, $examinationData['subject_id'])) {
                            $haveAnyPermission = true;
                        }
                    }

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                        header("Location: ./level-details");
                    } else {
?>
                        <!DOCTYPE html>
                        <html lang="en" data-bs-theme="light" data-custom-theme="light">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>Subject Examination - Admin Dashboard | <?= Project::getBaseName() ?> </title>

                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        </head>

                        <body class="overflow-hidden" onload="initializeSelectedSubjectExamination(<?= $id ?>,<?= $examType ?>);">
                            <div class="notifications"></div>
                            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                                <div id="appContainer" class="row p-0">
                                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                        <?php
                                        include "includes/side-menu.php";
                                        load_side_menu::load("Course Overview");
                                        ?>
                                    </div>
                                    <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                                        <div class="row p-0 m-0 g-0 h-100">
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
                                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                                <div class="row p-4 m-0 g-0">
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <span class="main-topic lh-lg">Exam - <?= $examinationData['exam_name'] ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="faculties.php">Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="programes?faculty-id=<?= $examinationData['faculty_id'] ?>"><?= $examinationData['faculty'] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="level-details?level-id=<?= $examinationData['level_id'] ?>"><?= $examinationData['subject'] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Examinations</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $examinationData['exam_name'] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php

                                                    if ($examType == 1) {
                                                        $cardData = Database::search("SELECT 
                                                            COUNT(*) as total_students,
                                                            COUNT(CASE WHEN `exam_status_exam_status_id` = 1 THEN 1 END) as status_1_count,
                                                            COUNT(CASE WHEN `exam_status_exam_status_id` = 2 THEN 1 END) as status_2_count,
                                                            COUNT(CASE WHEN `exam_status_exam_status_id` = 3 THEN 1 END) as status_3_count
                                                        FROM `students_has_physical_exam` 
                                                        WHERE `physical_exam_physical_exam_id` = (
                                                            SELECT `physical_exam_id` 
                                                            FROM `physical_exam` 
                                                            WHERE `exams_exam_id` = '" . $id . "'
                                                        )");

                                                        $cardData = $cardData->fetch_assoc();
                                                        $markingPrecentage = round(($cardData['status_2_count'] + $cardData['status_3_count'] / $cardData['total_students']) * 100, 2);
                                                        $markReleasePrecentage = round(($cardData['status_3_count'] / $cardData['total_students']) * 100, 2);
                                                    ?>

                                                        <div class="container p-0 m-0 g-0 mb-4">
                                                            <div class="row g-4 d-flex">
                                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                                    <div class="component-theme p-3 w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <div class="hwr-70 table-btn-purple d-flex align-items-center justify-content-center"> <iconify-icon icon="ic:round-ssid-chart" width="32" height="32" class="text-dark-purple"></iconify-icon></div>
                                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                                <div class="row no-gutters align-items-center">
                                                                                    <div class="col mr-2">
                                                                                        <div class="text-xs font-weight-bold text-dark-purple text-uppercase mb-1">
                                                                                            TOTAL STUDENTS</div>
                                                                                        <div class="stat-value mb-0 text-gray-800"><?= $cardData['total_students'] ?></div>
                                                                                        <div class="text-xs text-muted">Active students</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                                    <div class="component-theme p-3 w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="gg:check-o" width="32" height="32" class="text-dark-yellow"></iconify-icon></div>
                                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                                <div class="row no-gutters align-items-center">
                                                                                    <div class="col mr-2">
                                                                                        <div class="text-xs font-weight-bold text-dark-yellow text-uppercase mb-1">
                                                                                            Marking Completed</div>
                                                                                        <div class="stat-value mb-0 text-gray-800"><?= $markingPrecentage ?>%</div>
                                                                                        <div class="text-xs text-muted"><?= $cardData['status_3_count'] + $cardData['status_2_count'] ?> from <?= $cardData['total_students'] ?></div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                                    <div class="component-theme p-3 w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <div class="hwr-70 table-btn-success d-flex align-items-center justify-content-center"> <iconify-icon icon="ci:wavy-check" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                                <div class="row no-gutters align-items-center">
                                                                                    <div class="col mr-2">
                                                                                        <div class="text-xs font-weight-bold text-dark-green text-uppercase mb-1">
                                                                                            MARK RELEASED</div>
                                                                                        <div class="stat-value mb-0 text-gray-800"><?= $markReleasePrecentage ?>%</div>
                                                                                        <div class="text-xs text-muted"><?= $cardData['status_3_count'] ?> from <?= $cardData['total_students'] ?></div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    } elseif ($examType == 2) {
                                                    ?>
                                                        <div class="col-12 p-0 mb-4">
                                                            <div class="row g-0 g-md-4 g-lg-4 gy-4 d-flex">
                                                                <div class="col-12 col-lg-6 d-flex">
                                                                    <div class="d-flex flex-column component-theme p-4 w-100">
                                                                        <div class="d-flex flex-row">
                                                                            <span class="main-topic">Complete Examination</span>
                                                                        </div>
                                                                        <div class="d-flex flex-column flex-xxl-row align-items-center justify-content-center p-4 w-100">
                                                                            <div class="h-auto p-0 m-0" id="chart-container">
                                                                                <canvas id="examinationChart"></canvas>
                                                                            </div>
                                                                            <div class="col-12 col-xxl-4 px-xxl-4 mt-3 mt-xxl-0 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                                <div class="row w-100 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                                    <div class="col-5 col-md-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-start">
                                                                                        <span class="grade-legend-item" style="background-color:var(--chart-color-1);"></span> Complete
                                                                                    </div>
                                                                                    <div class="col-7 col-md-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                        <span class="grade-legend-item" style="background-color:var(--chart-color-2);"></span> Not-Complete
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-lg-6 d-flex">
                                                                    <div class="d-flex flex-column component-theme p-4 w-100">
                                                                        <div class="d-flex flex-row">
                                                                            <span class="main-topic">Examination Marking Process</span>
                                                                        </div>
                                                                        <div class="d-flex flex-column flex-xxl-row align-items-center justify-content-center p-4 w-100">
                                                                            <div class="h-auto p-0 m-0" id="chart-container">
                                                                                <canvas id="examinationMarkChart"></canvas>
                                                                            </div>
                                                                            <div class="col-12 col-xxl-4 px-xxl-4 mt-3 mt-xxl-0 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                                <div class="row w-100 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                                    <div class="col-5 col-md-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-start">
                                                                                        <span class="grade-legend-item" style="background-color: var(--chart-color-1);"></span> Pending
                                                                                    </div>
                                                                                    <div class="col-7 col-md-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                        <span class="grade-legend-item" style="background-color: var(--chart-color-2);"></span> Mark Submit
                                                                                    </div>
                                                                                    <div class="col-5 col-md-6 col-xxl-12 d-flex mb-3 align-items-center justify-content-start">
                                                                                        <span class="grade-legend-item" style="background-color: var(--chart-color-3);"></span> Mark Released
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>

                                                    <div class="col-12 p-4 mb-4 component-theme">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="col-12 mb-4">
                                                                <div class="row gy-3 gx-3 align-content-center justify-content-end">
                                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                                            <?php
                                                                            $submit_student_count = 0;
                                                                            if ($examType == 1) {
                                                                                $students = Database::search("SELECT COUNT(*) as count FROM `students_has_physical_exam` 
                                                                        WHERE `physical_exam_physical_exam_id` = (SELECT `physical_exam_id` FROM `physical_exam` WHERE `exams_exam_id`='" . $id . "') ")->fetch_assoc()['count'];
                                                                                $submit_student_count = $students;
                                                                            } else {
                                                                                $students = Database::search("SELECT COUNT(*) as count FROM `students_has_online_exam` 
                                                                        WHERE `online_exam_online_exam_id` = (SELECT `online_exam_id` FROM `online_exam` WHERE `exams_exam_id`='" . $id . "') ")->fetch_assoc()['count'];
                                                                                $submit_student_count = $students;
                                                                            }
                                                                            ?>
                                                                            <span class="text-14 dark-theme fw-medium"><?= $submit_student_count; ?> Submit Examination</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-8 col-md-8 col-lg-7 d-flex flex-column">
                                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleStudentsExaminationssPagination('<?= $id; ?>','filter')">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" id="filter-selector" aria-label="Default select example" onchange="handleStudentsExaminationssPagination('<?= $id; ?>','filter')">
                                                                            <option selected value="0">- Exam Status -</option>
                                                                            <?php
                                                                            $status = Database::search("SELECT * FROM `exam_status`");
                                                                            if ($status->num_rows > 0) {
                                                                                while ($data = $status->fetch_assoc()) {
                                                                            ?>
                                                                                    <option value="<?= $data["exam_status_id"]; ?>"><?= $data["exam_status"]; ?></option>
                                                                            <?php
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>

                                                                    <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                        <button class="custom-btn w-100" type="button">Search</button>
                                                                    </div>
                                                                    <?php
                                                                    if ($submit_student_count > 0 && $examinationData['exam_date'] < $formatedDateTime) {

                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 20, $examinationData['subject_id']) || $validLecturer) {
                                                                    ?>
                                                                            <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                                <button class="custom-btn" data-bs-toggle="modal" data-bs-target="#marksBulkUpload">Upload Marks CSV File</button>
                                                                            </div>
                                                                        <?php
                                                                        }

                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 17, $examinationData['subject_id'])) {
                                                                        ?>
                                                                            <div class="col-12 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                                <button class="custom-btn d-flex align-content-center align-items-center justify-content-center" onclick="openExamMarksReleasedModal(<?= $id; ?>);">
                                                                                    All Marks Released
                                                                                    <span class="permission-info-icon mx-3 me-0 d-flex align-items-center"
                                                                                        data-tooltip="Filter students to release marks.&#10;Check the boxes to release marks for selected students only.&#10;If no students are selected, marks will be released to all who have marks added.">
                                                                                        <iconify-icon icon="bxs:info-square" width="24" height="24" class="text-white"></iconify-icon>
                                                                                    </span>
                                                                                </button>
                                                                            </div>
                                                                        <?php
                                                                        }

                                                                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 20, $examinationData['subject_id']) || $validLecturer)) {
                                                                        ?>
                                                                            <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            </div>

                                                                        <?php
                                                                        }

                                                                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 20, $examinationData['subject_id']))) {
                                                                        ?>
                                                                            <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                    <div class="col-5 d-none d-lg-block"></div>
                                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                        <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                        <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleStudentsExaminationssPagination('<?= $id; ?>','filter')">
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
                                                                <div class="table-responsive" id="students-examination-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-examination-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium">Student</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>

                                                                                <?php
                                                                                if ($examType == 2) {
                                                                                ?>
                                                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Answers</th>
                                                                                <?php
                                                                                } elseif ($examType == 1) {
                                                                                ?>
                                                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Admission Id</th>
                                                                                <?php
                                                                                }

                                                                                ?>
                                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>

                                                                                <?php
                                                                                if ($examinationData['exam_date'] < $formatedDateTime) {
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || $validLecturer) {
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

                                                                            if ($examType == 1) {
                                                                                $query = "SELECT `students_has_physical_exam_id` AS `student_exam_id`,`exam_name`,`marks`,`admission`,`exam_status`,`exam_status_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`title`,`profile_img` FROM `students_has_physical_exam`
                                                                    INNER JOIN `students` ON `students_has_physical_exam`.`students_student_id`=`students`.`student_id`
                                                                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                    INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN `physical_exam` ON `students_has_physical_exam`.`physical_exam_physical_exam_id` = `physical_exam`.`physical_exam_id`
                                                                    INNER JOIN `exams` ON `physical_exam`.`exams_exam_id`=`exams`.`exam_id`
                                                                    INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                    INNER JOIN `students_has_intakes` ON (`students_has_physical_exam`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                    AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                                                                    INNER JOIN `exam_status` ON `students_has_physical_exam`.`exam_status_exam_status_id` = `exam_status`.`exam_status_id`
                                                                    WHERE `exams`.`exam_id`='" . $id . "'
                                                                    ";
                                                                            } elseif ($examType == 2) {
                                                                                $query = "SELECT `students_has_online_exam_id` AS `student_exam_id`,`exam_name`,`marks`,`exam_status`,`exam_status_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`title`,`profile_img` FROM `students_has_online_exam`
                                                                    INNER JOIN `students` ON `students_has_online_exam`.`students_student_id`=`students`.`student_id`
                                                                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                    INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN `online_exam` ON `students_has_online_exam`.`online_exam_online_exam_id` = `online_exam`.`online_exam_id`
                                                                    INNER JOIN `exams` ON `online_exam`.`exams_exam_id`=`exams`.`exam_id`
                                                                    INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                    INNER JOIN `students_has_intakes` ON (`students_has_online_exam`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                    AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                                                                    INNER JOIN `exam_status` ON `students_has_online_exam`.`exam_status_exam_status_id` = `exam_status`.`exam_status_id`
                                                                    WHERE `exams`.`exam_id`='" . $id . "'
                                                                    ";
                                                                            }

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

                                                                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?= $data['marks']; ?>);">
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
                                                                                        <?php
                                                                                        if ($examType == 1) {
                                                                                        ?>
                                                                                            <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["admission"]; ?></span></td>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <td class="px-4 pt-3 pb-3 align-middle">
                                                                                                <button class="table-btn table-btn-dark-theme" onclick="location.href = 'online-examination-answers?student-exam-id=<?= $data['student_exam_id'] ?>'">View Answers</button>
                                                                                            </td>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <?php
                                                                                        if ($data['marks'] == null) {
                                                                                        ?>
                                                                                            <td class="px-4 pt-3 pb-3 align-middle">
                                                                                                <span class="text-14 main-text">N/A</span>
                                                                                            </td>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["marks"]; ?></span></td>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <td class="px-4 py-2 align-middle">
                                                                                            <?php
                                                                                            $status_classes = [
                                                                                                "1"  => "table-btn-yellow",
                                                                                                "2"  => "table-btn-pink",
                                                                                                "3"  => "table-btn-success"
                                                                                            ];

                                                                                            $sbutton_class = $status_classes[$data["exam_status_id"]];
                                                                                            ?>
                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-75"> <?php echo $data["exam_status"]; ?></button>
                                                                                            <?php

                                                                                            ?>
                                                                                        </td>
                                                                                        <?php
                                                                                        if ($examinationData['exam_date'] < $formatedDateTime) {
                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || $validLecturer) {
                                                                                        ?>
                                                                                                <td class="px-4 py-2 align-middle">
                                                                                                    <?php
                                                                                                    $studentName = $data["first_name"] . " " . $data["last_name"];
                                                                                                    if ($examType == 1) {
                                                                                                        if ($data["marks"] == "") {
                                                                                                    ?>
                                                                                                            <button class="table-btn table-btn-purple" onclick="loadLecturerExamAddMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['student_exam_id']; ?>')">Add Mark</button>
                                                                                                        <?php
                                                                                                        } else {
                                                                                                        ?>
                                                                                                            <button class="table-btn table-btn-danger" onclick="loadLecturerExamEditMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['marks'] ?>','<?= $data['student_exam_id']; ?>')">Edit Mark</button>
                                                                                                        <?php
                                                                                                        }
                                                                                                    } else {
                                                                                                        if ($data["marks"] == "") {
                                                                                                        ?>
                                                                                                            <button class="table-btn table-btn-purple" onclick="loadLecturerExamAddMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['student_exam_id']; ?>','2')">Add Mark</button>
                                                                                                        <?php
                                                                                                        } else {
                                                                                                        ?>
                                                                                                            <button class="table-btn table-btn-danger" onclick="loadLecturerExamEditMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['marks'] ?>','<?= $data['student_exam_id']; ?>','2')">Edit Mark</button>
                                                                                                    <?php
                                                                                                        }
                                                                                                    }
                                                                                                    ?>
                                                                                                </td>
                                                                                        <?php
                                                                                            }
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
                                            <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Add Examination Mark</h1>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-0 mt-3">
                                            <div class="col-12 p-2 bl-solid">
                                                <div class="row p-2 gy-3 align-content-center">
                                                    <div class="d-flex">
                                                        <span class="col-2 text-14 sub-text">Exam Name</span>
                                                        <span class="px-2 pe-2">:</span>
                                                        <span class="text-14 sub-text" id="code"></span>
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
                                            <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Edit Examination Mark</h1>
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
                                                                <button class="custom-btn d-flex align-items-center justify-content-center bg-light-theme mx-2" onclick="downloadExamMarksUploadCSV('<?= $examinationData['exam_name']; ?>')">
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
                                            <button class="col-8 col-md-4 custom-btn m-0" onclick="uploadExamMarksBulk(<?= $id ?>)">Upload</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Student Bulk Upload Model => End -->

                            <!-- Mark Released Model => Start -->
                            <div class="modal fade" id="markReleasedModal" tabindex="-1" role="dialog" aria-labelledby="markReleasedModalTitle" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLongTitle">Exam Mark Release</h5>

                                        </div>
                                        <div class="modal-body" id="markReleasedModalBody">

                                        </div>
                                        <div class="modal-footer" id="markReleasedModalFooter">

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Mark Released Model => End -->

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
}
?>