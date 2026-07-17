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
                            s.subject_id,
                            s.subject,
                            f.faculty,
                            f.faculty_id,
                            p.programme,
                            p.programme_id,
                            i.intake,
                            i.intake_id,
                            l.level
                        FROM `subjects` AS s
                        INNER JOIN `levels` AS l 
                            ON s.levels_level_id = l.level_id
                        INNER JOIN `intakes` AS i 
                            ON l.intakes_intake_id = i.intake_id
                        INNER JOIN `programmes` AS p 
                            ON i.programmes_programme_id = p.programme_id
                        INNER JOIN `faculties` AS f 
                            ON p.faculties_faculty_id = f.faculty_id
                        WHERE s.subject_id = '" . $id . "'
                    ");

            if ($subject->num_rows == 0) {
                header("Location: ./selected-subject");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                $validLecturer = false;
                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subjectData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('subject_lecturers', $id) || $validLecturer)) {
                    header("Location: ./subjects");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Lecturers - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                        <div class="col-12 col-md-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="assigned-lecturers">Lecturers</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $subjectData['faculty_id'] ?>"><?= $subjectData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $subjectData['programme_id'] ?>"><?= $subjectData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id="><?= $subjectData['level'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Lecturers</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 2, $id)) {
                                                        ?>
                                                            <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row align-items-center">
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn px-5 pe-5 w-100" data-i18n="add-new" data-bs-target="#addLecturer" data-bs-toggle="modal">Add</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="col-12 component-theme mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $lecturerCount = Database::search("SELECT COUNT(DISTINCT `users_user_id`) AS unique_lecturer_count FROM 
                                                                                                            `lecturers` WHERE `subjects_subject_id`= '" . $id . "'");
                                                                        $data = $lecturerCount->fetch_assoc();
                                                                        ?>
                                                                        <span class="text-14 dark-theme fw-medium"><?= $data["unique_lecturer_count"]; ?> <span data-i18n="lecturers"></span></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-8 col-md-6 col-lg-9 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="lecturers-search-text" onkeyup="filterSubjectHasLecturers(<?= $id ?>)">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-1 d-flex justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#preRecordedLectureFilter" aria-expanded="false" aria-controls="preRecordedLectureFilter">
                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                                <div class="col-12 collapse" id="preRecordedLectureFilter">
                                                                    <div class="card card-body border-0 p-0">
                                                                        <div class="row gy-3 justify-content-end">
                                                                            <?php
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 4, $id)) {
                                                                            ?>
                                                                                <div class="col-6 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="lecturers-update-selector" onchange="filterSubjectHasLecturers('<?= $id ?>')">
                                                                                        <option selected value="0">- Update Lecturers -</option>
                                                                                        <option value="1">Active Users</option>
                                                                                        <option value="2">Inactive Users</option>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn w-100" type="button" onclick="applySubjectHasLecturersUpdate(<?= $id ?>)">Apply</button>
                                                                                </div>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                            <div class="col-12 offset-0 offset-lg-6 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                <select class="form-select custom-select" aria-label="Default select example" id="lecturers-sort-selector" onchange="handleLecturersPagination('<?= $id ?>','filter')">
                                                                                    <option selected value="0">- Filter Data -</option>
                                                                                    <option value="1">Active</option>
                                                                                    <option value="2">Inactive</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="lecturers-show-result-selector" onchange="handleLecturersPagination('<?= $id ?>','filter')">
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
                                                            <div class="table-responsive" id="lecturers-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="lecturers-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="lecturers-check-all" onchange="lecturersTableCheckEvents()">
                                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleLecturersPagination('<?= $id ?>','sortByName')">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium" onclick="handleLecturersPagination('<?= $id ?>','sortByDate')">
                                                                                <div class="d-flex flex-row justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Date Added</span>
                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>

                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Completed Lecturing Hours</th>

                                                                            <th scope="col" class="py-3 bg-table-header">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-14 main-text fw-medium">Status</span>
                                                                                    <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <?php
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 1, $id) || $validLecturer) {
                                                                            ?>
                                                                                <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                            <?php
                                                                            }
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 10, $id) || $validLecturer) {
                                                                            ?>
                                                                                <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        $query = "
                                                               SELECT 
                                                                   `title`,
                                                                   `user_id`,
                                                                   `user_fname`,
                                                                   `user_lname`,
                                                                   `profile_img`,
                                                                   `level`,
                                                                   `subject_id`,
                                                                   `subject`,
                                                                   `max_course_hours`,
                                                                   `status`,
                                                                   `status_id`,
                                                                   `lecturers_id`,
                                                                    `hourly_rate`,
                                                                   `per_examination_fee`,
                                                                   `per_assignment_fee`,
                                                                   `lecturers`.`added_at`
                                                               FROM `lecturers`
                                                               INNER JOIN `subjects` 
                                                                   ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                                                               INNER JOIN `levels` 
                                                                   ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                               INNER JOIN `users` 
                                                                   ON `lecturers`.`users_user_id` = `users`.`user_id`
                                                               INNER JOIN `user_roles` 
                                                                   ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                                                               INNER JOIN `title` 
                                                                   ON `users`.`title_title_id` = `title`.`title_id`
                                                               INNER JOIN `status` 
                                                                   ON `lecturers`.`status_status_id` = `status`.`status_id`
                                                               WHERE `subjects`.`subject_id` = '" . $id . "'
                                                                   ORDER BY `lecturers`.`added_at` DESC
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
                                                                        $lecturers = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                        if ($lecturers->num_rows > 0) {
                                                                            while ($data = $lecturers->fetch_assoc()) {
                                                                                $name = $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"];
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 lecturer-row-check" id="check-lecturer<?= $data["lecturers_id"]; ?>" onchange="lecturerCheckRowEvents(<?= $data['lecturers_id']; ?>);">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["added_at"])); ?> </span></td>
                                                                                    <td class="py-2 align-middle">
                                                                                        <?php
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
                                                                                            WHERE `subjects_subject_id` = '" . $data['subject_id'] . "'
                                                                                                AND `lecturers_lecturers_id` = '" . $data['lecturers_id'] . "'
                                                                                                AND (
                                                                                                    (`date` = '" . $todayDate . "' 
                                                                                                        AND `start_time` <= '" . $nowTime . "' 
                                                                                                        AND `end_time` <= '" . $nowTime . "')
                                                                                                    OR `date` < '" . $todayDate . "'
                                                                                                )
                                                                                                AND `time_table`.`time_table_status_time_table_status_id` = '1'
                                                                                        ");

                                                                                        $row = $result->fetch_assoc();
                                                                                        ?>
                                                                                        <span class="text-14 main-text"><?= $row["total_completed_hours"]; ?></span>
                                                                                    </td>


                                                                                    <td class="py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "Active"     => "table-btn-success",
                                                                                            "Inactive"  => "table-btn-danger",
                                                                                        ];
                                                                                        $sbutton_class = $status_classes[$data["status"]];
                                                                                        ?>
                                                                                        <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["status"]; ?></button>
                                                                                    </td>
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 1, $id)) {
                                                                                    ?>
                                                                                        <td class="py-2 align-middle">
                                                                                            <button class="table-btn table-btn-dark-theme text-white w-100" onclick="window.location.href='lecturer-subject-activity.php?lecturer-id=<?= $data['lecturers_id']; ?>' ">View</button>
                                                                                        </td>
                                                                                        <?php
                                                                                    } else if ($validLecturer) {
                                                                                        if ($sessionUserObject['user_id'] == $data['user_id']) {
                                                                                        ?>
                                                                                            <td class="py-2 align-middle">
                                                                                                <button class="table-btn bg-dark-theme text-white w-100" onclick="window.location.href='lecturer-subject-activity.php?lecturer-id=<?= $data['lecturers_id']; ?>' ">View</button>
                                                                                            </td>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <td class="py-2 align-middle"></td>
                                                                                        <?php
                                                                                        }
                                                                                    }
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 10, $id)) {
                                                                                        ?>
                                                                                        <td class="py-3 align-middle">
                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                            <div class="dropdown">
                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="openModelEditLecture('<?= $name ?>',<?= $data['hourly_rate'] ?>,<?= $data['per_assignment_fee'] ?>,<?= $data['per_examination_fee'] ?>,<?= $data['lecturers_id'] ?>)">
                                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Edit</a></li>
                                                                                                </ul>
                                                                                            </div>
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
                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container2">
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

                        <?php
                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 2, $id)) {
                        ?>
                            <!-- Add lecturer modal => Start -->
                            <div class="modal fade br-0" id="addLecturer" aria-hidden="true" aria-labelledby="addlecturerLabel" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                    <div class="modal-content p-4">
                                        <div class="modal-header p-0 border-0">
                                            <h1 class="modal-title text-16 text-dark-color" id="addlecturerLabel">Add Lecturer</h1>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-0 mt-3">
                                            <div class="col-12 p-0">
                                                <div class="row p-0 gy-3 gx-3 align-content-center">
                                                    <div class="col-12 d-flex flex-column">
                                                        <label for="" class="text-14 main-text required">Select Lecturer</label>
                                                        <select name="" id="lecturer-selector" class="form-select custom-select mt-2">
                                                            <option value="0">- Select -</option>
                                                            <?php
                                                            $lecturers = Database::search("SELECT `user_id`,`title`,`user_fname`,`user_lname` FROM `users`
                                                                                            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                            WHERE `status_status_id`='1'
                                                                                            AND `user_id` NOT IN (SELECT `users_user_id` FROM `lecturers` WHERE `subjects_subject_id`='" . $id . "')");
                                                            while ($data = $lecturers->fetch_assoc()) {
                                                            ?>
                                                                <option value="<?= $data['user_id'] ?>"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></option>
                                                            <?php
                                                            }
                                                            ?>
                                                        </select>
                                                        <span class="text-12 text-dark-red" id="lecturer-feedback"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                        <label for="" class="text-14 main-text required">Hourly Rate</label>
                                                        <input type="number" class="form-control custom-input mt-2" id="lecturer-hourly-rate" min="0">
                                                        <span class="text-12 text-dark-red" id="hourly-rate-feedback"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                        <label for="" class="text-14 main-text required">Per Assignment Fee</label>
                                                        <input type="number" class="form-control custom-input mt-2" id="assignment-fee" min="0">
                                                        <span class="text-12 text-dark-red" id="assignment-fee-feedback"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                        <label for="" class="text-14 main-text required">Per Examination Fee</label>
                                                        <input type="number" class="form-control custom-input mt-2" id="examination-fee" min="0">
                                                        <span class="text-12 text-dark-red" id="examination-fee-feedback"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer p-0 mt-4 border-0">
                                            <div class="col-12 col-md-4">
                                                <button class="custom-btn m-0 w-100" onclick="assignLecturerToSubject(this,<?= $id ?>);">Add Lecturer</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Add lecturer modal => End -->
                        <?php
                        }

                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 10, $id)) {
                        ?>
                            <!-- Edit lecturer modal => Start -->
                            <div class="modal fade br-0" id="editLecturer" aria-hidden="true" aria-labelledby="editlecturerLabel" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                    <div class="modal-content p-4">
                                        <div class="modal-header p-0 border-0">
                                            <h1 class="modal-title text-16 text-dark-color" id="editlecturerLabel">Edit Lecturer</h1>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-0 mt-3">
                                            <div class="col-12 p-0">
                                                <div class="row p-0 gy-3 gx-3 align-content-center">
                                                    <div class="col-12 d-flex flex-column">
                                                        <label for="" class="text-14 main-text">Lecturer</label>
                                                        <input type="text" class="form-control custom-input mt-2" disabled id="lecturer-name">
                                                        <span class="text-12 text-dark-red" id="lecturer-feedback"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                        <label for="" class="text-14 main-text required">Hourly Rate</label>
                                                        <input type="number" class="form-control custom-input mt-2" id="edit-lecturer-hourly-rate" min="0">
                                                        <span class="text-12 text-dark-red" id="edit-hourly-rate-feedback"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                        <label for="" class="text-14 main-text required">Per Assignment Fee</label>
                                                        <input type="number" class="form-control custom-input mt-2" id="edit-assignment-fee" min="0">
                                                        <span class="text-12 text-dark-red" id="edit-assignment-free-feedback"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                        <label for="" class="text-14 main-text required">Per Examination Fee</label>
                                                        <input type="number" class="form-control custom-input mt-2" id="edit-examination-fee" min="0">
                                                        <span class="text-12 text-dark-red" id="edit-examination-free-feedback"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer p-0 mt-4 border-0">
                                            <div class="col-12 col-md-4">
                                                <button class="custom-btn m-0 w-100" id="editLecturerBtn">Save Changes</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Edit lecturer modal => End -->
                        <?php
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