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
            header("Location: ./subjects");
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
                `subjects`.`subject`
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
                header("Location: ./subjects");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subjectData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('pre_Recorded_lectures', $id) || $validLecturer)) {
                    header("Location: ./subjects");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Pre Recorded Lectures - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

                    </head>

                    <body class="overflow-hidden">
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
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-table.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="pre-recorded-lectures">Subject Pre Recorded Lectures</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $subjectData['programme_id'] ?>"><?= $subjectData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id=<?= $subjectData['level_id'] ?>"><?= $subjectData['level'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $subjectData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Pre Recorded Lecture</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('pre_Recorded_lectures', 2, $id)) {
                                                        ?>
                                                            <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row align-items-center">
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-i18n="add-new" onclick="window.location.href='subject-add-pre-recorded-lecture?subject-id=<?= $id ?>'">Add</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="col-12 mb-4">
                                                            <div class="row gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 justify-content-end">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $lecture_count = Database::search("SELECT COUNT(*) as 'count' FROM `time_table` WHERE `subjects_subject_id`='" . $id . "' AND `lecture_type_lecture_type_id` = '3'");
                                                                        $lecture_count_data = $lecture_count->fetch_array();
                                                                        ?>
                                                                        <span class="text-14 dark-theme fw-medium"><?php echo $lecture_count_data[0]; ?> <span data-i18n="recorded-lectures"></span></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-9 justify-content-end">
                                                                    <div class="d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-1 d-flex justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#preRecordedLectureFilter" aria-expanded="false" aria-controls="preRecordedLectureFilter">
                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                                <div class="collapse" id="preRecordedLectureFilter">
                                                                    <div class="card card-body border-0 p-0">
                                                                        <div class="row gy-3 justify-content-end">
                                                                            <div class="col-6 col-md-4 col-xl-2 justify-content-end">
                                                                                <select name="" id="lecturer-selector" class="form-select custom-select" onchange="handleSubjectPrerecordedLecturesPagination(<?= $id ?>,'filter')">
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
                                                                            </div>

                                                                            <div class="col-6 col-md-4 col-xl-2 justify-content-end">
                                                                                <select class="form-select custom-select" aria-label="Default select example" id="filter-selector" onchange="handleSubjectPrerecordedLecturesPagination(<?= $id ?>,'filter')">
                                                                                    <option selected value="0">- Filter Data -</option>
                                                                                    <option value="1">UnLocked</option>
                                                                                    <option value="2">Locked</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-6 col-md-4 col-xl-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleSubjectPrerecordedLecturesPagination(<?= $id ?>,'filter')">
                                                                        <option selected value="10">10</option>
                                                                        <option value="20">20</option>
                                                                        <option value="30">30</option>
                                                                        <option value="40">40</option>
                                                                        <option value="50">50</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="schedule-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="lecture-schedule-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium mx-2">Lecturer Name</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Status</th>


                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                            <?php
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('pre_Recorded_lectures', 10, $id)) {
                                                                            ?>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        $query = "
                                                                SELECT 
                                                                    `tt`.`time_table_id`,
                                                                    `lt`.`lecture_type`,
                                                                    `lt`.`lecture_type_id`,
                                                                    `tts`.`time_table_status_id`,
                                                                    `tts`.`time_table_status`,
                                                                    `tt`.`date`,
                                                                    `tt`.`duration`,
                                                                    `u`.`user_fname`,
                                                                    `u`.`user_lname`,
                                                                    `t`.`title`,
                                                                    `tt`.`lecture`
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
                                                                WHERE `tt`.`subjects_subject_id`='" . $id . "'
                                                                AND `tt`.`lecture_type_lecture_type_id`='3'
                                                                ORDER BY `tt`.`time_table_id` DESC
                                                                ";

                                                                        $results = Database::search($query);
                                                                        $pageno = 1;
                                                                        $results_count = $results->num_rows;
                                                                        $result_per_page = 10;

                                                                        if ($results_count > $result_per_page) {
                                                                            $number_of_pages = ceil($results_count / $result_per_page);
                                                                        } else {
                                                                            $number_of_pages = 1;
                                                                        }

                                                                        $page_results = ($pageno - 1) * $result_per_page;
                                                                        $lectures = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                        if ($lectures->num_rows > 0) {
                                                                            while ($data = $lectures->fetch_assoc()) {

                                                                                $hours = floor($data["duration"] / 60);
                                                                                $mins = $data["duration"] % 60;

                                                                                $data["duration"] =  ($hours > 0 ? "{$hours}h " : "") . ($mins > 0 ? "{$mins}min" : "");

                                                                                $iframeHtml = $data['lecture'];
                                                                                $src = "";

                                                                                if (preg_match('/src="([^"]+)"/', $iframeHtml, $matches)) {
                                                                                    $src = $matches[1];
                                                                                }
                                                                        ?>
                                                                                <tr>
                                                                                    <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['duration'] ?></span></td>

                                                                                    <td class="px-4 py-3 align-middle">
                                                                                        <?php
                                                                                        $lecture_status = [
                                                                                            "1" => "table-btn-success",
                                                                                            "2"  => "table-btn-danger",

                                                                                        ];
                                                                                        $status = "Unlocked";
                                                                                        $statusId = '1';
                                                                                        if ($data["time_table_status_id"] != 1) {
                                                                                            $status = "Locked";
                                                                                            $statusId = '2';
                                                                                        }

                                                                                        $status_button_class = $lecture_status[$statusId];
                                                                                        ?>
                                                                                        <button class="table-btn <?php echo $status_button_class; ?> w-100"><?php echo $status; ?></button>
                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <button class="table-btn table-btn-dark-theme" onclick="openLectureModal('<?= $src ?>')">View</button>
                                                                                    </td>
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('pre_Recorded_lectures', 10, $id)) {
                                                                                    ?>
                                                                                        <td class="px-4 py-3 align-middle">
                                                                                            <div class="dropdown">
                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer z-0" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5 z-1" aria-labelledby="dropdownMenuButton2">

                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-subject-pre-recorded-lecture?lecture-id=<?php echo $data['time_table_id']; ?>">
                                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-2"></iconify-icon>
                                                                                                            Edit</a></li>

                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="subject-pre-recorded-lecture-attendance?lecture-id=<?php echo $data['time_table_id']; ?>">
                                                                                                            <iconify-icon icon="gg:check-o" width="20" height="20" class="sub-text me-2"></iconify-icon>
                                                                                                            Attendnace</a></li>
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
                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                    <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                        <div class="row p-4 align-items-center">
                                                                            <!-- Previous Button (col-1) -->
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectPrerecordedLecturesPagination(<?= $id ?>,'previousPage')">
                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                                            </button>
                                                                            <!-- Page Numbers (col-10, centered) -->
                                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                <ul class="pagination-list d-flex mb-0">
                                                                                    <?php
                                                                                    $shudderPages1 = false;
                                                                                    $shudderPages2 = false;

                                                                                    function renderPageButton($id, $i, $isActive = false)
                                                                                    {
                                                                                        $activeClass = $isActive ? 'active' : '';
                                                                                        $action = $isActive ? '' : "onclick=\"handleSubjectPrerecordedLecturesPagination('{$id}','goToPage', '{$i}')\"";
                                                                                        echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                    }

                                                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                            renderPageButton($id, $i, $pageno == $i);
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectPrerecordedLecturesPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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

                                            <div class="modal fade br-0" id="lectureView" aria-hidden="true" aria-labelledby="lectureViewLabel" tabindex="-1">
                                                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                                    <div class="modal-content p-4">
                                                        <div class="modal-header p-0 border-0">
                                                            <h1 class="modal-title text-16 text-dark-color" id="lectureViewLabel">Lecture</h1>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body align-content-center justify-content-center p-0 mt-3" id="lectureModalBody">

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
                    </body>

                    </html>
<?php
                }
            }
        }
    }
} ?>