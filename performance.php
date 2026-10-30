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

        // Bucketed lead_student_status_id groups (agreed grouping)
        // 1  Pending Action     : 1,6,7,11
        // 2  Rejected           : 2,9,12
        // 3  In Progress        : 3,4,5,8,10,13
        // 4  Ready to Enroll    : 14,15,16
        // 5  Enrolled           : 17
        $PENDING_IDS     = "1,6,7,11";
        $REJECTED_IDS    = "2,9,12";
        $INPROGRESS_IDS  = "3,4,5,8,10,13";
        $READY_IDS       = "14,15,16";
        $ENROLLED_IDS    = "17";

        /* ── Initial unfiltered page load, page 1. All subsequent
               filtering/sorting/pagination happens via AJAX against
               api/filterPerformance.php, which duplicates this exact
               bucket logic on purpose so the two never disagree. ── */
        $query = "SELECT 
            `users`.`user_id`, 
            `users`.`user_fname`, 
            `users`.`user_lname`, 
            `users`.`user_email`, 
            `users`.`user_mobile`, 
            `users`.`user_reg_date`,
            `users`.`user_verification`,
            `user_roles`.`user_role`, 
            `user_roles`.`user_role_id`, 
            `title`.`title`, 
            `status`.`status`,
            `status`.`status_id`,
            `users`.`profile_img`,

            COUNT(`students_has_intakes`.`students_has_intakes_id`) AS total_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($PENDING_IDS) 
                THEN 1 ELSE 0 
            END) AS pending_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($REJECTED_IDS) 
                THEN 1 ELSE 0 
            END) AS rejected_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($INPROGRESS_IDS) 
                THEN 1 ELSE 0 
            END) AS inprogress_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($READY_IDS) 
                THEN 1 ELSE 0 
            END) AS ready_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($ENROLLED_IDS) 
                THEN 1 ELSE 0 
            END) AS enrolled_students

        FROM `users` 

        INNER JOIN `user_roles` 
            ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id` 

        INNER JOIN `title` 
            ON `users`.`title_title_id` = `title`.`title_id` 

        INNER JOIN `status` 
            ON `users`.`status_status_id` = `status`.`status_id`

        LEFT JOIN `students_has_intakes` 
            ON `users`.`user_id` = `students_has_intakes`.`counselor_user_id`
            AND `students_has_intakes`.`lead_student_status_lead_student_status_id` IS NOT NULL

        WHERE `user_roles`.`user_role_id` IN (3,9)
        AND `status`.`status_id` = 1

        GROUP BY `users`.`user_id`

        ORDER BY `users`.`user_reg_date` DESC";

        $results = Database::search($query);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Performance - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">

            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body>
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("Performance");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Performance");
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
                                            <div class="col-12 col-md-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="">Performance</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="users-desc">Effortlessly manage users, access profiles, and maintain oversight</span>
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

                                                            <span class="text-14 dark-theme fw-medium p-0 m-0" id="user-count-label"><?php echo $results->num_rows ?> <span data-i18n="users"></span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handlePerformancePagination('filter')">
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="col-12 collapse" id="filterCollapse">
                                                        <div class="card card-body border-0 p-0">
                                                            <div class="col-12">
                                                                <div class="row gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="branch-selector" class="form-select custom-select mt-2" onchange="loadFaculties(),handlePerformancePagination('filter');">
                                                                            <option value="0" selected>- Select Branch -</option>
                                                                            <?php
                                                                            $branches = Database::search("SELECT * FROM `branches`");
                                                                            while ($bData = $branches->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $bData["branch_id"]; ?>"><?php echo $bData["branch"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="branch-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="faculty-selector" disabled class="form-select custom-select mt-2" onchange="loadProgrammes(),handlePerformancePagination('filter');">
                                                                            <option value="0" selected>- Select Faculty -</option>
                                                                        </select>
                                                                        <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes(),handlePerformancePagination('filter');">
                                                                            <option value="0" selected>- Select Programme -</option>
                                                                        </select>
                                                                        <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="handlePerformancePagination('filter');">
                                                                            <option value="0" selected>- Select Intake -</option>
                                                                        </select>
                                                                        <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="show-result-selector" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handlePerformancePagination('filter')">
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
                                                                    <tr class="br-t-5 align-content-center">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                <div class="d-flex justify-content-between align-items-center w-100" onclick="handlePerformancePagination('sortByName')">
                                                                                    <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Pending Action</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Rejected</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">In Progress</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Ready to Enroll</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Enrolled</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Total</th>
                                                                        <?php

                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4) || UserPermissions::hasPermission('users', 10)) {
                                                                        ?>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php
                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = 10;

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                    if ($users->num_rows > 0) {
                                                                        while ($data = $users->fetch_assoc()) {
                                                                            $sbutton_class = "label-role-" . $data["user_role_id"];
                                                                            $reportUserName = $data["title"] . ". " . $data["user_fname"] . " " . $data["user_lname"];

                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                                                        <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
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
                                                                                    </div>
                                                                                </td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["pending_students"]); ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["rejected_students"]); ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["inprogress_students"]); ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["ready_students"]); ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["enrolled_students"]); ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["total_students"]); ?></span></td>

                                                                                <td class="py-3 align-middle">
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="counselor-performance?user-id=<?= $data['user_id']; ?>">
                                                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                    View</a></li>
                                                                                            <!-- <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="javascript:void(0)" onclick="openGenerateReportModal(<?php echo (int) $data['user_id']; ?>, '<?php echo htmlspecialchars($reportUserName, ENT_QUOTES); ?>')">
                                                                                                        <iconify-icon icon="mdi:report-bar" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                        Genarate Report</a></li> -->
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handlePerformancePagination('previousPage')">
                                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                                                        </button>
                                                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                            <ul class="pagination-list d-flex mb-0">
                                                                                <?php
                                                                                $shudderPages1 = false;
                                                                                $shudderPages2 = false;

                                                                                function renderPageButton($i, $isActive = false)
                                                                                {
                                                                                    $activeClass = $isActive ? 'active' : '';
                                                                                    $action = $isActive ? '' : "onclick=\"handlePerformancePagination('goToPage', '{$i}')\"";
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handlePerformancePagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                            <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
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

            <!-- Generate Performance Report Modal -->
            <div class="modal fade" id="generateReportModal" tabindex="-1" aria-labelledby="generateReportModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 br-10">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-semibold main-text" id="generateReportModalLabel">Generate Performance Report</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pt-3">
                            <input type="hidden" id="report-user-id" value="">
                            <div class="mb-3">
                                <label class="form-label sub-text text-14 mb-1 required">Counselor</label>
                                <input type="text" class="form-control custom-input" id="report-user-name" readonly>
                            </div>
                            <div class="mb-1">
                                <label for="report-date" class="form-label sub-text text-14 mb-1 required">Report Date</label>
                                <input type="date" class="form-control custom-input" id="report-date">
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="custom-btn px-4" id="generate-report-btn" onclick="generateCounselorPerformanceReport(this)">
                                <span id="generate-report-btn-text">Generate</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/performance.js?v=<?= Project::getVersion() ?>"></script>
          
        </body>

        </html>
<?php
    }
}
?>