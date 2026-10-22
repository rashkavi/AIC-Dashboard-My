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

        $query = "
                                                                            SELECT
                                                                                `students`.`student_id` AS `sId`,
                                                                                `students`.`ms_team_username` AS `ms_team_username`,
                                                                                `students_has_intakes`.`students_has_intakes_id` AS `shiId`,
                                                                                `login_details`.`first_name`,
                                                                                `login_details`.`last_name`,
                                                                                `login_details`.`email`,
                                                                                `students`.`mobile`,
                                                                                `login_details`.`whatsapp`,
                                                                                `students`.`profile_img`,
                                                                                `students_has_intakes`.`addmission_date` AS`date_registered`,
                                                                                `title`.`title`,

                                                                                `lead_student_status`.`lead_student_status`,
                                                                                `lead_student_status`.`lead_student_status_id`,

                                                                                `login_details`.`verification`,
                                                                                `login_details`.`reset_time`,

                                                                                `users`.`user_fname`,
                                                                                `users`.`user_lname`,

                                                                                `title2`.`title`                                           AS `user_title`,

                                                                                `students_logs`.`students_log_id`,
                                                                                `students_logs`.`date_updated`,
                                                                                `students_logs`.`lead_student_status_lead_student_status_id` AS `latest_log_status_id`,

                                                                                `programmes`.`programme`,
                                                                                `students_has_intakes`.`students_has_intakes_id`,

                                                                                CASE
                                                                                    WHEN `payment`.`students_has_intakes_students_has_intakes_id` IS NOT NULL
                                                                                        THEN 1
                                                                                    ELSE 0
                                                                                END AS `has_due_payment`

                                                                            FROM `students`

                                                                            INNER JOIN `login_details`
                                                                                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`

                                                                            INNER JOIN `title`
                                                                                ON `students`.`title_title_id` = `title`.`title_id`
                                                                            
                                                                            INNER JOIN `students_has_intakes`
                                                                                ON `students`.`student_id` = `students_has_intakes`.`students_student_id`

                                                                            INNER JOIN `lead_student_status`
                                                                                ON `students_has_intakes`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`

                                                                            INNER JOIN `intakes`
                                                                                ON `intakes`.`intake_id` = `students_has_intakes`.`intakes_intake_id`

                                                                            INNER JOIN `programmes`
                                                                                ON `programmes`.`programme_id` = `intakes`.`programmes_programme_id`

                                                                            INNER JOIN `faculties`
                                                                                ON `faculties`.`faculty_id` = `programmes`.`faculties_faculty_id`

                                                                            INNER JOIN `users`
                                                                                ON `students_has_intakes`.`counselor_user_id` = `users`.`user_id`

                                                                            INNER JOIN `title` AS `title2`
                                                                                ON `users`.`title_title_id` = `title2`.`title_id`

                                                                            LEFT JOIN `students_logs`
                                                                                ON `students_logs`.`students_log_id` = (
                                                                                    SELECT `sl2`.`students_log_id`
                                                                                    FROM `students_logs` `sl2`
                                                                                    WHERE `sl2`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                                                                                    ORDER BY `sl2`.`date_updated` DESC, `sl2`.`students_log_id` DESC
                                                                                    LIMIT 1
                                                                                )

                                                                            LEFT JOIN (
                                                                                SELECT DISTINCT `pshi`.`students_has_intakes_students_has_intakes_id`
                                                                                FROM `course_payments` AS `cp`
                                                                                INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                                                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                                WHERE `cp`.`payment_status_payment_status_id` = '1'
                                                                                AND `cp`.`due_date` < '" . date("Y-m-d") . "'
                                                                            ) AS `payment`
                                                                                ON `payment`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                                                                        ";

        if ($sessionUserRole == "Counselor") {
            $query .= " WHERE `users`.`user_id`='" . $sessionUserObject['user_id'] . "'";
        } else if ($sessionUserRole == "Branch Manager") {
            $userData = Database::search("SELECT * FROM `users` WHERE `user_id`='" . $sessionUserObject['user_id'] . "'")->fetch_assoc();

            $query .= " WHERE (`users`.`user_id`='" . $sessionUserObject['user_id'] . "' 
                                                                OR `faculties`.`branches_branch_id`='" . $userData['branches_branch_id'] . "')
                                                                ";
        }

        $query .= "  ORDER BY 
                                                               `students_logs`.`date_updated` DESC,
                                                               `students_has_intakes`.`students_has_intakes_id` DESC";

        $results = Database::search($query);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Inquiries - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                        load_side_menu::load("Registrations");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Registrations");
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
                                                    <span class="main-topic lh-lg" data-i18n="">Registrations</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="students-desc">Effortlessly manage students, access profiles, and maintain oversight</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-12 col-xxl-4 mt-3 mt-xxl-0">
                                                <div class="row gx-3 justify-content-end">
                                                    <?php

                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole) || UserPermissions::hasPermission('students', 2) || UserPermissions::hasPermission('students', 1)) {
                                                    ?>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <div class="dropdown w-100">
                                                                <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                    Action
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                    <li><a class="dropdown-item" href="add-registration" data-i18n="add-registration">Add New Registration</a></li>
                                                                    <li><a class="dropdown-item" href="existing-student-registration" data-i18n="existing-student-registration">Existing Student Registration</a></li>
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

                                                            <span class="text-14 dark-theme fw-medium"><?php echo $results->num_rows; ?> <span data-i18n="students">Students</span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-8 col-lg-7 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleInquiryPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" onclick="genarateStudentRegistrationsReport(this)">
                                                            <span class="text-14 fw-semibold">Export Report</span>
                                                            <iconify-icon icon="line-md:download-loop" width="26" height="26" class="text-white px-3 pe-0"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="collapse" id="studentsFilter">
                                                        <div class="card card-body border-0 p-0">
                                                            <div class="col-12">
                                                                <div class="row justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="branch-selector" class="form-select custom-select" onchange="loadFaculties(),handleInquiryPagination('filter');">
                                                                            <option value="0" selected>- Select Branch -</option>
                                                                            <?php

                                                                            $branches = Database::search("SELECT * FROM `branches`");
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
                                                                        <select name="" id="faculty-selector" disabled class="form-select custom-select" onchange="loadProgrammes(),handleInquiryPagination('filter');">
                                                                            <option value="0" selected>- Select Faculty -</option>
                                                                        </select>
                                                                        <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="programme-selector" disabled class="form-select custom-select" onchange="loadIntakes(),handleInquiryPagination('filter');">
                                                                            <option value="0" selected>- Select Programme -</option>
                                                                        </select>
                                                                        <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="intake-selector" disabled class="form-select custom-select" onchange="handleInquiryPagination('filter');">
                                                                            <option value="0" selected>- Select Intake -</option>
                                                                        </select>
                                                                        <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <?php
                                                                    if (!($sessionUserRole == "Counselor")) {
                                                                    ?>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="user-selector" class="form-select custom-select" onchange="handleInquiryPagination('filter');">
                                                                                <option value="0" selected>- Select Counselor -</option>
                                                                                <?php
                                                                                $counselors = Database::search("SELECT * FROM `users` 
                                                                                INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                                                WHERE `user_roles_user_role_id` IN (3,9,10)");
                                                                                while ($counselor = $counselors->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?= $counselor['user_id'] ?>"><?= $counselor['title'] . "." . $counselor['user_fname'] . " " . $counselor['user_lname'] ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex align-items-center justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleInquiryPagination('filter')">
                                                                            <option selected value="0">- Select Status -</option>
                                                                            <?php
                                                                            $leadStatus = Database::search("SELECT * FROM `lead_student_status`");
                                                                            while ($status = $leadStatus->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $status["lead_student_status_id"]; ?>"><?php echo $status["lead_student_status"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 mt-2 d-flex align-items-center justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Payment status filter" id="payment-status-selector" onchange="handleInquiryPagination('filter')">
                                                                            <option selected value="0">- Select Payment Status -</option>
                                                                            <option value="1">Has Due Payments</option>
                                                                            <option value="2">No Due Payments</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 d-none d-lg-block"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleInquiryPagination('filter')">
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
                                                <div class="table-responsive overflow-visible" id="students-table-container">
                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                        <thead>
                                                            <tr class="br-t-5">
                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleInquiryPagination('sortByName')">
                                                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Programme</th>
                                                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleInquiryPagination('sortByDate')">
                                                                    <div class="d-flex justify-content-between align-items-center w-100">
                                                                        <span class="text-14 main-text fw-medium">Date Added</span>
                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Assigned Counselor</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>

                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>

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
                                                            ?>
                                                                    <tr class="position-relative">
                                                                        <td class="align-middle lead-id-td">
                                                                            <?php
                                                                            if ($data['has_due_payment']) {
                                                                            ?>
                                                                                <div class="lead-id-card position-relative d-flex align-items-center">
                                                                                    <span class="mini-left-ribbon"
                                                                                        style="background: #EB5757">
                                                                                        Due
                                                                                    </span>

                                                                                    <div class="d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['shiId']; ?>)">
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
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <div class="d-flex align-items-center cursor-pointer px-4" onclick="toggleCheckbox(<?php echo $data['shiId']; ?>)">
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
                                                                            <?php
                                                                            }
                                                                            ?>

                                                                        </td>

                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["programme"]; ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_registered"])); ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span></td>
                                                                        <?php

                                                                        $displayStatusId = $data['lead_student_status_id'];
                                                                        $displayStatus = $data['lead_student_status'];
                                                                        ?>

                                                                        <td class="py-2 align-middle">
                                                                            <button class="table-btn label-student-status-<?php echo $displayStatusId; ?> w-100">
                                                                                <?php echo htmlspecialchars($displayStatus); ?>
                                                                            </button>
                                                                        </td>

                                                                        <td class="py-3 align-middle">
                                                                            <div class="dropdown">
                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                                                    <?php
                                                                                    if ($displayStatusId == 17 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcdemicDepartment($sessionUserRole) || SessionUser::ifUserIsPro($sessionUserRole))) {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendWelcomeEmail('<?php echo $data['shiId']; ?>')">
                                                                                                <iconify-icon icon="bi:send" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Resend Welcome Email</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                    <?php
                                                                                    }
                                                                                    if ($displayStatusId == 17) {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="generateLetter('<?php echo $data['shiId']; ?>')">
                                                                                                <iconify-icon icon="carbon:email-user" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Genarate Studentship Letter</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-time-table?student-id=<?php echo $data['shiId']; ?>">
                                                                                                <iconify-icon icon="heroicons:calendar-date-range-solid" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                View Student Time Table</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="registration-view?registration-id=<?php echo $data['shiId']; ?>">
                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                            View</a></li>
                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="registration-update-history?registration-id=<?php echo $data['shiId']; ?>">
                                                                                            <iconify-icon icon="ic:baseline-history" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                            Update History</a></li>
                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                    <?php

                                                                                    if ($displayStatusId != 17) {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-registration?registration-id=<?php echo $data['shiId']; ?>">
                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Edit</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                    <?php
                                                                                    }
                                                                                    if ($displayStatusId == 2 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole))) {
                                                                                    ?>
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="re-upload-registration-payment-slip?inquiry-id=<?php echo $data['shiId']; ?>">
                                                                                                <iconify-icon icon="material-symbols-light:upload-2-rounded" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Re-Upload Registration Payment Slip</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                    <?php
                                                                                    }
                                                                                    if ($displayStatusId >= 5) {
                                                                                    ?>

                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="upload-other-documents?registration-id=<?php echo $data['shiId']; ?>">
                                                                                                <iconify-icon icon="solar:document-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Other Documents</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=<?php echo $data['students_has_intakes_id']; ?>&path=inquiries">
                                                                                                <iconify-icon icon="tabler:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Payment Plan</a></li>
                                                                                    <?php
                                                                                    }

                                                                                    if ($displayStatusId == 5 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole))) {
                                                                                    ?>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <li>
                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                onclick="openRegistrationVerificationModal(<?= $data['shiId']; ?>)">
                                                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                                                    width="20"
                                                                                                    height="20"
                                                                                                    class="sub-text me-3"></iconify-icon>
                                                                                                Confirm Academic & Initial Payment Completion
                                                                                            </a>
                                                                                        </li>
                                                                                    <?php
                                                                                    }
                                                                                    if ($displayStatusId == 12 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole))) {
                                                                                    ?>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <li>
                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                onclick="openRegistrationVerificationModal(<?= $data['shiId']; ?>)">
                                                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                                                    width="20"
                                                                                                    height="20"
                                                                                                    class="sub-text me-3"></iconify-icon>
                                                                                                Confirm Academic & Initial Payment Completion
                                                                                            </a>
                                                                                        </li>
                                                                                    <?php
                                                                                    }

                                                                                    if ($displayStatusId == 11 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcdemicAdministration($sessionUserRole))) {
                                                                                    ?>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <li>
                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                onclick="openAcademicAdministrationRejectModal(<?= $data['shiId']; ?>)">
                                                                                                <iconify-icon icon="solar:close-circle-bold"
                                                                                                    width="20"
                                                                                                    height="20"
                                                                                                    class="sub-text me-3"></iconify-icon>
                                                                                                Academic Documents Rejected
                                                                                            </a>
                                                                                        </li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <li>
                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                onclick="openAcademicAdministrationConfirmModal(<?= $data['shiId']; ?>,'<?= $data['ms_team_username'] ?>')">
                                                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                                                    width="20"
                                                                                                    height="20"
                                                                                                    class="sub-text me-3"></iconify-icon>
                                                                                                Confirm Academic Completion
                                                                                            </a>
                                                                                        </li>
                                                                                    <?php
                                                                                    }
                                                                                    if ($displayStatusId == 14 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcdemicDepartment($sessionUserRole))) {
                                                                                    ?>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <li>
                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                onclick="openAcademicDepartmentConfirmModal(<?= $data['shiId']; ?>)">
                                                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                                                    width="20"
                                                                                                    height="20"
                                                                                                    class="sub-text me-3"></iconify-icon>
                                                                                                Confirm Enrollement Completion
                                                                                            </a>
                                                                                        </li>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleInquiryPagination('previousPage');">
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
                                                                            $action = $isActive ? '' : "onclick=\"handleInquiryPagination('goToPage', '{$i}')\"";
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleInquiryPagination('nextPage',<?= $number_of_pages ?>);">
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

            <style>
                .verification-status-box {
                    border: 1px solid #EEF1FA;
                    border-radius: 16px;
                    padding: 18px;
                    background: #FCFDFF;
                }

                .verification-status-item {
                    display: flex;
                    align-items: center;
                    gap: 14px;
                }

                .verification-status-icon {
                    width: 42px;
                    height: 42px;
                    border-radius: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .success-bg {
                    background: rgba(25, 135, 84, 0.12);
                    color: #198754;
                }

                .warning-bg {
                    background: rgba(255, 193, 7, 0.15);
                    color: #C79000;
                }

                .verification-status-label {
                    display: block;
                    font-size: 12px;
                    color: #667085;
                    margin-bottom: 2px;
                }

                .verification-status-value {
                    font-size: 14px;
                    font-weight: 700;
                    color: #101828;
                }
            </style>

            <div class="modal fade" id="registrationVerificationModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow br-12">
                        <div class="modal-body p-4 text-center">

                            <div class="mb-3">
                                <iconify-icon icon="solar:clipboard-check-bold"
                                    width="54"
                                    height="54"
                                    style="color:#D4A800;"></iconify-icon>
                            </div>

                            <h5 class="main-text fw-bold mb-2">
                                Confirm Academic & Initial Payment Completion
                            </h5>

                            <p class="sub-text text-14 mb-4">
                                Please confirm that all required registration documents
                                have been successfully collected and verified, and that
                                the student's initial payment has been confirmed.
                                <br><br>
                                Once finalized, this student will proceed to the next
                                stage of the admission workflow.
                            </p>

                            <!-- ERROR DISPLAY AREA -->
                            <div id="registrationVerificationErrorArea"
                                class="alert alert-danger text-start d-none mb-4">

                                <div class="d-flex align-items-start">
                                    <iconify-icon icon="solar:danger-triangle-bold"
                                        width="20"
                                        height="20"
                                        class="me-2 mt-1"></iconify-icon>

                                    <div>
                                        <span class="fw-semibold d-block mb-1">
                                            Unable to Finalize Registration
                                        </span>

                                        <span id="registrationVerificationErrorText"
                                            class="text-14">
                                        </span>
                                    </div>
                                </div>

                            </div>

                            <div class="verification-status-box mb-4">

                                <div class="verification-status-item">
                                    <div class="verification-status-icon success-bg">
                                        <iconify-icon icon="solar:document-text-bold"
                                            width="18"
                                            height="18"></iconify-icon>
                                    </div>

                                    <div class="text-start">
                                        <span class="verification-status-label">
                                            Registration Documents
                                        </span>

                                        <p class="verification-status-value mb-0">
                                            Collected & Verified
                                        </p>
                                    </div>
                                </div>

                                <div class="verification-status-item mt-3">
                                    <div class="verification-status-icon warning-bg">
                                        <iconify-icon icon="solar:wallet-money-bold"
                                            width="18"
                                            height="18"></iconify-icon>
                                    </div>

                                    <div class="text-start">
                                        <span class="verification-status-label">
                                            Initial Payment
                                        </span>

                                        <p class="verification-status-value mb-0">
                                            Payment Confirmed
                                        </p>
                                    </div>
                                </div>

                            </div>

                            <input type="hidden" id="registrationVerificationStudentId">

                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button"
                                        class="btn btn-light h-48 w-100 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                </div>

                                <div class="col-6">
                                    <button type="button"
                                        class="custom-btn w-100"
                                        onclick="finalizeRegistrationVerification(this)">
                                        Finalize & Proceed
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="academicAdministrationConfirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow br-12">
                        <div class="modal-body p-4 text-center">

                            <div class="mb-3">
                                <iconify-icon icon="mynaui:book-check"
                                    width="54"
                                    height="54"
                                    style="color:#4F46E5;"></iconify-icon>
                            </div>

                            <h5 class="main-text fw-bold mb-2">
                                Confirm Academic Completion
                            </h5>

                            <p class="sub-text text-14 mb-4">
                                Please confirm that the student's academic registration
                                process has been successfully reviewed and completed by
                                the Academic Administration Department.
                                <br><br>
                                Once confirmed, the student will proceed to the next
                                stage of the academic workflow.
                            </p>

                            <!-- ERROR DISPLAY AREA -->
                            <div id="academicCompletionErrorArea"
                                class="alert alert-danger text-start d-none mb-4">

                                <div class="d-flex align-items-start">
                                    <iconify-icon icon="solar:danger-triangle-bold"
                                        width="20"
                                        height="20"
                                        class="me-2 mt-1"></iconify-icon>

                                    <div>
                                        <span class="fw-semibold d-block mb-1">
                                            Unable to Confirm Academic Completion
                                        </span>

                                        <span id="academicCompletionErrorText"
                                            class="text-14">
                                        </span>
                                    </div>
                                </div>

                            </div>

                            <!-- STATUS BOX -->
                            <div class="verification-status-box mb-4">

                                <div class="verification-status-item">
                                    <div class="verification-status-icon success-bg">
                                        <iconify-icon icon="solar:notebook-bookmark-bold"
                                            width="18"
                                            height="18"></iconify-icon>
                                    </div>

                                    <div class="text-start">
                                        <span class="verification-status-label">
                                            Academic Registration
                                        </span>

                                        <p class="verification-status-value mb-0">
                                            Reviewed & Completed
                                        </p>
                                    </div>
                                </div>

                                <div class="verification-status-item mt-3">
                                    <div class="verification-status-icon warning-bg">
                                        <iconify-icon icon="solar:user-check-bold"
                                            width="18"
                                            height="18"></iconify-icon>
                                    </div>

                                    <div class="text-start">
                                        <span class="verification-status-label">
                                            Student Status
                                        </span>

                                        <p class="verification-status-value mb-0">
                                            Ready for Next Workflow Stage
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- MICROSOFT TEAMS CREDENTIALS -->
                            <div class="row g-3 text-start mb-4">
                                <div class="col-12">
                                    <label class="text-14 main-text">
                                        Microsoft Teams Username <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                        class="form-control custom-input mt-2"
                                        id="academicAdministrationTeamsUsername"

                                        required>
                                    <div class="text-12 text-danger d-none" id="academicAdministrationTeamsUsernameFeedback">
                                        Microsoft Teams username is required.
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="academicAdministrationStudentId">

                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button"
                                        class="btn btn-light h-48 w-100 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                </div>

                                <div class="col-6">
                                    <button type="button"
                                        class="custom-btn w-100"
                                        onclick="confirmAcademicCompletion(this)">
                                        Confirm & Proceed
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="academicDepartmentConfirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow br-12">
                        <div class="modal-body p-4 text-center">

                            <div class="mb-3">
                                <iconify-icon icon="solar:user-check-bold"
                                    width="54"
                                    height="54"
                                    style="color:#198754;"></iconify-icon>
                            </div>

                            <h5 class="main-text fw-bold mb-2">
                                Confirm Enrollment Completion?
                            </h5>

                            <p class="sub-text text-14 mb-4">
                                Are you sure you want to confirm that the student's intake has been verified
                                and the enrollment process has been completed by the Academic Department?
                                Once confirmed, this student will be marked as fully enrolled and moved to the next stage.
                            </p>

                            <input type="hidden" id="academicDepartmentStudentId">

                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button"
                                        class="btn btn-light h-48 w-100 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                </div>

                                <div class="col-6">
                                    <button type="button"
                                        class="custom-btn w-100"
                                        onclick="confirmAcademicDepartmentCompletion(this)">
                                        Yes, Confirm
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="academicDocumentsRejectModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow br-12">
                        <div class="modal-body p-4">

                            <!-- Header -->
                            <div class="text-center mb-4">

                                <div class="hw-80 mx-auto br-8 mb-3">
                                    <iconify-icon icon="solar:close-circle-bold"
                                        width="54"
                                        height="54"
                                        class="text-dark-red"></iconify-icon>
                                </div>

                                <h5 class="main-text fw-bold mb-2">
                                    Reject Academic Documents
                                </h5>

                                <p class="sub-text text-14 mb-0">
                                    Please provide a rejection reason for the submitted
                                    academic documents. This note will be visible to the student
                                    for re-submission guidance.
                                </p>

                            </div>

                            <!-- Error Area -->
                            <div id="academicRejectErrorArea"
                                class="alert alert-danger d-none text-14 mb-3">
                            </div>

                            <!-- Note Area -->
                            <div class="mb-4">

                                <label class="text-14 main-text fw-semibold mb-2 required">
                                    Rejection Note
                                </label>

                                <textarea
                                    class="form-control custom-text-area"
                                    id="academicRejectNote"
                                    placeholder="Enter rejection reason here..."></textarea>

                                <div class="invalid-feedback"
                                    id="academicRejectNote-feedback"></div>

                            </div>

                            <input type="hidden" id="academicRejectStudentId">

                            <!-- Actions -->
                            <div class="row g-2">

                                <div class="col-6">
                                    <button type="button"
                                        class="btn btn-light h-48 w-100 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                </div>

                                <div class="col-6">
                                    <button type="button"
                                        class="custom-danger-btn w-100"
                                        onclick="rejectAcademicDocuments(this)">
                                        Reject Documents
                                    </button>
                                </div>

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
?>