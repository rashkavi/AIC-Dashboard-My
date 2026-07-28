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
            <title>Inquiries Registration Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                        load_side_menu::load("Payments");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Payments");
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
                                                    <span class="main-topic lh-lg" data-i18n="">Registrations Payments</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./registrations-registration-payments">Registrations Payments</a></li>
                                                        </ol>
                                                    </nav>
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
                                                            $studentCount = Database::search("SELECT COUNT(DISTINCT `student_id`) AS unique_student_count FROM 
                                                            `students`");
                                                            $data = $studentCount->fetch_assoc();
                                                            ?>
                                                            <span class="text-14 dark-theme fw-medium"><?php echo $data["unique_student_count"]; ?> <span data-i18n="students">Students</span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleInquiryRegistrationPaymentsPagination('filter')">
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
                                                                <div class="row">

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 d-none d-lg-block"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleInquiryRegistrationPaymentsPagination('filter')">
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
                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleInquiryRegistrationPaymentsPagination('sortByName')">
                                                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleInquiryRegistrationPaymentsPagination('sortByDate')">
                                                                    <div class="d-flex justify-content-between align-items-center w-100">
                                                                        <span class="text-14 main-text fw-medium">Date Added</span>
                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Added By</th>
                                                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleInquiryRegistrationPaymentsPagination('sortByDate')">
                                                                    <div class="d-flex justify-content-between align-items-center w-100">
                                                                        <span class="text-14 main-text fw-medium">Date Updated</span>
                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Updated By</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>

                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole)) {
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
                                                         
                                                             `login_details`.`first_name`, 
                                                             `login_details`.`last_name`, 
                                                             `login_details`.`email`, 
                                                         
                                                             `students`.`mobile`, 
                                                         
                                                             `login_details`.`whatsapp`, 
                                                             `students`.`profile_img`, 
                                                             `login_details`.`date_registered`, 
                                                         
                                                             `title`.`title`, 
                                                         
                                                             `lead_student_status`.`lead_student_status`,
                                                             `lead_student_status`.`lead_student_status_id`,
                                                         
                                                             `login_details`.`verification`,
                                                             `login_details`.`reset_time`,
                                                         
                                                             `users`.`user_fname`,
                                                             `users`.`user_lname`,
                                                         
                                                             `title2`.`title` AS `user_title`,
                                                         
                                                             `sl`.`students_log_id`,
                                                             `sl`.`date_updated` AS `latest_log_date`,
                                                             `sl`.`log` AS `latest_log`,
                                                             `sl`.`note` AS `latest_note`,

                                                             `students_has_intakes`.`students_has_intakes_id` AS `shiId`,

                                                             
                                                         
                                                             `updated_user`.`user_id` AS `updated_user_id`,
                                                             `updated_user`.`user_fname` AS `updated_user_fname`,
                                                             `updated_user`.`user_lname` AS `updated_user_lname`,
                                                         
                                                             `updated_user_title`.`title` AS `updated_user_title`
                                                         
                                                         FROM `students`
                                                         
                                                         INNER JOIN `login_details`
                                                             ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                         
                                                         INNER JOIN `title`
                                                             ON `students`.`title_title_id` = `title`.`title_id`

                                                        INNER JOIN `students_has_intakes`
                                                            ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                                                         
                                                         INNER JOIN `lead_student_status`
                                                             ON `students`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`
                                                         
                                                         INNER JOIN `users`
                                                             ON `students`.`users_user_id` = `users`.`user_id`
                                                         
                                                         INNER JOIN `title` AS `title2`
                                                             ON `users`.`title_title_id` = `title2`.`title_id`
                                                         
                                                         LEFT JOIN `students_logs` AS `sl`
                                                             ON `sl`.`students_log_id` = (
                                                                 SELECT `sl2`.`students_log_id`
                                                                 FROM `students_logs` AS `sl2`
                                                                 WHERE `sl2`.`students_student_id` = `students`.`student_id`
                                                                   AND `sl2`.`lead_student_status_lead_student_status_id` IN (2,3,4)
                                                                 ORDER BY `sl2`.`date_updated` DESC
                                                                 LIMIT 1
                                                             )
                                                         
                                                         LEFT JOIN `users` AS `updated_user`
                                                             ON `sl`.`users_user_id` = `updated_user`.`user_id`
                                                         
                                                         LEFT JOIN `title` AS `updated_user_title`
                                                             ON `updated_user`.`title_title_id` = `updated_user_title`.`title_id`
                                                         
                                                            GROUP BY `students`.`student_id`
                                                         ORDER BY `students`.`student_id` DESC
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

                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_registered"])); ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span></td>
                                                                        <?php
                                                                        if ($data["lead_student_status_id"] == 1) {
                                                                        ?>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text">N/A</span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text">N/A</span></td>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["latest_log_date"])); ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["updated_user_title"] . "." . $data["updated_user_fname"] . " " . $data["updated_user_lname"]; ?></span></td>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                        <td class="py-2 align-middle">
                                                                            <label class="table-btn label-student-status-<?php echo $data["lead_student_status_id"]; ?> text-center w-100"> <?php echo $data["lead_student_status"]; ?></label>
                                                                        </td>

                                                                        <?php
                                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  || SessionUser::ifUserIsAccounts($sessionUserRole)) {
                                                                        ?>
                                                                            <td class="py-3 align-middle">
                                                                                <div class="dropdown">
                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                    <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="inquiry-registration-payment?inquiry-id=<?php echo $data['sId']; ?>">
                                                                                                <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                View</a></li>

                                                                                        <?php
                                                                                        if ($data['lead_student_status_id'] == 4 || $data['lead_student_status_id'] > 4) {
                                                                                        ?>

                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=<?php echo $data['shiId']; ?>&path=payments">
                                                                                                    <iconify-icon icon="stash:plan" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    View Student Payment Plan</a></li>

                                                                                        <?php
                                                                                        }
                                                                                        if ($data['lead_student_status_id'] == 5) {
                                                                                        ?>
                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">


                                                                                            <li>
                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                    onclick="openAccountsConfirmModal(<?= $data['sId']; ?>)">
                                                                                                    <iconify-icon icon="solar:verified-check-bold"
                                                                                                        width="20"
                                                                                                        height="20"
                                                                                                        class="sub-text me-3"></iconify-icon>
                                                                                                    Confirm Accounts Completion
                                                                                                </a>
                                                                                            </li>
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleInquiryRegistrationPaymentsPagination('previousPage');">
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
                                                                            $action = $isActive ? '' : "onclick=\"handleInquiryRegistrationPaymentsPagination('goToPage', '{$i}')\"";
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleInquiryRegistrationPaymentsPagination('nextPage',<?= $number_of_pages ?>);">
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

            <div class="modal fade" id="accountsConfirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow br-12">
                        <div class="modal-body p-4 text-center">

                            <div class="mb-3">
                                <iconify-icon icon="solar:verified-check-bold"
                                    width="54"
                                    height="54"
                                    style="color:#198754;"></iconify-icon>
                            </div>

                            <h5 class="main-text fw-bold mb-2">
                                Confirm Accounts Completion?
                            </h5>

                            <p class="sub-text text-14 mb-4">
                                Are you sure you want to confirm this inquiry as completed by the Accounts Department?
                                Once confirmed, the student payment process will be marked as completed and moved to the next stage.
                            </p>

                            <input type="hidden" id="accountsConfirmStudentId">

                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button"
                                        class="btn btn-light h-48 w-100"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                </div>

                                <div class="col-6">
                                    <button type="button"
                                        class="custom-btn w-100"
                                        onclick="confirmAccountsCompletion(this)">
                                        Yes, Confirm
                                    </button>
                                </div>
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