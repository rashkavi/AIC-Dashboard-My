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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['category-id']) && !empty($_GET['category-id']))) {

            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
                header("Location: ./dashboard");
            } else {

                $chartData = Database::search("
                               SELECT 
                            COUNT(CASE WHEN cpp.payment_status_payment_status_id = 2 THEN 1 END) AS pending_count,
                            COUNT(CASE WHEN cpp.payment_status_payment_status_id = 3 THEN 1 END) AS approved_count,
                            COUNT(CASE WHEN cpp.payment_status_payment_status_id = 4 THEN 1 END) AS rejected_count,
                            COUNT(CASE WHEN cpp.payment_method_payment_method_id = 1 AND cpp.payment_status_payment_status_id = 3 THEN 1 END) AS bank_transfer,
                            COUNT(CASE WHEN cpp.payment_method_payment_method_id = 2 AND cpp.payment_status_payment_status_id = 3 THEN 1 END) AS card_payments,
                            COUNT(CASE WHEN cpp.payment_method_payment_method_id = 3 AND cpp.payment_status_payment_status_id = 3 THEN 1 END) AS cash_payments
                        FROM
                            course_payment_has_payments cpp
            ");

                $chartData = $chartData->fetch_assoc();

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Students Scheduled Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body class="overflow-hidden" onload="initializeStudentsScheduledPayments();">
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
                                    <div class="container p-4 m-0 d-none" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-table.php"; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <span class="main-topic lh-lg">Students Payments</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="payments" data-i18n="payments">Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./students-payment" data-i18n="student-payments">Manage Students Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Students Payments</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                            <div class="col-12 p-0 mb-4">
                                                <div class="row g-0 g-md-4 g-lg-4 gy-4 d-flex">
                                                    <div class="col-12 col-lg-6 d-flex">
                                                        <div class="d-flex flex-column component-theme p-4 w-100">
                                                            <div class="d-flex flex-row">
                                                                <div class="col-8 d-flex flex-column">
                                                                    <span class="main-topic">Payment Status</span>
                                                                </div>
                                                            </div>
                                                            <div class="component-theme d-flex flex-column flex-xl-row align-items-center justify-content-center w-100">
                                                                <div class="h-auto p-0 m-0" id="chart-container">
                                                                    <canvas id="myChart"></canvas>
                                                                </div>
                                                                <div class="col-12 col-xl-5 px-3 pe-0 legend d-flex flex-column gy-3 align-items-start justify-content-start">
                                                                    <div class="d-flex flex-row w-100 mb-3 align-items-center">
                                                                        <span class="grade-legend-item hwr-10" style="background-color:var(--dark-yellow);"></span> Awaiting Payment
                                                                    </div>
                                                                    <div class="d-flex flex-row w-100 mb-3 align-items-center">
                                                                        <span class="grade-legend-item hwr-10" style="background-color:var(--dark-orange);"></span> Awaiting Approval
                                                                    </div>
                                                                    <div class="d-flex flex-row w-100 mb-3 align-items-center">
                                                                        <span class="grade-legend-item hwr-10" style="background-color:var(--dark-green);"></span> Approved
                                                                    </div>
                                                                    <div class="d-flex flex-row w-100 mb-3 align-items-center">
                                                                        <span class="grade-legend-item hwr-10" style="background-color:var(--dark-red);"></span> Rejected
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 d-flex">
                                                        <div class="d-flex flex-column component-theme p-4 w-100">
                                                            <div class="d-flex flex-row">
                                                                <div class="col-8 d-flex flex-column">
                                                                    <span class="main-topic">Payment Source</span>
                                                                </div>

                                                            </div>
                                                            <div class="component-theme d-flex flex-column flex-xl-row align-items-center justify-content-center my-4 w-100">
                                                                <div class="h-auto p-0 m-0" id="chart-container">
                                                                    <canvas id="paymentMethodChart"
                                                                        data-bank="<?= $chartData['bank_transfer']; ?>"
                                                                        data-card="<?= $chartData['card_payments']; ?>"
                                                                        data-cash="<?= $chartData['cash_payments']; ?>"></canvas>
                                                                </div>
                                                                <div class="col-12 col-xl-3 px-4 pe-0 legend d-flex flex-column gy-3 align-items-end justify-content-start">
                                                                    <div class="d-flex flex-row w-100 mb-3 align-items-center">
                                                                        <span class="grade-legend-item hwr-10" style="background-color:var(--dark-orange);"></span> Bank Trnsfers
                                                                    </div>
                                                                    <div class="d-flex flex-row w-100 mb-3 align-items-center">
                                                                        <span class="grade-legend-item hwr-10" style="background-color:var(--dark-pink);"></span> Card Pyaments
                                                                    </div>
                                                                    <div class="d-flex flex-row w-100 mb-3 align-items-center">
                                                                        <span class="grade-legend-item hwr-10" style="background-color:var(--dark-purple);"></span> Direct Pyaments
                                                                    </div>
                                                                </div>
                                                            </div>
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
                                                                    <?php
                                                                    $payments_count = Database::search("SELECT COUNT(*) as 'count' FROM `course_payment_has_payments` WHERE `payment_status_payment_status_id`='1'");
                                                                    $data = $payments_count->fetch_assoc();
                                                                    ?>
                                                                    <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> <span data-i18n="awaiting-payments"></span></span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                    <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                    <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleStudentsPaymentsPagination('filter')">
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-md-12 col-lg-1 d-flex flex-column justify-content-end">
                                                                <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                                    <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                </button>
                                                            </div>
                                                            <div class="collapse" id="filter">
                                                                <div class="card card-body border-0 p-0">
                                                                    <div class="col-12">
                                                                        <div class="row gy-3 justify-content-end">
                                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                <select class="form-select custom-select" aria-label="Default select example" id="status-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                    <option selected value="0">- Select Payment Status -</option>
                                                                                    <?php
                                                                                    $paymentStatus = Database::search("SELECT * FROM `payment_status`");
                                                                                    while ($data = $paymentStatus->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?= $data['payment_status_id'] ?>"><?= $data['payment_status'] ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                    <option selected value="0">- Filter Data -</option>
                                                                                    <option value="1">Bank Transfer</option>
                                                                                    <option value="2">Card Payments</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                <select class="form-select custom-select" name="year" id="year" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                    <option value="0">- Select Year -</option>

                                                                                    <?php

                                                                                    $paymentsScheduled = Database::search("
                                                                                                SELECT 
                                                                                                    MIN(date_updated) AS minDate, 
                                                                                                    MAX(date_updated) AS maxDate 
                                                                                                FROM payment_logs
                                                                                                ");

                                                                                    $minYear = null;
                                                                                    $maxYear = null;

                                                                                    if ($paymentsScheduled->num_rows > 0) {
                                                                                        $row = $paymentsScheduled->fetch_assoc();

                                                                                        if (!empty($row["minDate"]) && !empty($row["maxDate"])) {
                                                                                            $minYear = date("Y", strtotime($row["minDate"]));
                                                                                            $maxYear = date("Y", strtotime($row["maxDate"]));
                                                                                        }
                                                                                    }

                                                                                    if ($minYear === null || $maxYear === null) {
                                                                                        $currentYear = date("Y");
                                                                                        $minYear = $currentYear;
                                                                                        $maxYear = $currentYear;
                                                                                    }

                                                                                    for ($y = $minYear; $y <= $maxYear; $y++) {
                                                                                        echo "<option value='$y'>$y</option>";
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                <select class="form-select custom-select" name="month" id="month" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                    <option value="0">- Select Month -</option>
                                                                                    <?php
                                                                                    for ($m = 1; $m <= 12; $m++) {
                                                                                        echo "<option value='$m'>" . date("F", mktime(0, 0, 0, $m, 1)) . "</option>";
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                    <option value="100" selected>100</option>
                                                                    <option value="200">200</option>
                                                                    <option value="300">300</option>
                                                                    <option value="400">400</option>
                                                                    <option value="500">500</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="payments-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPaymentsPagination('sortByName')">
                                                                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                        </div>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsPaymentsPagination('sortById')">
                                                                                    <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                        <span class="text-14 main-text fw-medium me-2">Admission Number</span>
                                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Fees Type</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Due Date</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Discount</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty Amount </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                                                                    <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                        <span class="text-14 main-text fw-medium me-2">Payable Amount</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsPaymentsPagination('sortByDate')">
                                                                                    <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                        <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </th>

                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment method</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php

                                                                            $query = "
                                                                        SELECT 
                                                                            `cp`.`course_payments_id`,
                                                                            `cpp`.`course_payment_has_payment_id`,
                                                                            `cp`.`discount`,
                                                                            `cp`.`discount_type_discount_type_id`,
                                                                            `cp`.`due_date`,
                                                                            `cpp`.`paid_amount`,
                                                                            `ps`.`payment_status`,
                                                                            `ps`.`payment_status_id`,
                                                                            `pgp`.`payment_type`,
                                                                            `pm`.`payment_method`,
                                                                            `pm`.`payment_method_id`,
                                                                            `cp`.`amount`,
                                                                            `cpp`.`date_paid`,
                                                                            `shi`.`student_id`,
                                                                            `ld`.`first_name`,
                                                                            `ld`.`last_name`,
                                                                            `t`.`title`,
                                                                            `s`.`profile_img`,
                                                                            `s`.`student_id` AS `aId`,
                                                                           `cpp`.`panelty_amount` AS `panelty_charge`
                                                                                                                                              
                                                                        FROM `course_payment_has_payments` `cpp`
                                                                        INNER JOIN `course_payments` `cp`
                                                                            ON `cp`.`course_payments_id` = `cpp`.`course_payments_course_payments_id`
                                                                        INNER JOIN `payment_status` `ps` 
                                                                            ON `cpp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                        INNER JOIN `payment_method` `pm` 
                                                                            ON `cpp`.`payment_method_payment_method_id` = `pm`.`payment_method_id`
                                                                        INNER JOIN `payment_type` `pgp`
                                                                            ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
                                                                        INNER JOIN `payment_plan_has_students_has_intakes` `phsi`
                                                                            ON `cp`.`payment_plan_has_students_has_intakes_id` = `phsi`.`payment_plan_has_students_has_intakes_id`
                                                                        INNER JOIN `students_has_intakes` `shi` 
                                                                            ON `phsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                        INNER JOIN `students` `s` 
                                                                            ON `shi`.`students_student_id` = `s`.`student_id`
                                                                        INNER JOIN `login_details` `ld`
                                                                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                                                                        INNER JOIN `title` `t` 
                                                                            ON `s`.`title_title_id` = `t`.`title_id`
                                                                         
                                                                       
                                                                        ORDER BY `cpp`.`course_payment_has_payment_id` DESC
                                                                        ";

                                                                            $results = Database::search($query);
                                                                            $pageno = 1;
                                                                            $results_count = $results->num_rows;
                                                                            $result_per_page = 100;

                                                                            $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                                                                            $page_results = ($pageno - 1) * $result_per_page;

                                                                            $query_paginated = $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results;
                                                                            $users = Database::search($query_paginated);

                                                                            if ($users->num_rows > 0) {
                                                                                while ($data = $users->fetch_assoc()) {
                                                                                    $discount = $data['discount'];
                                                                                    if ($data['discount_type_discount_type_id'] == 3) {
                                                                                        $discount = $data['amount'] * $data['discount'] / 100;
                                                                                    }
                                                                                    $payableAmount = $data['amount'] - $discount + ($data['panelty_charge'] == null ? 0 : $data['panelty_charge']);
                                                                            ?>
                                                                                    <tr>
                                                                                        <td class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <?php
                                                                                                if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
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
                                                                                        </td>

                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["aId"]; ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['payment_type'] ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['due_date'] == '' ? "N/A" : $data['due_date'] ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($data["amount"], 2); ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($discount, 2); ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($data["panelty_charge"], 2); ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($payableAmount, 2); ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($data["paid_amount"], 2); ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date_paid"] == '' ? "N/A" : date("Y-m-d", strtotime($data["date_paid"])); ?>
                                                                                            </span></td>
                                                                                        <td class="py-2 align-middle">
                                                                                            <?php
                                                                                            $status_classes = [
                                                                                                "1"     => "table-btn-orange",
                                                                                                "2"  => "table-btn-pink",
                                                                                                "3"  => "table-btn-purple",
                                                                                                "4"  => "table-btn-yellow-green",
                                                                                                "5"  => "table-btn-purle-pink",
                                                                                            ];
                                                                                            $sbutton_class = $status_classes[$data["payment_method_id"]];
                                                                                            ?>
                                                                                            <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_method"]; ?></button>
                                                                                        </td>
                                                                                        <td class="py-2 align-middle">
                                                                                            <?php
                                                                                            $status_classes = [
                                                                                                "0" => "table-btn-purple",
                                                                                                "1" => "table-btn-yellow",
                                                                                                "2" => "table-btn-orange",
                                                                                                "3" => "table-btn-success",
                                                                                                "4" => "table-btn-danger",
                                                                                                "8" => "table-btn-green-blue",

                                                                                            ];
                                                                                            $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                                                            ?>
                                                                                            <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle">
                                                                                            <div class="dropdown">
                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-payment?payment-id=<?php echo $data['course_payment_has_payment_id']; ?>">
                                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            View</a></li>

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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPaymentsPagination('previousPage')">
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
                                                                                            $action = $isActive ? '' : "onclick=\"handleStudentsPaymentsPagination('goToPage', '{$i}')\"";
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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
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

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php

            }
        }
    }
}
?>