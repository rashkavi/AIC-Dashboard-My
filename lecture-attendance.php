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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecture-id']) && !empty($_GET['lecture-id']))) {
            header("Location: ./lecture-schedule");
        } else {

            $lecture_id = $_GET['lecture-id'];
            $subject_id = 0;
            $query = "
            SELECT 
                `tt`.`time_table_id`,
                `lt`.`lecture_type`,
                `lt`.`lecture_type_id`,
                `tts`.`time_table_status_id`,
                `tts`.`time_table_status`,
                `f`.`faculty`,
                `f`.`faculty_id`,
                `p`.`programme`,
                `p`.`programme_id`,
                `i`.`intake_id`,
                `i`.`intake`,
                `l`.`level`,
                `l`.`level_id`,
                `s`.`subject_id`,
                `s`.`subject`,
                `s`.`subject_cover`,
                `s`.`subject_type_subject_type_id`,
                `tt`.`date`,
                `tt`.`start_time`,
                `tt`.`end_time`,
                `u`.`user_fname`,
                `u`.`user_lname`,
                `u`.`profile_img`,
                `t`.`title`
            FROM `time_table` AS `tt`
            
            INNER JOIN `lecture_type` AS `lt`
                ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`
            INNER JOIN `time_table_status` AS `tts`
                ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`  
            INNER JOIN `lecturers` AS `lec`
                ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`
            INNER JOIN `users` AS `u`
                ON `lec`.`users_user_id` = `u`.`user_id`
            INNER JOIN `title` AS `t`
                ON `u`.`title_title_id` = `t`.`title_id`
            INNER JOIN `subjects` AS `s`
                ON `lec`.`subjects_subject_id` = `s`.`subject_id`
            INNER JOIN `levels` AS `l`
                ON `s`.`levels_level_id` = `l`.`level_id`
            INNER JOIN `intakes` AS `i`
                ON `l`.`intakes_intake_id` = `i`.`intake_id`
            INNER JOIN `programmes` AS `p`
                ON `i`.`programmes_programme_id` = `p`.`programme_id`
          
            INNER JOIN `faculties` AS `f`
                ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
            WHERE 
             `tt`.`time_table_status_time_table_status_id` = 1
            AND `tt`.`time_table_id` = '" . $lecture_id . "'
            
        ";

            if (isset($_GET['subject-id']) && !empty($_GET['subject-id'])) {
                $subject_id = $_GET['subject-id'];
                $query .= " AND `s`.`subject_id`='" . $subject_id . "'";
            }

            $lecture = Database::search($query);

            if ($lecture->num_rows == 0) {
                header("Location: ./lecture-schedule");
            } else {
                $lectureData = $lecture->fetch_assoc();
                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                $isValidLecture = true;
                if ($lectureData['lecture_type_id'] != 3) {
                    $lectureDateTime = new DateTime($lectureData['date'] . " " . $lectureData['start_time'], new DateTimeZone("Asia/Colombo"));
                    $lectureEndDateTime = new DateTime($lectureData['date'] . " " . $lectureData['end_time'], new DateTimeZone("Asia/Colombo"));
                    if ($dateTime < $lectureDateTime) {
                        $isValidLecture = false;
                    }
                }

                if (!$isValidLecture) {
                    header("Location: ./lecture-schedule");
                } else {

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d');
                    $nowTime = $today->format('H:i:s');

                    $query = "SELECT 
                            COUNT(CASE WHEN `attendance`.`attendance_status_attendance_status_id` = 2 THEN 1 END) AS present_count,
                            COUNT(CASE WHEN `attendance`.`attendance_status_attendance_status_id` = 4 THEN 1 END) AS watch_count,
                            COUNT(`students`.`student_id`) AS students_count
                        FROM `students`
                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                        INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `status` ON `login_details`.`status_status_id` = `status`.`status_id`
                        INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                        " . ($lectureData["subject_type_subject_type_id"] == 2 ? "
                        INNER JOIN `students_has_subjects` ON `students`.`student_id` = `students_has_subjects`.`students_student_id`
                        " : "") . "
                        LEFT JOIN `attendance` ON (
                            `students`.`student_id` = `attendance`.`students_student_id`
                            AND `attendance`.`time_table_time_table_id` = '" . $lectureData["time_table_id"] . "'
                        )
                        WHERE `students_has_intakes`.`intakes_intake_id` = '" . $lectureData["intake_id"] . "'
                        AND `students_has_intakes`.`status_status_id` = '1'
                        " . ($lectureData["subject_type_subject_type_id"] == 2 ? "
                        AND `students_has_subjects`.`subjects_subject_id` = '" . $lectureData["subject_id"] . "'
                        AND `students_has_subjects`.`status_status_id` = '1'
                        " : "") . "
                        ";

                    $cardData = Database::search($query)->fetch_assoc();
                    if ($lectureData['lecture_type_id'] == 3) {
                        $cardData['present_count'] = $cardData['watch_count'];
                    }

                    $presentPercentage = $cardData['present_count'] == 0 ? 0 : round($cardData['present_count'] / $cardData['students_count'] * 100);
                    $absentPercentage = 100 - $presentPercentage;

                    $isLectureOngoing = new DateTime($lectureData['date'] . " " . $lectureData['end_time'], new DateTimeZone("Asia/Colombo")) > $dateTime;
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Lecture Attendnace - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0  vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    if ($subject_id == 0) {
                                        load_side_menu::load("Lecture Schedule");
                                    } else {
                                        load_side_menu::load("Course Overview");
                                    }
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            if ($subject_id == 0) {
                                                selectItem::setSelected("Lecture Schedule");
                                            } else {
                                                selectItem::setSelected("Course Overview");
                                            }
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0 g-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-table.php"; ?>
                                        </div>
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg" data-i18n="lecture-attendance">Lecture Attendnace</span>
                                                        <?php
                                                        if ($subject_id == 0) {
                                                        ?>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="lecture-attendance">Lecture Attendance</a></li>
                                                                </ol>
                                                            </nav>
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $lectureData['programme_id'] ?>"><?= $lectureData['programme'] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id=<?= $lectureData['level_id'] ?>"><?= $lectureData['level'] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $lectureData['subject_id'] ?>"><?= $lectureData['subject'] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-lecture-schedule?subject-id=<?= $lectureData['subject_id'] ?>">Lectures</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Lecture Attendance</a></li>
                                                                </ol>
                                                            </nav>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="row p-0 m-0" id="attendnace-content-area">
                                                        <div class="container p-0 m-0 g-0 mb-4">
                                                            <div class="row g-4 d-flex">
                                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                                    <div class="component-theme p-3 w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <div class="hwr-70 table-btn-purple d-flex align-items-center justify-content-center"> <iconify-icon icon="ic:round-ssid-chart" width="32" height="32" class="text-dark-purple"></iconify-icon></div>
                                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                                <div class="row no-gutters align-items-center">
                                                                                    <div class="col mr-2">
                                                                                        <div class="text-xs font-weight-bold text-dark-purple text-uppercase mb-1" data-i18n="total-students">
                                                                                            TOTAL STUDENTS</div>
                                                                                        <div class="stat-value mb-0 text-gray-800"><?= $cardData['students_count'] ?></div>
                                                                                        <div class="text-xs text-muted" data-i18n="active-students">Active students</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                                    <div class="component-theme p-3 w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <div class="hwr-70 table-btn-green-blue d-flex align-items-center justify-content-center"> <iconify-icon icon="ci:wavy-check" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                                <div class="row no-gutters align-items-center">
                                                                                    <div class="col mr-2">
                                                                                        <div class="text-xs font-weight-bold text-dark-green text-uppercase mb-1" data-i18n="present-students">
                                                                                            PRESENTED STUDENTS</div>
                                                                                        <div class="stat-value mb-0 text-gray-800"><?= $presentPercentage ?>%</div>
                                                                                        <div class="text-xs text-muted"><?= $cardData['present_count'] ?> from <?= $cardData['students_count'] ?></div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                                    <div class="component-theme p-3 w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <div class="hwr-70 table-btn-danger d-flex align-items-center justify-content-center"> <iconify-icon icon="token:pay" width="32" height="32" class="text-dark-red"></iconify-icon></div>
                                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                                <div class="row no-gutters align-items-center">
                                                                                    <div class="col mr-2">
                                                                                        <div class="text-xs font-weight-bold text-dark-red text-uppercase mb-1" data-i18n="absent-students">
                                                                                            ABSENTED STUDENTS</div>
                                                                                        <div class="stat-value mb-0 text-gray-800"><?= $absentPercentage ?>%</div>
                                                                                        <div class="text-xs text-muted"><?= $cardData['students_count'] - $cardData['present_count'] ?> from <?= $cardData['students_count'] ?></div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 px-4 py-4 component-theme">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-start">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <span class="text-14 dark-theme fw-medium"><?= $cardData['students_count']; ?> <span data-i18n="students"></span></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleSubjectLectureAttendancePagination(<?= $lecture_id ?>,'filter')">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterAccordian" aria-expanded="false" aria-controls="filterAccordian">
                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                                <div class="col-12 collapse" id="filterAccordian">
                                                                    <div class="card card-body border-0 p-0">
                                                                        <div class="col-12">
                                                                            <div class="row justify-content-end">
                                                                                <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleSubjectLectureAttendancePagination(<?= $lecture_id ?>,'filter')">
                                                                                        <option selected value="0">- Filter Data -</option>

                                                                                        <?php
                                                                                        if ($lectureData['lecture_type_id'] != 3) {
                                                                                        ?>
                                                                                            <?php
                                                                                            if ($lectureData['lecture_type_id'] == 2) {
                                                                                            ?>
                                                                                                <option value="2">Joined</option>
                                                                                            <?php
                                                                                            } else if ($isLectureOngoing) {
                                                                                            ?>
                                                                                                <option value="1">Pending Attendnace</option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <option value="3">Present</option>
                                                                                            <option value="4">Absent</option>
                                                                                            <?php
                                                                                            $isHaveRecording = Database::search("SELECT * FROM `lecture_recording` WHERE `time_table_time_table_id`='" . $lecture_id . "'");
                                                                                            if ($isHaveRecording->num_rows > 0) {
                                                                                            ?>
                                                                                                <option value="5">View Recording</option>
                                                                                            <?php
                                                                                            }
                                                                                        } else {
                                                                                            ?>
                                                                                            <option value="6">Watched</option>
                                                                                            <option value="7">Not Watched</option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleSubjectLectureAttendancePagination(<?= $lecture_id ?>,'filter')">
                                                                        <option selected value="10">10</option>
                                                                        <option value="25">25</option>
                                                                        <option value="50">50</option>
                                                                        <option value="100">100</option>
                                                                        <option value="250">250</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 px-4 pb-4 component-theme">
                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                <div class="table-responsive-wrapper">
                                                                    <div class="table-responsive" id="attendance-table-container">
                                                                        <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                            <thead>
                                                                                <tr class="br-t-5">
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer w-25">
                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleSubjectLectureAttendancePagination(<?= $lecture_id ?>,'sortByName')">
                                                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                            </div>
                                                                                        </div>
                                                                                    </th>

                                                                                    <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer align-content-center" onclick="handleSubjectLectureAttendancePagination(<?= $lecture_id ?>,'sortById')">
                                                                                        <div class="d-flex justify-content-between align-items-center w-100">
                                                                                            <span class="text-14 main-text fw-medium">Student Id</span>
                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>

                                                                                    <th scope="col" class="px-4 py-3 bg-table-header align-content-center">
                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                            <span class="text-14 main-text fw-medium">Attendance Status</span>
                                                                                            <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                        </div>
                                                                                    </th>
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 27) || UserPermissions::hasPermission("lecture_schedule", 27, $lectureData['subejcts_subject_id'])) {
                                                                                        if ($lectureData['lecture_type_id'] != 3 && $dateTime > $lectureDateTime && $dateTime < $lectureEndDateTime) {
                                                                                    ?>
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center">Action</th>
                                                                                    <?php
                                                                                        }
                                                                                    }
                                                                                    ?>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
                                                                                <?php

                                                                                $query = "SELECT 
                                                                        `students`.`student_id` AS 'sId', 
                                                                        `students_has_intakes`.`student_id` AS 'shiId',
                                                                        `login_details`.`first_name`, 
                                                                        `login_details`.`last_name`, 
                                                                        `login_details`.`email`, 
                                                                        `login_details`.`whatsapp`,
                                                                        `students`.`profile_img`,
                                                                        `title`.`title`, 
                                                                        `status`.`status`";

                                                                                $query .= " FROM `students`
                                                                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                        INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                        INNER JOIN `status` ON `login_details`.`status_status_id` = `status`.`status_id`
                                                                        INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`";

                                                                                if ($lectureData["subject_type_subject_type_id"] == 2) {
                                                                                    $query .= " 
                                                                        INNER JOIN `students_has_subjects` ON `students`.`student_id` = `students_has_subjects`.`students_student_id`";
                                                                                }

                                                                                $query .= " 
                                                                        WHERE `students_has_intakes`.`intakes_intake_id` = '" . $lectureData["intake_id"] . "' 
                                                                        AND `students_has_intakes`.`status_status_id` = '1'";

                                                                                if ($lectureData["subject_type_subject_type_id"] == 2) {
                                                                                    $query .= " 
                                                                        AND `students_has_subjects`.`subjects_subject_id` = '" . $lectureData["subject_id"] . "'
                                                                        AND `students_has_subjects`.`status_status_id` = '1'";
                                                                                }

                                                                                $query .= " 
                                                                        ORDER BY `students`.`student_id` DESC";

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
                                                                                $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                                if ($users->num_rows > 0) {
                                                                                    while ($data = $users->fetch_assoc()) {
                                                                                ?>
                                                                                        <tr>
                                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sId"]; ?>" onchange="checkRowEvents(<?php echo $data['sId']; ?>);">
                                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                                                                        <?php
                                                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                        ?>
                                                                                                            <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                            $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                        ?>
                                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . ". " . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["shiId"]; ?></span></td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>
                                                                                            <?php
                                                                                            $attendStatus = "Pending";
                                                                                            $attendanceId = 0;
                                                                                            $alreadyMarkAttendance = Database::search("SELECT `attendance_status_attendance_status_id`,`attendance_id` FROM `attendance` WHERE `students_student_id`='" . $data["sId"] . "' AND `time_table_time_table_id`='" . $lectureData["time_table_id"] . "'");
                                                                                            if ($alreadyMarkAttendance->num_rows > 0) {
                                                                                                $alreadyMarkAttendanceData = $alreadyMarkAttendance->fetch_assoc();
                                                                                                $attendanceId = $alreadyMarkAttendanceData["attendance_id"];
                                                                                                if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 2) {
                                                                                                    $attendStatus = "Present";
                                                                                                } else if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 3) {
                                                                                                    $attendStatus = "Absent";
                                                                                                } else
                                                                                                if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 1) {
                                                                                                    $attendStatus = "Joined";
                                                                                                } else if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 4) {
                                                                                                    $attendStatus = "Recording Watched";
                                                                                                    if ($lectureData['lecture_type_id'] == 3) {
                                                                                                        $attendStatus = "Watch";
                                                                                                    }
                                                                                                }
                                                                                            }

                                                                                            if ($lectureData['lecture_type_id'] != 3 && $attendanceId == 0 && $dateTime > $lectureEndDateTime) {
                                                                                                $attendStatus = "Absent";
                                                                                            } else if ($lectureData['lecture_type_id'] == 3 && $attendanceId == 0) {
                                                                                                $attendStatus = "Not Watch";
                                                                                            }

                                                                                            ?>
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                                <?php
                                                                                                $status_classes = [
                                                                                                    "Pending"     => "table-btn-yellow",
                                                                                                    "Present"     => "table-btn-success",
                                                                                                    "Absent"  => "table-btn-danger",
                                                                                                    "Joined"  => "table-btn-pink",
                                                                                                    "Watched Recording"  => "table-btn-orange",
                                                                                                    "Not Watch"  => "table-btn-danger",
                                                                                                    "Watch"  => "table-btn-success",
                                                                                                ];
                                                                                                $sbutton_class = $status_classes[$attendStatus];
                                                                                                ?>
                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $attendStatus ?></button>
                                                                                            </td>
                                                                                            <?php
                                                                                            if ($lectureData['lecture_type_id'] != 3) {

                                                                                                if ($dateTime > $lectureDateTime && $dateTime < $lectureEndDateTime) {
                                                                                            ?>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                        <?php
                                                                                                        if ($lectureData["lecture_type_id"] == 2 && $attendStatus == "Joined") {
                                                                                                        ?>
                                                                                                            <div class="d-flex gap-3 align-items-center justify-content-center">
                                                                                                                <button class="table-btn table-btn-success active w-100" onclick="updateAttendance(<?= $attendanceId ?>,<?= $lecture_id ?>,2)">Present</button>
                                                                                                                <button class="table-btn table-btn-danger active w-100" onclick="updateAttendance(<?= $attendanceId ?>,<?= $lecture_id ?>,3)">Absent</button>
                                                                                                            </div>
                                                                                                        <?php
                                                                                                        } else if ($lectureData["lecture_type_id"] == 1 && $attendStatus == "Pending") {
                                                                                                        ?>
                                                                                                            <div class="d-flex gap-3 align-items-center justify-content-center">
                                                                                                                <button class="table-btn table-btn-success active w-100" onclick="markAttendance(<?= $data['sId'] ?>,<?= $lectureData['time_table_id'] ?>,2)">Present</button>
                                                                                                                <button class="table-btn table-btn-danger active w-100" onclick="markAttendance(<?= $data['sId'] ?>,<?= $lectureData['time_table_id'] ?>,3)">Absent</button>
                                                                                                            </div>
                                                                                                        <?php
                                                                                                        } else if ($attendStatus == "Present") {
                                                                                                        ?>
                                                                                                            <button class="table-btn table-btn-danger active w-100" onclick="updateAttendance(<?= $attendanceId ?>,<?= $lecture_id ?>,3)">Absent</button>
                                                                                                        <?php
                                                                                                        } else if ($attendStatus == "Absent") {
                                                                                                        ?>
                                                                                                            <button class="table-btn table-btn-success active w-100" onclick="updateAttendance(<?= $attendanceId ?>,<?= $lecture_id ?>,2)">Present</button>
                                                                                                        <?php
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectLectureAttendancePagination(<?= $lecture_id ?>,'previousPage');">
                                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                    </button>
                                                                                    <!-- Page Numbers (col-10, centered) -->
                                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                        <ul class="pagination-list d-flex mb-0">
                                                                                            <?php
                                                                                            $shudderPages1 = false;
                                                                                            $shudderPages2 = false;

                                                                                            function renderPageButton($i, $isActive = false, $lectureId)
                                                                                            {
                                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                                $action = $isActive ? '' : "onclick=\"handleSubjectLectureAttendancePagination({$lectureId},'goToPage', '{$i}')\"";
                                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                                            }

                                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                                    renderPageButton($i, $pageno == $i, $lecture_id);
                                                                                                } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                                                                    $shudderPages1 = true;
                                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                                                                    $shudderPages2 = true;
                                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                }
                                                                                            }
                                                                                            ?>

                                                                                        </ul>
                                                                                    </div>
                                                                                    <!-- Next Button (col-1) -->
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectLectureAttendancePagination(<?= $lecture_id ?>,'nextPage',<?= $number_of_pages ?>);">
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
                        <script src="./assets/js/fileChooser.js"></script>
                    </body>

                    </html>

                <?php

                }
                ?>
<?php
            }
        }
    }
}
?>