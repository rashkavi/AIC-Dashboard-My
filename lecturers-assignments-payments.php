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

        // one row per lecturer + assignment for the marked assignments
        // payment_status => 1 Scheduled, 2 Completed, 3 Rejected, 4 Pending Approval (not scheduled yet)
        $query = "SELECT 
            `lhsaa`.`lecturers_lecturers_id`,
            `sha`.`assignments_assignment_id`,
            COUNT(DISTINCT `lhsaa`.`lecturers_has_students_has_assignments_id`) AS marked_count,
            COUNT(DISTINCT `paid`.`lecturers_has_students_has_assignments_id`) AS paid_count,
            `lecturers`.`per_assignment_fee`,
            `users`.`user_id`, `users`.`user_fname`, `users`.`user_lname`, `users`.`profile_img`, `title`.`title`,
            `assignments`.`assignment_code`, `assignments`.`assignment`,
            `subjects`.`subject`, `levels`.`level`, `intakes`.`intake`, `programmes`.`programme`, `faculties`.`faculty`,
            MAX(`open_payment`.`assignment_payments_id`) AS assignment_payments_id,
            MAX(`open_payment`.`amount`) AS amount,
            MAX(`completed_payment`.`assignment_payments_id`) AS completed_payment_id,
            MAX(`completed_payment`.`completed_amount`) AS completed_amount,
            (CASE 
                WHEN MAX(`open_payment`.`assignment_payments_id`) IS NOT NULL THEN MAX(`open_payment`.`earning_status_earning_status_id`)
                WHEN COUNT(DISTINCT `lhsaa`.`lecturers_has_students_has_assignments_id`) > COUNT(DISTINCT `paid`.`lecturers_has_students_has_assignments_id`) THEN 4
                ELSE 2
            END) AS payment_status
        FROM `lecturers_has_students_has_assignments` AS `lhsaa`
        INNER JOIN `students_has_assignments` AS `sha` ON `lhsaa`.`students_has_assignments_students_has_assignments_id` = `sha`.`students_has_assignments_id`
        INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
        INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
        INNER JOIN `assignments` ON `assignments`.`assignment_id` = `sha`.`assignments_assignment_id`
        INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
        INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
        LEFT JOIN (
            SELECT `apma`.`lecturers_has_students_has_assignments_id`
            FROM `assignment_payments_has_marked_assignments` AS `apma`
            INNER JOIN `assignment_payments` AS `ap` ON `ap`.`assignment_payments_id` = `apma`.`assignment_payments_id`
            WHERE `ap`.`earning_status_earning_status_id` = '2'
            GROUP BY `apma`.`lecturers_has_students_has_assignments_id`
        ) AS `paid` ON `paid`.`lecturers_has_students_has_assignments_id` = `lhsaa`.`lecturers_has_students_has_assignments_id`
        LEFT JOIN `assignment_payments` AS `open_payment` ON (`open_payment`.`lecturers_lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
            AND `open_payment`.`assignments_assignment_id` = `sha`.`assignments_assignment_id`
            AND `open_payment`.`earning_status_earning_status_id` IN ('1','3'))
        LEFT JOIN (
            SELECT `lecturers_lecturers_id`, `assignments_assignment_id`, MAX(`assignment_payments_id`) AS assignment_payments_id, SUM(`amount`) AS completed_amount
            FROM `assignment_payments`
            WHERE `earning_status_earning_status_id` = '2'
            GROUP BY `lecturers_lecturers_id`, `assignments_assignment_id`
        ) AS `completed_payment` ON (`completed_payment`.`lecturers_lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
            AND `completed_payment`.`assignments_assignment_id` = `sha`.`assignments_assignment_id`)
        WHERE `lhsaa`.`submission_sts_submission_sts_id` = '2'";

        $groupBy = " GROUP BY `lhsaa`.`lecturers_lecturers_id`, `sha`.`assignments_assignment_id`, `lecturers`.`per_assignment_fee`, `users`.`user_id`, `users`.`user_fname`, `users`.`user_lname`, `users`.`profile_img`, `title`.`title`, `assignments`.`assignment_code`, `assignments`.`assignment`, `assignments`.`assignment_id`, `subjects`.`subject`, `levels`.`level`, `intakes`.`intake`, `programmes`.`programme`, `faculties`.`faculty`";
        $orderBy = " ORDER BY `payment_status` DESC, `assignments`.`assignment_id` DESC";

        $pendingApprovalAssignmentPayments = Database::search("SELECT COUNT(*) AS 'count' FROM (" . $query . $groupBy . " HAVING `payment_status` = '4') AS `pending_payments`")->fetch_assoc();
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Lecturers Assignments Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                        include "./includes/side-menu.php";
                        load_side_menu::load("Users");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Users");
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
                                                    <span class="main-topic lh-lg">Lecturers Assignment Payments</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./payments';">Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-payments';">Lecturers Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-assignments-payments';">Lecturers Assignment Payments</a></li>
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
                                                            <span class="text-14 dark-theme fw-medium"><?= $pendingApprovalAssignmentPayments['count'] ?> pending Approval</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-7 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="user-search-text" onkeyup="handleLecturersAssignmentsPaymentsPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                        <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleLecturersAssignmentsPaymentsPagination('filter')">
                                                            <option selected value="0">- Filter Data -</option>
                                                            <option value="4">Pending Approval</option>
                                                            <option value="1">Scheduled</option>
                                                            <option value="2">Completed</option>
                                                            <option value="3">Rejected</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button">Search</button>
                                                    </div>

                                                    <div class="d-none d-lg-block col-6"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleLecturersAssignmentsPaymentsPagination('filter')">
                                                            <option selected value="10">10</option>
                                                            <option value="25">25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                            <option value="250">250</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="table-responsive-wrapper">
                                                        <div class="table-responsive" id="users-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                <span class="text-14 main-text fw-medium mx-2">Assignment</span>
                                                                            </div>
                                                                        </th>

                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecturer Name</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Marked Assignments</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                        <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php

                                                                    $results = Database::search($query . $groupBy . $orderBy);
                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = 10;  // Consistent variable name

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $payments = Database::search("" . $query . $groupBy . $orderBy . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                    if ($payments->num_rows > 0) {
                                                                        while ($data = $payments->fetch_assoc()) {
                                                                            $status_classes = [
                                                                                "1" => "table-btn-yellow",
                                                                                "2" => "table-btn-success",
                                                                                "3" => "table-btn-danger",
                                                                                "4" => "table-btn-pink",
                                                                            ];
                                                                            $status_names = [
                                                                                "1" => "Scheduled",
                                                                                "2" => "Completed",
                                                                                "3" => "Rejected",
                                                                                "4" => "Pending Approval",
                                                                            ];
                                                                            $unpaidCount = $data['marked_count'] - $data['paid_count'];
                                                                            if ($data['payment_status'] == 4) {
                                                                                $amount = $unpaidCount * $data['per_assignment_fee'];
                                                                            } else if ($data['payment_status'] == 2) {
                                                                                $amount = $data['completed_amount'];
                                                                            } else {
                                                                                $amount = $data['amount'];
                                                                            }
                                                                            $viewPaymentId = !empty($data['assignment_payments_id']) ? $data['assignment_payments_id'] : $data['completed_payment_id'];
                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 pe-0 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                        <p class="text-14 main-text mx-2"><?= $data['assignment_code'] . " - " . $data['assignment'] ?></br><span class="sub-text text-12"><?= $data['faculty'] . "/" . $data['programme'] . "/" . $data['intake'] . "/" . $data['level'] . "/" . $data['subject'] ?></span></p>
                                                                                    </div>
                                                                                </td>
                                                                                <td colspan="3" class="py-2 align-middle">
                                                                                    <div class="flex-grow-1 d-flex align-items-center">
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
                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                    </div>
                                                                                </td>

                                                                                <td colspan="3" class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?= $data['marked_count'] ?> Marked / <?= $data['paid_count'] ?> Paid</span></br>
                                                                                    <span class="text-12 sub-text">LKR <?= number_format($data['per_assignment_fee'], 2) ?> per assignment</span>
                                                                                </td>

                                                                                <td colspan="2" class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($amount, 2); ?></span></td>
                                                                                <td class="py-2 align-middle">
                                                                                    <button class="table-btn <?php echo $status_classes[$data['payment_status']]; ?> w-100"> <?php echo $status_names[$data['payment_status']]; ?></button>
                                                                                </td>
                                                                                <?php

                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments')) {
                                                                                ?>
                                                                                    <td class="py-3 pe-4 align-middle">
                                                                                        <div class="dropdown">
                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                <?php
                                                                                                if (!empty($viewPaymentId)) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lecturer-assignment-payment?payment-id=<?php echo $viewPaymentId; ?>">
                                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            View</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                if ($data['payment_status'] == 4) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="addLecturerAssignmentPayment('<?= $data['lecturers_lecturers_id'] ?>', '<?= $data['assignments_assignment_id'] ?>')">
                                                                                                            <iconify-icon icon="mdi:approve" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Schedule Payment</a></li>
                                                                                                <?php
                                                                                                } else if ($data['payment_status'] == 1) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="addLecturerAssignmentPayment('<?= $data['lecturers_lecturers_id'] ?>', '<?= $data['assignments_assignment_id'] ?>')">
                                                                                                            <iconify-icon icon="mdi:refresh" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Re-calculate Payment</a></li>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="updateLecturerAssignmentPayment(<?= $data['assignment_payments_id'] ?>,3)">
                                                                                                            <iconify-icon icon="zondicons:close-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Reject Payment</a></li>
                                                                                                <?php
                                                                                                } else if ($data['payment_status'] == 3) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="addLecturerAssignmentPayment('<?= $data['lecturers_lecturers_id'] ?>', '<?= $data['assignments_assignment_id'] ?>')">
                                                                                                            <iconify-icon icon="mdi:calendar-refresh" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Re-schedule Payment</a></li>
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersAssignmentsPaymentsPagination('previousPage')">
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
                                                                                    $action = $isActive ? '' : "onclick=\"handleLecturersAssignmentsPaymentsPagination('goToPage', '{$i}')\"";
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersAssignmentsPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
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

            <!-- Confirmation Modal=>Start -->
            <div class="modal fade  confirmation " id="confirmation" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-4">
                        <div class="modal-header p-0 border-0">
                            <h1 class="modal-title text-16 text-dark-color" id="editPaymentLabel">Confirmation Message</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="model-body p-0 mt-3">
                            <div class="col-12 container-fluid p-0">
                                <div class="d-flex flex-row align-items-center">
                                    <iconify-icon icon="fluent:question-circle-16-filled" class="dark-blue" width="40" height="40"></iconify-icon>
                                    <p class="text-16 main-text px-2 m-0">Would you like to remove selected
                                        examination ?</p>
                                </div>
                                <div class="col-12 d-flex flex-row align-items-center justify-content-end mt-3">
                                    <button class="custom-btn px-5 pe-5 me-3">Yes</button>
                                    <button class="custom-btn component-theme bl-solid main-text px-5 pe-5">No</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Confirmation Modal => End -->

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
    }
}
?>