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
            header("Location: ./level-details");
        } else {

            $id = $_GET['quiz-id'];

            $quiz = Database::search("SELECT `students_has_quiz_id`,`marks`,`quiz_id`,`first_name`,`last_name`,`title`,`student_id`,`faculty`,`faculty_id`,`level`,`level_id`,`subject`,`subject_id`,`exam_name` FROM `students_has_quiz`
            INNER JOIN `students` ON `students_has_quiz`.`students_student_id`=`students`.`student_id`
            INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
            INNER JOIN `quiz` ON `students_has_quiz`.`quiz_quiz_id`=`quiz`.`quiz_id`
            INNER JOIN `exams` ON `quiz`.`exams_exam_id` = `exams`.`exam_id`
            INNER JOIN `subjects` ON `exams`.`subjects_subject_id`=`subjects`.`subject_id`
            INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id`=`intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id`=`faculties`.`faculty_id`
            WHERE `students_has_quiz_id` = '" . $id . "'");

            if ($quiz->num_rows == 0) {
                header("Location: ./level-details");
            } else {
                $quizData = $quiz->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $quizData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('quiz', 1, $quizData['subject_id']) || UserPermissions::hasPermission('quiz', 17, $quizData['subject_id']) || $validLecturer)) {
                    header("Location: ./subject-quizes?subject-id=" . $quizData['subject_id']);
                } else {

                    $quizHasQuestions = Database::search("SELECT * FROM `questions` WHERE `quiz_quiz_id`='" . $quizData['quiz_id'] . "' ");
                    $studentAnsweredQuestions = Database::search("SELECT `students_has_questions`.`questions_question_id`,`answer_order` FROM `students_has_questions`
                    INNER JOIN `answers` ON `students_has_questions`.`answers_answer_id`=`answers`.`answer_id` 
                    WHERE `students_student_id`='" . $quizData['student_id'] . "' AND `students_has_questions`.`questions_question_id` IN (SELECT `question_id` FROM `questions` WHERE `quiz_quiz_id`='" . $quizData['quiz_id'] . "')");

                    $correctAnswers = ($quizData["marks"] * $quizHasQuestions->num_rows) / 100;
                    $wrongAnswers = $quizHasQuestions->num_rows - $correctAnswers;

                    $studentAnswers = [];

                    while ($row = $studentAnsweredQuestions->fetch_assoc()) {
                        $studentAnswers[$row['questions_question_id']] = $row['answer_order'];
                    }
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Student Quiz Performance - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 pe-4 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg"><?= $quizData['title'] . "." . $quizData['first_name'] . " " . $quizData['last_name'] ?> - Quiz Results</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="programes?faculty-id=<?= $quizData['faculty_id'] ?>"><?= $quizData['faculty'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="level-details?level-id=<?= $quizData['level_id'] ?>"><?= $quizData['subject'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="level-details?level-id=<?= $quizData['level_id'] ?>">Quizes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $quizData['exam_name'] ?> - Students Performance</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $quizData['title'] . "." . $quizData['first_name'] . " " . $quizData['last_name'] ?></a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                                <div class="col-12 px-4 py-3 pe-4 component-theme">
                                                    <div class="p-0 m-0 g-0 align-items-center justify-content-center">
                                                        <div class="col-12 d-flex flex-column align-items-center">
                                                            <img src="./assets/imgs/common/award.png" alt="award image" class="award-image">
                                                        </div>

                                                        <div class="col-12 d-flex flex-column align-items-center mt-2">
                                                            <span class="main-topic lh-lg">Keep up the great work</span>
                                                            <span class="text-16 sub-text lh-lg">Well done, you have completed</span>
                                                            <span class="text-16 sub-text lh-lg"><?= $quizData["exam_name"] ?></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-row align-items-center justify-content-center p-3">
                                                            <div class="d-flex flex-row align-items-center justify-content-center px-4 pe-4 pt-2 pb-2 m-0 g-0 bg-dark-theme">
                                                                <div class="d-flex flex-row">
                                                                    <iconify-icon icon="lets-icons:check-fill" width="24" height="24" class="text-white"></iconify-icon>
                                                                    <span class="text-16 text-white px-2 pe-0 m-0 g-0">Correct <?= $correctAnswers ?></span>
                                                                </div>
                                                                <span class="text-24 text-white px-2 px-md-3 px-lg-3 pe-2 pe-md-3 pe-lg-3">|</span>
                                                                <div class="d-flex flex-row">
                                                                    <iconify-icon icon="carbon:close-filled" width="24" height="24" class="text-white"></iconify-icon>
                                                                    <span class="text-16 text-white px-2 pe-0 m-0 g-0">Wrong <?= $wrongAnswers ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php

                                                        for ($i = 0; $i < $quizHasQuestions->num_rows; $i++) {
                                                            $question = $quizHasQuestions->fetch_assoc();

                                                        ?>
                                                            <div class="col-12  bl-solid px-3 px-md-4 px-lg-4 pe-3 pe-md-4 pe-lg-4 pt-3 pb-3 mt-4">
                                                                <div class="row p-0 m-0 g-0 align-content-center justify-content-center">
                                                                    <span class="text-18 dark-color fw-medium">Question <?= $i + 1 ?></span>
                                                                    <p class="text-16 dark-color mt-3 text-justify">
                                                                        <?= $question["question"] ?>
                                                                    </p>
                                                                    <?php
                                                                    $answers = Database::search("SELECT * FROM `answers` WHERE `questions_question_id`='" . $question["question_id"] . "' ORDER BY `answer_order` ASC");

                                                                    for ($j = 0; $j < $answers->num_rows; $j++) {
                                                                        $answer = $answers->fetch_assoc();
                                                                        $roundClass = "custom-round-div";
                                                                        $answerText = "sub-text";

                                                                        if (array_key_exists($question["question_id"], $studentAnswers)) {
                                                                            if ($answer["answer_order"] == $question["correct_answer"] && $answer["answer_order"] == $studentAnswers[$question["question_id"]]) {
                                                                                $roundClass = "quiz-correct";
                                                                                $answerText = "text-dark-green";
                                                                            } elseif ($answer["answer_order"] == $question["correct_answer"] && $answer["answer_order"] != $studentAnswers[$question["question_id"]]) {
                                                                                $roundClass = "quiz-indicator-not-selected";
                                                                                $answerText = "text-dark-green";
                                                                            } elseif ($answer["answer_order"] != $question["correct_answer"] && $answer["answer_order"] == $studentAnswers[$question["question_id"]]) {
                                                                                $roundClass = "quiz-wrong";
                                                                                $answerText = "text-dark-red";
                                                                            }
                                                                        }
                                                                    ?>
                                                                        <div class="col-12 d-flex flex-row align-items-center mt-3">
                                                                            <div class="<?= $roundClass ?>"><?= $j + 1 ?></div>
                                                                            <p class="text-15 <?= $answerText ?> lh-lg px-2 px-md-4 px-lg-4 pe-2 pe-md-4 pe-lg-4 m-0 g-0"><?= $answer["answer"] ?></p>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    if (!array_key_exists($question["question_id"], $studentAnswers)) {
                                                                    ?>
                                                                        <div class="d-flex align-items-center mt-3">
                                                                            <div class="table-btn table-btn-danger">Not Answered</div>
                                                                            <div class="table-btn table-btn-success mx-3"> Correct Answer Is : <?= $question["correct_answer"] ?></div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }

                                                        ?>

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