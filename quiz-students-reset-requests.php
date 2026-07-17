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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['quiz-id']) && !empty($_GET['quiz-id']))) {
            header("Location: ./module-details");
        } else {

            $id = $_GET['quiz-id'];

            $quiz = Database::search("
            SELECT 
                `quiz`.`quiz_id`,
                `faculties`.`faculty`,
                `faculties`.`faculty_id`,
                `intakes`.`intake_id`,
                `levels`.`level`,
                `levels`.`level_id`,
                `subjects`.`subject`,
                `subjects`.`subject_id`,
                `exams`.`exam_name`,
                `exams`.`exam_date`
            FROM `quiz`
            INNER JOIN `exams` 
                ON `quiz`.`exams_exam_id` = `exams`.`exam_id`
            INNER JOIN `subjects` 
                ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
            INNER JOIN `levels` 
                ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` 
                ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `quiz`.`quiz_id` = '" . $id . "'
        ");

            if ($quiz->num_rows == 0) {
                header("Location: ./module-details");
            } else {
                $quizData = $quiz->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $quizData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 1, $quizData['subject_id']) || UserPermissions::hasPermission('quiz', 17, $quizData['subject_id']) || $validLecturer)) {
                    header("Location: ./unit-quizes?unit-id=" . $quizData['subject_id']);
                } else {

                    $query = "SELECT * FROM `students_has_quiz_reset_request` 
                INNER JOIN `students_has_quiz` ON `students_has_quiz_reset_request`.`students_has_quiz_students_has_quiz_id`=`students_has_quiz`.`students_has_quiz_id`
                INNER JOIN `students` ON `students_has_quiz`.`students_student_id`=`students`.`student_id`
                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                INNER JOIN `quiz_reset_status` ON `students_has_quiz_reset_request`.`quiz_reset_status_quiz_reset_status_id`=`quiz_reset_status`.`quiz_reset_status_id`
                WHERE `quiz_quiz_id`='" . $id . "'";

                    $results = Database::search($query);
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Quiz Reset Requests - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Quiz - Students Reset Requests</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="programes?program-id=<?= $quizData['faculty_id'] ?>"><?= $quizData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subject-details?level-id=<?= $quizData['subject_id'] ?>"><?= $quizData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subject-quizes?subject-id=<?= $quizData['subject_id'] ?>">Quizes</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./quiz-students-performance.php?quiz-id=<?= $quizData['quiz_id'] ?>"><?= $quizData['exam_name'] ?> - Students Performance</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" >Reset Requests</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <span class="text-14 dark-theme fw-medium"><?= $results->num_rows; ?> Requests</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-5 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="user-search-text" onkeyup="handleStudentsQuizResetRequestPagination(<?= $id ?>,'filter')">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="filter-selector" onchange="handleStudentsQuizResetRequestPagination(<?= $id ?>,'filter')">
                                                                        <option selected value="0">- filter Selector -</option>
                                                                        <?php
                                                                        $quiz_reset_status = Database::search("SELECT * FROM `quiz_reset_status`");
                                                                        while ($data = $quiz_reset_status->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $data["quiz_reset_status_id"]; ?>"><?= $data["quiz_reset_status"]; ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleStudentsQuizResetRequestPagination(<?= $id ?>,'filter')">
                                                                        <option selected value="0">- Filter Data -</option>
                                                                        <option value="1">Mark High to Low</option>
                                                                        <option value="2">Mark Low to High</option>
                                                                        <option value="3">Recently Submitted</option>
                                                                        <option value="4">Early Submitted</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button">Search</button>
                                                                </div>

                                                                <div class="d-none d-lg-block col-7"></div>
                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentsQuizResetRequestPagination(<?= $id ?>,'filter')">
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
                                                                    <div class="table-responsive" id="students-quiz-table-container">
                                                                        <table class="table bl-solid m-0 p-0 g-0" id="students-quiz-table">
                                                                            <thead>
                                                                                <tr class="br-t-5">
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                            <span class="text-14 main-text fw-medium mx-2">Student</span>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Request Date</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Approve User</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Approve Date</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Status</th>

                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
                                                                                <?php

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
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["student_id"]; ?>" onchange="checkRowEvents(<?= $data['student_id']; ?>);">
                                                                                                    <?php
                                                                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                    ?>
                                                                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Student image">
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
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["request_date_time"]; ?></span></td>

                                                                                            <?php

                                                                                            if ($data["quiz_reset_status_id"] == 1) {
                                                                                            ?>
                                                                                                <td colspan="4" class="px-4 py-2 align-middle"><span class="text-14 main-text">N/A</span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text">N/A</span></td>
                                                                                                <?php
                                                                                            } else {
                                                                                                $approveUser = Database::search("SELECT * FROM `users` INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` WHERE `user_id`='" . $data["users_user_id"] . "'");
                                                                                                if ($approveUser->num_rows > 0) {
                                                                                                    $approveUserData = $approveUser->fetch_assoc();
                                                                                                ?>
                                                                                                    <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                                                <?php
                                                                                                                if (!empty($approveUserData["profile_img"]) && file_exists('./assets/imgs/users/' . $approveUserData["profile_img"])) {
                                                                                                                ?>
                                                                                                                    <img src="./assets/imgs/users/<?php echo $approveUserData["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                                <?php
                                                                                                                } else {
                                                                                                                    $firstNameInitial = strtoupper(substr($approveUserData["user_fname"], 0, 1));
                                                                                                                    $lastNameInitial = strtoupper(substr($approveUserData["user_lname"], 0, 1));
                                                                                                                ?>
                                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                                <?php
                                                                                                                }
                                                                                                                ?>
                                                                                                                <span class="text-14 main-text mx-2"><?php echo $approveUserData["title"] . "." . $approveUserData["user_fname"] . " " . $approveUserData["user_lname"]; ?></span>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                <?php
                                                                                                } else {
                                                                                                    echo "<td colspan='4' class='px-4 py-3 pe-0 align-middle'>N/A</td>";
                                                                                                }
                                                                                                ?>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data['update_date_tme'] ?></span></td>
                                                                                            <?php
                                                                                            }
                                                                                            ?>


                                                                                            <td class="px-4 py-2 align-middle">
                                                                                                <?php
                                                                                                $status_classes = [
                                                                                                    "1"     => "table-btn-yellow",
                                                                                                    "2"  => "table-btn-success",
                                                                                                ];
                                                                                                $sbutton_class = $status_classes[$data["quiz_reset_status_id"]];
                                                                                                ?>
                                                                                                <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["quiz_reset_status"]; ?></button>
                                                                                            </td>

                                                                                            <td class="px-4 py-3 align-middle">
                                                                                                <?php
                                                                                                if ($data["quiz_reset_status_id"] == 1) {
                                                                                                ?>
                                                                                                    <button class="table-btn table-btn-dark-theme w-100" onclick="approveQuizResubmitRequest(<?= $data['students_has_quiz_reset_request_id']; ?>,<?= $id ?>);">Approve</button>
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsQuizResetRequestPagination(<?= $id ?>,'previousPage')">
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
                                                                                                $action = $isActive ? '' : "onclick=\"handleStudentsQuizResetRequestPagination('{$id}','goToPage', '{$i}')\"";
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsQuizResetRequestPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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
                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 17, $quizData['subject_id'])) {
                        ?>
                            <!-- Mark Released Model => Start -->
                            <div class="modal fade" id="markReleasedModal" tabindex="-1" role="dialog" aria-labelledby="markReleasedModalTitle" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLongTitle">Quiz Mark Release</h5>

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
} ?>