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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-exam-id']) && !empty($_GET['student-exam-id']))) {
            header("Location: ./subject-examination");
        } else {

            $id = $_GET['student-exam-id'];

            $examination = Database::search("SELECT * FROM `students_has_online_exam`
            INNER JOIN `students` ON `students_has_online_exam`.`students_student_id`=`students`.`student_id`
            INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `online_exam` ON `students_has_online_exam`.`online_exam_online_exam_id` = `online_exam`.`online_exam_id`
            INNER JOIN `exams` ON `online_exam`.`exams_exam_id`=`exams`.`exam_id` 
            WHERE `students_has_online_exam_id` = '" . $id . "'");


            if ($examination->num_rows == 0) {
                header("Location: ./subject-examinations");
            } else {
                $examinationData = $examination->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $examinationData['subjects_subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                $allowedPermissions = [1, 17, 20];
                $haveAnyPermission = false;

                foreach ($allowedPermissions as $permission) {
                    if (UserPermissions::hasPermission('exams', $permission, $examinationData['subjects_subject_id'])) {
                        $haveAnyPermission = true;
                    }
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                    header("Location: ./subjects");
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Examination - Admin Dashboard | <?= Project::getBaseName() ?></title>
                        
                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeSelectedSubjectExamination(<?= $id ?>);">
                        <div class="notifications"></div>
                        <!-- Loader Section -->
                        <?php include './includes/loader.php' ?>
                        <!-- Main Content Section (Initially Hidden) -->
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
                                        <div class="col-12 p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-flex flex-column">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg"><?= $examinationData['title'] . '.' . $examinationData['first_name'] . '' . $examinationData['last_name'] ?> - Online Exam : <?= $examinationData['exam_name'] ?></span>
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

                                                $questions = Database::search("SELECT * FROM `essay_questions` WHERE `online_exam_online_exam_id`='" . $examinationData['online_exam_online_exam_id'] . "'");
                                                if ($questions->num_rows > 0) {
                                                    for ($x = 0; $questions->num_rows > $x; $x++) {
                                                        $question = $questions->fetch_assoc();
                                                ?>
                                                        <div class="col-12 component-theme mb-3">
                                                            <div class="row p-4">
                                                                <span>Question <?= $x + 1 ?></span>
                                                                <p class="text-18 fw-medium my-3"><?= $question['essay_question'] ?></p>
                                                                <div class="col-12">
                                                                    <div class="row m-0 p-4 bl-solid">
                                                                        <?php
                                                                        $studentAnswer = Database::search("SELECT * FROM `students_has_essay_questions` 
                                                                    WHERE `essay_questions_essay_question_id`='" . $question['essay_question_id'] . "' AND `students_student_id`='" . $examinationData['students_student_id'] . "'");

                                                                        if ($studentAnswer->num_rows > 0) {
                                                                            $answer = $studentAnswer->fetch_assoc()['answer'];
                                                                        ?>
                                                                            <p class="text-16 m-0 p-0"><?= $answer ?></p>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <p class="text-16 m-0 p-0">Not given any answer</p>
                                                                        <?php
                                                                        }

                                                                        ?>

                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                <?php
                                                    }
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
                                                    <span class="col-2 text-14 sub-text">Exam Name</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="code"></span>
                                                </div>

                                                <div class="d-flex align-items-center">
                                                    <span class="col-2 text-14 sub-text">Student</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="student-name"></span>
                                                </div>
                                                <div class="d-flex">
                                                    <div class="col-2 text-14 sub-text">Student Id</div>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="student-id"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-3">
                                            <div class="row gy-3 align-content-center">
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Add Mark</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="mark">
                                                    <span id="mark-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 mt-4 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0" id="saveMark">Save Mark</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Mark Add Model => End -->

                        <!-- Mark Edit Model => Start -->
                        <div class="modal fade br-0" id="editMark" aria-hidden="true" aria-labelledby="addMarkLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Edit Examination Mark</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-2 bl-solid">
                                            <div class="row p-2 gy-3 align-content-center">
                                                <div class="d-flex">
                                                    <span class="col-2 text-14 sub-text">Code</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-code"></span>
                                                </div>
                                                <div class="d-flex">
                                                    <span class="col-2 text-14 sub-text">Topic</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-topic"></span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <span class="col-2 text-14 sub-text">Student</span>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-student-name"></span>
                                                </div>
                                                <div class="d-flex">
                                                    <div class="col-2 text-14 sub-text">Student Id</div>
                                                    <span class="px-2 pe-2">:</span>
                                                    <span class="text-14 sub-text" id="edit-student-id"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-3">
                                            <div class="row gy-3 align-content-center">
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Already Assigned Mark</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="edit-already-mark" disabled>
                                                </div>
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">New Mark</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="edit-mark">
                                                    <span id="mark-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 mt-4 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0" id="edit-saveMark">Save Mark</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Mark Edit Model => End -->


                        <!-- Student Bulk Upload Model => Start -->
                        <div class="modal fade br-0" id="marksBulkUpload" aria-hidden="true" aria-labelledby="marksBulkUploadLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="MarksBulkUploadLabel">Add Assignment Marks Bulk</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 container-fluid">
                                            <div class="row">
                                                <div class="col-12 p-0 component-theme mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                        <div class="col-12 d-flex align-items-center">
                                                            <span class="text-16 sub-text flex-grow-1">Marks CSV Format File</span>
                                                            <button class="custom-btn d-flex align-items-center justify-content-center bg-light-theme mx-2" onclick="downloadExamMarksUploadCSV('<?= $examinationData['exam_name']; ?>')">
                                                                <span class="sub-topic mx-4 fw-medium">Download CSV Format</span>
                                                            </button>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-5" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" accept=".csv">
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="text-12 text-dark-red" id="file-feedback"></span>
                                                        </div>
                                                        <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer p-0 m-0 border-0">
                                        <button class="col-8 col-md-4 custom-btn m-0" onclick="uploadExamMarksBulk(<?= $id ?>)">Upload</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Student Bulk Upload Model => End -->

                        <!-- Mark Released Model => Start -->
                        <div class="modal fade" id="markReleasedModal" tabindex="-1" role="dialog" aria-labelledby="markReleasedModalTitle" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLongTitle">Assignment Mark Release</h5>

                                    </div>
                                    <div class="modal-body" id="markReleasedModalBody">

                                    </div>
                                    <div class="modal-footer" id="markReleasedModalFooter">

                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Mark Released Model => End -->


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