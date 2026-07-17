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
            header("Location: ./subjects");
        } else {

            $id = $_GET['subject-id'];
            $subject = Database::search("SELECT 
                `subject_id`,
                `faculty`,
                `faculty_id`,
                `programme`,
                `programme_id`,
                `intake`,
                `intake_id`,
                `level`,
                `level_id`,
                `subject`
            FROM `subjects`
            INNER JOIN `levels` 
                ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` 
                ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `subject_id` = '" . $id . "'
        ");

            if ($subject->num_rows == 0) {
                header("Location: ./subjects");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                $validLecturer = false;
                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subjectData['subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('subject_students', $id) || $validLecturer)) {
                    header("Location: ./subjects");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Students - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                            include "./includes/components/skeleton-table.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Subject has students</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./programes?faculty_id=<?php echo $subjectData['faculty_id'] ?>'><?php echo ucwords(strtolower($subjectData["programme"])) ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programme_id=<?php echo $subjectData['programme_id'] ?>'><?php echo ucwords(strtolower($subjectData["intake"])) ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./level?level_id=<?php echo $subjectData['level_id'] ?>'><?php echo ucwords(strtolower($subjectData["level"])) ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./subject-details?subject-id=<?php echo $id ?>'><?php echo ucwords(strtolower($subjectData["subject"])) ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Students</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_students', 2, $id)) {
                                                        ?><div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row gx-3 gy-3 gy-md-0">
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn col-12" onclick="location.href='subject-add-students?subject-id=<?= $subjectData['subject_id'] ?>'">Add Student</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>

                                                    </div>
                                                </div>
                                                <div class="col-12 px-4 py-4 component-theme">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $studentCount = Database::search("SELECT COUNT(DISTINCT `students_has_subjects_id`) AS unique_student_count 
                                                            FROM `students_has_subjects` 
                                                            WHERE `subjects_subject_id` = '" . $id . "' AND `status_status_id` = '1'");
                                                                $data = $studentCount->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?php echo $data["unique_student_count"]; ?> Active Students</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-8 col-md-8 col-lg-7 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleSubjectStudentsPagination(<?= $id ?>,'filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                            <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleSubjectStudentsPagination(<?= $id ?>,'filter')">
                                                                <option selected value="0">- Filter Data -</option>
                                                                <option value="1">A-Z</option>
                                                                <option value="2">Z-A</option>
                                                                <option value="3">Active</option>
                                                                <option value="4">Inactive</option>
                                                                <option value="5">Recently Registered</option>
                                                                <option value="6">Early Registered</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button">Search</button>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_students', 4, $id)) {
                                                        ?>
                                                            <div class="col-12 col-md-6 col-lg-2 d-flex flex-column justify-content-end">
                                                                <select class="form-select custom-select" aria-label="Default select example" id="update-selector" onchange="filterSubjectStudents(<?= $id ?>)">
                                                                    <option selected value="0">- Update Students -</option>
                                                                    <option value="1">Active Students</option>
                                                                    <option value="2">Inactive Students</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                <button class="custom-btn w-100" type="button" onclick="applySubjectStudentsUpdate(<?= $id ?>)">Apply</button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                        <div class="col-6 d-none d-lg-block"></div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleSubjectStudentsPagination(<?= $id ?>,'filter')">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 px-4 pb-4 component-theme">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="students-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Email Address</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Mobile</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Admission Date</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-14 main-text fw-medium">Status</span>
                                                                                    <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <?php
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("subject_students", 4, $id)) {
                                                                            ?>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        $query = " SELECT
                                                                       `students_has_subjects`.`students_has_subjects_id` AS shsId,
                                                                       `students`.`student_id`,
                                                                       `login_details`.`first_name`,
                                                                       `login_details`.`last_name`,
                                                                       `login_details`.`email`,
                                                                       `students`.`mobile`,
                                                                       `students`.`profile_img`,
                                                                       `students_has_intakes`.`addmission_date`,
                                                                       `title`.`title`,
                                                                       `status`.`status`
                                                                   FROM `students_has_subjects`
                                                                   INNER JOIN `students` 
                                                                       ON `students_has_subjects`.`students_student_id` = `students`.`student_id`
                                                                    INNER JOIN `login_details`
                                                                       ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                   INNER JOIN `subjects` 
                                                                       ON `students_has_subjects`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                   INNER JOIN `levels` 
                                                                       ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                   INNER JOIN `students_has_intakes` 
                                                                       ON (
                                                                           `students`.`student_id` = `students_has_intakes`.`students_student_id` 
                                                                           AND `levels`.`intakes_intake_id` = `students_has_intakes`.`intakes_intake_id`
                                                                       )
                                                                   INNER JOIN `intakes` 
                                                                       ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                   INNER JOIN `title` 
                                                                       ON `students`.`title_title_id` = `title`.`title_id`
                                                                   INNER JOIN `status` 
                                                                       ON `students_has_subjects`.`status_status_id` = `status`.`status_id`
                                                                   WHERE `students_has_subjects`.`subjects_subject_id` = '" . $id . "'
                                                                   ORDER BY `students_has_subjects`.`students_has_subjects_id` DESC
                                                               ";


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
                                                                        $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                        if ($users->num_rows > 0) {
                                                                            while ($data = $users->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["shsId"]; ?>" onchange="checkRowEvents(<?php echo $data['shsId']; ?>);">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["student_id"]; ?></span></td>
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                                                    <td colspan="3" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["addmission_date"]; ?></span></td>
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "Active"     => "table-btn-success",
                                                                                            "Inactive"  => "table-btn-danger",
                                                                                        ];
                                                                                        $sbutton_class = $status_classes[$data["status"]];
                                                                                        ?>
                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["status"]; ?></button>
                                                                                    </td>
                                                                                    <?php

                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("subject_students", 4, $id)) {
                                                                                    ?>
                                                                                        <td class="px-4 pt-3 pb-3 align-middle">
                                                                                            <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='student-details.php?student-id=<?php echo $data['student_id']; ?>'">View</button>
                                                                                        </td>
                                                                                        <td class="px-4 py-3 align-middle">
                                                                                            <div class="dropdown">
                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-details?student-id=<?php echo $data['student_id']; ?>">
                                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Edit</a></li>
                                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                    <?php
                                                                                                    if ($data["status"] == "Active") {
                                                                                                    ?>
                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateSubjectStudentStatus(<?= $id ?>,2,'<?php echo $data['shsId']; ?>')">
                                                                                                                <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                Deactivate</a></li>
                                                                                                    <?php
                                                                                                    } else {
                                                                                                    ?>
                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateSubjectStudentStatus(<?= $id ?>,1,'<?php echo $data['shsId']; ?>')">
                                                                                                                <iconify-icon icon="dashicons:plus-alt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                Activate</a></li>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </ul>
                                                                                            </div>
                                                                                        </td>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectStudentsPagination(<?= $id ?>,'previousPage');">
                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                                            </button>
                                                                            <!-- Page Numbers (col-10, centered) -->
                                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                <ul class="pagination-list d-flex mb-0">
                                                                                    <?php
                                                                                    $shudderPages1 = false;
                                                                                    $shudderPages2 = false;

                                                                                    function renderPageButton($id, $i, $isActive = false)
                                                                                    {
                                                                                        $activeClass = $isActive ? 'active' : '';
                                                                                        $action = $isActive ? '' : "onclick=\"handleSubjectStudentsPagination('{$id}','goToPage', '{$i}')\"";
                                                                                        echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                                    }

                                                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                            renderPageButton($id, $i, $pageno == $i);
                                                                                        } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                                                            $shudderPages1 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                                                            $shudderPages2 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        }
                                                                                    }
                                                                                    ?>

                                                                                </ul>
                                                                            </div>
                                                                            <!-- Next Button (col-1) -->
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectStudentsPagination(<?= $id ?>,'nextPage',<?= $number_of_pages ?>);">
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
}
?>