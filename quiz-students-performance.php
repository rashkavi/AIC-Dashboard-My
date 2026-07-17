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
            header("Location: ./subjects");
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
                header("Location: ./subjects");
            } else {
                $quizData = $quiz->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $quizData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 1, $quizData['subject_id']) || UserPermissions::hasPermission('quiz', 17, $quizData['subject_id']) || $validLecturer)) {
                    header("Location: ./subject-quizes?subject-id=" . $quizData['subject_id']);
                } else {

                    $cardData = Database::search("SELECT 
                                                            COUNT(*) as total_students,
                                                            COUNT(CASE WHEN `exam_status_exam_status_id` = 1 THEN 1 END) as status_1_count,
                                                            COUNT(CASE WHEN `exam_status_exam_status_id` = 3 THEN 1 END) as status_3_count
                                                        FROM `students_has_quiz` 
                                                        WHERE `quiz_quiz_id` = '" . $id . "'");

                    $cardData = $cardData->fetch_assoc();

                    $totalIntakeStudents = Database::search("SELECT COUNT(*) as 'count' FROM `students_has_intakes` WHERE `intakes_intake_id` = '" . $quizData['intake_id'] . "'")->fetch_assoc()['count'];
                    $notSunmitedStudents = (int)$totalIntakeStudents - (int)$cardData['total_students'];

                    $pendingResubmitRequests = Database::search("SELECT COUNT(*) FROM `students_has_quiz_reset_request` WHERE `quiz_reset_status_quiz_reset_status_id`='1' 
                    AND `students_has_quiz_students_has_quiz_id` IN (SELECT `students_has_quiz_id` FROM `students_has_quiz` WHERE `quiz_quiz_id`='" . $id . "')")->fetch_assoc()['COUNT(*)'];

                    $datetime = new DateTime('now', new DateTimeZone('Asia/Colombo'));
                    $formatedDateTime = $datetime->format('Y-m-d');

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Quiz Studets Performance - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                            include './includes/components/skeleton-header.php';
                                            include './includes/components/skeleton-dashboards.php'; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Quiz - Students Performance</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="programes?faculty-id=<?= $quizData['faculty_id'] ?>"><?= $quizData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subjects?level-id=<?= $quizData['level_id'] ?>"><?= $quizData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subjects?level-id=<?= $quizData['level_id'] ?>">Quizes</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $quizData['exam_name'] ?> - Students Performance</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3 p-0 m-0 align-content-center">
                                                                <button class="custom-btn w-100" onclick="location.href='quiz-students-reset-requests?quiz-id=<?= $id ?>'">Reset Requests</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="container p-0 m-0 g-0 mb-4">
                                                    <div class="row g-4 d-flex">
                                                        <div class="col-12 col-md-6 col-lg-4 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row h-100 align-items-center">
                                                                    <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:student" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Submit Students</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $cardData['total_students'] ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row h-100 align-items-center">
                                                                    <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:pending-outline" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Not-Submit Students</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $notSunmitedStudents ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 py-md-4 px-md-4 pe-md-2 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-purple d-flex align-items-center justify-content-center"> <iconify-icon icon="lsicon:submit-outline" width="32" height="32" style="color: var(--dark-purple);"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <label class="flex-grow-1 text-18 sub-text fw-semibold">Pending Reset Requests</label>
                                                                        <h2 class="text-dark-color fw-semibold lh-base"><?= $pendingResubmitRequests ?></h2>
                                                                    </div>
                                                                </div>
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
                                                                        <span class="text-14 dark-theme fw-medium"><?= $cardData['total_students'] ?> Submissions</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-5 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="user-search-text" onkeyup="handleStudentsQuizPagination(<?= $id ?>,'filter')">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="filter-selector" onchange="handleStudentsQuizPagination(<?= $id ?>,'filter')">
                                                                        <option selected value="0">- filter Selector -</option>
                                                                        <?php
                                                                        $exam_status = Database::search("SELECT * FROM `exam_status` WHERE `exam_status_id` != 2");
                                                                        while ($data = $exam_status->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $data["exam_status_id"]; ?>"><?= $data["exam_status"]; ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleStudentsQuizPagination(<?= $id ?>,'filter')">
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
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentsQuizPagination(<?= $id ?>,'filter')">
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
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Submitted Date</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>

                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
                                                                                <?php
                                                                                $query = "SELECT * FROM `students_has_quiz` 
                                                                                        INNER JOIN `students` ON `students_has_quiz`.`students_student_id`=`students`.`student_id`
                                                                                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                                                                        INNER JOIN `quiz` ON `students_has_quiz`.`quiz_quiz_id`=`quiz`.`quiz_id`
                                                                                        INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                                                                        INNER JOIN `exam_status` ON `students_has_quiz`.`exam_status_exam_status_id`=`exam_status`.`exam_status_id`
                                                                                        WHERE `quiz`.`quiz_id`='" . $id . "'
                                                                                        ORDER BY `students_has_quiz`.`submitted_at` ASC";

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
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["submitted_at"]; ?></span></td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["marks"]; ?>%</span></td>
                                                                                            <td class="px-4 py-3 align-middle">
                                                                                                <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='student-quiz-performance.php?quiz-id=<?= $data['students_has_quiz_id']; ?>'">View</button>
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsQuizPagination(<?= $id ?>,'previousPage')">
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
                                                                                                $action = $isActive ? '' : "onclick=\"handleStudentsQuizPagination('{$id}','goToPage', '{$i}')\"";
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsQuizPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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