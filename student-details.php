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
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 1) || UserPermissions::hasPermission('students', 10))) {
                header("Location: ./students");
            } else {

                $id = $_GET['student-id'];

                $student = Database::search("SELECT * FROM `students` 
                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                WHERE `students`.`student_id`='" . $id . "'");

                if ($student->num_rows == 0) {
                    header("Location: ./students");
                } else {
                    $sdata = $student->fetch_assoc();
                    function truncateText($text, $maxLength = 45)
                    {
                        if (mb_strlen($text) > $maxLength) {
                            return mb_substr($text, 0, $maxLength) . '...';
                        }
                        return $text;
                    }
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Student Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    load_side_menu::load("Students");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Students");
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
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?php echo ucwords(strtolower($sdata['title'] . " . " . $sdata['first_name'] . " " . $sdata['last_name'])); ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./students">Students</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Student Details</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="row gx-4 gy-4">
                                                        <?php
                                                        $sudentHasIntakes = Database::search("SELECT * FROM `students_has_intakes`
                                                      INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                                                  
                                                      INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                      INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                                      INNER JOIN `status` ON `students_has_intakes`.`status_status_id` = `status`.`status_id`
                                                   
                                                     
                                                      WHERE `students_student_id`='" . $id . "' ORDER BY `students_has_intakes`.`students_has_intakes_id` DESC");
                                                        if ($sudentHasIntakes->num_rows > 0) {
                                                            while ($data = $sudentHasIntakes->fetch_assoc()) {
                                                        ?>
                                                                <div class="col-12 col-lg-4 mb-4">
                                                                    <div class="student-details-card position-relative px-4 py-3 component-theme">

                                                                        <!-- Ribbon -->
                                                                        <div class="student-details-ribbon 
        <?php
                                                                echo match ($data['status']) {
                                                                    'Active' => 'ribbon-active',
                                                                    'Inactive' => 'ribbon-deactive',
                                                                    'Transferred' => 'ribbon-transfer',
                                                                    default => 'ribbon-default'
                                                                };
        ?>"> <?= htmlspecialchars($data['status'] ?? 'Unknown') ?>
                                                                        </div>

                                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                                            <!-- Cover -->
                                                                            <div class="col-12 d-flex flex-column align-items-center p-0 m-0 g-0">
                                                                                <?php
                                                                                $data_cover = "./assets/imgs/intakes-covers/" . $data['intake_cover'];
                                                                                if (!empty($data['intake_cover']) && file_exists($data_cover)) {
                                                                                ?>
                                                                                    <img src="./assets/imgs/intakes-covers/<?php echo $data['intake_cover'] ?>" class="w-100 h-auto" alt="intake img">
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="intake img">
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </div>

                                                                            <!-- Info -->
                                                                            <div class="col-12 d-flex flex-column px-0 mt-3 gap-3 align-content-center justify-content-center">
                                                                                <h6 class="text-18 text-dark-color fw-semibold m-0"><?= $data['intake'] ?></h6>
                                                                                <p class="text-13 sub-text p-0 m-0 d-flex flex-wrap align-items-center">
                                                                                    <?= truncateText($data['faculty']) ?>
                                                                                    <iconify-icon icon="gg:arrow-right" width="20" height="20" class="sub-text mx-1 p-0 my-0"></iconify-icon>
                                                                                    <?= truncateText($data['programme']) ?>
                                                                                </p>

                                                                                <div class="d-flex flex-column gap-3">
                                                                                    <style>
                                                                                        .student-details-item {
                                                                                            position: relative;
                                                                                        }

                                                                                        .copy-icon {
                                                                                            opacity: 0;
                                                                                            transition: opacity 0.2s ease-in-out;
                                                                                        }

                                                                                        .student-details-item:hover .copy-icon {
                                                                                            opacity: 1;
                                                                                        }
                                                                                    </style>
                                                                                    <div class="student-details-item d-flex align-items-center cursor-pointer gap-2">
                                                                                        <iconify-icon icon="tabler:number" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                        <span class="student-details-id text-13">Admission Number : <?= $sdata['student_id'] ?></span>
                                                                                        <!-- Copy icon (hidden by default) -->
                                                                                        <iconify-icon
                                                                                            icon="gala:copy"
                                                                                            width="18"
                                                                                            height="18"
                                                                                            class="copy-icon"
                                                                                            style="cursor: pointer;"
                                                                                            title="Copy ID" onclick="copyToClipboardIntakeStudentId('<?= $data['student_id'] ?>',this)"></iconify-icon>
                                                                                    </div>
                                                                                    <div class="student-details-item d-flex align-items-center cursor-pointer gap-2">
                                                                                        <iconify-icon icon="hugeicons:identification" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                        <span class="student-details-id text-13">Intake Student ID : <?= $data['student_id'] == '' ? 'N/A' : $data['student_id'] ?></span>
                                                                                        <!-- Copy icon (hidden by default) -->
                                                                                        <iconify-icon
                                                                                            icon="gala:copy"
                                                                                            width="18"
                                                                                            height="18"
                                                                                            class="copy-icon"
                                                                                            style="cursor: pointer;"
                                                                                            title="Copy ID" onclick="copyToClipboardIntakeStudentId('<?= $data['student_id'] ?>',this)"></iconify-icon>
                                                                                    </div>

                                                                                    <div class="student-details-item d-flex align-items-center gap-2">
                                                                                        <iconify-icon icon="hugeicons:date-time" width="20" height="20" class="text-dark-green"></iconify-icon>
                                                                                        <span class="student-details-intake text-13 text-dark-green">Activate Date: <?= date('Y-m-d H:i A', strtotime($data['addmission_date'])) ?>
                                                                                        </span>
                                                                                    </div>

                                                                                </div>
                                                                            </div>
                                                                            <!-- Actions -->
                                                                            <div class="col-12 p-0 mt-3 m-lg-0">
                                                                                <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                                    <div class="mt-3 d-flex justify-content-end align-items-center">
                                                                                        <div class="dropdown w-100">
                                                                                            <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                                                Action
                                                                                            </button>
                                                                                            <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=<?= $data['students_has_intakes_id']; ?>">
                                                                                                        <iconify-icon icon="hugeicons:activity-01" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        Payments</a></li>
                                                                                                <!-- <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="">
                                                                                                        <iconify-icon icon="hugeicons:assignments" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        Assignments</a></li>
                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="">
                                                                                                        <iconify-icon icon="ph:exam-bold" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        Examinations</a></li>
                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="">
                                                                                                        <iconify-icon icon="ph:seal-check-bold" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        Attendnace</a></li> -->

                                                                                            </ul>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                        } else {
                                                            ?>
                                                            <div class="col-12 px-4">
                                                                <div class="row align-items-center component-theme justify-content-center">
                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
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