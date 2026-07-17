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

            /* ================================================================
               Same currency-conversion helpers used across the other payment
               reports — kept identical so amounts never disagree.
               ================================================================ */

            function resolveExchangeRate(array $row, int $courseCurrencyId): ?float
            {
                $paidCurrId = (int)($row['paid_currency_id'] ?? 0);
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
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Late Payments Report - Admin Dashboard | <?= Project::getBaseName() ?></title>
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
                                                <span class="main-topic lh-lg">Late Payments</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="payments" data-i18n="payments">Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./students-payment" data-i18n="student-payments">Manage Students Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Late Payments</a></li>
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
                                                                $late_count = Database::search("
                                                                    SELECT COUNT(*) as 'count'
                                                                    FROM `course_payment_has_payments` cpp
                                                                    INNER JOIN `course_payments` cp
                                                                        ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                                                    WHERE cpp.payment_status_payment_status_id = 3
                                                                    AND cpp.date_paid IS NOT NULL
                                                                    AND cp.due_date IS NOT NULL
                                                                    AND DATE(cpp.date_paid) > DATE(cp.due_date)
                                                                ");
                                                                $cnt = $late_count->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?= $cnt['count'] ?> Late Payments</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-7 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleLatePaymentsReportPagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" onclick="genarateLatePaymentsReport(this)">
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
                                                                                onblur="if(!this.value)this.type='text'" onchange="handleLatePaymentsReportPagination('filter')">
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none" id="to-date-container">
                                                                            <input type="text"
                                                                                id="to-date"
                                                                                class="form-control custom-input mt-2"
                                                                                placeholder="To Date"
                                                                                onfocus="this.type='date'"
                                                                                onblur="if(!this.value)this.type='text'" onchange="handleLatePaymentsReportPagination('filter')">
                                                                        </div>

                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <label class="sub-text text-12 mb-1">Min. Days Late</label>
                                                                            <input type="number" min="1" class="form-control custom-input" id="min-days-late" placeholder="e.g. 7" onchange="handleLatePaymentsReportPagination('filter')">
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleLatePaymentsReportPagination('filter')">
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
                                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleLatePaymentsReportPagination('sortByName')">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                        <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                            </th>

                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Fees Type</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleLatePaymentsReportPagination('sortByDueDate')">
                                                                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Due Date</span>
                                                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleLatePaymentsReportPagination('sortByPaidDate')">
                                                                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleLatePaymentsReportPagination('sortByDaysLate')">
                                                                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Days Late</span>
                                                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty Charged</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Method</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        /*
                                                                         * "Late" = an approved receipt (status 3) whose date_paid
                                                                         * fell after the instalment's due_date. Days Late is
                                                                         * DATEDIFF(date_paid, due_date), computed in SQL so
                                                                         * sorting/filtering can use it directly.
                                                                         *
                                                                         * Penalty shown is whatever panelty_payments logged
                                                                         * against this course_payment (always LKR, per your
                                                                         * schema notes) — shown as-is, not currency-converted,
                                                                         * same as the other reports display it.
                                                                         */
                                                                        $query = "
                                                                            SELECT
                                                                                cp.course_payments_id,
                                                                                cpp.course_payment_has_payment_id,

                                                                                cp.amount,
                                                                                cp.due_date,

                                                                                cpp.paid_amount,
                                                                                cpp.date_paid,
                                                                                cpp.currencies_currency_id AS paid_currency_id,
                                                                                cpp.currency_exchange_rate,
                                                                                cpp.currency_exchange_rate_id,

                                                                                pt.payment_type,
                                                                                pm.payment_method,
                                                                                pm.payment_method_id,

                                                                                shi.student_id AS shiStudentId,
                                                                                ld.first_name,
                                                                                ld.last_name,
                                                                                t.title,
                                                                                s.profile_img,
                                                                                s.student_id AS aId,
                                                                                s.mobile,
                                                                                ld.email,

                                                                                i.intake,

                                                                                course_cur.currency_id    AS course_currency_id,
                                                                                course_cur.code           AS course_currency_code,
                                                                                course_cur.decimal_places AS course_decimal_places,
                                                                                COALESCE(paid_cur.code, course_cur.code) AS paid_currency_code,
                                                                                COALESCE(paid_cur.decimal_places, course_cur.decimal_places) AS paid_decimal_places,

                                                                                IFNULL(pp.penalty_amount, 0) AS penalty_amount,

                                                                                DATEDIFF(DATE(cpp.date_paid), DATE(cp.due_date)) AS days_late

                                                                            FROM course_payment_has_payments cpp

                                                                            INNER JOIN course_payments cp
                                                                                ON cp.course_payments_id = cpp.course_payments_course_payments_id

                                                                            INNER JOIN payment_type pt
                                                                                ON cp.payment_type_payment_type_id = pt.payment_type_id

                                                                            INNER JOIN payment_method pm
                                                                                ON cpp.payment_method_payment_method_id = pm.payment_method_id

                                                                            INNER JOIN payment_plan_has_students_has_intakes phsi
                                                                                ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

                                                                            INNER JOIN payment_category pc
                                                                                ON phsi.payment_category_payment_category_id = pc.payment_category_id

                                                                            INNER JOIN currencies course_cur
                                                                                ON pc.currencies_currency_id = course_cur.currency_id

                                                                            LEFT JOIN currencies paid_cur
                                                                                ON cpp.currencies_currency_id = paid_cur.currency_id

                                                                            INNER JOIN students_has_intakes shi
                                                                                ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                                                                            INNER JOIN intakes AS i
                                                                                ON shi.intakes_intake_id = i.intake_id

                                                                            INNER JOIN programmes AS prog
                                                                                ON i.programmes_programme_id = prog.programme_id

                                                                            INNER JOIN faculties AS fac
                                                                                ON prog.faculties_faculty_id = fac.faculty_id

                                                                            INNER JOIN students s
                                                                                ON shi.students_student_id = s.student_id

                                                                            INNER JOIN login_details ld
                                                                                ON s.login_details_login_details_id = ld.login_details_id

                                                                            INNER JOIN title t
                                                                                ON s.title_title_id = t.title_id

                                                                            LEFT JOIN (
                                                                                SELECT course_payments_course_payments_id, SUM(amount) AS penalty_amount
                                                                                FROM panelty_payments
                                                                                GROUP BY course_payments_course_payments_id
                                                                            ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id

                                                                            WHERE cpp.payment_status_payment_status_id = 3
                                                                            AND cpp.date_paid IS NOT NULL
                                                                            AND cp.due_date IS NOT NULL
                                                                            AND DATE(cpp.date_paid) > DATE(cp.due_date)

                                                                            ORDER BY days_late DESC
                                                                        ";

                                                                        $results         = Database::search($query);
                                                                        $results_count   = $results ? $results->num_rows : 0;
                                                                        $result_per_page = 10;
                                                                        $pageno          = 1;
                                                                        $page_results    = ($pageno - 1) * $result_per_page;
                                                                        $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;

                                                                        $payments = Database::search($query . " LIMIT $result_per_page OFFSET $page_results");

                                                                        if ($payments && $payments->num_rows > 0):
                                                                            while ($data = $payments->fetch_assoc()):

                                                                                $rate = resolveExchangeRate($data, (int)$data['course_currency_id']);
                                                                                $paidInCourse = toCourse((float)$data['paid_amount'], $rate);

                                                                                $cc  = htmlspecialchars($data['course_currency_code']);
                                                                                $dp  = (int)$data['course_decimal_places'];
                                                                                $pcc = htmlspecialchars($data['paid_currency_code']);
                                                                                $pdp = (int)$data['paid_decimal_places'];
                                                                                $isForeign = ($rate !== null);

                                                                                $daysLate = (int)$data['days_late'];

                                                                                $status_classes_method = [
                                                                                    "1" => "table-btn-orange",
                                                                                    "2" => "table-btn-pink",
                                                                                    "3" => "table-btn-purple",
                                                                                    "4" => "table-btn-yellow-green",
                                                                                    "5" => "table-btn-purle-pink",
                                                                                ];
                                                                                $mclass = $status_classes_method[$data['payment_method_id']] ?? 'table-btn-yellow';
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

                                                                                    <!-- Fees Type -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= htmlspecialchars($data['payment_type']) ?></span>
                                                                                    </td>

                                                                                    <!-- Due Date -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= date('Y-m-d', strtotime($data['due_date'])) ?></span>
                                                                                    </td>

                                                                                    <!-- Paid Date -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= date('Y-m-d', strtotime($data['date_paid'])) ?></span>
                                                                                    </td>

                                                                                    <!-- Days Late -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <button class="table-btn <?= $daysLate > 30 ? 'table-btn-danger' : 'table-btn-orange' ?> w-100"><?= $daysLate ?> day<?= $daysLate == 1 ? '' : 's' ?></button>
                                                                                    </td>

                                                                                    <!-- Amount -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $cc . ' ' . number_format((float)$data['amount'], $dp) ?></span>
                                                                                    </td>

                                                                                    <!-- Penalty Charged (raw LKR from panelty_payments) -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= ((float)$data['penalty_amount'] > 0) ? 'LKR ' . number_format((float)$data['penalty_amount'], 2) : '—' ?></span>
                                                                                    </td>

                                                                                    <!-- Paid Amount -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $pcc . ' ' . number_format((float)$data['paid_amount'], $pdp) ?></span>
                                                                                        <?php if ($isForeign): ?>
                                                                                            <br><small class="sub-text" style="font-size:10px;">
                                                                                                ≈ <?= $cc . ' ' . number_format($paidInCourse, $dp) ?>
                                                                                            </small>
                                                                                        <?php endif; ?>
                                                                                    </td>

                                                                                    <!-- Payment Method -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <button class="table-btn <?= $mclass ?> w-100"><?= htmlspecialchars($data['payment_method']) ?></button>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php
                                                                            endwhile;
                                                                        else:
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLatePaymentsReportPagination('previousPage')">
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
                                                                                        $ac2 = $isActive ? '' : "onclick=\"handleLatePaymentsReportPagination('goToPage', '{$i}')\"";
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLatePaymentsReportPagination('nextPage',null,'<?= $number_of_pages ?>')">
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