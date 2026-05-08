<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/DateTimeClass.php";
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
            $subject = Database::search("
            SELECT 
                `s`.`subject_id`,
                `f`.`faculty`,
                `f`.`faculty_id`,
                `p`.`programme`,
                `p`.`programme_id`,
                `i`.`intake`,
                `i`.`intake_id`,
                `l`.`level`,
                `l`.`level_id`,
                `s`.`subject`
            FROM `subjects` AS `s`
            INNER JOIN `levels` AS `l` ON `s`.`levels_level_id` = `l`.`level_id`
            INNER JOIN `intakes` AS `i` ON `l`.`intakes_intake_id` = `i`.`intake_id`
            INNER JOIN `programmes` AS `p` ON `i`.`programmes_programme_id` = `p`.`programme_id`
            INNER JOIN `faculties` AS `f` ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
            WHERE `s`.`subject_id` = '" . $id . "'
        ");

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

                $allowedPermissions = [1, 2, 4, 10, 11];
                $haveAnyPermission = false;

                foreach ($allowedPermissions as $permission) {
                    if (UserPermissions::hasPermission('quiz', $permission, $id)) {
                        $haveAnyPermission = true;
                    }
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                    header("Location: ./level-details?level-id=" . $subjectData['level_id']);
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Quizes - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/translations.js?= Project::getVersion() ?>"></script>
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
                                                                <span class="main-topic lh-lg" data-i18n="quizes">Quizes</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $subjectData['programme_id'] ?>"><?= $subjectData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="level-details?level-id=<?= $subjectData['level_id'] ?>"><?= $subjectData['level'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Quizes</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 2, $id)) {
                                                        ?>
                                                            <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row align-items-center">
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-i18n="add-new" onclick="location.href='add-quiz.php?subject_id=<?= $id ?>'">Add Quiz</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $quizCount = Database::search("SELECT COUNT(*) as 'count' FROM `quiz` WHERE `exams_exam_id` IN (SELECT `exam_id` FROM `exams` WHERE `subjects_subject_id` = '" . $id . "')");
                                                                        $data = $quizCount->fetch_assoc();
                                                                        ?>
                                                                        <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> <span data-i18n="quizes"></span></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-7 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleQuizesPagination(<?= $id ?>,'filter')">
                                                                    </div>
                                                                </div>

                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleQuizesPagination(<?= $id ?>,'filter')">
                                                                        <option selected value="0">- Filter Status -</option>
                                                                        <option value="1">Upcoming</option>
                                                                        <option value="2">Ongoing</option>
                                                                        <option value="3">Complete</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button">Search</button>
                                                                </div>

                                                                <div class="d-none d-lg-block col-6"></div>
                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleQuizesPagination(<?= $id ?>,'filter')">
                                                                        <option selected value="10">10</option>
                                                                        <option value="25">25</option>
                                                                        <option value="50">50</option>
                                                                        <option value="100">100</option>
                                                                        <option value="250">250</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12">
                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                <div class="table-responsive-wrapper">
                                                                    <div class="table-responsive" id="quizes-table-container">
                                                                        <table class="table bl-solid m-0 p-0 g-0" id="quizes-table">
                                                                            <thead>
                                                                                <tr class="br-t-5">
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                                            <span class="text-14 main-text fw-medium mx-2">Quiz Title</span>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Start Date Time</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">End Date Time</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Add User</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header">
                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                            <span class="text-14 main-text fw-medium">Status</span>
                                                                                            <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 10, $id) || UserPermissions::hasPermission('quiz', 11, $id)) {
                                                                                    ?>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <?php
                                                                                    }
                                                                                    ?>

                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
                                                                                <?php
                                                                                $query = "SELECT `quiz`.`quiz_id`,`exams`.`exam_id`,`exams`.`exam_name`,`exams`.`exam_date`,`quiz`.`start_time`,`quiz`.`end_date`,`quiz`.`end_time`,`quiz`.`duration`,`quiz`.`users_user_id`,`users`.`user_fname`,`user_lname`,`users`.`profile_img`,`title`.`title` FROM `quiz`
                                                                                        INNER JOIN `exams` ON `quiz`.`exams_exam_id`=`exams`.`exam_id`
                                                                                        INNER JOIN  `users` ON `quiz`.`users_user_id`=`users`.`user_id` 
                                                                                        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                                                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                                                        ORDER BY `quiz`.`date_added` DESC";

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
                                                                                            <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                                        <iconify-icon icon="material-symbols:quiz-outline" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                                    </div>
                                                                                                    <span class="text-14 main-text mx-2"><?= $data["exam_name"]; ?></span>
                                                                                                </div>
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["exam_date"] . " " . CheckDateTime::formatTimeTo12Hour($data["start_time"]); ?></span></td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["end_date"] . " " . CheckDateTime::formatTimeTo12Hour($data["end_time"]); ?></span></td>

                                                                                            <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
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
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                                <?php

                                                                                                $status_classes = [
                                                                                                    "Upcoming" => "table-btn-primary",
                                                                                                    "Ongoing"  => "table-btn-yellow",
                                                                                                    "Completed"  => "table-btn-success",
                                                                                                ];
                                                                                                $sbutton_class = $status_classes[CheckDateTime::checkTimeStatus($data['exam_date'], $data['start_time'], $data['end_date'], $data['end_time'])];
                                                                                                ?>
                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= CheckDateTime::checkTimeStatus($data['exam_date'], $data['start_time'], $data['end_date'], $data['end_time']); ?></button>
                                                                                            </td>

                                                                                            <td class="px-4 py-3 align-middle">
                                                                                                <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='quiz-students-performance.php?quiz-id=<?= $data['quiz_id']; ?>'">View</button>
                                                                                            </td>
                                                                                            <?php
                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 10, $id) || UserPermissions::hasPermission('quiz', 11, $id)) {
                                                                                            ?>
                                                                                                <td class="px-4 py-3 align-middle">
                                                                                                    <div class="dropdown">
                                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                            <?php
                                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 10, $id)) {
                                                                                                            ?>
                                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="quiz-details?quiz-id=<?= $data['quiz_id']; ?>">
                                                                                                                        <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                        Edit</a></li>
                                                                                                                <?php
                                                                                                            }

                                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 11, $id)) {
                                                                                                                if (CheckDateTime::checkTimeStatus($data['exam_date'], $data['start_time'], $data['end_date'], $data['end_time']) == "Upcoming") {
                                                                                                                ?>
                                                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="removeQuiz(<?= $data['quiz_id'] ?>)">
                                                                                                                            <iconify-icon icon="material-symbols:delete-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                            Remove</a></li>
                                                                                                            <?php
                                                                                                                }
                                                                                                            }
                                                                                                            ?>

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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleQuizesPagination(<?= $id ?>,'previousPage')">
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
                                                                                                $action = $isActive ? '' : "onclick=\"handleQuizesPagination('{$id}','goToPage', '{$i}')\"";
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleQuizesPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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

                        <?php
                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 11, $id)) {
                        ?>
                            <div class="modal fade" id="removeConfirmModal" tabindex="-1" role="dialog" aria-labelledby="removeConfirmModalTitle" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <iconify-icon icon="mdi:alert-circle-outline" class="text-danger me-2" width="24" height="24"></iconify-icon>
                                            <h5 class="modal-title" id="removeConfirmModalTitle"> Confirmation </h5>
                                        </div>
                                        <div class="modal-body">
                                            <div class="d-flex align-items-center">
                                                <iconify-icon icon="mdi:delete-alert" class="text-warning me-3" width="32" height="32"></iconify-icon>
                                                <p class="mb-0">Are you sure you want to permanently remove this quiz? This action cannot be undone.</p>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary d-flex align-items-center" data-dismiss="modal">
                                                <iconify-icon icon="mdi:close" class="me-2" width="20" height="20"></iconify-icon>
                                                Cancel
                                            </button>
                                            <button type="button" class="btn btn-danger d-flex align-items-center" id="confirmRemoveBtn" onclick="removeQuizPermanantly()">
                                                <iconify-icon icon="mdi:delete" class="me-2" width="20" height="20"></iconify-icon>
                                                Remove Permanently
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                        }
                        ?>



                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>