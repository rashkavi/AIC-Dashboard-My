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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['assignment-id']) && !empty($_GET['assignment-id']))) {
            header("Location: ./selected-subject");
        } else {

            $id = $_GET['assignment-id'];
            $assignment = Database::search("SELECT * FROM `assignments`
             INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
             WHERE `assignment_id` = '" . $id . "'");

            if ($assignment->num_rows == 0) {
                header("Location: ./subject-assignments");
            } else {
                $assignmentData = $assignment->fetch_assoc();
                $hasRecorrectionFee = Database::search("SELECT * FROM `assignment_recorrection_fee` WHERE `assignments_assignment_id` = '" . $id . "'");

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $validLecturer = false;

                $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $assignmentData['subjects_subject_id'] . "'");
                if ($lecturerSubject->num_rows > 0) {
                    $validLecturer = true;
                }

                $allowedPermissions = [15, 16, 17, 18, 19, 20];
                $haveAnyPermission = false;

                foreach ($allowedPermissions as $permission) {
                    if (UserPermissions::hasPermission('assignments', $permission, $assignmentData['subjects_subject_id'])) {
                        $haveAnyPermission = true;
                    }
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                    header("Location: ./subjects");
                } else {

                    $query = "
                    SELECT 
                        lhsaa.lecturers_lecturers_id AS lecturers_id,
                        
                        COUNT(*) AS total_count,
                        COUNT(CASE WHEN lhsaa.submission_sts_submission_sts_id = 1 THEN 1 END) AS pending_count,
                        COUNT(CASE WHEN lhsaa.submission_sts_submission_sts_id = 2 THEN 1 END) AS marked_count,
                        user_fname,user_lname,title,profile_img,per_assignment_fee
                    FROM 
                        lecturers_has_students_has_assignments AS lhsaa
                    INNER JOIN 
                        lecturers ON lhsaa.lecturers_lecturers_id = lecturers.lecturers_id
                    INNER JOIN 
                        users ON lecturers.users_user_id = users.user_id
                    INNER JOIN 
                        title ON users.title_title_id = title.title_id
                    INNER JOIN 
                        students_has_assignments AS sha ON lhsaa.students_has_assignments_students_has_assignments_id = sha.students_has_assignments_id
                    WHERE 
                        sha.assignments_assignment_id = '$id'
                    GROUP BY 
                        lhsaa.lecturers_lecturers_id, user_fname, user_lname, title, profile_img, per_assignment_fee
                    ";

                    $results = Database::search($query);

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Lecturers Assignment Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
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
                                <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                                    <div class="row p-0 m-0 g-0 h-100">
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

                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0" id="accordionParent">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Assignment Payments - #<?= $assignmentData['assignment_code'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $assignmentData['faculty_id'] ?>"><?= $assignmentData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $assignmentData['programme_id'] ?>"><?= $assignmentData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $assignmentData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-assignments.php?subject-id=<?= $assignmentData['subject_id'] ?>">Assignments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="">Students Payments</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">

                                                                        <span class="text-14 dark-theme fw-medium"><?php echo $results->num_rows; ?> <?= $results->num_rows == 1 ? "Lecturer" : "Lecturers" ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="user-search-text" onkeyup="handleUsersPagination('filter')">
                                                                    </div>
                                                                </div>

                                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button">Search</button>
                                                                </div>

                                                            </div>
                                                        </div>

                                                        <div class="col-12">
                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                <div class="overflow-x-auto p-0 m-0 g-0" id="users-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Lecturer</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assign Count</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Marked Count</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Pending Count</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                                <th scope="col" class="py-3 bg-table-header">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <span class="text-14 main-text fw-medium">Status</span>
                                                                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php

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
                                                                                    $amount = $data["marked_count"] * $data["per_assignment_fee"];
                                                                            ?>
                                                                                    <tr>
                                                                                        <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                                    <?php
                                                                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                                    ?>
                                                                                                        <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                    <?php
                                                                                                    } else {
                                                                                                        $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                        $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                    ?>
                                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["total_count"]; ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["marked_count"]; ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["pending_count"]; ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $amount; ?></span></td>
                                                                                        <td class="py-2 align-middle">
                                                                                            <?php
                                                                                            $status_classes = [
                                                                                                "Pending"  => "table-btn-yellow",
                                                                                                "Scheduled"     => "table-btn-pink",
                                                                                                "Completed"     => "table-btn-success",
                                                                                                "Rejected"  => "table-btn-danger",
                                                                                            ];
                                                                                            $status = "Pending";
                                                                                            $sbutton_class = $status_classes[$status];
                                                                                            $lectureAssignmentPayment = Database::search("SELECT * FROM `assignment_payments` 
                                                                                            WHERE `lecturers_lecturers_id` = '" . $data["lecturers_id"] . "' 
                                                                                            AND `assignments_assignment_id` = '" . $id . "' ORDER BY `assignment_payments_id` DESC LIMIT 1");
                                                                                            if ($lectureAssignmentPayment->num_rows > 0) {
                                                                                                $payment = $lectureAssignmentPayment->fetch_assoc();

                                                                                                if ($payment["earning_status_earning_status_id"] == 1) {
                                                                                                    $sbutton_class = $status_classes['Scheduled'];
                                                                                                    $status = "Scheduled";
                                                                                                } else if ($payment["earning_status_earning_status_id"] == 2) {
                                                                                                    $sbutton_class = $status_classes['Completed'];
                                                                                                    $status = "Completed";
                                                                                                } else if ($payment["earning_status_earning_status_id"] == 3) {
                                                                                                    $sbutton_class = $status_classes['Rejected'];
                                                                                                    $status = "Rejected";
                                                                                                }
                                                                                            }

                                                                                            ?>
                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $status; ?></button>
                                                                                        </td>
                                                                                        <td class="py-3 align-middle">
                                                                                            <?php
                                                                                            if ($data["total_count"] == $data["marked_count"]) {
                                                                                                if ($lectureAssignmentPayment->num_rows > 0) {
                                                                                            ?>
                                                                                                    <button class="table-btn table-btn-dark-theme active w-75" onclick="window.location.href='lecturer-assignment-payment?payment-id=<?php echo $payment['assignment_payments_id']; ?>'">View</button>
                                                                                                <?php
                                                                                                } else {
                                                                                                ?>
                                                                                                    <button class="table-btn table-btn-success active w-75" onclick="addLecturerAssignmentPayment('<?php echo $data['lecturers_id']; ?>', '<?php echo $id; ?>')">Confirm Payment</button>
                                                                                            <?php
                                                                                                }
                                                                                            }
                                                                                            ?>

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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleUsersPagination('previousPage')">
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
                                                                                            $action = $isActive ? '' : "onclick=\"handleUsersPagination('goToPage', '{$i}')\"";
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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleUsersPagination('nextPage',null,'<?= $number_of_pages ?>')">
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

                        <?php
                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 20, $assignmentData['subjects_subject_id'])) {
                        ?>
                            <!-- Student Mark Bulk Upload Model => Start -->
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
                                                                <button class="custom-btn d-flex align-items-center justify-content-center bg-light-theme mx-2" onclick="downloadMarksUploadCSV('<?= $assignmentData['assignment_code']; ?>')">
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
                                            <button class="col-8 col-md-4 custom-btn m-0" id="markBulkUploadBtn">Upload</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Student Mark Bulk Upload Model => End -->
                        <?php
                        }

                        ?>

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