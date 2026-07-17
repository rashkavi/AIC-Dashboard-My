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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['quiz-id']) && !empty($_GET['quiz-id']))) {
            header("Location: ./subjects");
        } else {
            $id = $_GET['quiz-id'];
            $quiz = Database::search("
            SELECT 
                `quiz`.*,
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

                $allowedPermissions = [1, 2, 4, 10, 11];
                $haveAnyPermission = false;

                foreach ($allowedPermissions as $permission) {
                    if (UserPermissions::hasPermission('quiz', $permission, $id)) {
                        $haveAnyPermission = true;
                    }
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                    header("Location: ./subject-details?subject-id=" . $quizData['subject_id']);
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Quiz Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                            include './includes/components/skeleton-add-edit.php'; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Update Quiz</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="programes?program-id=<?= $quizData['faculty_id'] ?>"><?= $quizData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subject-details?level-id=<?= $quizData['subject_id'] ?>"><?= $quizData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subject-quizes?subject-id=<?= $quizData['subject_id'] ?>">Quizes</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./quiz-students-performance.php?quiz-id=<?= $quizData['quiz_id'] ?>"><?= $quizData['exam_name'] ?> - Students Performance</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Quiz Details</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" onclick="window.location.href='quiz-questions?quiz-id=<?= $id ?>'">Quiz Questions</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 g-0">
                                                            <div class="row gx-4 gy-3">
                                                                <div class="col-12 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Quiz Title</label>
                                                                    <input type="text" class="form-control custom-input mt-2" value="<?= $quizData['exam_name'] ?>" placeholder="Enter Quiz Title" id="quiz-title">
                                                                    <span id="quiz-title-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Exam Date</label>
                                                                    <input type="date" class="form-control custom-input mt-2" value="<?= $quizData['exam_date'] ?>" id="exam-date">
                                                                    <span id="exam-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Start Time</label>
                                                                    <input type="time" class="form-control custom-input mt-2" value="<?= $quizData['start_time'] ?>" id="start-time">
                                                                    <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">End Date</label>
                                                                    <input type="date" class="form-control custom-input mt-2" value="<?= $quizData['end_date'] ?>" id="end-date">
                                                                    <span id="end-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">End Time</label>
                                                                    <input type="time" class="form-control custom-input mt-2" value="<?= $quizData['end_time'] ?>" id="end-time">
                                                                    <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Duration</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="duration" value="<?= $quizData['duration'] ?>">
                                                                    <span id="duration-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Select Quiz Type</label>
                                                                    <select class="form-select custom-select mt-2" aria-label="Quiz type selector" id="quiz-type">
                                                                        <option selected value="0">Select Type</option>
                                                                        <?php
                                                                        $quizTypes = Database::search("SELECT * FROM `quiz_type`");
                                                                        while ($type = $quizTypes->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $type['quiz_type_id'] ?>" <?= $quizData['quiz_type_quiz_type_id'] == $type['quiz_type_id'] ? "selected" : "" ?>><?= $type['quiz_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="quiz-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Select Quiz Order</label>
                                                                    <select class="form-select custom-select mt-2" aria-label="Quiz order selector" id="quiz-order">
                                                                        <option selected value="0">Select Type</option>
                                                                        <?php
                                                                        $quizOrders = Database::search("SELECT * FROM `quiz_order`");
                                                                        while ($order = $quizOrders->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $order['quiz_order_id'] ?>" <?= $quizData['quiz_order_quiz_order_id'] == $order['quiz_order_id'] ? "selected" : "" ?>><?= $order['quiz_order'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                    </select>
                                                                    <span id="quiz-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 p-0 g-0 mt-4">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="custom-btn w-100" onclick="updateQuiz(<?= $quizData['quiz_id'] ?>)">Update Quiz</button>
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
                    </body>

                    </html>
<?php
                }
            }
        }
    }
} ?>