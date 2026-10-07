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
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1, $studentIntakeData['intakes_intake_id']) || UserPermissions::hasPermission('intake_students', 10, $studentIntakeData['intakes_intake_id']))) {
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
                                            load_side_menu::load("Students");
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
                                                    selectItem::setSelected("Students");
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
                                                            <div class="col-12 col-md-12 col-xxl-2 mt-3 mt-xxl-0">
                                                                <div class="row gx-3 justify-content-end">
                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('students', 0) || UserPermissions::validPermisisonType('course_overview', 0)) {
                                                                    ?>
                                                                        <div class="col-12 m-md-0 d-flex justify-content-end align-items-center">
                                                                            <div class="dropdown w-100">
                                                                                <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                                    Action
                                                                                </button>
                                                                                <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                                    <li><a class="dropdown-item" href="./intake-student-payments?student-id=<?= $id ?>">Student Payments</a></li>
                                                                                    <li><a class="dropdown-item" href="./intake-student-assignments?student-id=<?= $id ?>">Assignments</a></li>
                                                                                    <!-- <li><a class="dropdown-item" href="./intake-student-examinations?student-id=<?= $id ?>">Examinations</a></li>
                                                                                    <li><a class="dropdown-item" href="./intake-student-quizes?student-id=<?= $id ?>">Quizes</a></li> -->
                                                                                    <li><a class="dropdown-item" href="./intake-student-attendance?student-id=<?= $id ?>">Attendance</a></li>
                                                                                    <!-- <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changedDeactiveDate">Edit Deactivate Date</a></li> -->
                                                                                    <!-- <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#assignedUser">Reassign User</a></li> -->
                                                                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editStudentIntakeId">Edit Registration Number</a></li>
                                                                                    <?php

                                                                                    if ($studentIntakeData['status_status_id'] == 1) {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#deactivateModal">Deactivate from this batch</a></li>
                                                                                    <?php
                                                                                    } else {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item" href="#" onclick="applyIntakeStudentUpdate(this,<?= $studentIntakeData['intake_id'] ?>,1,'<?= $studentIntakeData['students_has_intakes_id'] ?>');">Activate from this batch</a></li>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </ul>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="container p-0 m-0 g-0 mb-4">
                                                        <div class="row g-4 d-flex">
                                                            <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                                <div class="component-theme p-3 w-100">
                                                                    <div class="d-flex flex-row align-items-center">
                                                                        <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="hugeicons:identification" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                        <div class="d-flex flex-column flex-grow-1 px-4">
                                                                            <div class="row no-gutters align-items-center">
                                                                                <div class="col mr-2">
                                                                                    <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                        Reg. Number</div>
                                                                                    <div class="stat-value mb-0 text-gray-800"><?= $studentIntakeData['student_id'] == null ? "N/A" : $studentIntakeData['student_id'] ?></div>
                                                                                </div>

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                                <div class="component-theme p-3 w-100">
                                                                    <div class="d-flex flex-row align-items-center">
                                                                        <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="hugeicons:user-ai" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                        <div class="d-flex flex-column flex-grow-1 px-4">
                                                                            <div class="row no-gutters align-items-center">
                                                                                <div class="col mr-2">
                                                                                    <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                        Assigned User</div>
                                                                                    <div class="stat-value mb-0 text-gray-800">
                                                                                        <?php
                                                                                        if (!empty($studentIntakeData['user_id'])) {
                                                                                            echo $studentIntakeData['title'] . "." . " " . $studentIntakeData['user_fname'] . " " . $studentIntakeData['user_lname'];
                                                                                        } else {
                                                                                            echo "N/A";
                                                                                        }
                                                                                        ?></div>
                                                                                </div>

                                                                            </div>

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                                <div class="component-theme p-3 w-100">
                                                                    <div class="d-flex flex-row align-items-center">
                                                                        <div class="hwr-70 d-flex align-items-center justify-content-center table-btn-success"> <iconify-icon icon="hugeicons:date-time" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                                        <div class="d-flex flex-column flex-grow-1 px-4">
                                                                            <div class="row no-gutters align-items-center">
                                                                                <div class="col mr-2">
                                                                                    <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                        Activate Date</div>
                                                                                    <div class="stat-value mb-0 text-gray-800"><?= date('Y-m-d H:i A', strtotime($studentIntakeData['addmission_date'])) ?></div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                                <div class="component-theme p-3 w-100">
                                                                    <div class="d-flex flex-row align-items-center">
                                                                        <div class="hwr-70 d-flex align-items-center justify-content-center table-btn-danger"> <iconify-icon icon="hugeicons:date-time" width="32" height="32" class="text-dark-red"></iconify-icon></div>
                                                                        <div class="d-flex flex-column flex-grow-1 px-4">
                                                                            <div class="row no-gutters align-items-center">
                                                                                <div class="col mr-2">
                                                                                    <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                        Deactivate Date</div>
                                                                                    <?php
                                                                                    $deactivateDate = "N/A";
                                                                                    if ($studentIntakeData['status_status_id'] == 2) {
                                                                                        $latestDeactiateLog = Database::search("SELECT * FROM `students_has_intakes_logs` 
                                                                                            WHERE `students_has_intakes_students_has_intakes_id` = '" . $studentIntakeData['students_has_intakes_id'] . "' 
                                                                                            AND `students_inactive_reasons_students_inactive_reasons_id` IS NOT NULL
                                                                                            ORDER BY `students_has_intakes_logs_id` DESC LIMIT 1");

                                                                                        if ($latestDeactiateLog->num_rows > 0) {
                                                                                            $deactivateDate = date('Y-m-d H:i A', strtotime($latestDeactiateLog->fetch_assoc()['date_updated']));
                                                                                        }
                                                                                    }
                                                                                    ?>
                                                                                    <div class="stat-value mb-0 text-gray-800"><?= $deactivateDate ?></div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 component-theme">
                                                        <div class="row p-4 m-0 g-0">
                                                            <span class="sub-topic">Updates History</span>

                                                            <div class="col-12 mt-3">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <div class="table-responsive-wrapper">
                                                                        <div class="table-responsive" id="users-table-container">
                                                                            <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium w-25">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                            </div>
                                                                                        </th>

                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Reason</th>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Remark</th>

                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="user-table-data">
                                                                                    <?php
                                                                                    $query = "SELECT * FROM `students_has_intakes_logs`
                                                                                    INNER JOIN `users` ON `students_has_intakes_logs`.`users_user_id` = `users`.`user_id`
                                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                    LEFT JOIN `students_inactive_reasons` ON `students_has_intakes_logs`.`students_inactive_reasons_students_inactive_reasons_id` = `students_inactive_reasons`.`students_inactive_reasons_id`
                                                                                    WHERE `students_has_intakes_students_has_intakes_id` = '" . $id . "' ORDER BY `date_updated` DESC";

                                                                                    $users = Database::search($query);
                                                                                    if ($users->num_rows > 0) {
                                                                                        while ($data = $users->fetch_assoc()) {
                                                                                    ?>
                                                                                            <tr>
                                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                        <?php
                                                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                                        ?>
                                                                                                            <img src="./assets/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                            $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                        ?>
                                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                        <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= date('Y-m-d H:i A', strtotime($data["date_updated"])); ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["students_inactive_reasons_students_inactive_reasons_id"] != null ? $data["students_inactive_reason"] : "N/A"; ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"] != null ? $data["note"] : "N/A"; ?></span></td>
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

                            <div class="modal fade" id="editStudentIntakeId" tabindex="-1" aria-labelledby="editStudentIntakeIdLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editStudentIntakeIdLabel">Edit Registration Number</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="col-12 p-0 g-0">
                                                <div class="row gx-4 gy-3">
                                                    <div class="col-12 d-flex flex-column">
                                                        <label class="text-14 main-text required" for="">Current Registration Number</label>
                                                        <input type="text" class="form-control custom-input mt-2" disabled value="<?php echo $studentIntakeData['student_id'] == '' ? 'N/A' : $studentIntakeData['student_id']; ?>">
                                                    </div>
                                                    <div class="col-12 d-flex flex-column">
                                                        <label class="text-14 main-text required" for="">New Registration Number</label>
                                                        <input type="text" class="form-control custom-input mt-2" id="siid">
                                                        <span id="siid-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 d-flex flex-column">
                                                        <label class="text-14 main-text" for="">Note</label>
                                                        <textarea type="text" class="form-control custom-input mt-2" rows="7" id="note-siid"></textarea>
                                                        <span id="siid-note-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="custom-btn px-5" onclick="editIntakeStudentId(this,'<?= $id ?>')">Save changes</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="deactivateModal" tabindex="-1" role="dialog" aria-labelledby="deactivateModalTitle" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLongTitle">Deactivate Students</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="col-12 p-0 g-0">
                                                <div class="row gx-4 gy-3">
                                                    <div class="col-12 d-flex flex-column">
                                                        <label class="text-14 main-text required" for="">Deactivate Reson</label>
                                                        <select class="form-select custom-select mt-2" id="reson-selector">
                                                            <option value="0">- Select -</option>
                                                            <?php
                                                            $status = Database::search("SELECT * FROM `students_inactive_reasons`");
                                                            while ($data = $status->fetch_assoc()) {
                                                            ?>
                                                                <option value="<?= $data["students_inactive_reasons_id"]; ?>"><?= $data["students_inactive_reason"]; ?></option>
                                                            <?php
                                                            }
                                                            ?>
                                                        </select>
                                                        <span id="reson-selector-feedback" class="text-dark-red text-12"></span>
                                                    </div>

                                                    <div class="col-12 d-flex flex-column">
                                                        <label class="text-14 main-text" for="">Note</label>
                                                        <textarea class="form-control custom-input mt-2" id="reson-note" rows="5"></textarea>
                                                        <span id="reson-note-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="col-12 col-md-4 custom-btn" onclick="applyIntakeStudentUpdate(this,<?= $studentIntakeData['intake_id'] ?>,2,'<?= $studentIntakeData['students_has_intakes_id'] ?>');">Update</button>
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