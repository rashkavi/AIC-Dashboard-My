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
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./users");
        } else {

            $id = $_GET['student-id'];

            $path = 0;
            if (isset($_GET['path']) && !empty($_GET['path'])) {
                $path = $_GET['path'];
            }

            $studentHasIntake = Database::search("SELECT
                `faculty`,
                `faculty_id`,
                `programme`,
                `programme_id`,
                `intake`,
                `intake_id`,
                `students_has_intakes`.`student_id`,
                `students`.`student_id` AS `admissionId`,
                `first_name`,
                `last_name`,
                `title`,
                `students_has_intakes_id`
            FROM `students_has_intakes`
            INNER JOIN `students` 
                ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `login_details`
                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`    
            INNER JOIN `title` 
                ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `intakes` 
                ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
              
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'
        ");


            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./students");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('course_overview'))) {
                    header("Location: ./faculties");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Batch Student Assignments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    $path == 0 ? load_side_menu::load("Course Overview") : load_side_menu::load("Students");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            $path == 0 ? selectItem::setSelected("Course Overview") : selectItem::setSelected("Students");
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
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Student Assignments</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <?php
                                                                        if ($path == 0) {
                                                                        ?>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $studentHasIntakeData['faculty_id'] ?>'>...</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $studentHasIntakeData['programme_id'] ?>'><?= $studentHasIntakeData['programme']; ?></a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='batch-details?batch-id=<?= $studentHasIntakeData['intake_id'] ?>'><?= $studentHasIntakeData['intake']; ?></a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo $studentHasIntakeData['title'] . "." . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name']; ?></a></li>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./students">Students</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Student Details</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo $studentHasIntakeData['title'] . "." . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name']; ?> (<?= $studentHasIntakeData['programme']; ?> / <?= $studentHasIntakeData['intake']; ?>)</a></li>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 component-theme">
                                                    <div class="row p-4 m-0 g-0 align-content-center">
                                                        <div class="overflow-x-auto bg-table-header p-0 m-0 g-0" id="students-assignments-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                <div class="d-flex justify-content-between align-items-center w-100">
                                                                                    <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                </div>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">
                                                                            <div class="d-flex justify-content-between align-items-center cursor-pointer"">
                                                                                <span class="text-14 main-text fw-medium me-2">Uploaded At</span>
                                                                            </div>
                                                                        </th>

                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assigned Lecturer</th>
                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>
                                                                        <th scope="col-2" class="px-4 py-3 bg-table-header">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <span class="text-14 main-text fw-medium">Submission Status</span>
                                                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            </div>
                                                                        </th>

                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php

                                                                    $query = "SELECT
                                                                                                `students_has_assignments_id`,
                                                                                                `title`,
                                                                                                `first_name`,
                                                                                                `last_name`,
                                                                                                `profile_img`,
                                                                                                `students_has_intakes`.`student_id`,
                                                                                                `uploaded_at`,
                                                                                                `upload`,
                                                                                                `submission_id`,
                                                                                                `submission`,
                                                                                                `assignment_marks_status`,
                                                                                                `assignment_marks_status_id`,
                                                                                                `uploaded_at`
                                                                                            FROM `students_has_assignments`
                                                                                            INNER JOIN `students` 
                                                                                                ON `students_has_assignments`.`students_student_id` = `students`.`student_id`
                                                                                            INNER JOIN `login_details`
                                                                                                    ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                                                                                            INNER JOIN `title` 
                                                                                                ON `students`.`title_title_id` = `title`.`title_id`
                                                                                            INNER JOIN `assignments` 
                                                                                                ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
                                                                                            INNER JOIN `subjects` 
                                                                                                ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                                            INNER JOIN `levels` 
                                                                                                ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                                            INNER JOIN `students_has_intakes` 
                                                                                                ON (
                                                                                                    `students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id` 
                                                                                                    AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`
                                                                                                )
                                                                                            INNER JOIN `assignment_submission` 
                                                                                                ON `students_has_assignments`.`submission_submission_id` = `assignment_submission`.`submission_id`
                                                                                            INNER JOIN `assignment_marks_status` 
                                                                                                ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = `assignment_marks_status`.`assignment_marks_status_id`
                                                                                            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'
                                                                                            ORDER BY `students_has_assignments`.`uploaded_at` DESC
                                                                                            ";

                                                                    $results = Database::search($query);
                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = $results_count;  // Consistent variable name

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $assignments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
                                                                    if ($assignments->num_rows > 0) {
                                                                        while ($data = $assignments->fetch_assoc()) {

                                                                            $assignmentAssignLecturer = Database::search("SELECT `title`,`user_fname`,`user_lname`,`profile_img`,`marks` FROM `lecturers_has_students_has_assignments`
                                                                                                INNER JOIN `lecturers` ON `lecturers_has_students_has_assignments`.`lecturers_lecturers_id`=`lecturers`.`lecturers_id`
                                                                                                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` 
                                                                                                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                                WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                                                            $assignmentAssignLecturerData = $assignmentAssignLecturer->fetch_assoc();

                                                                    ?>
                                                                            <tr class="br-t-5">
                                                                                <td scope="row" class="px-4 pt-3 pb-3">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <?php
                                                                                        $checkboxIsEnable = true;
                                                                                        if ($assignmentAssignLecturer->num_rows > 0) {
                                                                                            if ($assignmentAssignLecturerData['marks'] != null) {
                                                                                                $checkboxIsEnable = false;
                                                                                        ?>
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" disabled>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["students_has_assignments_id"]; ?>" onchange="checkRowEvents(<?= $data['students_has_assignments_id']; ?>);">
                                                                                            <?php
                                                                                            }
                                                                                        } else {
                                                                                            ?>
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["students_has_assignments_id"]; ?>" onchange="checkRowEvents(<?= $data['students_has_assignments_id']; ?>);">
                                                                                        <?php
                                                                                        }

                                                                                        if ($checkboxIsEnable) {
                                                                                        ?>
                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?= $data['students_has_assignments_id']; ?>);">
                                                                                                <?php
                                                                                                if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                <?php
                                                                                                } else {
                                                                                                    $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                    $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                ?>
                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                            </div>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                                <?php
                                                                                                if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                <?php
                                                                                                } else {
                                                                                                    $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                    $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                ?>
                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                            </div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["uploaded_at"])) ?></span></td>
                                                                                <?php
                                                                                if ($assignmentAssignLecturer->num_rows > 0) {
                                                                                ?>
                                                                                    <td scope="row" class="px-4 pt-3 pb-3">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <?php
                                                                                            if (!empty($assignmentAssignLecturerData["profile_img"]) && file_exists('./assets/imgs/users/' . $assignmentAssignLecturerData["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?= $assignmentAssignLecturerData["profile_img"]; ?>" class="hwr-40" alt="lecturer image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($assignmentAssignLecturerData["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($assignmentAssignLecturerData["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?= $assignmentAssignLecturerData["title"] . "." . $assignmentAssignLecturerData["user_fname"] . " " . $assignmentAssignLecturerData["user_lname"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <button class="table-btn table-btn-dark-theme" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <?php
                                                                                            if ($assignmentAssignLecturerData['marks'] == null) {
                                                                                            ?>
                                                                                                <button class="table-btn table-btn-purple">Pending</button>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <span class="text-14 main-text"><?= $assignmentAssignLecturerData['marks']; ?></span>
                                                                                            <?php
                                                                                            }
                                                                                            ?>

                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "1"  => "table-btn-yellow",
                                                                                            "2"  => "table-btn-pink",
                                                                                            "3"  => "table-btn-orange",
                                                                                            "4"  => "table-btn-success"
                                                                                        ];
                                                                                        if ($data["assignment_marks_status_id"] == 5) {
                                                                                            $sbutton_class = $status_classes[4];
                                                                                        ?>
                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> Mark Released</button>
                                                                                        <?php
                                                                                        } else {
                                                                                            $sbutton_class = $status_classes[$data["assignment_marks_status_id"]];
                                                                                        ?>
                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["assignment_marks_status"]; ?></button>
                                                                                        <?php
                                                                                        }

                                                                                        ?>

                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "1"  => "table-btn-success",
                                                                                            "2"  => "table-btn-yellow",
                                                                                            "3"  => "table-btn-danger",
                                                                                        ];
                                                                                        $sbutton_class = $status_classes[$data["submission_id"]];
                                                                                        ?>
                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["submission"]; ?> Submission</button>
                                                                                    </td>


                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <td scope="row" class="px-4 pt-3 pb-3 align-middle">
                                                                                        <span class="text-14 main-text">Lecturer Not Assigned</span>
                                                                                    </td>
                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <button class="table-btn table-btn-dark-theme" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                            <button class="table-btn table-btn-purple">Pending</button>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "1"  => "table-btn-yellow",
                                                                                            "2"  => "table-btn-pink",
                                                                                            "3"  => "table-btn-orange",
                                                                                            "4"  => "table-btn-success"
                                                                                        ];
                                                                                        if ($data["assignment_marks_status_id"] == 5) {
                                                                                            $sbutton_class = $status_classes[4];
                                                                                        ?>
                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> Mark Released</button>
                                                                                        <?php
                                                                                        } else {
                                                                                            $sbutton_class = $status_classes[$data["assignment_marks_status_id"]];
                                                                                        ?>
                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["assignment_marks_status"]; ?></button>
                                                                                        <?php
                                                                                        }

                                                                                        ?>

                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "1"  => "table-btn-success",
                                                                                            "2"  => "table-btn-yellow",
                                                                                            "3"  => "table-btn-danger",
                                                                                        ];
                                                                                        $sbutton_class = $status_classes[$data["submission_id"]];
                                                                                        ?>
                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["submission"]; ?> Submission</button>
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