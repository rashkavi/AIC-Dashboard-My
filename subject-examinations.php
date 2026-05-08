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
                `subjects`.`subject`,
                `subjects`.`subject_id`
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
                header("Location: ./level-details");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions'], $id);

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subjectData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                $allowedPermissions = [1, 2, 4, 10, 11, 17, 20];
                $haveAnyPermission = false;

                foreach ($allowedPermissions as $permission) {
                    if (UserPermissions::hasPermission('exams', $permission, $id)) {
                        $haveAnyPermission = true;
                    }
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                    header("Location: ./level-details");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Examinations - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                                <span class="main-topic lh-lg" data-i18n="examinations">Examinatons</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $subjectData['faculty_id'] ?>"><?= $subjectData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $subjectData['programme_id'] ?>"><?= $subjectData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./level-details?level-id=<?= $subjectData['level_id'] ?>"><?= $subjectData['level'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Examinations</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 2, $id)) {
                                                        ?><div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row align-items-center">
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-i18n="add-new" onclick="window.location.href='subject-add-examination?subject-id=<?= $id ?>'">Add Examination</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-end">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $subjectHasExams = Database::search("SELECT COUNT(*) as count FROM `exams` WHERE `subjects_subject_id` = '" . $id . "'");
                                                                $subjectHasExamsCount = $subjectHasExams->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?= $subjectHasExamsCount['count'] != null ? $subjectHasExamsCount['count'] : 0 ?> <span data-i18n="examinations"></span></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleSubjectHasExaminationsPagination(<?= $id ?>,'filter')">
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterAccordion" aria-expanded="false" aria-controls="usersFilter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 collapse" id="filterAccordion">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="row gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="type-selector" onchange="handleSubjectHasExaminationsPagination(<?= $id ?>,'filter')">
                                                                            <option selected value="0">Select Type</option>
                                                                            <option value="1">Physical</option>
                                                                            <option value="2">Online</option>
                                                                        </select>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleSubjectHasExaminationsPagination(<?= $id ?>,'filter')">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="examinations-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="examinations-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                                    <span class="text-14 main-text fw-medium">Examination</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Exam Type</th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Examination Date</th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Schedule Date</th>
                                                                            <th scope="col-2" class="px-4 py-3 bg-table-header">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-14 main-text fw-medium">Status</span>
                                                                                    <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                            <?php
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 10, $id)) {
                                                                            ?>
                                                                                <th scope="col-2" class="px-4 py-3 bg-table-header"></th>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        $query = "SELECT * FROM `exams` INNER JOIN `exam_types` ON `exams`.`exam_types_exam_type_id`=`exam_types`.`exam_type_id` 
                                                                        WHERE `subjects_subject_id`='" . $id . "' AND `exam_types_exam_type_id` !='3' ORDER BY `exams`.`date_added` DESC";

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
                                                                                    <td scope="row" class="px-4 py-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                                            <span class="text-14 main-text mx-2"><?= $data["exam_name"] ?></span>
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="px-4 py-3 align-middle"><span class="text-14 main-text"><?= $data["exam_type"] ?></span></td>
                                                                                    <td class="px-4 py-3 align-middle"><span class="text-14 main-text"><?= $data["exam_date"] ?></span></td>
                                                                                    <td class="px-4 py-3 align-middle"><span class="text-14 main-text"><?= $data["date_added"] ?></span></td>

                                                                                    <td class="px-4 py-3 align-middle">
                                                                                        <?php

                                                                                        $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                                                                                        $examDate = new DateTime($data["exam_date"], new DateTimeZone("Asia/Colombo"));
                                                                                        $startTime;
                                                                                        $endTime;
                                                                                        $isScheduledExams = false;
                                                                                        if ($data["exam_type_id"] == 1) {
                                                                                            $physical_exam = Database::search("SELECT `start_time`,`end_time` FROM `physical_exam` WHERE `exams_exam_id`='" . $data["exam_id"] . "'");
                                                                                            if ($physical_exam->num_rows > 0) {
                                                                                                $physical_exam = $physical_exam->fetch_assoc();
                                                                                                $startTime = $physical_exam["start_time"];
                                                                                                $endTime = $physical_exam["end_time"];
                                                                                                $isScheduledExams = true;
                                                                                            }
                                                                                        } else if ($data["exam_type_id"] == 2) {
                                                                                            $online_exam = Database::search("SELECT `start_time`,`end_time` FROM `online_exam` WHERE `exams_exam_id`='" . $data["exam_id"] . "'");
                                                                                            if ($online_exam->num_rows > 0) {
                                                                                                $online_exam = $online_exam->fetch_assoc();
                                                                                                $startTime = $online_exam["start_time"];
                                                                                                $endTime = $online_exam["end_time"];
                                                                                                $isScheduledExams = true;
                                                                                            }
                                                                                        }
                                                                                        if (!$isScheduledExams) {
                                                                                        ?>
                                                                                            <button class="table-btn table-btn-danger w-100">Not-Scheduled</button>
                                                                                            <?php
                                                                                        } else {
                                                                                            if ($dateTime->format("Y-m-d") === $examDate->format("Y-m-d") && $dateTime->format("H:i:s") >= $startTime && $dateTime->format("H:i:s") <= $endTime) {
                                                                                            ?>
                                                                                                <button class="table-btn table-btn-yellow w-100">Ongoing</button>
                                                                                            <?php
                                                                                            } else if ($dateTime->format("Y-m-d") > $examDate->format("Y-m-d") || ($dateTime->format("Y-m-d") === $examDate->format("Y-m-d") && $dateTime->format("H:i:s") > $endTime)) {
                                                                                            ?>
                                                                                                <button class="table-btn table-btn-success w-100">Completed</button>
                                                                                            <?php
                                                                                            } else if ($dateTime->format("Y-m-d") < $examDate->format("Y-m-d") || ($dateTime->format("Y-m-d") === $examDate->format("Y-m-d") && $dateTime->format("H:i:s") < $startTime)) {
                                                                                            ?>
                                                                                                <button class="table-btn table-btn-purple w-100">Upcoming</button>
                                                                                        <?php
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                    </td>
                                                                                    <td class="px-4 pt-2 pb-2 align-middle">
                                                                                        <?php
                                                                                        if ($isScheduledExams) {
                                                                                        ?>
                                                                                            <button class="table-btn table-btn-dark-theme" onclick="window.location.href='subject-examination?examination-id=<?= $data['exam_id']; ?>' ">View</button>
                                                                                        <?php
                                                                                        }
                                                                                        ?>

                                                                                    </td>
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 10, $id)) {
                                                                                    ?>

                                                                                        <td class="px-4 py-3 align-middle">
                                                                                            <div class="dropdown">
                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="update-examination?examination-id=<?= $data['exam_id']; ?>">
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
                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                    <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                        <div class="row p-4 align-items-center">
                                                                            <!-- Previous Button (col-1) -->
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectHasExaminationsPagination(<?= $id ?>,'previousPage');">
                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                                            </button>
                                                                            <!-- Page Numbers (col-10, centered) -->
                                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                <ul class="pagination-list d-flex mb-0">
                                                                                    <?php
                                                                                    $shudderPages1 = false;
                                                                                    $shudderPages2 = false;

                                                                                    function renderPageButton($i, $isActive = false, $id)
                                                                                    {
                                                                                        $activeClass = $isActive ? 'active' : '';
                                                                                        $action = $isActive ? '' : "onclick=\"handleSubjectHasExaminationsPagination('{$id}','goToPage', '{$i}')\"";
                                                                                        echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                                    }

                                                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                            renderPageButton($i, $pageno == $i, $id);
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectHasExaminationsPagination(<?= $id ?>,'nextPage',<?= $pageno ?>,<?= $number_of_pages ?>);">
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