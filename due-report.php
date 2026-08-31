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
               DUE REPORT — CALCULATION RULES
               ================================================================
               One row per course_payments installment that still has an
               outstanding balance (due_amount > 0). Same formula as the
               outstanding-balances export, kept identical on purpose so
               this report never disagrees with it:

                   cpDiscount   = fixed or % discount from course_payments
                   totalDiscount = cpDiscount + receipt-level discounts
                   totalPenalty  = receipt-level panelty_amount only (course
                                    currency). The panelty_payments LOG table
                                    does NOT feed into this — it's shown
                                    separately in the Penalty column, in LKR.
                   totalTax      = tax charged on settled receipts
                   payableAmount = cp.amount - totalDiscount + totalPenalty + totalTax
                   settled       = SUM(paid_amount) on approved receipts
                   dueAmount     = payableAmount - settled   (skip if <= 0)

               Due Amount and Penalty are shown in the COURSE's own currency
               (not converted to LKR) — that's what "Foreign Currency" in the
               column header refers to, since many courses here are priced
               in GBP/USD/EUR.

               PAGINATION NOTE: due amount can't be filtered/sorted purely
               in SQL (it depends on per-installment subqueries), so this
               page fetches every installment matching the location/date/
               status filters, computes due amounts in PHP, then slices the
               page in PHP. Same approach as api/filterDueReport.php. If the
               due-installment volume ever gets very large, this is the
               first place to optimize.
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
                        /* Due Amount's penalty component: ONLY the receipt-level
                           panelty_amount (already in course currency). The
                           separate panelty_payments LOG table does NOT feed
                           into the due-amount math — see penalty_lkr below. */
                        $penalties += toCourse((float)$r['panelty_amount'], $rate);
                        $tax += toCourse((float)$r['tax_amount'], $rate);

                        if ((int)$r['payment_status_payment_status_id'] === 3) {
                            $settled += toCourse((float)$r['paid_amount'], $rate);
                        }
                    }
                }

                /* penalty_lkr: raw SUM from panelty_payments, shown as-is in
                   the Penalty column — always LKR, no conversion, and NOT
                   part of the Due Amount calculation above. */
                $penaltyLkr = 0;
                $pr = Database::search("
                    SELECT SUM(amount) AS total_lkr_penalty
                    FROM panelty_payments
                    WHERE course_payments_course_payments_id = '" . $cpId . "'
                ");
                if ($pr && $pr->num_rows > 0) {
                    $prRow = $pr->fetch_assoc();
                    if (!empty($prRow['total_lkr_penalty'])) {
                        $penaltyLkr = (float)$prRow['total_lkr_penalty'];
                    }
                }

                return [
                    'discounts'   => $discounts,
                    'penalties'   => $penalties,
                    'tax'         => $tax,
                    'settled'     => $settled,
                    'penalty_lkr' => $penaltyLkr,
                ];
            }

            function calculateTaxInclusiveAmount($base, $taxRate)
            {
                $base = (float)$base;
                $taxRate = (float)$taxRate;
                if ($taxRate <= 0 || $base <= 0) {
                    return $base;
                }
                $basePortion = 1 - ($taxRate / 100);
                if ($basePortion <= 0) {
                    return $base;
                }
                return round($base / $basePortion, 2);
            }

            /* ── Fetch every matching installment, compute due amounts,
                   filter to due > 0, sort — pagination happens after ──── */
            function fetchDueInstallments(array $f): array
            {
                $query = "
                    SELECT
                        cp.course_payments_id,
                        cp.amount,
                        cp.discount,
                        cp.discount_type_discount_type_id,
                        cp.due_date,

                        c.code AS currency_code,
                        c.decimal_places,
                        c.currency_id AS course_currency_id,

                        shi.status_status_id,

                        s.student_id AS aId,
                        ld.first_name,
                        ld.last_name,
                        t.title,

                        intk.intake,
                        intk.intake_id,
                        prog.programme,
                        prog.programme_id,
                        fac.faculty_id,
                        br.branch,
                        br.branch_id,

                        cu.user_id      AS counsellor_id,
                        cu.user_fname   AS counsellor_first_name,
                        cu.user_lname   AS counsellor_last_name

                    FROM course_payments AS cp

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

                    INNER JOIN intakes AS intk
                        ON shi.intakes_intake_id = intk.intake_id

                    INNER JOIN programmes AS prog
                        ON intk.programmes_programme_id = prog.programme_id

                    INNER JOIN faculties AS fac
                        ON prog.faculties_faculty_id = fac.faculty_id

                    INNER JOIN branches AS br
                        ON fac.branches_branch_id = br.branch_id

                    LEFT JOIN users cu
                        ON s.users_user_id = cu.user_id

                    WHERE cp.payment_status_payment_status_id != 5
                    AND cp.due_date < CURDATE()
                ";

                if (!empty($f['text'])) {
                    $t = Database::escape($f['text']);
                    $query .= " AND (
                        s.student_id LIKE '%$t%'
                        OR ld.first_name LIKE '%$t%'
                        OR ld.last_name LIKE '%$t%'
                    ) ";
                }
                if (!empty($f['intake']) && $f['intake'] != '0') {
                    $query .= " AND intk.intake_id = '" . (int)$f['intake'] . "' ";
                } elseif (!empty($f['programme']) && $f['programme'] != '0') {
                    $query .= " AND prog.programme_id = '" . (int)$f['programme'] . "' ";
                } elseif (!empty($f['faculty']) && $f['faculty'] != '0') {
                    $query .= " AND fac.faculty_id = '" . (int)$f['faculty'] . "' ";
                } elseif (!empty($f['branch']) && $f['branch'] != '0') {
                    $query .= " AND br.branch_id = '" . (int)$f['branch'] . "' ";
                }
                if (!empty($f['counsellor']) && $f['counsellor'] != '0') {
                    $query .= " AND cu.user_id = '" . (int)$f['counsellor'] . "' ";
                }
                if (!empty($f['currency']) && $f['currency'] != '0') {
                    $query .= " AND c.currency_id = '" . (int)$f['currency'] . "' ";
                }
                if (!empty($f['status']) && $f['status'] != '0') {
                    $query .= " AND shi.status_status_id = '" . (int)$f['status'] . "' ";
                }
                if (!empty($f['dateFilter']) && $f['dateFilter'] == '1') {
                    $query .= " AND MONTH(cp.due_date) = MONTH(CURDATE()) AND YEAR(cp.due_date) = YEAR(CURDATE()) ";
                } elseif (!empty($f['dateFilter']) && $f['dateFilter'] == '2') {
                    $query .= " AND MONTH(cp.due_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(cp.due_date) = YEAR(CURDATE() - INTERVAL 1 MONTH) ";
                } elseif (!empty($f['dateFilter']) && $f['dateFilter'] == '3') {
                    if (!empty($f['fromDate'])) $query .= " AND cp.due_date >= '" . Database::escape($f['fromDate']) . "' ";
                    if (!empty($f['toDate']))   $query .= " AND cp.due_date <= '" . Database::escape($f['toDate']) . "' ";
                }

                $query .= " ORDER BY cp.course_payments_id DESC ";

                $latestTaxRate = 2.5;
                $rate = Database::search("SELECT * FROM tax_rates ORDER BY tax_rate_id DESC LIMIT 1");
                if ($rate && $rate->num_rows > 0) {
                    $latestTaxRate = (float)$rate->fetch_assoc()['tax_rate'];
                }

                $rows = [];
                $results = Database::search($query);
                if ($results && $results->num_rows > 0) {
                    while ($data = $results->fetch_assoc()) {
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
                        $dueAmount = $payableAmount - $settled;

                        $dueAmountWithTax = calculateTaxInclusiveAmount($dueAmount, $latestTaxRate);
                        $dueAmountSchouldPaidTax = $dueAmountWithTax - $dueAmount;

                        if ($dueAmount <= 0.009) {
                            continue;
                        }

                        $data['due_amount']    = $dueAmount;
                        $data['due_amount_with_tax'] = $dueAmountWithTax;
                        $data['due_amount_should_paid_tax'] = $dueAmountSchouldPaidTax;
                        /* Penalty column shows the panelty_payments LOG total —
                           raw LKR, unrelated to the penalty component already
                           folded into $dueAmount above. */
                        $data['penalty_amount'] = $summary['penalty_lkr'];
                        $data['student_name']  = trim($data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name']);
                        $data['counsellor_name'] = trim(($data['counsellor_first_name'] ?? '') . ' ' . ($data['counsellor_last_name'] ?? ''));
                        if ($data['counsellor_name'] === '') {
                            $data['counsellor_name'] = 'N/A';
                        }

                        $rows[] = $data;
                    }
                }

                if (!empty($f['sortByName'])) {
                    $dir = $f['sortByName'] == 1 ? 1 : -1;
                    usort($rows, fn($a, $b) => $dir * strcasecmp($a['first_name'], $b['first_name']));
                } elseif (!empty($f['sortByDue'])) {
                    $dir = $f['sortByDue'] == 1 ? 1 : -1;
                    usort($rows, fn($a, $b) => $dir * ($a['due_amount'] <=> $b['due_amount']));
                }

                return $rows;
            }

            $initialFilters = [];
            $allDueRows = fetchDueInstallments($initialFilters);
            $totalCount = count($allDueRows);
            $result_per_page = 10;
            $pageno = 1;
            $number_of_pages = ($totalCount > $result_per_page) ? ceil($totalCount / $result_per_page) : 1;
            $pageRows = array_slice($allDueRows, 0, $result_per_page);
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Due Report - Admin Dashboard | <?= Project::getBaseName() ?></title>
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
                                                <span class="main-topic lh-lg">Due Report</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="payments" data-i18n="payments">Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./students-payment" data-i18n="student-payments">Manage Students Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Due Report</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <!-- Table Section -->
                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleDueReportPagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" onclick="genarateDueReport(this)">
                                                                <span class="text-14 fw-semibold">Export Report</span>
                                                                <iconify-icon icon="line-md:download-loop" width="26" height="26" class="text-white px-3 pe-0"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="filter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3 justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="branch-selector" class="form-select custom-select mt-2" onchange="loadFaculties(),handleDueReportPagination('filter');">
                                                                                <option value="0" selected>- Select -</option>
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
                                                                            <select name="" id="faculty-selector" disabled class="form-select custom-select mt-2" onchange="loadProgrammes(),handleDueReportPagination('filter');">
                                                                                <option value="0" selected>- Select -</option>
                                                                            </select>
                                                                            <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes(),handleDueReportPagination('filter');">
                                                                                <option value="0" selected>- Select -</option>
                                                                            </select>
                                                                            <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="loadLevels(),handleDueReportPagination('filter');">
                                                                                <option value="0" selected>- Select -</option>
                                                                            </select>
                                                                            <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="counsellor-selector" class="form-select custom-select mt-2" onchange="handleDueReportPagination('filter')">
                                                                                <option value="0" selected>- Select Counsellor -</option>
                                                                                <?php
                                                                                $counsellors = Database::search(
                                                                                    "SELECT DISTINCT u.user_id, u.user_fname, u.user_lname
                                                                                     FROM users u
                                                                                     INNER JOIN students s ON s.users_user_id = u.user_id
                                                                                     ORDER BY u.user_fname"
                                                                                );
                                                                                while ($c = $counsellors->fetch_assoc()) {
                                                                                    echo "<option value='{$c['user_id']}'>" . htmlspecialchars($c['user_fname'] . ' ' . $c['user_lname']) . "</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="currency-selector" class="form-select custom-select mt-2" onchange="handleDueReportPagination('filter')">
                                                                                <option value="0" selected>- Select Currency -</option>
                                                                                <?php
                                                                                $currenciesList = Database::search("SELECT `currency_id`, `code` FROM `currencies` ORDER BY `code`");
                                                                                while ($cx = $currenciesList->fetch_assoc()) {
                                                                                    echo "<option value='{$cx['currency_id']}'>" . htmlspecialchars($cx['code']) . "</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="status-selector" class="form-select custom-select mt-2" onchange="handleDueReportPagination('filter')">
                                                                                <option value="0" selected>- Select Status -</option>
                                                                                <?php
                                                                                $statusList = Database::search("SELECT * FROM `status`");
                                                                                while ($stt = $statusList->fetch_assoc()) {
                                                                                    echo "<option value='{$stt['status_id']}'>" . htmlspecialchars($stt['status']) . "</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
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
                                                                                onblur="if(!this.value)this.type='text'" onchange="handleDueReportPagination('filter')">
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none" id="to-date-container">
                                                                            <input type="text"
                                                                                id="to-date"
                                                                                class="form-control custom-input mt-2"
                                                                                placeholder="To Date"
                                                                                onfocus="this.type='date'"
                                                                                onblur="if(!this.value)this.type='text'" onchange="handleDueReportPagination('filter')">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleDueReportPagination('filter')">
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
                                                            <div class="table-responsive" id="due-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="due-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th class="px-4 py-3 bg-table-header main-text fw-medium align-content-center">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium mx-2">Program</span>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Intake</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Branch</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleDueReportPagination('sortByName')">
                                                                                <div class="d-flex justify-content-between align-items-center">
                                                                                    <span class="text-14 main-text fw-medium me-2">Student Name</span>
                                                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleDueReportPagination('sortByDue')">
                                                                                <div class="d-flex justify-content-between align-items-center">
                                                                                    <span class="text-14 main-text fw-medium me-2">Due Amount (Foreign Currency)</span>
                                                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Tax Amount (Foreign Currency)</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Total Amount (Foreign Currency)</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty</th>
                                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Counsellor</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="due-table-data">
                                                                        <?php if (count($pageRows) > 0): ?>
                                                                            <?php foreach ($pageRows as $data): ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                            <span class="text-14 main-text mx-2"><?= htmlspecialchars($data['programme']) ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['intake']) ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['branch']) ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['student_name']) ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['currency_code']) . ' ' . number_format($data['due_amount'], $data['decimal_places']) ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['currency_code']) . ' ' . number_format($data['due_amount_should_paid_tax'], $data['decimal_places']) ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['currency_code']) . ' ' . number_format($data['due_amount_with_tax'], $data['decimal_places']) ?></span></td>
                                                                                    <td class="py-2 align-middle">
                                                                                        <?php if ($data['penalty_amount'] > 0): ?>
                                                                                            <span class="text-14 main-text">LKR <?= number_format($data['penalty_amount'], 2) ?></span>
                                                                                        <?php else: ?>
                                                                                            <span class="text-14 sub-text">—</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['counsellor_name']) ?></span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleDueReportPagination('previousPage')">
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
                                                                                        $ac2 = $isActive ? '' : "onclick=\"handleDueReportPagination('goToPage', '{$i}')\"";
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleDueReportPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/due-report.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}
?>