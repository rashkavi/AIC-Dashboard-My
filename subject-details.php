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
            header("Location: ./faculties");
        } else {

            $id = $_GET['subject-id'];
            $subject = Database::search("SELECT * FROM `subjects`
            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id` 
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` 
            WHERE `subject_id` = '" . $id . "'");
            if ($subject->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $haveAnyPermission = false;
                $loginLecturer = false;

                if (UserPermissions::validPermisisonType('subject', $id)) {
                    $haveAnyPermission = true;
                }

                if (UserPermissions::validPermisisonType('lecture_schedule', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('pre_Recorded_lectures', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('lecture_recording', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('subject_students', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('exams', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('assignments', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('quiz', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('subject_overview', $id)) {
                    $haveAnyPermission = true;
                }
                if (UserPermissions::validPermisisonType('subject_lecturers', $id)) {
                    $haveAnyPermission = true;
                }


                if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
                    $lecturerSubject = Database::search("SELECT `lecturers_id` FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $id . "'");
                    if ($lecturerSubject->num_rows > 0) {
                        $haveAnyPermission = true;
                        $loginLecturer = true;
                    }
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || $haveAnyPermission)) {
                    header("Location: ./subjects?level-id=" . $subjectData['level_id']);
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeSelectedSubject(<?= $id ?>);">
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
                                            include './includes/components/skeleton-courses.php'; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?= $subjectData["subject"] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="faculties" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?id=<?= $subjectData['faculties_faculty_id'] ?>'><?= $subjectData["faculty"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programme-id=<?= $subjectData['programme_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intake-details?intake-id=<?= $subjectData['intake_id'] ?>'><?= $subjectData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./subjects?level-id=<?= $subjectData['level_id'] ?>'><?= $subjectData["level"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $subjectData["subject"] ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                        ?>
                                                            <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row gx-3">
                                                                    <div class="col-12 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="bg-transparent border-0" onclick="window.location.href='subject-permissions?subject-id=<?= $id ?>'">
                                                                            <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 mt-4">
                                                    <div class="row g-0 g-md-4 g-lg-4 gy-4 d-flex">
                                                        <div class="col-12 col-lg-6 d-flex">
                                                            <div class="d-flex flex-column component-theme p-4 w-100">
                                                                <div class="d-flex flex-row">
                                                                    <span class="sub-topic" data-i18n="assignment-performance">Assignment Performance</span>
                                                                </div>
                                                                <div class="d-flex flex-column flex-xxl-row align-items-center justify-content-center my-4 w-100">
                                                                    <div class="h-auto p-0 m-0" id="chart-container">
                                                                        <canvas id="subjectAssignmentChart"></canvas>
                                                                    </div>
                                                                    <div class="col-12 col-xxl-5 px-xxl-3 mt-3 mt-xxl-0 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                        <div class="row w-100 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                            <div class="col-5 col-md-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-1);"></span>
                                                                                <span data-i18n="submit">Submit</span>
                                                                            </div>
                                                                            <div class="col-7 col-md-6 col-xxl-12 d-flex align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-2);"></span>
                                                                                <span data-i18n="not-submitted">Not-Submitted</span>
                                                                            </div>
                                                                            <div class="col-5 col-md-6 col-xxl-12 d-flex mt-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-3);"></span>
                                                                                <span data-i18n="marking">Marking</span>
                                                                            </div>
                                                                            <div class="col-7 col-md-6 col-xxl-12 d-flex mt-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-4);"></span>
                                                                                <span data-i18n="mark-released">Mark Released</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 d-flex">
                                                            <div class="d-flex flex-column component-theme p-4 w-100">
                                                                <div class="d-flex flex-row">
                                                                    <span class="sub-topic">Quiz Examinations Performance</span>
                                                                </div>
                                                                <div class="d-flex flex-column flex-xxl-row align-items-center justify-content-center my-4 w-100">
                                                                    <div class="h-auto p-0 m-0" id="chart-container">
                                                                        <canvas id="subjectExamChart"></canvas>
                                                                    </div>
                                                                    <div class="col-12 col-xxl-5 px-xxl-3 mt-3 mt-xxl-0 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                        <div class="row w-100 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                            <div class="col-5 col-md-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-1);"></span>
                                                                                <span data-i18n="submit">Submit</span>
                                                                            </div>
                                                                            <div class="col-7 col-md-6 col-xxl-12 d-flex align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-2);"></span>
                                                                                <span data-i18n="not-submitted">Not-Submitted</span>
                                                                            </div>
                                                                            <div class="col-5 col-md-6 col-xxl-12 d-flex mt-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-3);"></span>
                                                                                <span data-i18n="marking">Marking</span>
                                                                            </div>
                                                                            <div class="col-7 col-md-6 col-xxl-12 d-flex mt-3 align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--chart-color-4);"></span>
                                                                                <span data-i18n="mark-released">Mark Released</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 mb-4">
                                                    <div class="row gy-4 m-0">
                                                        <div class="col-12">
                                                            <div class="row component-theme p-4">
                                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="lsicon:attachments-filled" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">Subject Attachmentts</span>
                                                                        <span class="text-14 main-text" data-i18n="">Access and organize learning materials, documents, and resources related to this subject.</span>
                                                                    </div>
                                                                </div>
                                                                <?php

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('subject', $id)) {
                                                                ?>
                                                                    <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                <button class="custom-btn w-100" data-i18n="view" onclick="location.href='subject-attachments?subject-id=<?= $id ?>'">View</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="row component-theme p-4">
                                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:teacher" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="lecturers">Assigned Lecturers</span>
                                                                        <span class="text-14 main-text" data-i18n="assigned-lecturers-desc">View and manage lecturers assigned to specific subjects.</span>
                                                                    </div>
                                                                </div>
                                                                <?php

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('subject_lecturers', $id) || $loginLecturer) {
                                                                ?>
                                                                    <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                <button class="custom-btn w-100" data-i18n="view" onclick="location.href='subject-lecturers?subject-id=<?= $id ?>'">View</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="row component-theme p-4">
                                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="mdi:timetable" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="lecture-schedule">Lecture Schedule</span>
                                                                        <span class="text-14 main-text" data-i18n="lec-schedule-desc">Manage lectures with ease and flexibility</span>
                                                                    </div>
                                                                </div>
                                                                <?php

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', $id) || UserPermissions::validPermisisonType('pre_Recorded_lectures', $id) || UserPermissions::validPermisisonType('lecture_recording', $id) || $loginLecturer) {
                                                                ?>
                                                                    <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center dropdown">
                                                                                <button class="custom-btn w-100 dropdown-toggle" data-i18n="view" type="button" data-bs-toggle="dropdown" aria-expanded="false">View</button>
                                                                                <ul class="dropdown-menu dropdown-menu-full mt-2">
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || $loginLecturer) {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item" href="subject-lecture-schedule?subject-id=<?= $id ?>" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                                                        <li><a class="dropdown-item" href="subject-pre-recorded-lectures?subject-id=<?= $id ?>" data-i18n="pre-recorded-lectures">Pre-recorded Lectures</a></li>
                                                                                        <!-- <li><a class="dropdown-item" href="subject-live-lecture-recordings?subject-id=<?= $id ?>" data-i18n="live-lecture-recording">Live Lecture Recording</a></li> -->
                                                                                        <?php
                                                                                    } else {
                                                                                        if (UserPermissions::validPermisisonType('lecture_schedule', $id)) {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item" href="subject-lecture-schedule?subject_id=<?= $id ?>" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                                                        <?php
                                                                                        }
                                                                                        if (UserPermissions::validPermisisonType('pre_Recorded_lectures', $id)) {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item" href="subject-pre-recorded-lectures?subject-id=<?= $id ?>" data-i18n="pre-recorded-lectures">Pre-recorded Lectures</a></li>
                                                                                        <?php
                                                                                        }
                                                                                        if (UserPermissions::validPermisisonType('lecture_recording', $id)) {
                                                                                        ?>
                                                                                            <!-- <li><a class="dropdown-item" href="subject-live-lecture-recordings?subject-id=<?= $id ?>" data-i18n="live-lecture-recording">Live Lecture Recording</a></li> -->
                                                                                    <?php
                                                                                        }
                                                                                    }
                                                                                    ?>
                                                                                </ul>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <?php if ($subjectData['subject_type_subject_type_id'] == 2) { ?>
                                                            <div class="col-12">
                                                                <div class="row component-theme p-4">
                                                                    <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                                        <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:student" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                        <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                            <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="enrolled-students">Enrolled Students</span>
                                                                            <span class="text-14 main-text" data-i18n="enrolled-students-desc">View and manage students registered for each subject.</span>
                                                                        </div>
                                                                    </div>
                                                                    <?php

                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('subject_students', $id) || $loginLecturer) {
                                                                    ?>
                                                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                                            <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                    <button class="custom-btn w-100" data-i18n="view" onclick="location.href='subject-students?subject-id=<?= $id ?>'">View</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php } ?>

                                                        <div class="col-12">
                                                            <div class="row component-theme p-4">
                                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:assignment-outline" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="assignments">Assignments</span>
                                                                        <span class="text-14 main-text" data-i18n="assignments-desc">Create, distribute, and track student assignment submissions easily.</span>
                                                                    </div>
                                                                </div>
                                                                <?php

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('assignments', $id) || $loginLecturer) {
                                                                ?>
                                                                    <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                <button class="custom-btn w-100" data-i18n="view" onclick="location.href='subject-assignments?subject-id=<?= $id ?>'">View</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="row component-theme p-4">
                                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="ph:exam" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="examinations">Examinations</span>
                                                                        <span class="text-14 main-text" data-i18n="examinations-desc">Organize, schedule, and monitor student exams with full control.</span>
                                                                    </div>
                                                                </div>
                                                                <?php

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('exams', $id) || UserPermissions::validPermisisonType('quiz', $id) || $loginLecturer) {
                                                                ?>
                                                                    <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center dropdown">
                                                                                <button class="custom-btn w-100 dropdown-toggle" data-i18n="view" type="button" data-bs-toggle="dropdown" aria-expanded="false">View</button>
                                                                                <ul class="dropdown-menu mt-2">
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || $loginLecturer) {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item" href="subject-examinations?subject-id=<?= $id ?>" data-i18n="online-physical-exams">Online/Physical Examinations</a></li>
                                                                                        <li><a class="dropdown-item" href="subject-quizes?subject-id=<?= $id ?>" data-i18n="quizes">Quizes</a></li>
                                                                                        <?php
                                                                                    } else {

                                                                                        if (UserPermissions::validPermisisonType('exams', $id)) {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item" href="subject-examinations?subject-id=<?= $id ?>" data-i18n="online-physical-exams">Online/Physical Examinations</a></li>
                                                                                        <?php
                                                                                        }

                                                                                        if (UserPermissions::validPermisisonType('quiz', $id)) {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item" href="subject-quizes?subject-id=<?= $id ?>" data-i18n="quizes">Quizes</a></li>
                                                                                    <?php

                                                                                        }
                                                                                    }
                                                                                    ?>
                                                                                </ul>
                                                                            </div>
                                                                        </div>
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
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
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