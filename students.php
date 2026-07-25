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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Students - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-table.php"; ?>
                            </div>

                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-12 col-xxl-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="students">Students</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="students-desc">Effortlessly manage students, access profiles, and maintain oversight</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-12 col-xxl-4 mt-3 mt-xxl-0">
                                                <div class="row gx-3 justify-content-end">
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                    ?>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="bg-transparent border-0" onclick="window.location = 'students-permissions'">
                                                                <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 2) || UserPermissions::hasPermission('students', 1)) {
                                                    ?>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <div class="dropdown w-100">
                                                                <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                    Action
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">

                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 2)) {
                                                                    ?>
                                                                        <li><a class="dropdown-item" href="add-student" data-i18n="add-student">Add New Student</a></li>
                                                                        <li><a class="dropdown-item" href="upload-student-bulk" data-i18n="add-students-bulk">Add New Students Bulk</a></li>
                                                                    <?php
                                                                    }
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 1)) {
                                                                    ?>
                                                                        <li><a class="dropdown-item" onclick="downloadStudentsCSV()" data-i18n="download-students">Download Students</a></li>
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

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0 align-content-center">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <?php
                                                            $studentCount = Database::search("SELECT COUNT(DISTINCT `student_id`) AS unique_student_count FROM `students`
                                                            INNER JOIN `login_details` ON `login_details_login_details_id` = `login_details_id`
                                                            INNER JOIN `status` ON `login_details`.`status_status_id` = `status`.`status_id`");
                                                            $data = $studentCount->fetch_assoc();
                                                            ?>
                                                            <span class="text-14 dark-theme fw-medium"><?php echo $data["unique_student_count"]; ?> <span data-i18n="students">Students</span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleStudentsPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="collapse" id="studentsFilter">
                                                        <div class="card card-body border-0 p-0">
                                                            <div class="col-12">
                                                                <div class="row justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="branch-selector" class="form-select custom-select" onchange="loadFaculties(),handleStudentsPagination('filter');">
                                                                            <option value="0" selected>- Select -</option>
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
                                                                        <select name="" id="faculty-selector" disabled class="form-select custom-select" onchange="loadProgrammes(),handleStudentsPagination('filter');">
                                                                            <option value="0" selected>- Select -</option>

                                                                        </select>
                                                                        <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="programme-selector" disabled class="form-select custom-select" onchange="loadIntakes(),handleStudentsPagination('filter');">
                                                                            <option value="0" selected>- Select -</option>
                                                                        </select>
                                                                        <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="intake-selector" disabled class="form-select custom-select" onchange="handleStudentsPagination('filter');">
                                                                            <option value="0" selected>- Select -</option>
                                                                        </select>
                                                                        <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                </div>
                                                                <div class="row mt-2"> 

                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4)) {
                                                                    ?>

                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="update-selector" onchange="filterStudents()">
                                                                                <option selected value="0">- Update Students -</option>
                                                                                <option value="1">Active Students</option>
                                                                                <option value="2">Inactive Students</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <button class="custom-btn w-100" type="button" onclick="applyStudentsUpdate(this)">Apply</button>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                    <div class="col-12 offset-0 offset-md-2 offset-lg-6 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleStudentsPagination('sortByStatus')">
                                                                            <option selected value="0">- Filter Students Status -</option>
                                                                            <option value="1">Active</option>
                                                                            <option value="2">Inactive</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 d-none d-lg-block"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentsPagination('filter')">
                                                            <option selected value="10">10</option>
                                                            <option value="25">25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                            <option value="250">250</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="table-responsive-wrapper">
                                                <div class="table-responsive" id="students-table-container">
                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                        <thead>
                                                            <tr class="br-t-5">
                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPagination('sortByName')">
                                                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleStudentsPagination('sortByDate')">
                                                                    <div class="d-flex justify-content-between align-items-center w-100">
                                                                        <span class="text-14 main-text fw-medium">Admission Date</span>
                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>

                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole)  || SessionUser::ifUserIsAccounts($sessionUserRole) ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 1) || UserPermissions::hasPermission('students', 4) || UserPermissions::hasPermission('students', 10)) {
                                                                ?>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                <?php
                                                                }
                                                                ?>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="user-table-data">
                                                            <?php
                                                            $query = "
                                                           SELECT 
                                                               `students`.`student_id` AS 'sId', 
                                                               `first_name`, 
                                                               `last_name`, 
                                                               `email`, 
                                                               `mobile`, 
                                                               `whatsapp`, 
                                                               `profile_img`, 
                                                               `date_registered`, 
                                                               `title`.`title`, 
                                                               `status`.`status`,
                                                               `verification`,
                                                               `reset_time`
                                                           FROM 
                                                               `students`
                                                           INNER JOIN 
                                                               `login_details` 
                                                               ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                           INNER JOIN 
                                                               `title` 
                                                               ON `students`.`title_title_id` = `title`.`title_id`
                                                           INNER JOIN 
                                                               `status` 
                                                               ON `login_details`.`status_status_id` = `status`.`status_id`
                                                           ORDER BY 
                                                               `students`.`student_id` DESC
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
                                                                        <td class="px-4 py-3 pe-0 align-middle">
                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sId"]; ?>" onchange="checkRowEvents(<?php echo $data['sId']; ?>);">
                                                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
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
                                                                            </div>
                                                                        </td>

                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_registered"])); ?></span></td>

                                                                        <td class="py-2 align-middle">
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
                                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  || SessionUser::ifUserIsAccounts($sessionUserRole) ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4) || UserPermissions::hasPermission('students', 10)) {
                                                                        ?>
                                                                            <td class="py-3 align-middle">
                                                                                <div class="dropdown">
                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                    <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-details?student-id=<?php echo $data['sId']; ?>">
                                                                                                <iconify-icon icon="hugeicons:activity-01" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Activity</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <!-- <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="downloadStudentIDCard('<?php echo $data['sId']; ?>')">
                                                                                                <iconify-icon icon="tabler:face-id" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Download Student ID Card</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2"> -->
                                                                                        <?php
                                                                                        if (!empty($data['verification']) && empty($data['reset_time'])) {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                                                    <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Resend Registration Link</a></li>
                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                                                    <iconify-icon icon="solar:password-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Send Password Reset Link</a></li>
                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <?php
                                                                                        }
                                                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 10)) {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-student?student-id=<?php echo $data['sId']; ?>">
                                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Edit</a></li>
                                                                                        <?php
                                                                                        }

                                                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4) || UserPermissions::hasPermission('students', 10)) {
                                                                                        ?>
                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <?php
                                                                                        }

                                                                                        ?>

                                                                                        <?php
                                                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  || SessionUser::ifUserIsAccounts($sessionUserRole) ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4)) {

                                                                                            if ($data["status"] == "Active") {
                                                                                        ?>
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateStudentStatus(2,'<?php echo $data['sId']; ?>')">
                                                                                                        <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        Deactivate</a></li>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateStudentStatus(1,'<?php echo $data['sId']; ?>')">
                                                                                                        <iconify-icon icon="dashicons:plus-alt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        Activate</a></li>
                                                                                        <?php
                                                                                            }
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPagination('previousPage');">
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
                                                                            $action = $isActive ? '' : "onclick=\"handleStudentsPagination('goToPage', '{$i}')\"";
                                                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                        }

                                                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                renderPageButton($i, $pageno == $i);
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPagination('nextPage',<?= $number_of_pages ?>);">
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

            <?php

            if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 1)) {
            ?>
                <div class="modal fade br-0" id="csvDownload" aria-hidden="true" aria-labelledby="csvDownloadLabel" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="csvDownloadLabel" data-i18n="download-students">Download Students CSV</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <!-- Waiting Message and Animation -->
                                <div id="waitingMessage" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden" data-i18n="loading">Loading...</span>
                                    </div>
                                    <h3 class="mt-3" data-i18n="preparing-csv">Preparing Your CSV File...</h3>
                                    <p class="text-muted" data-i18n="please-wait-preparing-csv">Please wait while we generate your download.</p>
                                </div>

                                <!-- Success Message (Hidden by Default) -->
                                <div id="successMessage" class="text-center" class="d-none">
                                    <div class="checkmark-circle">
                                        <div class="checkmark"></div>
                                    </div>
                                    <h3 class="mt-3" data-i18n="download-ready">Download Ready!</h3>
                                    <p class="text-muted" data-i18n="csv-ready">Your CSV file has been successfully generated.</p>
                                    <button class="custom-btn dark-theme px-5 mt-3 text-white" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            }
            ?>

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
?>