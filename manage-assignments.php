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
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsLecturer($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('assignments'))) {
            header("Location: ./dashboard");
        } else {

            // ---------------------------------------------------------------
            // LECTURER MODE: lecturer wa log unama eyage subjects walata adala
            // assignments witharai pennanne
            // ---------------------------------------------------------------
            $isLecturer = SessionUser::ifUserIsLecturer($sessionUserRole);
            // NOTE: session object eke user id key eka wenas nam methanin wenas karanna
            $lecturerUserId = $isLecturer ? (int)($sessionUserObject['user_id'] ?? $sessionUserObject['users_user_id'] ?? 0) : 0;

            $lecturerSelect = "";
            $lecturerWhere  = "";
            $countWhere     = "";
            $markingFilter  = $_GET['marking'] ?? '0';

            if ($isLecturer) {
                // me lecturer ta assign wela thiyena marking rows
                $baseSub = "SELECT COUNT(*) FROM `lecturers_has_students_has_assignments` lsa
                    INNER JOIN `students_has_assignments` sha ON sha.`students_has_assignments_id` = lsa.`students_has_assignments_students_has_assignments_id`
                    INNER JOIN `lecturers` l ON l.`lecturers_id` = lsa.`lecturers_lecturers_id`
                    WHERE sha.`assignments_assignment_id` = `assignments`.`assignment_id`
                    AND l.`users_user_id` = {$lecturerUserId}";

                $assignedSub = "(" . $baseSub . ")";
                $markedSub   = "(" . $baseSub . " AND lsa.`marks` IS NOT NULL)";
                $pendingSub  = "(" . $baseSub . " AND lsa.`marks` IS NULL)";

                $lecturerSelect = ", {$assignedSub} AS `assigned_count`, {$markedSub} AS `marked_count`";

                // eya assign wela inna subjects walata witharai
                $lecturerWhere = " WHERE `subjects`.`subject_id` IN (SELECT `subjects_subject_id` FROM `lecturers` WHERE `users_user_id` = {$lecturerUserId}) ";

                if ($markingFilter === '1') {            // Pending marking
                    $lecturerWhere .= " AND {$pendingSub} > 0 ";
                } elseif ($markingFilter === '2') {      // Marking completed
                    $lecturerWhere .= " AND {$assignedSub} > 0 AND {$pendingSub} = 0 ";
                }

                $countWhere = " WHERE `subjects_subject_id` IN (SELECT `subjects_subject_id` FROM `lecturers` WHERE `users_user_id` = {$lecturerUserId})";
            }
?>

            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Subject Assignments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-lg-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="assignments">Assignments</span>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="">Organize, track, and manage student assignments with ease and accuracy.</span>

                                                    </div>
                                                </div>
                                                <?php if (!$isLecturer) { ?>
                                                    <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                        <div class="row align-items-center">
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" data-i18n="add-new" onclick="window.location = 'add-assignment'">Add Assignment</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-end">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $subjectHasAssi = Database::search("SELECT COUNT(*) as count FROM `assignments`" . $countWhere);
                                                                $subjectHasAssiCount = $subjectHasAssi->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?= $subjectHasAssiCount['count'] ?> <span data-i18n="assignments"></span></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleAssignmentsPagination('filter')">
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#usersFilter" aria-expanded="false" aria-controls="usersFilter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 collapse" id="usersFilter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="row gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="branch-selector" class="form-select custom-select" onchange="loadFaculties(),handleAssignmentsPagination('filter');">
                                                                            <option value="0" selected>- Select Branch -</option>
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
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="faculty-selector" disabled class="form-select custom-select" onchange="loadProgrammes(),handleAssignmentsPagination('filter');">
                                                                            <option value="0" selected>- Select Faculty -</option>
                                                                        </select>
                                                                        <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="programme-selector" disabled class="form-select custom-select" onchange="loadIntakes(),handleAssignmentsPagination('filter');">
                                                                            <option value="0" selected>- Select Programme -</option>
                                                                        </select>
                                                                        <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="intake-selector" disabled class="form-select custom-select" onchange="handleAssignmentsPagination('filter');">
                                                                            <option value="0" selected>- Select Intake -</option>
                                                                        </select>
                                                                        <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleAssignmentsPagination('filter')">
                                                                            <option value="0">- Filter -</option>
                                                                            <option value="1">Not-Scheduled</option>
                                                                            <option value="2">Scheduled</option>
                                                                            <option value="3">Ongoing</option>
                                                                            <option value="4">Completed</option>
                                                                        </select>
                                                                    </div>
                                                                    <?php if ($isLecturer) { ?>
                                                                        <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="marking-selector" onchange="handleAssignmentsPagination('filter')">
                                                                                <option value="0" <?= $markingFilter === '0' ? 'selected' : '' ?>>- Marking -</option>
                                                                                <option value="1" <?= $markingFilter === '1' ? 'selected' : '' ?>>Pending Marking</option>
                                                                                <option value="2" <?= $markingFilter === '2' ? 'selected' : '' ?>>Marking Completed</option>
                                                                            </select>
                                                                        </div>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleAssignmentsPagination('filter')">
                                                                <option value="10" selected>10</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="200">200</option>
                                                                <option value="300">300</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="assignments-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="assignments-table">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                            <span class="text-14 main-text fw-medium mx-2">Branch</span>
                                                                        </div>
                                                                    </th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Intake</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Assignment Code</th>

                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Start Date</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">End Date</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Document</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">
                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer" onclick="handleAssignmentsPagination('sortByDate')">
                                                                            <span class="text-14 main-text fw-medium me-2">Date Added</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>
                                                                    <?php if ($isLecturer) { ?>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Marking Progress</th>
                                                                    <?php } ?>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $query = "SELECT `assignments`.*,`assignment_status`.*,`intakes`.`intake`,`programmes`.`programme`,`faculties`.`faculty`,`branches`.`branch` {$lecturerSelect} FROM `assignments`
                                                                INNER JOIN `assignment_status` ON `assignments`.`assignment_status_assignment_status_id` = `assignment_status`.`assignment_status_id`
                                                                INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                INNER JOIN `programmes`  ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                                INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                                                INNER JOIN `branches` ON `faculties`.`branches_branch_id` = `branches`.`branch_id`
                                                                {$lecturerWhere}
                                                                ORDER BY `assignments`.`added_date` DESC";

                                                                $results = Database::search($query);
                                                                $pageno = 1;
                                                                $results_count = $results->num_rows;
                                                                $result_per_page = 10;  // Consistent variable name

                                                                if ($results_count > $result_per_page) {
                                                                    $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                } else {
                                                                    $number_of_pages = 1;
                                                                }

                                                                $page_results = ($pageno - 1) * $result_per_page;
                                                                $assignments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                if ($assignments->num_rows > 0) {
                                                                    while ($data = $assignments->fetch_assoc()) {
                                                                ?>
                                                                        <tr>
                                                                            <td scope="row" class="px-4 py-4 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                                    <div class="d-flex flex-column">
                                                                                        <span class="text-14 main-text mx-2"><?= $data["branch"] ?></span>
                                                                                        <span class="text-12 sub-text mx-2"><?= $data["faculty"] ?>/<?= $data["programme"] ?></span>
                                                                                    </div>

                                                                                </div>
                                                                            </td>
                                                                            <td class="py-4 align-middle"><span class="text-14 main-text"><?= $data["intake"] ?></span></td>
                                                                            <td class="py-4 align-middle"><span class="text-14 main-text"><?= $data["assignment_code"] ?></span></td>
                                                                            <?php

                                                                            $startDateTime = new DateTime($data["start_date"]);
                                                                            $endDateTime = new DateTime($data["end_date"]);

                                                                            $interval = $startDateTime->diff($endDateTime);

                                                                            $days = $interval->days;
                                                                            $weeks = floor($interval->days / 7);

                                                                            ?>
                                                                            <td class="py-4 align-middle"><span class="text-14 main-text"><?= !empty($data["start_date"]) ? date("Y-m-d h:i A", strtotime($data["start_date"])) : " N/A " ?>
                                                                                </span></td>
                                                                            <td class="py-4 align-middle"><span class="text-14 main-text"><?= $data["end_date"] != '' ? date("Y-m-d h:i A", strtotime($data["end_date"])) : " N/A " ?></span></td>
                                                                            <td class="py-4 align-middle"><span class="text-14 main-text"><?= is_int($weeks) ? $weeks . " Weeks" : ($days == 0 ? " - - " : $days . " Days") ?></span></td>
                                                                            <td class="py-4 align-middle">
                                                                                <?php
                                                                                if (!empty($data["attachment"]) && file_exists("./assets/uploads/assignments-attachments/" . $data["attachment"])) {
                                                                                ?>
                                                                                    <button class="table-btn table-btn-pink active" onclick="viewAssignmentsAttachement('<?= $data['attachment']; ?>');">View</button>
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <span class="text-14 main-text">No file attached</span>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td class="py-4 align-middle"><span class="text-14 main-text"><?= $data["added_date"] != '' ? date("Y-m-d h:i A", strtotime($data["added_date"])) : " N/A " ?></span></td>

                                                                            <?php if ($isLecturer) {
                                                                                $assigned = (int)$data['assigned_count'];
                                                                                $marked   = (int)$data['marked_count'];
                                                                                $pending  = $assigned - $marked;
                                                                                $percent  = $assigned > 0 ? round(($marked / $assigned) * 100) : 0;
                                                                            ?>
                                                                                <td class="py-4 align-middle">
                                                                                    <div class="d-flex flex-column" style="min-width:140px;">
                                                                                        <span class="text-14 main-text"><?= $marked ?> / <?= $assigned ?> Marked</span>
                                                                                        <div class="progress mt-1" style="height:5px;">
                                                                                            <div class="progress-bar" style="width:<?= $percent ?>%"></div>
                                                                                        </div>
                                                                                        <span class="text-12 sub-text mt-1"><?= $pending ?> Pending</span>
                                                                                    </div>
                                                                                </td>
                                                                            <?php } ?>

                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "1"  => "table-btn-yellow",
                                                                                    "2"  => "table-btn-primary",
                                                                                    "3"  => "table-btn-danger",
                                                                                    "4"  => "table-btn-orange",
                                                                                    "5"  => "table-btn-purple",
                                                                                    "6"  => "table-btn-success",
                                                                                    "7"  => "table-btn-pink"

                                                                                ];
                                                                                $status = "Not Scheduled";
                                                                                $statusId = '1';
                                                                                if ($data["assignment_status_assignment_status_id"] == 2) {
                                                                                    $timezone = new DateTimeZone("Asia/Colombo");
                                                                                    $today = new DateTime("now", $timezone);
                                                                                    $todayDate = $today->format('Y-m-d H:i:s'); // Changed to 24-hour format

                                                                                    $status = "Scheduled";
                                                                                    $statusId = '2';

                                                                                    // Create DateTime objects for comparison
                                                                                    $startDateObj = new DateTime($data["start_date"], $timezone);
                                                                                    $endDateObj = new DateTime($data["end_date"], $timezone);

                                                                                    // Compare DateTime objects directly (more reliable)
                                                                                    if ($today >= $startDateObj && $today <= $endDateObj) {
                                                                                        $status = "Ongoing";
                                                                                        $statusId = '6';
                                                                                    } else if ($today > $endDateObj) {
                                                                                        $status = "Pending Marking";
                                                                                        $statusId = '7';
                                                                                    }
                                                                                } else {
                                                                                    $status = $data["assignment_status"];
                                                                                    $statusId = $data["assignment_status_assignment_status_id"];
                                                                                }
                                                                                $sbutton_class = $status_classes[$statusId];
                                                                                ?>
                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $status; ?></button>
                                                                            </td>


                                                                            <td class="py-3 align-middle">
                                                                                <div class="dropdown br-0">
                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                    <ul class="dropdown-menu scrolable border-0 shadow w-100 m-2 br-5" aria-labelledby="dropdownMenuButton">
                                                                                        <?php
                                                                                        // Lecturer -> marking panel, others -> performance page
                                                                                        $viewUrl = $isLecturer
                                                                                            ? "lecturer-assignment-marking-panel?assignment-id=" . $data['assignment_id']
                                                                                            : "assignment-students-performance.php?assignment-id=" . $data['assignment_id'];
                                                                                        ?>
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="<?= $viewUrl ?>">
                                                                                                <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                View</a></li>

                                                                                        <?php if (!$isLecturer) { ?>
                                                                                            <hr class="mt-1 mb-0 me-2 mx-2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-assignment.php?assignment-id=<?= $data['assignment_id']; ?>">
                                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Edit</a></li>
                                                                                            <hr class="mt-1 mb-0 me-2 mx-2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="cancelAssignment(<?= $data['assignment_id'] ?>)">
                                                                                                    <iconify-icon icon="lets-icons:cancel" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Cancel</a></li>

                                                                                            <?php
                                                                                            $studentsSubmit =  Database::search("SELECT COUNT(`students_has_assignments_id`) AS 'count' FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $data['assignment_id'] . "'")->fetch_assoc()['count'];
                                                                                            if ($studentsSubmit == 0) {
                                                                                            ?>
                                                                                                <hr class="mt-1 mb-0 me-2 mx-2">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="deleteAssignment(<?= $data['assignment_id'] ?>)">
                                                                                                        <iconify-icon icon="material-symbols:delete-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        Remove</a></li>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        <?php } ?>

                                                                                    </ul>
                                                                                </div>
                                                                            </td>

                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="13" class="align-middle justify-content-center">
                                                                            <div class="d-flex flex-column align-items-center">
                                                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }

                                                                ?>
                                                            </tbody>
                                                        </table>
                                                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                <div class="row p-4 align-items-center">
                                                                    <!-- Previous Button (col-1) -->
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleAssignmentsPagination('previousPage')">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                    </button>
                                                                    <!-- Page Numbers (col-10, centered) -->
                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                        <ul class="pagination-list d-flex mb-0">
                                                                            <?php
                                                                            $shudderPages1 = false;
                                                                            $shudderPages2 = false;

                                                                            function renderPageButton($i, $isActive = false)
                                                                            {
                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                $action = $isActive ? '' : "onclick=\"handleAssignmentsPagination('goToPage', '{$i}')\"";
                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                            }

                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                    renderPageButton($i, $pageno == $i);
                                                                                } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                    $shudderPages1 = true;
                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                    $shudderPages2 = true;
                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </ul>
                                                                    </div>
                                                                    <!-- Next Button (col-1) -->
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleAssignmentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </nav>
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
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>
            </body>

            </html>
<?php
        }
    }
}
?>