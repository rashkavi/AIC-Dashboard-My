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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2))) {
            header("Location: ./lecture-schedule");
        } else {
            $selectedDate = '';

            if (isset($_GET['date']) && !empty($_GET['date'])) {
                $selectedDate = $_GET['date'];
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Schedule a Lecture - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializeScheduleLecture();">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Lecture Schedule");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Lecture Schedule");
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
                                                <span class="main-topic lh-lg" data-i18n="schedule-lecture-title">Schedule Lecture</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="index" data-i18n="schedule-lecture">Schedule a Lecture</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <p class="sub-topic">Select lecture schedule type</p>
                                            <div class="d-flex gap-3 mb-4 flex-column flex-md-row">

                                                <!-- Option A: Intake Payment Group -->
                                                <div class="grp-toggle-card active" id="btn-intake-group" onclick="switchLectureScheduleType('single')" role="button" tabindex="0">
                                                    <div class="grp-icon">
                                                        <iconify-icon icon="cuida:single-select-outline" width="20" height="20" style="color:#fff;" aria-hidden="true"></iconify-icon>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <!-- Option A: Single Branch -->
                                                        <span class="text-14 main-text fw-semibold" id="grp-label-intake">Single Branch Lecture Schedule</span>
                                                        <span class="text-12 sub-text mt-1">Create a lecture schedule for a single branch</span>
                                                    </div>
                                                    <div class="grp-radio ms-auto" id="radio-intake">
                                                        <iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;" aria-hidden="true"></iconify-icon>
                                                    </div>
                                                </div>

                                                <!-- Option B: Custom Payment Group -->
                                                <div class="grp-toggle-card" id="btn-custom-group" onclick="switchLectureScheduleType('multiple')" role="button" tabindex="0">
                                                    <div class="grp-icon">
                                                        <iconify-icon icon="fluent:coin-multiple-16-regular" width="20" height="20" style="color:var(--sub-text);" id="icon-custom" aria-hidden="true"></iconify-icon>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <!-- Option B: Multiple Branch -->
                                                        <span class="text-14 main-text fw-semibold">Multiple Branch Lecture Schedule</span>
                                                        <span class="text-12 sub-text mt-1">Create and assign lecture schedules for multiple branches</span>
                                                    </div>
                                                    <div class="grp-radio ms-auto" id="radio-custom"></div>
                                                </div>

                                            </div>

                                            <hr class="my-3" style="border-color:var(--line-color);">
                                        </div>

                                        <div class="col-12 p-4 component-theme d-none" id="schedule-multiple-branch">
                                            <div class="row p-0 m-0 g-0">

                                                <!-- ======================= FILTER BAR ======================= -->
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">

                                                        <!-- Subject search (distinct subject names, search-as-you-type) -->
                                                        <div class="col-12 col-md-8 col-lg-11 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search subject name..." id="subject-search-text" onkeyup="handleCoursePagination('filter')">
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 collapse" id="filterCollapse">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3">
                                                                        <!-- Level selector -->
                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="ml-level-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Level -</option>
                                                                            </select>
                                                                        </div>

                                                                        <!-- Intake selector -->
                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="ml-intake-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Intake -</option>
                                                                            </select>
                                                                        </div>

                                                                        <!-- Programme selector -->
                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="ml-programme-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Programme -</option>
                                                                            </select>
                                                                        </div>

                                                                        <!-- Faculty selector -->
                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="ml-faculty-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Faculty -</option>
                                                                            </select>
                                                                        </div>

                                                                        <!-- Branch selector -->
                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="ml-branch-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Branch -</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>



                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="show-result-selector" class="pe-2 sub-text">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleCoursePagination('filter')">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>

                                                    </div>
                                                </div>

                                                <!-- ======================= TABLE ======================= -->
                                                <div class="col-12">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="course-structure-table-container">
                                                                <?php

                                                                require_once("includes/courseStructureHelpers.php");

                                                                // Empty filters for the initial page load — full unfiltered facet lists
                                                                $filters = [
                                                                    'text' => '',
                                                                    'branch' => 0,
                                                                    'faculty' => 0,
                                                                    'programme' => 0,
                                                                    'intake' => 0,
                                                                    'level' => 0,

                                                                ];

                                                                $pageno = 1;
                                                                $result_per_page = 10;
                                                                $sort_subject = 0;
                                                                $sort_date = 0;

                                                                renderCourseStructureTable($filters, $pageno, $result_per_page, $sort_subject, $sort_date, 1);
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme" id="schedule-single-branch">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Branch</label>
                                                            <select name="" id="branch-selector" class="form-select custom-select mt-2" onchange="loadFaculties();">
                                                                <option value="0" selected>- Select -</option>
                                                                <option value="-1">— Entire Institution (All Branches) —</option>
                                                                <?php
                                                                $query = "SELECT * FROM `branches`";

                                                                $branches = Database::search($query);
                                                                while ($data = $branches->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["branch_id"]; ?>"><?php echo $data["branch"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="branch-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Faculty</label>
                                                            <select name="" id="faculty-selector" disabled class="form-select custom-select mt-2" onchange="loadProgrammes();">
                                                                <option value="0" selected>- Select -</option>

                                                            </select>
                                                            <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Programmes</label>
                                                            <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Intake</label>
                                                            <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="loadLevels();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Level</label>
                                                            <select name="" id="level-selector" disabled class="form-select custom-select mt-2" onchange="loadSubjects();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="level-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Subject</label>
                                                            <select name="" id="subject-selector" disabled class="form-select custom-select mt-2" onchange="loadLecturers();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="subject-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">

                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Lecturer</label>
                                                            <select name="" id="lecturer-selector" disabled class="form-select custom-select mt-2">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="lecturer-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 mt-3">
                                                            <label class="text-16 fw-semibold main-text mb-2 d-block required">
                                                                Lecture Schedule Type
                                                            </label>

                                                            <div class="d-flex gap-4 px-2 mt-4">
                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="oneTimeLecture" checked>
                                                                    <label class="form-check-label" for="oneTimeLecture">
                                                                        One-Time Lecture
                                                                    </label>
                                                                </div>

                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="recurringLecture">
                                                                    <label class="form-check-label" for="recurringLecture">
                                                                        Recurring Lecture
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="container mt-4 d-none" id="recurringLectureArea">
                                                            <div class="card shadow-none p-0 border-0">
                                                                <div class="card-body p-0">
                                                                    <!-- Date Range -->
                                                                    <div class="row g-3 mb-4">
                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture Start Date</label>
                                                                            <input type="date" class="form-control custom-input mt-2" id="start-date">
                                                                            <span id="start-date-feedback" class="text-dark-red text-12"></span>
                                                                        </div>

                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture End Date</label>
                                                                            <input type="date" class="form-control custom-input mt-2" id="end-date">
                                                                            <span id="end-date-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture Start Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" id="r-start-time">
                                                                            <span id="r-start-time-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture End Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" id="r-end-time">
                                                                            <span id="r-end-time-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                    </div>
                                                                    <!-- Recurring Days -->
                                                                    <div class="mb-4">
                                                                        <label class="form-label fw-medium required">Recurring Days</label>
                                                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                                                            <?php
                                                                            $data = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

                                                                            for ($i = 0; $i < count($data); $i++) {
                                                                            ?>
                                                                                <div class="chip align-content-center" data-day-id="<?= $i + 1 ?>" onclick="dateChipBoxAction(this);">
                                                                                    <span class="chip-label"><?php echo $data[$i]; ?></span>
                                                                                    <span class="chip-check-icon">
                                                                                        <iconify-icon icon="mdi:check"></iconify-icon>
                                                                                    </span>
                                                                                </div>
                                                                            <?php
                                                                            }
                                                                            ?>

                                                                        </div>
                                                                        <span id="days-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <!-- Skip Options -->
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-medium">Skip Lectures On</label>
                                                                        <div class="mt-2">
                                                                            <div class="form-check mb-2">
                                                                                <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" id="skip-poya">
                                                                                <label class="form-check-label" for="skip-poya">
                                                                                    Skip Poya Days (Sri Lanka public holidays)
                                                                                </label>
                                                                            </div>
                                                                            <div class="form-check mb-2">
                                                                                <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" id="skip-holidays">
                                                                                <label class="form-check-label" for="skip-holidays">
                                                                                    Skip Special / Public Holidays
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Custom Skip Dates -->
                                                                    <div class="mt-3 d-none" id="custom-skip-dates">
                                                                        <label class="form-label fw-medium">Select Custom Skip Dates</label>
                                                                        <input type="date" class="form-control mb-2">
                                                                        <input type="date" class="form-control">
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>


                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 g-0 mt-4" id="onetimeLectureArea">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Date</label>
                                                            <input type="date" class="form-control custom-input mt-2" id="lecture-date" value="<?= $selectedDate ?>">
                                                            <span id="date-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Pick Start Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="start-time">
                                                            <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Pick End Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="end-time">
                                                            <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Lecture Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="title">
                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text" for="">Lecture Attachment</label>
                                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" multiple accept=".pdf">
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div id="attachment-feedback" class="text-dark-red text-12"></div>
                                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-3" id="lectureTypeArea">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 d-flex flex-column mb-4">
                                                            <label class="text-16 main-text required" for="">Lecture Type</label>
                                                            <div class="d-flex gap-4 px-2 mt-4">

                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="physical-check-box" checked>
                                                                    <label class="text-16 sub-text mx-2">Physical</label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="online-check-box">
                                                                    <label class="text-16 sub-text mx-2">Online</label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="hybrid-check-box">
                                                                    <label class="text-16 sub-text mx-2">Hybrid</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column mb-4 d-none" id="lecture-link-area">
                                                            <label class="text-16 main-text" for="">Lecture Link</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="link-input">
                                                            <span id="link-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" id="save-single-lecture" onclick="scheduleLecture(this);">Save Lecture</button>
                                                                <button class="custom-btn w-100 d-none" id="save-multiple-lectures" onclick="scheduleMultipleLectures(this);">Save Lecture</button>
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

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <!-- <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script> -->
                <script src="./assets/js/fileChooser.js"></script>
                <script>
                    const applyValidation = (response, validationMap, showMsg = true) => {
                        if (showMsg) {
                            showToast('error', 'Something went wrong.Check data fields and try again');
                        }
                        $('.contentBodyArea').animate({
                            scrollTop: 0
                        }, 'fast');
                        Object.entries(validationMap).forEach(([key, selectors]) => {
                            const [input, feedback] = selectors;

                            if (response[key]) {
                                $(input).addClass("is-invalid");
                                $(feedback).text(response[key]);
                            } else {
                                $(input).removeClass("is-invalid");
                                $(feedback).text("");
                            }
                        });
                    }



                    const oneTime = document.getElementById("oneTimeLecture");
                    const recurring = document.getElementById("recurringLecture");
                    const recurringSection = document.getElementById("recurring-section");

                    oneTime.addEventListener("change", () => {
                        recurringSection.classList.add("d-none");
                    });

                    recurring.addEventListener("change", () => {
                        recurringSection.classList.remove("d-none");
                    });

                    let selectedCoursePage = 1;
                    let courseSortSubject = 0; // 0 = none, 1 = ASC, 2 = DESC
                    let courseSortDate = 0;

                    const toggleCourseSort = (current) => {
                        if (current === 0) return 1;
                        if (current === 1) return 2;
                        return 0;
                    };

                    const handleCoursePagination = (action, pageId = null, totalPages = null) => {
                        switch (action) {
                            case 'goToPage':
                                selectedCoursePage = parseInt(pageId, 10);
                                break;
                            case 'previousPage':
                                if (selectedCoursePage > 1) selectedCoursePage--;
                                break;
                            case 'nextPage':
                                if (selectedCoursePage < parseInt(totalPages, 10)) selectedCoursePage++;
                                break;
                            case 'filter':
                                selectedCoursePage = 1;
                                break;
                            case 'sortBySubject':
                                courseSortDate = 0;
                                courseSortSubject = toggleCourseSort(courseSortSubject);
                                break;
                            case 'sortByDate':
                                courseSortSubject = 0;
                                courseSortDate = toggleCourseSort(courseSortDate);
                                break;
                        }

                        filterCourseStructure();
                    };

                    const clearCourseFilters = () => {
                        $("#subject-search-text").val("");
                        $("#m-level-selector").val("0");
                        $("#ml-intake-selector").val("0");
                        $("#ml-programme-selector").val("0");
                        $("#ml-faculty-selector").val("0");
                        $("#ml-branch-selector").val("0");
                        handleCoursePagination('filter');
                    };

                    // Rebuild a single <select>'s <option> list from a facet result, preserving the
                    // currently-selected value when it's still present in the new option set.
                    const populateSelector = (selectorId, placeholderLabel, options) => {
                        const $select = $(selectorId);
                        const currentValue = $select.val();

                        let html = `<option value="0">- ${placeholderLabel} -</option>`;
                        let currentStillValid = false;

                        options.forEach((opt) => {
                            const selectedAttr = String(opt.id) === String(currentValue) ? "selected" : "";
                            if (selectedAttr) currentStillValid = true;
                            html += `<option value="${opt.id}" ${selectedAttr}>${opt.name}</option>`;
                        });

                        $select.html(html);
                        if (currentStillValid) {
                            $select.val(currentValue);
                        } else {
                            $select.val("0");
                        }
                    };

                    const populateAllSelectors = (options) => {
                        populateSelector("#ml-level-selector", "Level", options.level);
                        populateSelector("#ml-intake-selector", "Intake", options.intake);
                        populateSelector("#ml-programme-selector", "Programme", options.programme);
                        populateSelector("#ml-faculty-selector", "Faculty", options.faculty);
                        populateSelector("#ml-branch-selector", "Branch", options.branch);
                        // Subject is a free-text search box rather than a select, so its distinct list
                        // (options.subject) is available if you want to wire up a typeahead/autocomplete
                        // later (e.g. via a <datalist> or select2) — left out here to keep the box a plain search field.
                    };

                    const filterCourseStructure = () => {
                        const data = {
                            type: 1,
                            text: $("#subject-search-text").val() || "",
                            level: $("#m-level-selector").val() || "0",
                            intake: $("#ml-intake-selector").val() || "0",
                            programme: $("#ml-programme-selector").val() || "0",
                            faculty: $("#ml-faculty-selector").val() || "0",
                            branch: $("#ml-branch-selector").val() || "0",
                            sortSubject: courseSortSubject,
                            sortDate: courseSortDate,
                            resultPerPage: $("#show-result-selector").val() || 10,
                            page: selectedCoursePage || 1,
                        };

                        fetch('api/filterCourseStructure.php', {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json"
                                },
                                body: JSON.stringify(data),
                            })
                            .then(res => res.text())
                            .then(text => {
                                let result;
                                try {
                                    result = JSON.parse(text);
                                } catch {
                                    result = text;
                                }

                                if (typeof result === "object" && result.error) {
                                    showToast("error", result.error);
                                } else if (typeof result === "object" && result.table) {
                                    $("#course-structure-table-container").html(result.table);
                                    populateAllSelectors(result.options);
                                } else {
                                    showToast("error", "Unexpected response from server.");
                                }
                            })
                            .catch(error => {
                                console.error("Filter course structure failed:", error);
                                showToast("error", "An error occurred. Please try again later.");
                            });
                    };

                    document.addEventListener('DOMContentLoaded', function() {
                        populateAllSelectors(<?php echo getCourseStructureOptionsJson($filters); ?>);
                    });

                    // ===================== Multi-branch schedule: subject selection + common lecturer =====================
                    // checkedSubjectIds persists across table re-renders (pagination/filtering) so selections
                    // made on one page of results survive navigating to another page.
                    const checkedSubjectIds = new Set();

                    // Fired by the header "check all" checkbox (id="check-all", onchange="tableCheckEvents()")
                    const LecTableCheckEvents = () => {
                        const checkAll = document.getElementById('check-all');
                        $(".row-check").each(function() {
                            this.checked = checkAll.checked;
                            const subjectId = parseInt(this.id.replace('check-user', ''), 10);
                            if (checkAll.checked) {
                                checkedSubjectIds.add(subjectId);
                            } else {
                                checkedSubjectIds.delete(subjectId);
                            }
                        });
                        refreshCommonLecturers();
                    };

                    // Fired by each row checkbox (id="check-user<subject_id>", onchange="checkRowEvents(<subject_id>);")
                    const lecCheckRowEvents = (subjectId) => {
                        const checkbox = document.getElementById('check-user' + subjectId);
                        if (checkbox.checked) {
                            checkedSubjectIds.add(subjectId);
                        } else {
                            checkedSubjectIds.delete(subjectId);
                        }
                        refreshCommonLecturers();
                    };

                    // Re-check the boxes for any subject_ids that are already in checkedSubjectIds after the
                    // table re-renders (pagination/filter/sort), so cross-page selections stay visibly checked.
                    const restoreSubjectCheckboxes = () => {
                        $(".row-check").each(function() {
                            const subjectId = parseInt(this.id.replace('check-user', ''), 10);
                            this.checked = checkedSubjectIds.has(subjectId);
                        });
                        const checkAllBox = document.getElementById('check-all');
                        if (checkAllBox) {
                            const rowBoxes = $(".row-check");
                            checkAllBox.checked = rowBoxes.length > 0 && rowBoxes.filter(':checked').length === rowBoxes.length;
                        }
                    };

                    // Queries lecturers assigned to EVERY checked subject (matched by user_id — see
                    // api/getCommonLecturers.php for the SQL). Populates/enables #lecturer-selector, or
                    // clears/disables it if nothing is selected or no lecturer covers all selected subjects.
                    const refreshCommonLecturers = () => {
                        const subjectIds = Array.from(checkedSubjectIds);
                        const $lecturerSelector = $("#lecturer-selector");
                        const $feedback = $("#lecturer-feedback");

                        if (subjectIds.length === 0) {
                            $lecturerSelector.html('<option value="0" selected>- Select -</option>');
                            $lecturerSelector.prop("disabled", true);
                            $feedback.text("");
                            return;
                        }

                        fetch('api/getCommonLecturers.php', {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json"
                                },
                                body: JSON.stringify({
                                    subjectIds
                                }),
                            })
                            .then(res => res.text())
                            .then(text => {
                                let result;
                                try {
                                    result = JSON.parse(text);
                                } catch {
                                    result = text;
                                }

                                if (typeof result === "object" && result.error) {
                                    showToast("error", result.error);
                                    return;
                                }

                                if (!result.lecturers || result.lecturers.length === 0) {
                                    $lecturerSelector.html('<option value="0" selected>- Select -</option>');
                                    $lecturerSelector.prop("disabled", true);
                                    $feedback.text("No lecturer is assigned to all the selected subjects.");
                                } else {
                                    // value = user_id, since the same person can hold several lecturers_id
                                    // rows (one per subject) — user_id is the identity that has to match
                                    // across every checked subject, not lecturers_id.
                                    let html = '<option value="0" selected>- Select -</option>';
                                    result.lecturers.forEach((lecturer) => {
                                        html += `<option value="${lecturer.id}">${lecturer.name}</option>`;
                                    });
                                    $lecturerSelector.html(html);
                                    $lecturerSelector.prop("disabled", false);
                                    $feedback.text("");
                                }
                            })
                            .catch((error) => {
                                console.error("Failed to load common lecturers:", error);
                                showToast("error", "An error occurred while loading lecturers.");
                            });
                    };

                    // ===================== Multi-branch schedule: submit =====================
                    const scheduleMultipleLectures = (btn) => {
                        const subjectIds = Array.from(checkedSubjectIds);

                        if (subjectIds.length === 0) {
                            showToast("error", "Please select at least one subject from the table.");
                            return;
                        }

                        toggleBtnLoader(btn, true);

                        const form = new FormData();
                        subjectIds.forEach((id) => form.append("subject_ids[]", id));
                        form.append("lecturer_user_id", $("#lecturer-selector").val());
                        form.append("lecture-date", $("#lecture-date").val());

                        form.append("title", $("#title").val());
                        form.append("physical", $("#physical-check-box").is(":checked") ? true : false);
                        form.append("online", $("#online-check-box").is(":checked") ? true : false);
                        form.append("hybrid", $("#hybrid-check-box").is(":checked") ? true : false);
                        form.append("link", $("#link-input").val());
                        form.append("one_time", $("#oneTimeLecture").is(":checked") ? true : false);
                        form.append("recurring", $("#recurringLecture").is(":checked") ? true : false);
                        form.append("start_date", $("#start-date").val());
                        form.append("end_date", $("#end-date").val());
                        if ($("#recurringLecture").is(":checked")) {
                            form.append("start_time", $("#r-start-time").val());
                            form.append("end_time", $("#r-end-time").val());
                        } else {
                            form.append("start_time", $("#start-time").val());
                            form.append("end_time", $("#end-time").val());
                        }

                        form.append("days", selectedDays);
                        form.append("skip_poya_days", $("#skip-poya").is(":checked") ? true : false);
                        form.append("skip_holidays", $("#skip-holidays").is(":checked") ? true : false);

                        var files = $("#file-selector-input")[0].files;
                        for (var i = 0; i < files.length; i++) {
                            form.append("tutes[]", files[i]);
                        }

                        const validationMap = {
                            subjects: ["#course-structure-table-container", "#subjects-feedback"],
                            lecturer: ["#lecturer-selector", "#lecturer-feedback"],
                            date: ["#lecture-date", "#date-feedback"],
                            start_time: ["#start-time", "#start-time-feedback"],
                            end_time: ["#end-time", "#end-time-feedback"],
                            link: ["#link-input", "#link-feedback"],
                            title: ["#title", "#title-feedback"],
                            tutes: ["#file-selector-input", "#tutes-feedback"],
                            start_date: ["#start-date", "#start-date-feedback"],
                            end_date: ["#end-date", "#end-date-feedback"],
                            days: ["#days", "#days-feedback"],
                        };

                        if ($("#recurringLecture").is(":checked")) {
                            validationMap.start_time = ["#r-start-time", "#r-start-time-feedback"];
                            validationMap.end_time = ["#r-end-time", "#r-end-time-feedback"];
                        }

                        fetch("api/scheduleMultipleLectures.php", {
                                method: "POST",
                                body: form
                            })
                            .then((res) => res.json())
                            .then((response) => {
                                console.log(response);
                                toggleBtnLoader(btn, false);
                                applyValidation(response, validationMap, false);
                                if (response.error) {
                                    showToast("error", response.error);
                                } else if (response.done) {
                                    showToast("success", response.done);
                                    setTimeout(() => {
                                        window.location.reload()
                                    }, 3000);
                                } else {
                                    applyValidation(response, validationMap);
                                    if (response.tute) {
                                        const feedback = document.getElementById('attachment-feedback');
                                        feedback.innerHTML = `
                        <ul class="ps-3">
                            ${response.tute.map(e => `<li>${e}</li>`).join('')}
                        </ul>
                        `;
                                    }
                                }
                            })
                            .catch((error) => {
                                console.log(error);
                                showToast("error", "Something went wrong. Please try again later");
                            });
                    };
                </script>


                <script>
                    /* =====================================================================
                     * Single-branch lecture schedule — cascading selectors with
                     * "Entire X" wildcard support at every level (branch → subject).
                     *
                     * WILDCARD_VALUE ("-1") means "don't narrow further, apply to the
                     * whole parent scope". Value "0" still means "nothing chosen yet"
                     * (invalid — same as before).
                     *
                     * Because Lecturer is always required (you always need a real person
                     * to run the lecture), it has two population modes:
                     *   - "subject" mode: populated from api/loadLecturers.php?id=<subject>,
                     *     value = a subject-bound `lecturers_id` row (unchanged behavior).
                     *   - "broad" mode: populated from api/loadLecturersByScope.php,
                     *     value = `users_user_id`. Used whenever Subject (or anything above
                     *     it in the cascade) was wildcarded, or a wildcard higher up left
                     *     Subject at its unselected placeholder.
                     * The selected mode is tracked via a data-lecturer-mode attribute and
                     * read in scheduleLecture() to decide which POST field to send.
                     *
                     * IMPORTANT: loadLecturers() decides "broad vs subject" mode by whether
                     * Subject holds a CONCRETE value (not "0", not WILDCARD_VALUE) — not by
                     * checking for WILDCARD_VALUE alone. That's because resetDownstreamFrom()
                     * (called whenever an ancestor selector is wildcarded) clears Subject back
                     * to the "0" placeholder, not to WILDCARD_VALUE. Checking only for the
                     * literal wildcard value would miss that case entirely.
                     * ===================================================================== */

                    const WILDCARD_VALUE = "-1";
                    const PLACEHOLDER_OPTION_HTML = "<option value='0'>- Select -</option>";

                    // Order matters — this is the cascade chain used for disabling/clearing
                    // everything "downstream" of a given selector.
                    const scheduleChain = [
                        "branch-selector",
                        "faculty-selector",
                        "programme-selector",
                        "intake-selector",
                        "level-selector",
                        "subject-selector",
                        "lecturer-selector",
                    ];

                    // e.g. "faculty-selector" -> "faculty-feedback"
                    const feedbackIdFor = (selectorId) => selectorId.replace("-selector", "-feedback");

                    /**
                     * Disable + clear every selector strictly AFTER `fromSelectorId` in the
                     * chain. Called whenever a parent selector changes (fresh data invalidates
                     * whatever was picked further down) or when a wildcard is chosen (nothing
                     * below a wildcard is meaningful anymore).
                     */
                    const resetDownstreamFrom = (fromSelectorId) => {
                        const idx = scheduleChain.indexOf(fromSelectorId);
                        if (idx === -1) return;

                        for (let i = idx + 1; i < scheduleChain.length; i++) {
                            const id = scheduleChain[i];
                            $(`#${id}`)
                                .html(PLACEHOLDER_OPTION_HTML)
                                .attr("disabled", true)
                                .removeClass("is-invalid")
                                .removeAttr("data-lecturer-mode");
                            $(`#${feedbackIdFor(id)}`).text("");
                        }
                    };

                    /**
                     * Prepend an "— Entire {label} —" option to a raw <option> list returned
                     * by the loadX.php endpoints (which currently just return the "- Select -"
                     * option plus the real rows). Inserted right after the first </option> so
                     * it sits directly under the placeholder.
                     */
                    const insertWildcardOption = (optionsHtml, wildcardLabel) => {
                        const wildcardOption = `<option value="${WILDCARD_VALUE}">— ${wildcardLabel} —</option>`;
                        const firstCloseTag = optionsHtml.indexOf("</option>");
                        if (firstCloseTag === -1) return wildcardOption + optionsHtml;
                        const insertAt = firstCloseTag + "</option>".length;
                        return optionsHtml.slice(0, insertAt) + wildcardOption + optionsHtml.slice(insertAt);
                    };

                    /**
                     * Walks the chain from Subject up to Branch and returns the deepest
                     * concretely-selected (non-zero, non-wildcard) scope. Used to figure out
                     * what to scope the "broad" lecturer list by once Subject is wildcarded
                     * (or left unselected because an ancestor was wildcarded).
                     * Returns null if even Branch is wildcarded/unselected (entire institution).
                     */
                    const findDeepestConcreteScope = () => {
                        const scopeOrder = [{
                                type: "level",
                                id: "#level-selector"
                            },
                            {
                                type: "intake",
                                id: "#intake-selector"
                            },
                            {
                                type: "programme",
                                id: "#programme-selector"
                            },
                            {
                                type: "faculty",
                                id: "#faculty-selector"
                            },
                            {
                                type: "branch",
                                id: "#branch-selector"
                            },
                        ];
                        for (const scope of scopeOrder) {
                            const val = $(scope.id).val();
                            if (val && val !== "0" && val !== WILDCARD_VALUE) {
                                return {
                                    type: scope.type,
                                    id: val
                                };
                            }
                        }
                        return null;
                    };

                    const loadFaculties = () => {
                        const branch = $("#branch-selector").val();
                        $("#branch-selector").removeClass("is-invalid");
                        $("#branch-feedback").text("");

                        if (branch === WILDCARD_VALUE) {
                            resetDownstreamFrom("branch-selector");
                            loadLecturers();
                            return;
                        }

                        fetch(`api/loadFaculties.php?id=${branch}`)
                            .then((response) => response.text())
                            .then((data) => {
                                try {
                                    const response = JSON.parse(data);
                                    if (response.hasOwnProperty('error')) {
                                        showToast("error", response.error);
                                        $("#faculty-selector").attr("disabled", true);
                                    } else if (response.hasOwnProperty('branch')) {
                                        $("#faculty-selector").attr("disabled", true);
                                        $("#faculty-selector").html(PLACEHOLDER_OPTION_HTML);
                                        $("#branch-selector").addClass("is-invalid");
                                        $("#branch-feedback").text(response.branch);
                                    }
                                } catch (error) {
                                    $("#branch-selector").removeClass("is-invalid");
                                    $("#branch-feedback").text("");
                                    $("#faculty-selector").removeAttr("disabled");
                                    $("#faculty-selector").html(insertWildcardOption(data, "Entire Branch (All Faculties)"));
                                    resetDownstreamFrom("faculty-selector");
                                }
                            })
                            .catch((error) => {
                                showToast("error", "An error occured. Please try again later.");
                            });
                    }

                    const loadProgrammes = () => {
                        const faculty = $("#faculty-selector").val();
                        $("#faculty-selector").removeClass("is-invalid");
                        $("#faculty-feedback").text("");

                        if (faculty === WILDCARD_VALUE) {
                            resetDownstreamFrom("faculty-selector");
                            loadLecturers();
                            return;
                        }

                        fetch(`api/loadProgrammes.php?id=${faculty}`)
                            .then((response) => response.text())
                            .then((data) => {
                                try {
                                    const response = JSON.parse(data);
                                    if (response.hasOwnProperty('error')) {
                                        showToast("error", response.error);
                                        $("#programme-selector").attr("disabled", true);
                                    } else if (response.hasOwnProperty('faculty')) {
                                        $("#programme-selector").attr("disabled", true);
                                        $("#programme-selector").html(PLACEHOLDER_OPTION_HTML);
                                        $("#faculty-selector").addClass("is-invalid");
                                        $("#faculty-feedback").text(response.faculty);
                                    }
                                } catch (error) {
                                    $("#faculty-selector").removeClass("is-invalid");
                                    $("#faculty-feedback").text("");
                                    $("#programme-selector").removeAttr("disabled");
                                    $("#programme-selector").html(insertWildcardOption(data, "Entire Faculty (All Programmes)"));
                                    resetDownstreamFrom("programme-selector");
                                }
                            })
                            .catch((error) => {
                                showToast("error", "An error occured. Please try again later.");
                            });
                    }

                    const loadIntakes = () => {
                        const programme = $("#programme-selector").val();
                        $("#programme-selector").removeClass("is-invalid");
                        $("#programme-feedback").text("");

                        if (programme === WILDCARD_VALUE) {
                            resetDownstreamFrom("programme-selector");
                            loadLecturers();
                            return;
                        }

                        fetch(`api/loadIntakes.php?id=${programme}`)
                            .then((response) => response.text())
                            .then((data) => {
                                try {
                                    const response = JSON.parse(data);
                                    if (response.hasOwnProperty('error')) {
                                        showToast("error", response.error);
                                        $("#intake-selector").attr("disabled", true);
                                    } else if (response.hasOwnProperty('programme')) {
                                        $("#intake-selector").attr("disabled", true);
                                        $("#intake-selector").html(PLACEHOLDER_OPTION_HTML);
                                        $("#programme-selector").addClass("is-invalid");
                                        $("#programme-feedback").text(response.programme);
                                    }
                                } catch (error) {
                                    $("#programme-selector").removeClass("is-invalid");
                                    $("#programme-feedback").text("");
                                    $("#intake-selector").removeAttr("disabled");
                                    $("#intake-selector").html(insertWildcardOption(data, "Entire Programme (All Intakes)"));
                                    resetDownstreamFrom("intake-selector");
                                }
                            })
                            .catch((error) => {
                                showToast("error", "An error occured. Please try again later.");
                            });
                    }

                    const loadLevels = () => {
                        const intake = $("#intake-selector").val();
                        $("#intake-selector").removeClass("is-invalid");
                        $("#intake-feedback").text("");

                        if (intake === WILDCARD_VALUE) {
                            resetDownstreamFrom("intake-selector");
                            loadLecturers();
                            return;
                        }

                        fetch(`api/loadLevels.php?id=${intake}`)
                            .then((response) => response.text())
                            .then((data) => {
                                try {
                                    const response = JSON.parse(data);
                                    if (response.hasOwnProperty('error')) {
                                        showToast("error", response.error);
                                        $("#level-selector").attr("disabled", true);
                                    } else if (response.hasOwnProperty('intake')) {
                                        $("#level-selector").attr("disabled", true);
                                        $("#level-selector").html(PLACEHOLDER_OPTION_HTML);
                                        $("#intake-selector").addClass("is-invalid");
                                        $("#intake-feedback").text(response.intake);
                                    }
                                } catch (error) {
                                    $("#intake-selector").removeClass("is-invalid");
                                    $("#intake-feedback").text("");
                                    $("#level-selector").removeAttr("disabled");
                                    // NOTE: Level has no dedicated column on `time_table` — choosing
                                    // "Entire Intake" here collapses to the same stored scope as
                                    // wildcarding Intake itself. It's offered anyway for UX symmetry.
                                    $("#level-selector").html(insertWildcardOption(data, "Entire Intake (All Levels)"));
                                    resetDownstreamFrom("level-selector");
                                }
                            })
                            .catch((error) => {
                                showToast("error", "An error occured. Please try again later.");
                            });
                    }

                    const loadSubjects = () => {
                        const level = $("#level-selector").val();
                        $("#level-selector").removeClass("is-invalid");
                        $("#level-feedback").text("");

                        if (level === WILDCARD_VALUE) {
                            resetDownstreamFrom("level-selector");
                            loadLecturers();
                            return;
                        }

                        fetch(`api/loadSubjects.php?id=${level}`)
                            .then((response) => response.text())
                            .then((data) => {
                                try {
                                    const response = JSON.parse(data);
                                    if (response.hasOwnProperty('error')) {
                                        showToast("error", response.error);
                                        $("#subject-selector").attr("disabled", true);
                                    } else if (response.hasOwnProperty('level')) {
                                        $("#subject-selector").attr("disabled", true);
                                        $("#subject-selector").html(PLACEHOLDER_OPTION_HTML);
                                        $("#level-selector").addClass("is-invalid");
                                        $("#level-feedback").text(response.level);
                                    }
                                } catch (error) {
                                    $("#level-selector").removeClass("is-invalid");
                                    $("#level-feedback").text("");
                                    $("#subject-selector").removeAttr("disabled");
                                    $("#subject-selector").html(insertWildcardOption(data, "Entire Level (All Subjects)"));
                                    resetDownstreamFrom("subject-selector");
                                }
                            })
                            .catch((error) => {
                                showToast("error", "An error occured. Please try again later.");
                            });
                    }

                    const loadLecturers = () => {
                        const subject = $("#subject-selector").val();
                        $("#subject-selector").removeClass("is-invalid");
                        $("#subject-feedback").text("");

                        // "Broad" mode applies whenever Subject isn't a concrete pick — either it
                        // was explicitly wildcarded, OR it's sitting at the "0" placeholder because
                        // resetDownstreamFrom() cleared it after an ancestor (branch, faculty,
                        // programme, intake, level) was wildcarded. Checking the literal value
                        // against WILDCARD_VALUE alone misses that second, much more common case.
                        const subjectIsConcrete = subject && subject !== "0" && subject !== WILDCARD_VALUE;

                        if (!subjectIsConcrete) {
                            const scope = findDeepestConcreteScope();

                            // No ancestor concretely selected → entire institution. Fetch every
                            // active lecturer via type=all instead of disabling the field.
                            const scopeType = scope ? scope.type : "all";
                            const scopeId = scope ? scope.id : "0";

                            fetch(`api/loadLecturersByScope.php?type=${scopeType}&id=${scopeId}`)
                                .then((response) => response.text())
                                .then((data) => {
                                    try {
                                        const response = JSON.parse(data);
                                        if (response.hasOwnProperty('error')) {
                                            showToast("error", response.error);
                                            $("#lecturer-selector").attr("disabled", true);
                                        }
                                    } catch (error) {
                                        $("#lecturer-selector")
                                            .removeAttr("disabled")
                                            .attr("data-lecturer-mode", "broad")
                                            .html(data);
                                    }
                                })
                                .catch(() => showToast("error", "An error occured. Please try again later."));
                            return;
                        }

                        fetch(`api/loadLecturers.php?id=${subject}`)
                            .then((response) => response.text())
                            .then((data) => {
                                try {
                                    const response = JSON.parse(data);
                                    if (response.hasOwnProperty('error')) {
                                        showToast("error", response.error);
                                        $("#lecturer-selector").attr("disabled", true);
                                    } else if (response.hasOwnProperty('subject')) {
                                        $("#lecturer-selector").attr("disabled", true);
                                        $("#lecturer-selector").html(PLACEHOLDER_OPTION_HTML);
                                        $("#subject-selector").addClass("is-invalid");
                                        $("#subject-feedback").text(response.subject);
                                    }
                                } catch (error) {
                                    $("#subject-selector").removeClass("is-invalid");
                                    $("#subject-feedback").text("");
                                    $("#lecturer-selector")
                                        .removeAttr("disabled")
                                        .attr("data-lecturer-mode", "subject")
                                        .html(data);
                                }
                            })
                            .catch((error) => {
                                showToast("error", "An error occured. Please try again later.");
                            });
                    }
                </script>
            </body>

            </html>
<?php
        }
    }
}
?>