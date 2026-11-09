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
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('assignments', 0))) {
            header("Location: ./dashboard");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Add Assignment - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            load_side_menu::load("Assignments");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Assignments");
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
                                                <span class="main-topic lh-lg">Add Assignment</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule">Assignments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./manage-assignments">Manage Assignments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="index">Add Assignment</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <!-- ======================= SCHEDULE TYPE TOGGLE ======================= -->
                                        <div class="col-12 p-4 component-theme">
                                            <p class="sub-topic">Select assignment type</p>
                                            <div class="d-flex gap-3 mb-4 flex-column flex-md-row">

                                                <!-- Option A: Single Branch -->
                                                <div class="grp-toggle-card active" id="btn-single-assignment" onclick="switchAssignmentType('single')" role="button" tabindex="0">
                                                    <div class="grp-icon">
                                                        <iconify-icon icon="cuida:single-select-outline" width="20" height="20" style="color:#fff;" aria-hidden="true"></iconify-icon>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-14 main-text fw-semibold">Single Branch Assignment</span>
                                                        <span class="text-12 sub-text mt-1">Create an assignment for a single branch</span>
                                                    </div>
                                                    <div class="grp-radio ms-auto" id="radio-single-assignment">
                                                        <iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;" aria-hidden="true"></iconify-icon>
                                                    </div>
                                                </div>

                                                <!-- Option B: Multiple Branch -->
                                                <div class="grp-toggle-card" id="btn-multiple-assignment" onclick="switchAssignmentType('multiple')" role="button" tabindex="0">
                                                    <div class="grp-icon">
                                                        <iconify-icon icon="fluent:coin-multiple-16-regular" width="20" height="20" style="color:var(--sub-text);" id="icon-multiple-assignment" aria-hidden="true"></iconify-icon>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-14 main-text fw-semibold">Multiple Branch Assignment</span>
                                                        <span class="text-12 sub-text mt-1">Create and assign this assignment across multiple branches</span>
                                                    </div>
                                                    <div class="grp-radio ms-auto" id="radio-multiple-assignment"></div>
                                                </div>

                                            </div>

                                            <hr class="my-3" style="border-color:var(--line-color);">
                                        </div>

                                        <!-- ======================= SINGLE BRANCH: existing cascading selectors ======================= -->
                                        <div class="col-12 p-4 component-theme" id="assignment-single-branch">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Select Faculty</label>
                                                            <select name="" id="faculty-selector" class="form-select custom-select mt-2" onchange="loadProgrammes();">
                                                                <option value="0" selected>- Select -</option>
                                                                <?php
                                                                $query = "SELECT * FROM `faculties`";
                                                                $faculties = Database::search($query);
                                                                while ($data = $faculties->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Select Programmes</label>
                                                            <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakesGroups();">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Select Intake</label>
                                                            <select name="" id="intake-group-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span id="intake-group-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Select Batch</label>
                                                            <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="loadLevels();">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Select Level</label>
                                                            <select name="" id="level-selector" disabled class="form-select custom-select mt-2" onchange="loadSubjects();">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span id="level-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Select Subject</label>
                                                            <select name="" id="subject-selector" disabled class="form-select custom-select mt-2" onchange="loadLecturers();">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span id="subject-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ======================= MULTIPLE BRANCH: course structure table ======================= -->
                                        <div class="col-12 p-4 component-theme d-none" id="assignment-multiple-branch">
                                            <div class="row p-0 m-0 g-0">

                                                <!-- ======================= FILTER BAR ======================= -->
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">

                                                        <div class="col-12 col-md-8 col-lg-11 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search subject name..." id="am-subject-search-text" onkeyup="handleCoursePagination('filter')">
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#amFilterCollapse" aria-expanded="false" aria-controls="amFilterCollapse">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 collapse" id="amFilterCollapse">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3">
                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="am-level-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Level -</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="am-intake-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Intake -</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="am-programme-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Programme -</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="am-faculty-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Faculty -</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-6 col-md-3 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="am-branch-selector" onchange="handleCoursePagination('filter')">
                                                                                <option selected value="0">- Branch -</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="am-show-result-selector" class="pe-2 sub-text">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" id="am-show-result-selector" onchange="handleCoursePagination('filter')">
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
                                                            <div class="table-responsive" id="am-course-structure-table-container">
                                                                <?php

                                                                require_once("includes/courseStructureHelpers.php");

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

                                                                renderCourseStructureTable($filters, $pageno, $result_per_page, $sort_subject, $sort_date, 2);
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ======================= COMMON ASSIGNMENT DETAILS ======================= -->
                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Assignment Code</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="code">
                                                            <span class="text-12 text-dark-red" id="code-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-8 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Assignment Topic</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="topic">
                                                            <span class="text-12 text-dark-red" id="topic-feedback"></span>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Start Date & Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="start-date" onchange="calculateAssignmentEndDate(this);">
                                                                        <span class="text-12 text-dark-red" id="start-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="start-time" value="00:00">
                                                                        <span class="text-12 text-dark-red" id="start-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">End Date & Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="end-date" onchange="acceptFutureDate('end-date');">
                                                                        <span class="text-12 text-dark-red" id="end-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="end-time" value="23:59">
                                                                        <span class="text-12 text-dark-red" id="end-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Assigment Document</label>
                                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input"
                                                                    accept=".pdf,.doc,.docx,.zip,.rar,.ppt,.pptx">
                                                                <div class="col-6 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="text-12 text-dark-red" id="file-feedback"></span>
                                                        </div>

                                                        <div class="col-12 col-lg-4 d-flex flex-column" id="recorection-fee-add-area">
                                                            <label for="" class="text-14 main-text required">Plagiarism & AI Report</label>
                                                            <select name="" class="form-select custom-select mt-2" disabled id="plagiarism-report">
                                                                <?php
                                                                $plagiarismStatus = Database::search("SELECT * FROM `plagiarism_status`");
                                                                while ($data = $plagiarismStatus->fetch_assoc()) {
                                                                ?>
                                                                    <option <?= $data['plagiarism_status_id'] == 2 ? 'selected' : '' ?> value="<?= $data['plagiarism_status_id'] ?>"><?= $data['plagiarism_status'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="recorrection-fee-feedback"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Late Submission End Date Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="late-submision-date" onchange="acceptFutureDate('late-submision-date');">
                                                                        <span class="text-12 text-dark-red" id="late-submision-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="late-submision-time">
                                                                        <span class="text-12 text-dark-red" id="late-submision-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 sub-topic">Assignment Recorrection</label>
                                                            <div class="col-12 mt-3">
                                                                <div class="row gx-3">
                                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                                        <label for="" class="text-14 main-text mb-2">Recorrection Fee</label>
                                                                        <input type="number" class="col-6 form-control custom-input" id="recorrection-fee">
                                                                        <span class="text-12 text-dark-red" id="recorrection-fee-feedback"></span>
                                                                    </div>
                                                                    <div class="col-12 col-lg-6 d-flex flex-column">
                                                                        <label for="" class="text-14 main-text">Recorrection Apply End Date & Time</label>
                                                                        <div class="col-12 mt-2">
                                                                            <div class="row gx-3">
                                                                                <div class="col-6 d-flex flex-column">
                                                                                    <input type="date" class="col-6 form-control custom-input" id="recorrection-end-date" onchange="acceptFutureDate('late-submision-date');">
                                                                                    <span class="text-12 text-dark-red" id="recorrection-end-date-feedback"></span>
                                                                                </div>
                                                                                <div class="col-6 d-flex flex-column">
                                                                                    <input type="time" class="col-6 form-control custom-input" id="recorrection-end-time">
                                                                                    <span class="text-12 text-dark-red" id="recorrection-end-time-feedback"></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="w-100 custom-btn" id="save-single-assignment" onclick="addMainAssignment(this);">Save Assgnment</button>
                                                                    <button class="w-100 custom-btn d-none" id="save-multiple-assignments" onclick="addMultipleAssignments(this);">Save Assignments</button>
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

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>

                <script>
                    // ===================== Single / Multiple branch toggle =====================
                    function switchAssignmentType(type) {
                        var singleBtn = document.getElementById('btn-single-assignment');
                        var multipleBtn = document.getElementById('btn-multiple-assignment');
                        var singleSec = document.getElementById('assignment-single-branch');
                        var multipleSec = document.getElementById('assignment-multiple-branch');
                        var singleSaveBtn = document.getElementById('save-single-assignment');
                        var multipleSaveBtn = document.getElementById('save-multiple-assignments');
                        var radioSingle = document.getElementById('radio-single-assignment');
                        var radioMultiple = document.getElementById('radio-multiple-assignment');
                        var iconMultiple = document.getElementById('icon-multiple-assignment');

                        if (type === 'single') {
                            singleBtn.classList.add('active');
                            multipleBtn.classList.remove('active');
                            singleSec.classList.remove('d-none');
                            multipleSec.classList.add('d-none');
                            singleSaveBtn.classList.remove('d-none');
                            multipleSaveBtn.classList.add('d-none');
                            if (iconMultiple) iconMultiple.style.color = 'var(--sub-text)';
                            radioSingle.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
                            radioMultiple.innerHTML = '';
                        } else {
                            multipleBtn.classList.add('active');
                            singleBtn.classList.remove('active');
                            multipleSec.classList.remove('d-none');
                            singleSec.classList.add('d-none');
                            singleSaveBtn.classList.add('d-none');
                            multipleSaveBtn.classList.remove('d-none');
                            if (iconMultiple) iconMultiple.style.color = '#fff';
                            radioMultiple.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
                            radioSingle.innerHTML = '';
                        }
                    }

                    // ===================== Multiple branch: course structure table =====================
                    let selectedAssignmentCoursePage = 1;
                    let assignmentCourseSortSubject = 0; // 0 = none, 1 = ASC, 2 = DESC
                    let assignmentCourseSortDate = 0;

                    const toggleAssignmentCourseSort = (current) => {
                        if (current === 0) return 1;
                        if (current === 1) return 2;
                        return 0;
                    };

                    const handleCoursePagination = (action, pageId = null, totalPages = null) => {
                        switch (action) {
                            case 'goToPage':
                                selectedAssignmentCoursePage = parseInt(pageId, 10);
                                break;
                            case 'previousPage':
                                if (selectedAssignmentCoursePage > 1) selectedAssignmentCoursePage--;
                                break;
                            case 'nextPage':
                                if (selectedAssignmentCoursePage < parseInt(totalPages, 10)) selectedAssignmentCoursePage++;
                                break;
                            case 'filter':
                                selectedAssignmentCoursePage = 1;
                                break;
                            case 'sortBySubject':
                                assignmentCourseSortDate = 0;
                                assignmentCourseSortSubject = toggleAssignmentCourseSort(assignmentCourseSortSubject);
                                break;
                            case 'sortByDate':
                                assignmentCourseSortSubject = 0;
                                assignmentCourseSortDate = toggleAssignmentCourseSort(assignmentCourseSortDate);
                                break;
                        }

                        filterCourseStructure();
                    };

                    const clearAssignmentCourseFilters = () => {
                        $("#am-subject-search-text").val("");
                        $("#am-level-selector").val("0");
                        $("#am-intake-selector").val("0");
                        $("#am-programme-selector").val("0");
                        $("#am-faculty-selector").val("0");
                        $("#am-branch-selector").val("0");
                        handleCoursePagination('filter');
                    };

                    const populateAssignmentSelector = (selectorId, placeholderLabel, options) => {
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

                    const populateAllAssignmentSelectors = (options) => {
                        populateAssignmentSelector("#am-level-selector", "Level", options.level);
                        populateAssignmentSelector("#am-intake-selector", "Intake", options.intake);
                        populateAssignmentSelector("#am-programme-selector", "Programme", options.programme);
                        populateAssignmentSelector("#am-faculty-selector", "Faculty", options.faculty);
                        populateAssignmentSelector("#am-branch-selector", "Branch", options.branch);
                    };

                    const filterCourseStructure = () => {
                        const data = {
                            text: $("#am-subject-search-text").val() || "",
                            level: $("#am-level-selector").val() || "0",
                            intake: $("#am-intake-selector").val() || "0",
                            programme: $("#am-programme-selector").val() || "0",
                            faculty: $("#am-faculty-selector").val() || "0",
                            branch: $("#am-branch-selector").val() || "0",
                            sortSubject: assignmentCourseSortSubject,
                            sortDate: assignmentCourseSortDate,
                            resultPerPage: $("#am-show-result-selector").val() || 10,
                            page: selectedAssignmentCoursePage || 1,
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
                                    $("#am-course-structure-table-container").html(result.table);
                                    populateAllAssignmentSelectors(result.options);
                                    restoreAssignmentSubjectCheckboxes();
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
                        populateAllAssignmentSelectors(<?php echo getCourseStructureOptionsJson($filters); ?>);
                    });

                    // ===================== Subject checkbox selection (persists across pagination/filtering) =====================
                    const checkedAssignmentSubjectIds = new Set();

                    // Fired by the header "check all" checkbox rendered by courseStructureHelpers.php
                    // (id="check-all", onchange="assignmnetTableCheckEvents()")
                    const assignmnetTableCheckEvents = () => {
                        const checkAll = document.getElementById('check-all');
                        $(".row-check").each(function() {
                            this.checked = checkAll.checked;
                            const subjectId = parseInt(this.id.replace('check-user', ''), 10);
                            if (checkAll.checked) {
                                checkedAssignmentSubjectIds.add(subjectId);
                            } else {
                                checkedAssignmentSubjectIds.delete(subjectId);
                            }
                        });
                    };

                    // Fired by each row checkbox rendered by courseStructureHelpers.php
                    // (id="check-user<subject_id>", onchange="assignmnetCheckRowEvents(<subject_id>);")
                    const assignmentCheckRowEvents = (subjectId) => {
                        const checkbox = document.getElementById('check-user' + subjectId);
                        if (checkbox.checked) {
                            checkedAssignmentSubjectIds.add(subjectId);
                        } else {
                            checkedAssignmentSubjectIds.delete(subjectId);
                        }
                    };

                    // Re-check boxes for subject_ids already selected after the table re-renders
                    // (pagination/filter/sort), so cross-page selections stay visible.
                    const restoreAssignmentSubjectCheckboxes = () => {
                        $(".row-check").each(function() {
                            const subjectId = parseInt(this.id.replace('check-user', ''), 10);
                            this.checked = checkedAssignmentSubjectIds.has(subjectId);
                        });
                        const checkAllBox = document.getElementById('check-all');
                        if (checkAllBox) {
                            const rowBoxes = $(".row-check");
                            checkAllBox.checked = rowBoxes.length > 0 && rowBoxes.filter(':checked').length === rowBoxes.length;
                        }
                    };

                    // ===================== Multiple branch: submit =====================
                    const addMultipleAssignments = (btn) => {
                        const subjectIds = Array.from(checkedAssignmentSubjectIds);

                        if (subjectIds.length === 0) {
                            showToast("error", "Please select at least one subject from the table.");
                            return;
                        }

                        toggleBtnLoader(btn, true);

                        const form = new FormData();
                        subjectIds.forEach((id) => form.append("subject_ids[]", id));
                        form.append("code", $("#code").val());
                        form.append("topic", $("#topic").val());
                        form.append("start_date", $("#start-date").val());
                        form.append("start_time", $("#start-time").val());
                        form.append("end_date", $("#end-date").val());
                        form.append("end_time", $("#end-time").val());
                        form.append("plagiarism_report", $("#plagiarism-report").val());
                        form.append("late_date", $("#late-submision-date").val());
                        form.append("late_time", $("#late-submision-time").val());
                        form.append("recorrection_date", $("#recorrection-end-date").val());
                        form.append("recorrection_time", $("#recorrection-end-time").val());
                        form.append("recorrection_fee", $("#recorrection-fee").val());

                        var files = $("#file-selector-input")[0].files;
                        for (var i = 0; i < files.length; i++) {
                            form.append("file", files[i]);
                        }

                        const validationMap = {
                            subjects: ["#am-course-structure-table-container", "#subjects-feedback"],
                            code: ["#code", "#code-feedback"],
                            topic: ["#topic", "#topic-feedback"],
                            start_date: ["#start-date", "#start-date-feedback"],
                            start_time: ["#start-time", "#start-time-feedback"],
                            end_date: ["#end-date", "#end-date-feedback"],
                            end_time: ["#end-time", "#end-time-feedback"],
                            file: ["#file-selector-input", "#file-feedback"],
                        };

                        // NOTE: api/addMultipleAssignments.php needs to be created server-side —
                        // it should mirror api/addMainAssignment.php but loop subject_ids[] and
                        // create one assignment row per subject with the shared fields above.
                        fetch("api/addMultipleAssignments.php", {
                                method: "POST",
                                body: form
                            })
                            .then((res) => res.json())
                            .then((result) => {
                                toggleBtnLoader(btn, false);
                                if (typeof result === "object" && result.error) {
                                    showToast("error", result.error);
                                } else if (typeof result === "object" && result.done) {
                                    showToast("success", result.done);
                                    setTimeout(() => {
                                        window.history.back();
                                    }, 3000);
                                } else if (typeof result === "object") {
                                    applyValidation(result, validationMap);
                                } else {
                                    showToast("error", "Unexpected response from server.");
                                }
                            })
                            .catch((error) => {
                                console.log(error);
                                toggleBtnLoader(btn, false);
                                showToast("error", "Something went wrong. Please try again later");
                            });
                    };
                </script>
            </body>

            </html>
<?php
        }
    }
}
?>