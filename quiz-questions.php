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
                    $questions = Database::search("SELECT * FROM `questions` WHERE `quiz_quiz_id`='" . $id . "'");
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Quiz Questions - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                        <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
                        <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
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
                                                                <span class="main-topic lh-lg">Add Quiz Questions</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="programes?program-id=<?= $quizData['faculty_id'] ?>"><?= $quizData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subject-details?level-id=<?= $quizData['subject_id'] ?>"><?= $quizData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="subject-quizes?subject-id=<?= $quizData['subject_id'] ?>">Quizes</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./quiz-students-performance.php?quiz-id=<?= $quizData['quiz_id'] ?>"><?= $quizData['exam_name'] ?> - Students Performance</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Quiz Questions</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <?php
                                                                    if ($questions->num_rows > 0) {
                                                                    ?>
                                                                        <button class="custom-btn col-12" id="save-quiz-btn" onclick="updateQuizQuestions(<?= $quizData['quiz_id'] ?>)">Save Questions</button>
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <button class="custom-btn col-12" id="save-quiz-btn" onclick="saveQuizQuestions(<?= $quizData['quiz_id'] ?>,<?= $quizData['subject_id'] ?>)">Save Questions</button>
                                                                    <?php
                                                                    }
                                                                    ?>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme" id="quiz-questions-container">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 m-0 g-0">
                                                            <div class="row p-0 m-0" id="quiz-container">

                                                                <?php

                                                                if ($questions->num_rows > 0) {
                                                                    $quizCount = 1;
                                                                    while ($question = $questions->fetch_assoc()) {
                                                                ?>
                                                                        <div class="col-12 p-0 mb-3 quiz-item" data-quiz-id="<?= $question['question_id'] ?>" data-quiz-status="1">
                                                                            <div class="row p-0 m-0">
                                                                                <div class="col-12 p-2 px-md-4 pe-md-4 pt-md-2 pb-md-2 d-flex p-0 m-0 align-items-center bl-solid" data-bs-toggle="collapse" data-bs-target="#quiz-body-<?= $quizCount ?>" aria-expanded="false" aria-controls="quiz-body-<?= $quizCount ?>">
                                                                                    <span class="text-16 main-text flex-grow-1">Quiz <?= $quizCount ?></span>
                                                                                    <div class="col-6 d-flex justify-content-end">
                                                                                        <iconify-icon icon="typcn:delete" width="30" height="30" class="sub-text" onclick="deleteQuiz(this)"></iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 p-0 m-0 bl-solid-lrb p-4 accordion-collapse collapse" id="quiz-body-<?= $quizCount ?>" data-bs-parent="#quiz-container">
                                                                                    <div class="row gy-3 accordion-body">
                                                                                        <div class="col-12 d-flex flex-column">
                                                                                            <label for="" class="text-16 sub-text">Question</label>
                                                                                            <div class="d-flex align-items-center gx-3 mt-3">
                                                                                                <div class="d-flex align-items-center flex-grow-1 pe-3">
                                                                                                    <input type="text" class="form-control custom-input question-input" value="<?= $question['question'] ?>" placeholder="Enter question">
                                                                                                </div>
                                                                                                <div class="d-flex align-items-center">
                                                                                                    <img src="./assets/imgs/common/vector.png" alt="add image question">
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                        <?php
                                                                                        $answers = Database::search("SELECT * FROM `answers` WHERE `questions_question_id`='" . $question['question_id'] . "'");
                                                                                        for ($i = 0; $i < $answers->num_rows; $i++) {
                                                                                            $answer = $answers->fetch_assoc();
                                                                                        ?>
                                                                                            <div class="col-12 col-md-6 d-flex align-items-center">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" <?= $question['correct_answer'] == $i + 1 ? 'checked' : '' ?> name="quiz-<?= $quizCount ?>" value="Option <?= $i + 1 ?>">
                                                                                                <input type="text" class="form-control custom-input mt-2 answer-input" data-answer-id="<?= $answer["answer_id"] ?>" value="<?= $answer['answer'] ?>" placeholder="Option <?= $i + 1 ?>">
                                                                                            </div>
                                                                                        <?php
                                                                                        }

                                                                                        ?>

                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                        $quizCount++;
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <div class="col-12 bl-solid p-0 m-0 d-flex flex-column">
                                                                        <div class="p-2 d-flex flex-column align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                            <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                            <span>Drag & Drop your files here</span>
                                                                            <span>OR</span>
                                                                            <div class="col-12 mt-3">
                                                                                <div class="row gap-3 align-items-center justify-content-center">
                                                                                    <button class="custom-btn px-4 col-4" onclick="downloadQuizCSVFormat()">Download CSV Format</button>
                                                                                    <button class="custom-btn px-4 col-4" id="file-selector">Browse Files</button>
                                                                                </div>
                                                                            </div>

                                                                            <input type="file" class="d-none" id="file-selector-input" accept=".csv">

                                                                        </div>
                                                                        <div class="col-12 p-4 d-none" id="list-section-container">
                                                                            <div class="row">
                                                                                <div class="col-12">
                                                                                    <div class="row" id="list-section">

                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12">
                                                                                    <div class="row justify-content-end">
                                                                                        <div class="col-12 col-md-2">
                                                                                            <button class="custom-btn w-100" type="button" onclick="uploadQuizQuestionBulk(this);">
                                                                                                Upload Bulk
                                                                                            </button>
                                                                                        </div>

                                                                                    </div>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <span class="text-12 text-dark-red p-2" id="csv-file-feedback"></span>
                                                                    </div>
                                                                <?php
                                                                }

                                                                ?>

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
                        <script src="./assets/js/bulkFileChooser.js"></script>
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