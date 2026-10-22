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
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./users");
        } else {

            $id = $_GET['student-id'];


            $path = 0;
            if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['path']) && !empty($_GET['path'])) {
                $path = $_GET['path'];
            }


            $studentHasIntake = Database::search("SELECT
                `faculty`, `faculty_id`, `programme`, `programme_id`, `intake`, `intake_id`,
                `students_has_intakes`.`student_id`, `first_name`, `last_name`, `title`, `students_has_intakes_id`,
                `students_has_intakes`.`status_status_id`,`login_details`.`status_status_id` AS 'status_status_id_login_details'
            FROM `students_has_intakes`
            INNER JOIN `students`       ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `login_details`  ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
            INNER JOIN `title`          ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `intakes`        ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes`     ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties`      ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'");

            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./students");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole)
                    || SessionUser::ifUserIsAcademic($sessionUserRole)
                    || SessionUser::ifUserIsAccounts($sessionUserRole)
                    || SessionUser::ifUserIsCounsiler($sessionUserRole)
                    || UserPermissions::hasPermission('intake_payments', 10, $studentHasIntakeData['intake_id'])
                    || UserPermissions::hasPermission('intake_payments', 12, $studentHasIntakeData['intake_id'])
                    || UserPermissions::hasPermission('intake_payments', 13, $studentHasIntakeData['intake_id'])
                    || UserPermissions::hasPermission('intake_payments', 15, $studentHasIntakeData['intake_id']))) {
                    header("Location: ./faculties");
                } else {

                    $query = "
                                                                        SELECT 
                                                                            `tt`.`time_table_id`,
                                                                            `tt`.`lecture_title`,
                                                                            `tt`.`duration`,
                                                                            `tt`.`date_added`,
                                                                            `lt`.`lecture_type`,
                                                                            `lt`.`lecture_type_id`,
                                                                            `tts`.`time_table_status_id`,
                                                                            `tts`.`time_table_status`,
                                                                            `tt`.`date`,
                                                                            `tt`.`start_time`,
                                                                            `tt`.`end_time`,
                                                                            `u`.`user_fname`,
                                                                            `u`.`user_lname`,
                                                                            `t`.`title`,
                                                                            COUNT(DISTINCT `att`.`attachment_id`) AS `attachment_count`,
                                                                            COUNT(DISTINCT `rec`.`lecture_recording_lecture_recording_id`) AS `recording_count`,

                                                                            CASE
                                                                                WHEN CONCAT(`tt`.`date`, ' ', `tt`.`end_time`) >= NOW() THEN 1
                                                                                ELSE 2
                                                                            END AS `lecture_sort_group`

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

                                                                        INNER JOIN `subjects` AS `sub`
                                                                            ON `tt`.`subjects_subject_id` = `sub`.`subject_id`

                                                                        INNER JOIN `levels` AS `l`
                                                                            ON `sub`.`levels_level_id` = `l`.`level_id`

                                                                        INNER JOIN `intakes` AS `i`
                                                                            ON `l`.`intakes_intake_id` = `i`.`intake_id`

                                                                        INNER JOIN `programmes` AS `p`
                                                                            ON `i`.`programmes_programme_id` = `p`.`programme_id`

                                                                        LEFT JOIN `attachments` AS `att`
                                                                            ON `tt`.`time_table_id` = `att`.`time_table_time_table_id`

                                                                        LEFT JOIN `lecture_recording_has_lectures` AS `rec`
                                                                            ON `tt`.`time_table_id` = `rec`.`time_table_time_table_id`

                                                                        WHERE  `tt`.`lecture_type_lecture_type_id` != '3'

                                                                        AND `i`.`intake_id`='" . $studentHasIntakeData['intake_id'] . "'

                                                                        GROUP BY `tt`.`time_table_id`

                                                                        ORDER BY `tt`.`date`, `tt`.`start_time` ASC
                                                                        ";

                    $results = Database::search($query);
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Intake Student Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>
                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                                            include './includes/components/skeleton-header.php';
                                            include './includes/components/skeleton-table.php'; ?>
                                        </div>

                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="">Student Time Table</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <?php
                                                                        if ($path == 0) {
                                                                        ?>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./students">Registrations</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Student Time Table</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $studentHasIntakeData['title'] . "." . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name']  ?></a></li>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./students">Students</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Student Details</a></li>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="col-12 mb-4">
                                                            <div class="row gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 justify-content-end">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">

                                                                        <span class="text-14 dark-theme fw-medium"><?= $results->num_rows ?> Lectures</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-9 justify-content-end">
                                                                    <div class="d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-1 d-flex justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#lectureFilter" aria-expanded="false" aria-controls="lectureFilter">
                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                    </button>
                                                                </div>

                                                                <div class="collapse" id="lectureFilter">
                                                                    <div class="card card-body border-0 p-0">
                                                                        <div class="row gy-3 justify-content-end">
                                                                            <div class="col-6 col-md-4 col-xl-2 justify-content-end">
                                                                                <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleStudentTimeTablePagination(<?= $id ?>,'filter')">
                                                                                    <option selected value="0">- Filter Data -</option>
                                                                                    <option value="1">Upcoming</option>
                                                                                    <option value="2">Completed</option>
                                                                                    <option value="3">Study Leave</option>
                                                                                    <option value="3">Cancelled</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-6 col-md-4 col-xl-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentTimeTablePagination(<?= $id ?>,'filter')">
                                                                        <option value="100" selected>100</option>
                                                                        <option value="20">20</option>
                                                                        <option value="30">30</option>
                                                                        <option value="40">40</option>
                                                                        <option value="300">300</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="overflow-x-auto bg-table-header p-0 m-0 g-0" id="schedule-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="lecture-schedule-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="subjectLecScheduletableCheckEvents()">
                                                                                <span class="text-14 main-text fw-medium mx-2">Lecture Title</span>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Lecturer</th>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Lecture Type</th>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date</th>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Attachments</th>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Recordings</th>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <span class="text-14 main-text fw-medium">Status</span>
                                                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                                                            </div>
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php

                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = 100;  // Consistent variable name

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $lectures = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                    if ($lectures->num_rows > 0) {
                                                                        while ($data = $lectures->fetch_assoc()) {
                                                                            $lecture_status = [
                                                                                "1" => "table-btn-primary",
                                                                                "2"  => "table-btn-danger",
                                                                                "3" => "table-btn-purple",
                                                                                "4" => "table-btn-pink",
                                                                                "5" => "table-btn-yellow",
                                                                                "6" => "table-btn-success",
                                                                            ];
                                                                            $status = "Upcoming";
                                                                            $statusId = '1';
                                                                            if ($data["time_table_status_id"] != 1) {
                                                                                $status = $data['time_table_status'];
                                                                                $statusId = $data['time_table_status_id'];
                                                                            } else {
                                                                                $timezone = new DateTimeZone("Asia/Colombo");
                                                                                $today = new DateTime("now", $timezone);
                                                                                $todayDate = $today->format('Y-m-d');
                                                                                $nowTime = $today->format('H:i:s');

                                                                                $dataDate = (new DateTime($data["date"]))->format('Y-m-d');
                                                                                if ($dataDate === $todayDate) {
                                                                                    if ($data["start_time"] <= $nowTime && $data["end_time"] >= $nowTime) {
                                                                                        $status = "Ongoing";
                                                                                        $statusId = '5';
                                                                                    } else if ($data["end_time"] < $nowTime) {
                                                                                        $status = "Completed";
                                                                                        $statusId = '6';
                                                                                    } else {
                                                                                        $status = "Upcoming";
                                                                                    }
                                                                                } else if ($dataDate < $todayDate) {
                                                                                    $status = "Completed";
                                                                                    $statusId = '6';
                                                                                }
                                                                            }

                                                                            $status_button_class = $lecture_status[$statusId];

                                                                            $deleteEnabale = $statusId == 1 ? true : false;

                                                                            if (!$deleteEnabale) {
                                                                                $hasRecording = Database::search("SELECT * FROM `lecture_recording_has_lectures` WHERE `time_table_time_table_id`='" . $data['time_table_id'] . "'");
                                                                                if ($hasRecording->num_rows == 0) {
                                                                                    $addLecturerPayments = Database::search("SELECT * FROM `lecturer_payments_has_lectures` WHERE `time_table_time_table_id`='" . $data['time_table_id'] . "'");
                                                                                    if ($addLecturerPayments->num_rows == 0) {
                                                                                        $deleteEnabale = true;
                                                                                    }
                                                                                }
                                                                            }
                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox"
                                                                                            class="form-check-input text-20 mt-0 mb-0 me-2 row-check"
                                                                                            value="<?= $data['time_table_id'] ?>"
                                                                                            <?= $deleteEnabale ? "" : "disabled" ?>>
                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["lecture_title"] ?></span>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="px-4 py-2 align-middle">
                                                                                    <?php
                                                                                    if ($data["lecture_type_id"] == 1) {
                                                                                    ?>
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <div class="custom-round-div bg-light-green border-white d-flex flex-row align-items-center justify-content-center">
                                                                                                <iconify-icon icon="material-symbols:home-work" width="15" height="15" class="text-dark-green m-0 p-0 g-0"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-14 text-dark-green fw-medium px-3">Physical</span>
                                                                                        </div>
                                                                                    <?php
                                                                                    } else if ($data["lecture_type_id"] == 2) {
                                                                                    ?>
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <div class="custom-round-div bg-light-purple border-white d-flex flex-row align-items-center justify-content-center">
                                                                                                <iconify-icon icon="wpf:online" width="15" height="15" class="text-dark-purple m-0 p-0 g-0"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-14 text-dark-purple fw-medium px-3">Online</span>
                                                                                        </div>
                                                                                    <?php
                                                                                    } else if ($data["lecture_type_id"] == 3) {
                                                                                    ?>
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <div class="custom-round-div bg-light-orange border-white d-flex flex-row align-items-center justify-content-center">
                                                                                                <iconify-icon icon="mdi:video" width="15" height="15" class="text-dark-orange m-0 p-0 g-0"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-14 text-dark-orange fw-medium px-3">Recording</span>
                                                                                        </div>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </td>

                                                                                <?php
                                                                                $start = (new DateTime($data["start_time"]))->format('h:i A');
                                                                                $end = (new DateTime($data["end_time"]))->format('h:i A');
                                                                                $date = $data["date"] . " " . $start . " - " . $end;
                                                                                ?>
                                                                                <td class="px-4 py-2 align-middle">
                                                                                    <div class="d-flex flex-column">
                                                                                        <span class="text-14 main-text"><?php echo $data["date"] ?></span>
                                                                                        <span class="text-14 sub-text"><?php echo $start ?> - <?php echo $end ?></span>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['duration'] ?></span></td>
                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['attachment_count'] ?></span></td>
                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['recording_count'] ?></span></td>
                                                                                <td class="px-4 py-3 align-middle">
                                                                                    <button class="table-btn <?php echo $status_button_class; ?> w-100"><?php echo $status; ?></button>
                                                                                </td>

                                                                            </tr>
                                                                        <?php
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <tr class="justify-content-center align-middle">
                                                                            <td colspan="13" class="align-middle justify-content-center">
                                                                                <div class="d-flex flex-column align-items-center">
                                                                                    <img src="./assets/imgs/common/no-data.png" class="w-25 h-auto" alt="No data" class="empty-icon" />
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </tbody>
                                                            </table>
                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme" id="pagination-container">
                                                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                    <div class="row p-4 align-items-center">
                                                                        <!-- Previous Button (col-1) -->
                                                                        <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleStudentTimeTablePagination(<?= $id ?>,'previousPage')">
                                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                                                        </button>
                                                                        <!-- Page Numbers (col-10, centered) -->
                                                                        <div class="col-10 custom-pagination d-flex justify-content-center">
                                                                            <ul class="pagination-list d-flex mb-0">
                                                                                <?php
                                                                                $shudderPages1 = false;
                                                                                $shudderPages2 = false;

                                                                                function renderPageButton($id, $i, $isActive = false)
                                                                                {
                                                                                    $activeClass = $isActive ? 'active' : '';
                                                                                    $action = $isActive ? '' : "onclick=\"handleStudentTimeTablePagination('{$id}','goToPage', '{$i}')\"";
                                                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                }

                                                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                        renderPageButton($id, $i, $pageno == $i);
                                                                                    } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                        $shudderPages1 = true;
                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                                                    } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                        $shudderPages2 = true;
                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                                                    }
                                                                                }
                                                                                ?>
                                                                            </ul>
                                                                        </div>
                                                                        <!-- Next Button (col-1) -->
                                                                        <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentTimeTablePagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                                                        </button>
                                                                    </div>
                                                                </nav>
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

                        <div class="modal fade" id="removeLectureConfirmModal" tabindex="-1"
                            aria-labelledby="removeLectureConfirmModalTitle" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">

                                    <!-- Close button -->
                                    <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                                        <button type="button"
                                            class="btn-close text-end"
                                            data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body text-center p-4">

                                        <!-- Warning Icon -->
                                        <div class="mb-3">
                                            <iconify-icon
                                                icon="bx:error-circle"
                                                width="48"
                                                height="48"
                                                class="text-danger">
                                            </iconify-icon>
                                        </div>

                                        <!-- Title -->
                                        <h5 class="fw-semibold mb-2" id="removeLectureConfirmModalTitle">
                                            Confirm Scheduled Lecture Deletion
                                        </h5>

                                        <!-- Main message -->
                                        <p class="text-muted mb-3">
                                            You are about to delete this <strong>Scheduled Lecture</strong>.
                                        </p>

                                        <!-- Detailed impact explanation -->
                                        <div class="alert alert-warning text-start small mb-4">
                                            <div class="d-flex align-items-start gap-2">
                                                <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                                <div>
                                                    <strong>This action will:</strong>
                                                    <ul class="mb-0 ps-3">
                                                        <li>Permanently remove the scheduled lecture</li>
                                                        <li>Delete <strong>all associated attachments,attendance and materials</strong></li>
                                                        <li>This action <strong>cannot be undone</strong></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action buttons -->
                                        <div class="d-flex justify-content-center gap-3">
                                            <button type="button"
                                                class="btn btn-outline-secondary px-4 br-0"
                                                data-bs-dismiss="modal">
                                                Cancel
                                            </button>

                                            <button type="button"
                                                id="confirmRemoveLecture"
                                                class="btn btn-danger px-4 br-0">
                                                <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                                Delete Lecture
                                            </button>
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
                    </body>

                    </html>
<?php
                } // end permission check
            } // end student found
        } // end GET check
    } // end role check
} // end session check
?>