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

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
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
                FROM course_payment_has_payments cpp
            ");
            $chartData = $chartData->fetch_assoc();

            /* ================================================================
               CALCULATION RULES — kept byte-for-byte identical to
               students-outstanding-balances-report.php (the Excel export)
               so this page never disagrees with the exported numbers.
               Scoped per course_payment (installment), NOT per payment plan.
               ================================================================ */

            function resolveExchangeRate(array $row, int $courseCurrencyId): ?float
            {
                $paidCurrId = (int)($row['currencies_currency_id'] ?? 0);
                if (!$paidCurrId || $paidCurrId === $courseCurrencyId) {
                    return null;
                }
                if (!empty($row['currency_exchange_rate']) && (float)$row['currency_exchange_rate'] > 0) {
                    return (float)$row['currency_exchange_rate'];
                }
                if (!empty($row['currency_exchange_rate_id'])) {
                    $rr = Database::search(
                        "SELECT `rate` FROM `currency_exchange_rates`
                         WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "'
                         LIMIT 1"
                    );
                    if ($rr && $rr->num_rows > 0) {
                        $r = $rr->fetch_assoc();
                        if ((float)$r['rate'] > 0) return (float)$r['rate'];
                    }
                }
                return null;
            }

            function toCourse(float $amount, ?float $rate): float
            {
                if ($rate === null || $rate <= 0) return $amount;
                return $amount / $rate;
            }

            /* panelty_payments.amount is always LKR — convert into course currency */
            function convertPenaltyLkr(float $lkrAmount, int $courseCurrencyId): float
            {
                static $lkrCurrencyId = null;
                if ($lkrCurrencyId === null) {
                    $res = Database::search("SELECT `currency_id` FROM `currencies` WHERE `code` = 'LKR' LIMIT 1");
                    $lkrCurrencyId = ($res && $res->num_rows > 0) ? (int)$res->fetch_assoc()['currency_id'] : 0;
                }
                if ($lkrCurrencyId === 0 || $courseCurrencyId == $lkrCurrencyId) {
                    return $lkrAmount;
                }
                $rateRow = Database::search(
                    "SELECT `rate` FROM `currency_exchange_rates`
                     WHERE `base_currency_id` = '" . (int)$courseCurrencyId . "'
                     ORDER BY `currency_exchange_rate_id` DESC LIMIT 1"
                );
                if ($rateRow && $rateRow->num_rows > 0) {
                    $rate = (float)$rateRow->fetch_assoc()['rate'];
                    if ($rate > 0) return $lkrAmount / $rate;
                }
                return $lkrAmount;
            }

            /* Discount / penalty / tax / settled totals for ONE course_payment
               (installment), summed across its receipts — identical to the
               function used by the Excel export. */
            function getInstallmentSummary(int $cpId, int $courseCurrencyId): array
            {
                $discounts = 0;
                $penalties = 0;
                $tax = 0;
                $settled = 0;

                $res = Database::search("
                    SELECT paid_amount, discount_amount, panelty_amount, tax_amount,
                           currencies_currency_id, currency_exchange_rate,
                           currency_exchange_rate_id, payment_status_payment_status_id
                    FROM course_payment_has_payments
                    WHERE course_payments_course_payments_id = '" . $cpId . "'
                    AND payment_status_payment_status_id IN (2, 3)
                ");
                if ($res && $res->num_rows > 0) {
                    while ($r = $res->fetch_assoc()) {
                        $rate = resolveExchangeRate($r, $courseCurrencyId);
                        $discounts += toCourse((float)$r['discount_amount'], $rate);
                        $penalties += toCourse((float)$r['panelty_amount'], $rate);

                        if ((int)$r['payment_status_payment_status_id'] === 3) {
                            $settled += toCourse((float)$r['paid_amount'], $rate);
                            $tax     += toCourse((float)$r['tax_amount'], $rate);
                        }
                    }
                }

                $penaltyLkrConverted = 0;
                $pr = Database::search("
                    SELECT SUM(amount) AS total_lkr_penalty
                    FROM panelty_payments
                    WHERE course_payments_course_payments_id = '" . $cpId . "'
                ");
                if ($pr && $pr->num_rows > 0) {
                    $prRow = $pr->fetch_assoc();
                    if (!empty($prRow['total_lkr_penalty'])) {
                        $penaltyLkrConverted = convertPenaltyLkr((float)$prRow['total_lkr_penalty'], $courseCurrencyId);
                    }
                }

                return [
                    'discounts' => $discounts,
                    'penalties' => $penalties + $penaltyLkrConverted,
                    'tax'       => $tax,
                    'settled'   => $settled,
                ];
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Students Outstanding Balances Report - Admin Dashboard | <?= Project::getBaseName() ?></title>
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
                            <?php include "includes/side-menu.php";
                            load_side_menu::load("Payments"); ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php include "./includes/header.php";
                                    selectItem::setSelected("Payments"); ?>
                                </div>
                                <div class="container p-4 m-0 d-none" id="skeleton-list">
                                    <?php include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                                    <div class="row p-4 m-0 g-0">

                                        <!-- Page Header -->
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <span class="main-topic lh-lg">Students Outstanding Balances</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="payments" data-i18n="payments">Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./students-payment" data-i18n="student-payments">Manage Students Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Outstanding Balances</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <!-- Table Section -->
                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $payments_count = Database::search("SELECT COUNT(*) as 'count' FROM `course_payment_has_payments` WHERE `payment_status_payment_status_id`='1'");
                                                                $cnt = $payments_count->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?= $cnt['count'] ?> <span data-i18n="awaiting-payments"></span></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-7 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleStudentsOutstandingBalancesReportPagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" onclick="genarateStudentsOutstandingBalancesReport(this)">
                                                                <span class="text-14 fw-semibold">Export Report</span>
                                                                <iconify-icon icon="line-md:download-loop" width="26" height="26" class="text-white px-3 pe-0"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="filter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3 justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="branch-selector" class="form-select custom-select mt-2" onchange="loadFaculties();">
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
                                                                            <select name="" id="faculty-selector" disabled class="form-select custom-select mt-2" onchange="loadProgrammes();">
                                                                                <option value="0" selected>- Select -</option>

                                                                            </select>
                                                                            <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                                                <option value="0" selected>- Select -</option>
                                                                            </select>
                                                                            <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="loadLevels();">
                                                                                <option value="0" selected>- Select -</option>
                                                                            </select>
                                                                            <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="filter-selector" onchange="enableDateSelector(this)">
                                                                                <option selected value="0">- Filter -</option>
                                                                                <option value="1">This Month</option>
                                                                                <option value="2">Last Month</option>
                                                                                <option value="3">Filter Date Range</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none" id="from-date-container">
                                                                            <input type="text"
                                                                                class="form-control custom-input mt-2"
                                                                                id="from-date"
                                                                                placeholder="From Date"
                                                                                onfocus="this.type='date'"
                                                                                onblur="if(!this.value)this.type='text'" onchange="handleBalanceDueReportPagination('filter')">
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none" id="to-date-container">
                                                                            <input type="text"
                                                                                id="to-date"
                                                                                class="form-control custom-input mt-2"
                                                                                placeholder="To Date"
                                                                                onfocus="this.type='date'"
                                                                                onblur="if(!this.value)this.type='text'" onchange="handleBalanceDueReportPagination('filter')">
                                                                        </div>

                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">

                                                                            <select class="form-select custom-select" id="status-selector" onchange="handleStudentsOutstandingBalancesReportPagination('filter')">
                                                                                <option selected value="0">- Select Payment Status -</option>
                                                                                <?php
                                                                                $paymentStatus = Database::search("SELECT * FROM `payment_status`");
                                                                                while ($ps = $paymentStatus->fetch_assoc()) {
                                                                                    echo "<option value='{$ps['payment_status_id']}'>{$ps['payment_status']}</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleStudentsOutstandingBalancesReportPagination('filter')">
                                                                <option value="10" selected>10</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="150">150</option>
                                                                <option value="200">200</option>
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
                                                                            <th class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsOutstandingBalancesReportPagination('sortByName')">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                        <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                            </th>

                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Fees Type</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payable Amount</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Discount</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Tax Paid</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Settled Amount</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsOutstandingBalancesReportPagination('sortByBalance')">
                                                                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Outstanding Balance</span>
                                                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        /* ============================================================
                                                                           Query rebuilt to run per course_payment (installment) —
                                                                           identical shape to students-outstanding-balances-report.php —
                                                                           instead of aggregating a whole payment plan into one row.
                                                                           ============================================================ */
                                                                        $query = "
                                                                            SELECT
                                                                                cp.course_payments_id,
                                                                                cp.amount,
                                                                                cp.discount,
                                                                                cp.discount_type_discount_type_id,
                                                                                cp.due_date,

                                                                                pt.payment_type,
                                                                                pc.payment_category,

                                                                                c.code,
                                                                                c.decimal_places,
                                                                                c.currency_id AS course_currency_id,

                                                                                shi.student_id AS shiStudentId,
                                                                                shi.status_status_id,

                                                                                s.mobile,
                                                                                s.student_id AS aId,
                                                                                s.profile_img,

                                                                                ld.first_name,
                                                                                ld.last_name,
                                                                                ld.email,
                                                                                t.title,

                                                                                i.intake,
                                                                                i.intake_id,

                                                                                st.status

                                                                            FROM course_payments AS cp

                                                                            INNER JOIN payment_type AS pt
                                                                                ON cp.payment_type_payment_type_id = pt.payment_type_id

                                                                            INNER JOIN payment_plan_has_students_has_intakes AS phsi
                                                                                ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

                                                                            INNER JOIN payment_category AS pc
                                                                                ON phsi.payment_category_payment_category_id = pc.payment_category_id

                                                                            INNER JOIN currencies AS c
                                                                                ON pc.currencies_currency_id = c.currency_id

                                                                            INNER JOIN students_has_intakes AS shi
                                                                                ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                                                                            INNER JOIN students AS s
                                                                                ON shi.students_student_id = s.student_id

                                                                            INNER JOIN login_details AS ld
                                                                                ON s.login_details_login_details_id = ld.login_details_id

                                                                            INNER JOIN title AS t
                                                                                ON s.title_title_id = t.title_id

                                                                            INNER JOIN intakes AS i
                                                                                ON shi.intakes_intake_id = i.intake_id

                                                                            INNER JOIN programmes AS prog
                                                                                ON i.programmes_programme_id = prog.programme_id

                                                                            INNER JOIN faculties AS fac
                                                                                ON prog.faculties_faculty_id = fac.faculty_id

                                                                            INNER JOIN status AS st
                                                                                ON shi.status_status_id = st.status_id

                                                                            WHERE cp.payment_status_payment_status_id != 5
                                                                        ";

                                                                        $text   = isset($_GET['text']) ? Database::escape($_GET['text']) : "";
                                                                        $branch = isset($_GET['branch']) ? Database::escape($_GET['branch']) : "";
                                                                        $faculty = isset($_GET['faculty']) ? Database::escape($_GET['faculty']) : "";
                                                                        $programme = isset($_GET['programme']) ? Database::escape($_GET['programme']) : "";
                                                                        $intake = isset($_GET['intake']) ? Database::escape($_GET['intake']) : "";
                                                                        $status = isset($_GET['status']) ? Database::escape($_GET['status']) : "";

                                                                        if (!empty($text)) {
                                                                            $query .= " AND (
                                                                                s.student_id LIKE '%$text%'
                                                                                OR ld.first_name LIKE '%$text%'
                                                                                OR ld.last_name LIKE '%$text%'
                                                                                OR ld.email LIKE '%$text%'
                                                                                OR s.mobile LIKE '%$text%'
                                                                            ) ";
                                                                        }
                                                                        // TODO: confirm faculties.branches_branch_id is the real FK to branches
                                                                        if (!empty($branch) && $branch != '0') {
                                                                            $query .= " AND fac.branches_branch_id = '$branch' ";
                                                                        }
                                                                        if (!empty($faculty) && $faculty != '0') {
                                                                            $query .= " AND fac.faculty_id = '$faculty' ";
                                                                        }
                                                                        if (!empty($programme) && $programme != '0') {
                                                                            $query .= " AND prog.programme_id = '$programme' ";
                                                                        }
                                                                        if (!empty($intake) && $intake != '0') {
                                                                            $query .= " AND i.intake_id = '$intake' ";
                                                                        }
                                                                        if (!empty($status) && $status != '0') {
                                                                            $query .= " AND EXISTS (
                                                                                SELECT 1 FROM course_payment_has_payments cpp2
                                                                                WHERE cpp2.course_payments_course_payments_id = cp.course_payments_id
                                                                                AND cpp2.payment_status_payment_status_id = '$status'
                                                                            ) ";
                                                                        }

                                                                        $query .= " ORDER BY cp.course_payments_id DESC ";

                                                                        $results         = Database::search($query);
                                                                        $results_count   = $results ? $results->num_rows : 0;
                                                                        $result_per_page = 10;
                                                                        $pageno          = 1;
                                                                        $page_results    = ($pageno - 1) * $result_per_page;
                                                                        $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;

                                                                        $plans = Database::search($query . " LIMIT $result_per_page OFFSET $page_results");

                                                                        $anyRowsRendered = false;

                                                                        if ($plans && $plans->num_rows > 0):
                                                                            while ($data = $plans->fetch_assoc()):

                                                                                $cpDiscount = 0.0;
                                                                                if ($data['discount_type_discount_type_id'] == 3) {
                                                                                    $cpDiscount = (float)$data['amount'] / 100 * (float)$data['discount'];
                                                                                } elseif ($data['discount_type_discount_type_id'] == 2) {
                                                                                    $cpDiscount = (float)$data['discount'];
                                                                                }

                                                                                $summary = getInstallmentSummary((int)$data['course_payments_id'], (int)$data['course_currency_id']);

                                                                                $totalDiscount = $cpDiscount + $summary['discounts'];
                                                                                $totalPenalty  = $summary['penalties'];
                                                                                $totalTax      = $summary['tax'];
                                                                                $settled       = $summary['settled'];

                                                                                $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $totalTax;
                                                                                $outstandingBalance = $payableAmount - $settled;
                                                                                if ($outstandingBalance < 0) $outstandingBalance = 0;

                                                                                // Report only shows installments that actually have something outstanding
                                                                                if ($outstandingBalance <= 0.009) {
                                                                                    continue;
                                                                                }
                                                                                $anyRowsRendered = true;

                                                                                $cc = htmlspecialchars($data['code']);
                                                                                $dp = (int)$data['decimal_places'];
                                                                        ?>
                                                                                <tr>
                                                                                    <!-- Student Name -->
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                            <?php if (!empty($data['profile_img']) && file_exists('./assets/imgs/students/' . $data['profile_img'])): ?>
                                                                                                <img src="./assets/imgs/students/<?= $data['profile_img'] ?>" class="hwr-40" alt="Student">
                                                                                            <?php else:
                                                                                                $fi = strtoupper(substr($data['first_name'], 0, 1));
                                                                                                $li = strtoupper(substr($data['last_name'],  0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $fi . $li ?></div>
                                                                                            <?php endif; ?>
                                                                                            <span class="text-14 main-text mx-2"><?= htmlspecialchars($data['title'] . '.' . $data['first_name'] . ' ' . $data['last_name']) ?></span>
                                                                                        </div>
                                                                                    </td>

                                                                                    <!-- Fees Type (payment category) -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= htmlspecialchars($data['payment_category']) ?></span>
                                                                                    </td>

                                                                                    <!-- Payable Amount -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text fw-medium"><?= $cc . ' ' . number_format($payableAmount, $dp) ?></span>
                                                                                    </td>

                                                                                    <!-- Discount -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $totalDiscount > 0 ? $cc . ' ' . number_format($totalDiscount, $dp) : '—' ?></span>
                                                                                    </td>

                                                                                    <!-- Penalty -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $totalPenalty > 0 ? $cc . ' ' . number_format($totalPenalty, $dp) : '—' ?></span>
                                                                                    </td>

                                                                                    <!-- Tax Paid -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $totalTax > 0 ? $cc . ' ' . number_format($totalTax, $dp) : '—' ?></span>
                                                                                    </td>

                                                                                    <!-- Settled Amount -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $cc . ' ' . number_format($settled, $dp) ?></span>
                                                                                    </td>

                                                                                    <!-- Outstanding Balance -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text fw-semibold text-danger"><?= $cc . ' ' . number_format($outstandingBalance, $dp) ?></span>
                                                                                    </td>


                                                                                </tr>
                                                                            <?php
                                                                            endwhile;
                                                                        endif;

                                                                        if (!$anyRowsRendered):
                                                                            ?>
                                                                            <tr class="justify-content-center align-middle">
                                                                                <td colspan="15" class="align-middle justify-content-center">
                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>

                                                                <!-- Pagination -->
                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                    <nav class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                        <div class="row p-4 align-items-center">
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsOutstandingBalancesReportPagination('previousPage')">
                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                                            </button>
                                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                <ul class="pagination-list d-flex mb-0">
                                                                                    <?php
                                                                                    $shudderPages1 = false;
                                                                                    $shudderPages2 = false;
                                                                                    function renderPageButton($i, $isActive = false)
                                                                                    {
                                                                                        $ac  = $isActive ? 'active' : '';
                                                                                        $ac2 = $isActive ? '' : "onclick=\"handleStudentsOutstandingBalancesReportPagination('goToPage', '{$i}')\"";
                                                                                        echo "<li class='page-item {$ac}' id='page-{$i}'><button class='hw-40' {$ac2}>{$i}</button></li>";
                                                                                    }
                                                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                            renderPageButton($i, $pageno == $i);
                                                                                        } elseif (!$shudderPages1 && $i < $pageno - 1 && $i > 1) {
                                                                                            $shudderPages1 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        } elseif (!$shudderPages2 && $i > $pageno + 1 && $i < $number_of_pages) {
                                                                                            $shudderPages2 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        }
                                                                                    }
                                                                                    ?>
                                                                                </ul>
                                                                            </div>
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsOutstandingBalancesReportPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
                                    <footer><?php include './includes/footer.php' ?></footer>
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
?>