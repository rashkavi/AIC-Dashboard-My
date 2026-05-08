<?php
require_once './includes/UserClass.php';
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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./subject-examination");
        } else {
            $id = $_GET['student-id'];
            $studentHasExam = Database::search("SELECT `first_name`,`last_name`,`students`.`student_id`,`students_has_online_exam_id`,`title` FROM `students_has_intakes`
            INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id`=`intakes`.`intake_id`
            INNER JOIN `students_has_online_exam` ON `students_has_intakes`.`students_student_id`=`students_has_online_exam`.`students_student_id`
            INNER JOIN `students` ON `students_has_intakes`.`students_student_id`=`students`.`student_id`
            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
            WHERE `students_has_intakes`.`student_id`='" . $id . "'");

            if ($studentHasExam->num_rows == 0) {
                header("Location: ./subject-examination");
            } else {
                $studentHasExamData = $studentHasExam->fetch_assoc();
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Online Exam Answers - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body class="overflow-hidden" onload="initializeSelectedSubjectExamination();">
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
                                        include "./includes/components/skeleton-add-edit.php"; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <span class="main-topic lh-lg">Student - <?php echo $studentHasExamData['title'] . " " . $studentHasExamData['first_name'] . " " . $studentHasExamData['last_name'] ?></span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="https://royalacademylms.com">Department</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Educational Department</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Associate's Degree in Biology (A.A. or A.S.)</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Intake 1</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Diploma Level</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Subjects</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Examinations</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Exam #01256358</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                            <?php
                                            $answers = Database::search("SELECT `essay_question`,`answer` FROM `students_has_essay_questions` 
                                            INNER JOIN `essay_questions` ON `students_has_essay_questions`.`essay_questions_essay_question_id` = `essay_questions`.`essay_question_id`
                                            WHERE `online_exam_online_exam_id` = (SELECT `online_exam_online_exam_id` FROM `students_has_online_exam` WHERE `students_has_online_exam_id`='" . $studentHasExamData["students_has_online_exam_id"] . "')
                                            ORDER BY `essay_questions`.`essay_question_id` ASC");

                                            $qid = 1;

                                            while ($answersData = $answers->fetch_assoc()) {
                                            ?>
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg">Question - <?php echo $qid ?></span>
                                                        <span class="text-14 sub-text lh-base mt-1">Question</span>
                                                        <textarea class="mt-1 bl-solid p-2 form-control br-0" readonly><?php echo $answersData["essay_question"] ?></textarea>
                                                        <span class="text-14 sub-text lh-base mt-2">Answer</span>
                                                        <textarea class="mt-1 bl-solid p-2 form-control br-0" readonly><?php echo $answersData["answer"] ?></textarea>
                                                    </div>
                                                </div>
                                            <?php
                                                $qid++;
                                            }

                                            ?>

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

                    <!-- Mark Add Model => Start -->
                    <div class="modal fade br-0" id="addMark" aria-hidden="true" aria-labelledby="addMarkLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Add Examination Mark</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-2 bl-solid">
                                        <div class="row p-2 gy-3 align-content-center">
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Examination Id</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>H7DJ ASSI 1256</div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Topic</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>Career Hierarchy Development (Individual Assignment)</div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Time Duration</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>2 Week</div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Batch</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>Intake 1</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 p-2 bl-solid mt-3">
                                        <div class="row p-2 gy-3 align-content-center">
                                            <div class="d-flex align-items-center">
                                                <div class="col-2 text-14 sub-text">Student</div>
                                                <div class="col-10 text-14 d-flex align-items-center"><span class="px-2 pe-2">:</span>
                                                    <img src="./assets/imgs/user.png" class="hwr-40" alt="Lecturer image">
                                                    <span class="text-14 sub-text mx-2">Dr.Tharaka Sankalpa</span>
                                                </div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Student Id</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>12548</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <div class="row gy-3 align-content-center">
                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label for="" class="text-14 main-text">Add Mark</label>
                                                <input type="text" class="form-control custom-input mt-2">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 mt-4 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0">Save Mark</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Mark Add Model => End -->

                    <!-- Mark Edit Model => Start -->
                    <div class="modal fade br-0" id="editMark" aria-hidden="true" aria-labelledby="editMarkLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Edit examination Mark</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-2 bl-solid">
                                        <div class="row p-2 gy-3 align-content-center">
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">examination Id</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>H7DJ ASSI 1256</div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Topic</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>Career Hierarchy Development (Individual Assignment)</div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Time Duration</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>2 Week</div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Batch</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>Intake 1</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 p-2 bl-solid mt-3">
                                        <div class="row p-2 gy-3 align-content-center">
                                            <div class="d-flex align-items-center">
                                                <div class="col-2 text-14 sub-text">Student</div>
                                                <div class="col-10 text-14 d-flex align-items-center"><span class="px-2 pe-2">:</span>
                                                    <img src="./assets/imgs/user.png" class="hwr-40" alt="Lecturer image">
                                                    <span class="text-14 sub-text mx-2">Dr.Tharaka Sankalpa</span>
                                                </div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="col-2 text-14 sub-text">Student Id</div>
                                                <div class="col-10 text-14 sub-text"><span class="px-2 pe-2">:</span>12548</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <div class="row gy-3 align-content-center">
                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label for="" class="text-14 main-text">Mark</label>
                                                <input type="text" class="form-control custom-input mt-2">
                                            </div>
                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label for="" class="text-14 main-text">New Mark</label>
                                                <input type="text" class="form-control custom-input mt-2">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 mt-4 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0">Save Mark</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Mark Edit Model => End -->
                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php
            }
        }
    }
}
?>