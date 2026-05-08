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
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./students");
        } else {
            $id = $_GET['student-id'];
            $studentIntake = Database::search("SELECT * FROM `students_has_intakes`
            INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id`=`intakes`.`intake_id` 
            WHERE `students_has_intakes_id`='" . $id . "'");

            if ($studentIntake->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $studentIntakeData = $studentIntake->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1, $studentIntakeData['intakes_intake_id']) || UserPermissions::hasPermission('intake_students', 10, $studentIntakeData['intakes_intake_id']))) {
                    header("Location: ./students");
                } else {

                    $student = Database::search("SELECT * FROM `students`
                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                        INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                        WHERE `students`.`student_id`='" . $studentIntakeData['students_student_id'] . "'");

                    if ($student->num_rows == 0) {
                        header("Location: ./students");
                    } else {
                        $sdata = $student->fetch_assoc();

                        $intake_param = 0;
                        if (isset($_GET['intake-id']) && !empty($_GET['intake-id'])) {
                            $intake_param = $_GET['intake-id'];
                        }
?>
                        <!DOCTYPE html>
                        <html lang="en">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>Intake Student Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                                        if ($intake_param == 0) {
                                            load_side_menu::load("Sudents");
                                        } else {
                                            load_side_menu::load("Course Overview");
                                        }

                                        ?>
                                    </div>
                                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 p-0 m-0 g-0">
                                                <?php
                                                include "./includes/header.php";
                                                if ($intake_param == 0) {
                                                    selectItem::setSelected("Sudents");
                                                } else {
                                                    selectItem::setSelected("Course Overview");
                                                }
                                                ?>
                                            </div>
                                            <div class="container p-4 m-0" id="skeleton-list">
                                                <?php
                                                include "./includes/components/skeleton-header.php";
                                                include "./includes/components/skeleton-courses.php"; ?>
                                            </div>
                                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                                <div class="row p-4 m-0 g-0">
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 col-md-10">
                                                                <div class="row">
                                                                    <span class="main-topic lh-lg"><?= ucwords(strtolower($sdata['title'] . " . " . $sdata['first_name'] . " " . $sdata['last_name'])); ?> - Intake Student Id : <?= $studentIntakeData['student_id'] ?></span>
                                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                        <ol class="breadcrumb m-0">
                                                                            <?php
                                                                            if ($intake_param == 0) {
                                                                            ?>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./students">Students</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./student-details?student-id=<?= $sdata['student_id'] ?>">Student Details</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $studentIntakeData['intake'] ?> - Student Intake Details</a></li>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.history.back();">Students</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">View/Edit Student</a></li>
                                                                            <?php
                                                                            }
                                                                            ?>

                                                                        </ol>
                                                                    </nav>
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

                            <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Crop Image</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img id="image" class="img-fluid" />
                                        </div>
                                        <div class="modal-footer">
                                            <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
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
}
?>