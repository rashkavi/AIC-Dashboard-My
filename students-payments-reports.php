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
                FROM course_payment_has_payments cpp
            ");
            $chartData = $chartData->fetch_assoc();

            /* ================================================================
               CALCULATION RULES (from schema + business logic)
               ================================================================

               CURRENCIES
               ──────────
               course_currency     : payment_category → currencies  (the plan's base currency, e.g. GBP)
               paid_currency       : cpp.currencies_currency_id      (what the student handed over; may differ)
               lkr_currency_id = 1 : always LKR

               EXCHANGE RATE  (stored on cpp row)
               ─────────────────────────────────────
               cpp.currency_exchange_rate / currency_exchange_rate_id
               Meaning: 1 course_currency = X paid_currency
               Conversion: paid_amount_in_course = paid_amount / exchange_rate

               FIELDS ON course_payment_has_payments (cpp)
               ──────────────────────────────────────────
               paid_amount        : amount student actually paid  (in PAID currency)
               discount_amount    : receipt-level discount         (in COURSE currency)
               panelty_amount     : receipt-level penalty          (in PAID currency)  ← paid currency!
               tax_rate           : % applied to the balance
               tax_amount         : tax charged on this receipt    (in PAID currency)  ← paid currency!

               FIELDS ON panelty_payments (separate penalty log)
               ─────────────────────────────────────────────────
               amount             : ALWAYS in LKR regardless of course currency

               CP-LEVEL DISCOUNT  (course_payments.discount)
               ──────────────────────────────────────────────
               discount_type_id = 2 → fixed amount  (already in course currency)
               discount_type_id = 3 → percentage    (cp.amount / 100 * cp.discount)
               otherwise → 0

               CORRECT FORMULA  (all values in course currency)
               ──────────────────────────────────────────────────
               cp_discount        = fixed or % discount from course_payments   [course cur]
               receipt_discount   = cpp.discount_amount                        [course cur — no convert]
               total_discount     = cp_discount + receipt_discount

               receipt_penalty_cc = cpp.panelty_amount / exchange_rate         [paid→course]
               main_penalty_lkr   = SUM(panelty_payments.amount)               [always LKR]
               main_penalty_cc    = IF course==LKR: main_penalty_lkr
                                    ELSE:           main_penalty_lkr / exchange_rate
               total_penalty      = receipt_penalty_cc + main_penalty_cc

               tax_cc             = cpp.tax_amount / exchange_rate             [paid→course]

               payable_amount     = cp.amount - total_discount + total_penalty + tax_cc

               paid_in_course     = cpp.paid_amount / exchange_rate            [paid→course]
               balance            = payable_amount - paid_in_course
               ================================================================ */

            function resolveExchangeRate(array $row, int $courseCurrencyId): ?float
            {
                /* No paid-currency recorded, or same as course → no conversion */
                $paidCurrId = (int)($row['paid_currency_id'] ?? 0);
                if (!$paidCurrId || $paidCurrId === $courseCurrencyId) {
                    return null;
                }
                /* 1) Inline rate on the receipt row */
                if (!empty($row['currency_exchange_rate']) && (float)$row['currency_exchange_rate'] > 0) {
                    return (float)$row['currency_exchange_rate'];
                }
                /* 2) Look up from currency_exchange_rates table */
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

            /* Convert an amount from PAID currency to COURSE currency.
               rate = 1 course_cur = X paid_cur  →  paid ÷ rate = course */
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
                <title>Students Scheduled Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>
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
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleStudentsPaymentsPagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" data-bs-target="#downloadReport" data-bs-toggle="modal">
                                                                <span class="text-14 fw-semibold">Export Report</span>
                                                                <iconify-icon icon="line-md:download-loop" width="26" height="26" class="text-white px-3 pe-0"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="filter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3 justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="status-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option selected value="0">- Select Payment Status -</option>
                                                                                <?php
                                                                                $paymentStatus = Database::search("SELECT * FROM `payment_status`");
                                                                                while ($ps = $paymentStatus->fetch_assoc()) {
                                                                                    echo "<option value='{$ps['payment_status_id']}'>{$ps['payment_status']}</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="sort-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option selected value="0">- Filter Data -</option>
                                                                                <option value="1">Bank Transfer</option>
                                                                                <option value="2">Card Payments</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" name="year" id="year" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option value="0">- Select Year -</option>
                                                                                <?php
                                                                                $yr = Database::search("SELECT MIN(date_updated) AS minDate, MAX(date_updated) AS maxDate FROM payment_logs");
                                                                                $yrRow = $yr->fetch_assoc();
                                                                                $minYear = !empty($yrRow['minDate']) ? date('Y', strtotime($yrRow['minDate'])) : date('Y');
                                                                                $maxYear = !empty($yrRow['maxDate']) ? date('Y', strtotime($yrRow['maxDate'])) : date('Y');
                                                                                for ($y = $minYear; $y <= $maxYear; $y++) echo "<option value='$y'>$y</option>";
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" name="month" id="month" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option value="0">- Select Month -</option>
                                                                                <?php for ($m = 1; $m <= 12; $m++) echo "<option value='$m'>" . date("F", mktime(0, 0, 0, $m, 1)) . "</option>"; ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleStudentsPaymentsPagination('filter')">
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
                                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPaymentsPagination('sortByName')">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                        <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                            </th>

                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Fees Type</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Tax</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payable</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsPaymentsPagination('sortByDate')">
                                                                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Method</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        /*
                                                                         * NOTE ON COLUMNS FETCHED:
                                                                         *
                                                                         * cp.amount           — course-plan instalment amount (course currency)
                                                                         * cp.discount         — plan-level discount value (fixed amount or %)
                                                                         * cp.discount_type_discount_type_id
                                                                         *                     — 2=fixed(course cur), 3=percentage
                                                                         * cpp.discount_amount — receipt-level extra discount (course currency)
                                                                         * cpp.panelty_amount  — receipt-level penalty (PAID currency)
                                                                         * cpp.tax_rate        — % applied
                                                                         * cpp.tax_amount      — tax charged (PAID currency)
                                                                         * cpp.paid_amount     — amount the student paid (PAID currency)
                                                                         * pp.penalty_lkr      — cumulative penalty from panelty_payments (ALWAYS LKR)
                                                                         * cpp.currencies_currency_id  — paid currency FK
                                                                         * cpp.currency_exchange_rate   — inline rate: 1 course_cur = X paid_cur
                                                                         * course_cur.*        — course (plan) currency
                                                                         * paid_cur.*          — receipt (paid) currency
                                                                         */
                                                                        $query = "
                                                                            SELECT
                                                                                cp.course_payments_id,
                                                                                cpp.course_payment_has_payment_id,

                                                                                cp.amount,
                                                                                cp.discount,
                                                                                cp.discount_type_discount_type_id,
                                                                                cp.due_date,

                                                                                cpp.paid_amount,
                                                                                cpp.discount_amount,
                                                                                cpp.panelty_amount          AS receipt_penalty_paid_cur,
                                                                                cpp.tax_rate,
                                                                                cpp.tax_amount              AS receipt_tax_paid_cur,
                                                                                cpp.currencies_currency_id  AS paid_currency_id,
                                                                                cpp.currency_exchange_rate,
                                                                                cpp.currency_exchange_rate_id,
                                                                                cpp.date_paid,

                                                                                ps.payment_status,
                                                                                ps.payment_status_id,
                                                                                pgp.payment_type,
                                                                                pm.payment_method,
                                                                                pm.payment_method_id,

                                                                                shi.student_id,
                                                                                ld.first_name,
                                                                                ld.last_name,
                                                                                t.title,
                                                                                s.profile_img,
                                                                                s.student_id AS aId,

                                                                                /* penalty_lkr: cumulative from panelty_payments — always LKR */
                                                                                IFNULL(pp.penalty_lkr, 0) AS penalty_lkr,

                                                                                /* course currency */
                                                                                course_cur.currency_id   AS course_currency_id,
                                                                                course_cur.code          AS course_currency_code,
                                                                                course_cur.decimal_places AS course_decimal_places,

                                                                                /* paid currency */
                                                                                COALESCE(paid_cur.code, course_cur.code)
                                                                                                         AS paid_currency_code,
                                                                                COALESCE(paid_cur.decimal_places, course_cur.decimal_places)
                                                                                                         AS paid_decimal_places,

                                                                                /* LKR currency id for penalty conversion */
                                                                                1 AS lkr_currency_id

                                                                            FROM course_payment_has_payments cpp

                                                                            INNER JOIN course_payments cp
                                                                                ON cp.course_payments_id = cpp.course_payments_course_payments_id

                                                                            INNER JOIN payment_status ps
                                                                                ON cpp.payment_status_payment_status_id = ps.payment_status_id

                                                                            INNER JOIN payment_method pm
                                                                                ON cpp.payment_method_payment_method_id = pm.payment_method_id

                                                                            INNER JOIN payment_type pgp
                                                                                ON cp.payment_type_payment_type_id = pgp.payment_type_id

                                                                            INNER JOIN payment_plan_has_students_has_intakes phsi
                                                                                ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

                                                                            INNER JOIN payment_category pc
                                                                                ON phsi.payment_category_payment_category_id = pc.payment_category_id

                                                                            /* Course currency: from payment_category */
                                                                            INNER JOIN currencies course_cur
                                                                                ON pc.currencies_currency_id = course_cur.currency_id

                                                                            /* Paid currency: nullable (student may pay in same currency) */
                                                                            LEFT JOIN currencies paid_cur
                                                                                ON cpp.currencies_currency_id = paid_cur.currency_id

                                                                            INNER JOIN students_has_intakes shi
                                                                                ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                                                                            INNER JOIN students s
                                                                                ON shi.students_student_id = s.student_id

                                                                            INNER JOIN login_details ld
                                                                                ON s.login_details_login_details_id = ld.login_details_id

                                                                            INNER JOIN title t
                                                                                ON s.title_title_id = t.title_id

                                                                            /* Cumulative penalty log — amount always in LKR */
                                                                            LEFT JOIN (
                                                                                SELECT
                                                                                    course_payments_course_payments_id,
                                                                                    SUM(amount) AS penalty_lkr
                                                                                FROM panelty_payments
                                                                                GROUP BY course_payments_course_payments_id
                                                                            ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id

                                                                            ORDER BY cpp.course_payment_has_payment_id DESC
                                                                        ";

                                                                        $results         = Database::search($query);
                                                                        $results_count   = $results->num_rows;
                                                                        $result_per_page = 10;
                                                                        $pageno          = 1;
                                                                        $page_results    = ($pageno - 1) * $result_per_page;
                                                                        $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;

                                                                        $payments = Database::search($query . " LIMIT $result_per_page OFFSET $page_results");

                                                                        if ($payments && $payments->num_rows > 0):
                                                                            while ($data = $payments->fetch_assoc()):

                                                                                /* ── Exchange rate ───────────────────────────────────
                                                                                   rate: 1 course_cur = X paid_cur
                                                                                   null → same currency, no conversion needed           */
                                                                                $rate = resolveExchangeRate($data, (int)$data['course_currency_id']);

                                                                                /* ── Plan-level (CP) discount → always course currency ─
                                                                                   type 3 = percentage of cp.amount
                                                                                   type 2 = fixed amount (course currency)
                                                                                   other  = 0                                           */
                                                                                $cpDiscount = 0.0;
                                                                                if ($data['discount_type_discount_type_id'] == 3) {
                                                                                    $cpDiscount = (float)$data['amount'] / 100 * (float)$data['discount'];
                                                                                } elseif ($data['discount_type_discount_type_id'] == 2) {
                                                                                    $cpDiscount = (float)$data['discount'];
                                                                                }

                                                                                /* ── Receipt-level discount → stored in course currency ─
                                                                                   cpp.discount_amount is always course currency per schema */
                                                                                $receiptDiscount = (float)$data['discount_amount'];
                                                                                $totalDiscount   = $cpDiscount + $receiptDiscount;

                                                                                /* ── Receipt-level penalty → stored in PAID currency ────
                                                                                   Convert to course currency for display                  */
                                                                                $receiptPenaltyCc = toCourse((float)($data['receipt_penalty_paid_cur'] ?? 0), $rate);

                                                                                /* ── Cumulative penalty from panelty_payments → always LKR
                                                                                   If course currency is LKR: use as-is
                                                                                   If course currency is foreign: convert LKR → course
                                                                                     rate means 1 course = X paid (paid often = LKR here)
                                                                                     So: lkr_amount / rate = course_amount                 */
                                                                                $penaltyLkr = (float)($data['penalty_lkr'] ?? 0);
                                                                                $isCourseLkr = ((int)$data['course_currency_id'] === 1);
                                                                                if ($isCourseLkr || $rate === null) {
                                                                                    /* course IS lkr, or same currency: penalty already in course cur */
                                                                                    $mainPenaltyCc = $penaltyLkr;
                                                                                } else {
                                                                                    /* course is foreign (e.g. GBP), penalty in LKR
                                                                                       rate = 1 GBP = X LKR → lkr / rate = gbp           */
                                                                                    $mainPenaltyCc = ($rate > 0) ? $penaltyLkr / $rate : $penaltyLkr;
                                                                                }
                                                                                $totalPenalty = $receiptPenaltyCc + $mainPenaltyCc;

                                                                                /* ── Tax → stored in PAID currency, convert to course ── */
                                                                                $taxCc = toCourse((float)($data['receipt_tax_paid_cur'] ?? 0), $rate);

                                                                                /* ── Payable = amount - discounts + penalties + tax ──── */
                                                                                $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $taxCc;

                                                                                /* ── Paid amount → convert from paid currency to course ─ */
                                                                                $paidInCourse = toCourse((float)$data['paid_amount'], $rate);

                                                                                /* ── Display helpers ──────────────────────────────────── */
                                                                                $cc   = htmlspecialchars($data['course_currency_code']);
                                                                                $dp   = (int)$data['course_decimal_places'];
                                                                                $pcc  = htmlspecialchars($data['paid_currency_code']);
                                                                                $pdp  = (int)$data['paid_decimal_places'];
                                                                                $isForeign = ($rate !== null);

                                                                                $status_classes_method = [
                                                                                    "1" => "table-btn-orange",
                                                                                    "2" => "table-btn-pink",
                                                                                    "3" => "table-btn-purple",
                                                                                    "4" => "table-btn-yellow-green",
                                                                                    "5" => "table-btn-purle-pink",
                                                                                ];
                                                                                $status_classes_payment = [
                                                                                    "0" => "table-btn-purple",
                                                                                    "1" => "table-btn-yellow",
                                                                                    "2" => "table-btn-orange",
                                                                                    "3" => "table-btn-success",
                                                                                    "4" => "table-btn-danger",
                                                                                    "8" => "table-btn-green-blue",
                                                                                ];
                                                                                $mclass = $status_classes_method[$data['payment_method_id']] ?? 'table-btn-yellow';
                                                                                $sclass = $status_classes_payment[$data['payment_status_id']] ?? 'table-btn-yellow';
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

                                                                                    <!-- Amount (course currency — plan instalment) -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $cc . ' ' . number_format((float)$data['amount'], $dp) ?></span>
                                                                                    </td>


                                                                                    <!-- Tax (course currency) — NEW COLUMN -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <?php if ($taxCc > 0): ?>
                                                                                            <span class="text-14 main-text"><?= $cc . ' ' . number_format($taxCc, $dp) ?></span>
                                                                                            <?php if ((float)$data['tax_rate'] > 0): ?>
                                                                                                <br><small class="sub-text" style="font-size:10px;"><?= number_format((float)$data['tax_rate'], 2) ?>%</small>
                                                                                            <?php endif; ?>
                                                                                            <?php if ($isForeign): ?>
                                                                                                <br><small class="sub-text" style="font-size:10px;">
                                                                                                    <?= $pcc . ' ' . number_format((float)$data['receipt_tax_paid_cur'], $pdp) ?>
                                                                                                </small>
                                                                                            <?php endif; ?>
                                                                                        <?php else: ?>
                                                                                            <span class="text-14 sub-text">—</span>
                                                                                        <?php endif; ?>
                                                                                    </td>

                                                                                    <!-- Payable Amount (course currency) -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text fw-medium"><?= $cc . ' ' . number_format($payableAmount, $dp) ?></span>
                                                                                    </td>

                                                                                    <!-- Paid Amount:
                                                                                         Primary = paid currency (what student gave)
                                                                                         Sub     = course currency equivalent (if foreign) -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text"><?= $pcc . ' ' . number_format((float)$data['paid_amount'], $pdp) ?></span>
                                                                                        <?php if ($isForeign): ?>
                                                                                            <br><small class="sub-text" style="font-size:10px;">
                                                                                                ≈ <?= $cc . ' ' . number_format($paidInCourse, $dp) ?>
                                                                                                <?php if ($rate): ?>
                                                                                                    <span style="opacity:.65;">(@ <?= number_format($rate, 2) ?>)</span>
                                                                                                <?php endif; ?>
                                                                                            </small>
                                                                                        <?php endif; ?>
                                                                                    </td>

                                                                                    <!-- Paid Date -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <span class="text-14 main-text">
                                                                                            <?= $data['date_paid'] ? date('Y-m-d', strtotime($data['date_paid'])) : 'N/A' ?>
                                                                                        </span>
                                                                                    </td>

                                                                                    <!-- Payment Method -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <button class="table-btn <?= $mclass ?> w-100"><?= htmlspecialchars($data['payment_method']) ?></button>
                                                                                    </td>

                                                                                    <!-- Status -->
                                                                                    <td class="py-2 align-middle">
                                                                                        <button class="table-btn <?= $sclass ?> w-100"><?= htmlspecialchars($data['payment_status']) ?></button>
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPaymentsPagination('previousPage')">
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
                                                                                        $ac2 = $isActive ? '' : "onclick=\"handleStudentsPaymentsPagination('goToPage', '{$i}')\"";
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