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

        // ⚠️ ASSUMPTION: currencies table columns are currency_code / currency_name.
        // Run DESCRIBE currencies; and adjust the two aliases below if different.
        $currencyId = isset($_GET['currency']) ? (int)$_GET['currency'] : 0;

        if ($currencyId <= 0) {
            header('Location: dashboard');
            exit;
        }

        $currencyRow = Database::search("
            SELECT
                currency_id,
                code AS currency_code,
                name AS currency_name
            FROM currencies
            WHERE currency_id = " . $currencyId . "
            LIMIT 1
        ")->fetch_assoc();

        if (!$currencyRow) {
            header('Location: dashboard');
            exit;
        }

        $currencyCode = $currencyRow['currency_code'];
        $currencyName = $currencyRow['currency_name'];


        $LKR_CURRENCY_ID = 1;

        /* Resolve the LKR<->foreign relationship for a given foreign
               currency id. Returns the rate and which direction converts
               FOREIGN -> LKR ('multiply' or 'divide'). */
        function resolveLkrConversion(int $foreignCurrencyId, ?int $exchangeRateId, ?float $inlineRate): array
        {
            if ($foreignCurrencyId === 1 || $foreignCurrencyId === 0) {
                return ['rate' => null, 'mode' => 'none'];
            }
            if ($exchangeRateId) {
                $rr = Database::search(
                    "SELECT `base_currency_id`, `target_currency_id`, `rate`
                         FROM `currency_exchange_rates`
                         WHERE `currency_exchange_rate_id` = '" . (int)$exchangeRateId . "'
                         LIMIT 1"
                );
                if ($rr && $rr->num_rows > 0) {
                    $r      = $rr->fetch_assoc();
                    $base   = (int)$r['base_currency_id'];
                    $target = (int)$r['target_currency_id'];
                    $rate   = (float)$r['rate'];
                    if ($rate > 0) {
                        if ($base === $foreignCurrencyId && $target === 1) {
                            return ['rate' => $rate, 'mode' => 'multiply']; // foreign * rate = LKR
                        }
                        if ($base === 1 && $target === $foreignCurrencyId) {
                            return ['rate' => $rate, 'mode' => 'divide']; // foreign / rate = LKR
                        }
                        return ['rate' => $rate, 'mode' => 'multiply'];
                    }
                }
            }
            if ($inlineRate !== null && $inlineRate > 0) {
                return ['rate' => $inlineRate, 'mode' => 'multiply'];
            }
            return ['rate' => null, 'mode' => 'none'];
        }

        /* foreign amount -> LKR, using the direction resolved above */
        function foreignToLkr(float $foreignAmount, array $conv): float
        {
            if ($conv['mode'] === 'multiply' && $conv['rate'] !== null) {
                return $foreignAmount * $conv['rate'];
            }
            if ($conv['mode'] === 'divide' && $conv['rate'] !== null && $conv['rate'] > 0) {
                return $foreignAmount / $conv['rate'];
            }
            return $foreignAmount; // no rate resolvable — treat as already LKR
        }

        /* LKR amount -> foreign, the exact inverse of foreignToLkr() */
        function lkrToForeign(float $lkrAmount, array $conv): float
        {
            if ($conv['mode'] === 'multiply' && $conv['rate'] !== null && $conv['rate'] > 0) {
                return $lkrAmount / $conv['rate'];
            }
            if ($conv['mode'] === 'divide' && $conv['rate'] !== null) {
                return $lkrAmount * $conv['rate'];
            }
            return $lkrAmount; // no rate resolvable
        }
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Currency Income History - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                        include "./includes/side-menu.php";
                        load_side_menu::load("Dashboard");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Dashboard");
                                ?>
                            </div>

                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                                <div class="row p-4 m-0 g-0">

                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="d-flex flex-row align-items-center justify-content-between">
                                            <div class="d-flex flex-column">
                                                <span class="main-topic lh-lg"><?= htmlspecialchars($currencyCode) ?> - <?= htmlspecialchars($currencyName) ?> Income History</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./dashboard.php" data-i18n="dashboard">Dashboard</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Currency Income History</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                    // ---- This month vs previous month totals ----
                                    $summary = Database::search("
                                        SELECT
                                            COALESCE(SUM(CASE
                                                WHEN cpp.payment_status_payment_status_id = 3
                                                AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                                                 AND YEAR(cpp.date_paid) = YEAR(CURDATE())
                                                THEN cpp.paid_amount ELSE 0 END), 0) AS current_amount,
                                            COALESCE(SUM(CASE
                                                WHEN cpp.payment_status_payment_status_id = 3
                                                AND MONTH(cpp.date_paid) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                                 AND YEAR(cpp.date_paid) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                                THEN cpp.paid_amount ELSE 0 END), 0) AS previous_amount,
                                            COUNT(DISTINCT CASE
                                                WHEN cpp.payment_status_payment_status_id = 3
                                                AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                                                 AND YEAR(cpp.date_paid) = YEAR(CURDATE())
                                                THEN cpp.course_payments_course_payments_id END) AS current_payment_count,
                                            COUNT(DISTINCT CASE
                                                WHEN cpp.payment_status_payment_status_id = 2
                                                
                                                THEN cpp.course_payments_course_payments_id END) AS awaiting_payment_count
                                        FROM course_payment_has_payments cpp
                                        INNER JOIN course_payments cp
                                            ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                        INNER JOIN payment_plan_has_students_has_intakes ppsi
                                            ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
                                                                                
                                          AND cpp.currencies_currency_id = " . $currencyId . "
                                    ")->fetch_assoc();

                                    // ---- 6-month trend for the chart ----
                                    $trendRs = Database::search("
                                        SELECT
                                            DATE_FORMAT(cpp.date_paid, '%Y-%m') AS month_key,
                                            SUM(cpp.paid_amount) AS total
                                        FROM course_payment_has_payments cpp
                                        INNER JOIN course_payments cp
                                            ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                        INNER JOIN payment_plan_has_students_has_intakes ppsi
                                            ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
                                        WHERE cpp.payment_status_payment_status_id = 3
                                         
                                          AND cpp.currencies_currency_id = " . $currencyId . "
                                          AND cpp.date_paid >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
                                        GROUP BY DATE_FORMAT(cpp.date_paid, '%Y-%m')
                                    ");

                                    $monthLabels = [];
                                    for ($i = 5; $i >= 0; $i--) {
                                        $monthLabels[date('Y-m', strtotime("-{$i} month"))] = date('M Y', strtotime("-{$i} month"));
                                    }
                                    $trendMap = array_fill_keys(array_keys($monthLabels), 0);
                                    while ($row = $trendRs->fetch_assoc()) {
                                        if (isset($trendMap[$row['month_key']])) {
                                            $trendMap[$row['month_key']] = (float)$row['total'];
                                        }
                                    }

                                    // Last 3 month keys/labels, oldest -> newest
                                    $last3MonthLabels = [];
                                    for ($i = 2; $i >= 0; $i--) {
                                        $last3MonthLabels[date('Y-m', strtotime("-{$i} month"))] = date('M Y', strtotime("-{$i} month"));
                                    }

                                    // ---- Last 3 months branch-wise breakdown ----
                                    // ⚠️ ASSUMPTION: branches table columns are branch_id / branch.
                                    // Join chain: students_has_intakes -> intakes -> programmes -> faculties -> branches

                                    // Zero-fill EVERY branch first (regardless of whether it has payments),
                                    // so the chart/table always render with 0.00 bars/rows instead of a
                                    // "no data" placeholder when a currency has no transactions yet.
                                    $branchTotals = [];
                                    $allBranchesRs = Database::search("SELECT branch_id, branch FROM branches ORDER BY branch");
                                    while ($b = $allBranchesRs->fetch_assoc()) {
                                        $branchTotals[$b['branch']] = array_fill_keys(array_keys($last3MonthLabels), 0);
                                    }

                                    $branchBreakdownRs = Database::search("
                                        SELECT
                                            b.branch_id,
                                            b.branch AS branch_name,
                                            DATE_FORMAT(cpp.date_paid, '%Y-%m') AS month_key,
                                            SUM(cpp.paid_amount) AS total
                                        FROM course_payment_has_payments cpp
                                        INNER JOIN course_payments cp
                                            ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                        INNER JOIN payment_plan_has_students_has_intakes ppsi
                                            ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
                                        INNER JOIN students_has_intakes shi
                                            ON ppsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
                                        INNER JOIN intakes i
                                            ON shi.intakes_intake_id = i.intake_id
                                        INNER JOIN programmes p
                                            ON i.programmes_programme_id = p.programme_id
                                        INNER JOIN faculties f
                                            ON p.faculties_faculty_id = f.faculty_id
                                        INNER JOIN branches b
                                            ON f.branches_branch_id = b.branch_id
                                        WHERE cpp.payment_status_payment_status_id = 3
                                          AND cpp.currencies_currency_id = " . $currencyId . "
                                          AND cpp.date_paid >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01')
                                        GROUP BY b.branch_id, b.branch, DATE_FORMAT(cpp.date_paid, '%Y-%m')
                                        ORDER BY b.branch
                                    ");

                                    // Overlay actual totals onto the zero-filled map. If a branch somehow
                                    // isn't in the `branches` table lookup above, add it here too so no
                                    // data is silently dropped.
                                    while ($row = $branchBreakdownRs->fetch_assoc()) {
                                        $branchName = $row['branch_name'];
                                        if (!isset($branchTotals[$branchName])) {
                                            $branchTotals[$branchName] = array_fill_keys(array_keys($last3MonthLabels), 0);
                                        }
                                        if (isset($branchTotals[$branchName][$row['month_key']])) {
                                            $branchTotals[$branchName][$row['month_key']] = (float)$row['total'];
                                        }
                                    }
                                    $branchNames = array_keys($branchTotals);

                                    // Fixed color palette, one distinct color per branch (stable across months/tabs).
                                    // Extend this list if you ever have more than 10 branches — it repeats/cycles after that.
                                    $branchColorPalette = [
                                        '#02708E', // teal blue
                                        '#F2A93B', // amber
                                        '#D64545', // red
                                        '#5B8C5A', // green
                                        '#8E5CD9', // purple
                                        '#E0668B', // pink
                                        '#3AACA8', // aqua
                                        '#C98A2B', // gold/brown
                                        '#6C7A89', // slate
                                        '#D9A441', // mustard
                                    ];
                                    $branchColors = [];
                                    foreach ($branchNames as $idx => $bName) {
                                        $branchColors[$bName] = $branchColorPalette[$idx % count($branchColorPalette)];
                                    }

                                    // Grouped bar chart, matching your SWLC dashboard style:
                                    // x-axis = the 3 months, one dataset (colored bar series) per branch, legend on top.
                                    $branchChartDatasets = [];
                                    foreach ($branchNames as $bName) {
                                        $branchChartDatasets[] = [
                                            'label' => $bName,
                                            'data' => array_values($branchTotals[$bName]), // one value per month, oldest -> newest
                                            'backgroundColor' => $branchColors[$bName],
                                            'borderRadius' => 4,
                                            'maxBarThickness' => 40
                                        ];
                                    }

                                    // ---- Paid students list, this month, this currency ----
                                    // Matches the students.php field set (avatar, name, email, whatsapp)
                                    // so the table below can reuse the same row layout/style.
                                    $query = "
                                                                            SELECT
                                                                                r.course_payment_receipt_id,
                                                                                r.receipt_number,

                                                                                br.branch_id,
                                                                                br.branch,

                                                                                shi.students_has_intakes_id,
                                                                                s.student_id AS aId,
                                                                                ld.first_name,
                                                                                ld.last_name,
                                                                                t.title,

                                                                                cu.user_id      AS counsellor_id,
                                                                                cu.user_fname   AS counsellor_first_name,
                                                                                cu.user_lname   AS counsellor_last_name,

                                                                                GROUP_CONCAT(DISTINCT pgp.payment_type ORDER BY pgp.payment_type SEPARATOR ', ') AS payment_types,

                                                                                MIN(cpp.date_paid)                    AS paid_date,
                                                                                SUM(cpp.paid_amount)                  AS total_paid_amount,

                                                                                /* Raw paid currency as actually recorded (may be NULL) */
                                                                                MAX(cpp.currencies_currency_id)       AS raw_paid_currency_id,

                                                                                /* The course's own (base) currency */
                                                                                MAX(course_cur.currency_id)           AS base_currency_id,
                                                                                MAX(course_cur.code)                  AS base_currency_code,
                                                                                MAX(course_cur.decimal_places)        AS base_decimal_places,

                                                                                MAX(cpp.currency_exchange_rate_id)    AS exchange_rate_id,
                                                                                MAX(cpp.currency_exchange_rate)       AS inline_exchange_rate,

                                                                                COALESCE(paid_cur.code, course_cur.code) AS paid_currency_code,
                                                                                COALESCE(paid_cur.decimal_places, course_cur.decimal_places) AS paid_decimal_places

                                                                            FROM course_payment_receipts r

                                                                            INNER JOIN course_payment_has_payments cpp
                                                                                ON cpp.course_payment_receipts_course_payment_receipt_id = r.course_payment_receipt_id

                                                                            INNER JOIN course_payments cp
                                                                                ON cp.course_payments_id = cpp.course_payments_course_payments_id

                                                                            INNER JOIN payment_type pgp
                                                                                ON cp.payment_type_payment_type_id = pgp.payment_type_id

                                                                            INNER JOIN payment_plan_has_students_has_intakes phsi
                                                                                ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

                                                                            INNER JOIN payment_category pc
                                                                                ON phsi.payment_category_payment_category_id = pc.payment_category_id

                                                                            INNER JOIN currencies course_cur
                                                                                ON pc.currencies_currency_id = course_cur.currency_id

                                                                            LEFT JOIN currencies paid_cur
                                                                                ON cpp.currencies_currency_id = paid_cur.currency_id

                                                                            INNER JOIN students_has_intakes shi
                                                                                ON r.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                                                                            INNER JOIN students s
                                                                                ON shi.students_student_id = s.student_id

                                                                            INNER JOIN login_details ld
                                                                                ON s.login_details_login_details_id = ld.login_details_id

                                                                            INNER JOIN title t
                                                                                ON s.title_title_id = t.title_id

                                                                            /* Counsellor: students.users_user_id -> users */
                                                                            LEFT JOIN users cu
                                                                                ON s.users_user_id = cu.user_id

                                                                            /* Branch chain: intakes -> programmes -> faculties -> branches */
                                                                            INNER JOIN intakes intk
                                                                                ON shi.intakes_intake_id = intk.intake_id

                                                                            INNER JOIN programmes prog
                                                                                ON intk.programmes_programme_id = prog.programme_id

                                                                            INNER JOIN faculties fac
                                                                                ON prog.faculties_faculty_id = fac.faculty_id

                                                                            INNER JOIN branches br
                                                                                ON fac.branches_branch_id = br.branch_id

                                                                            WHERE paid_cur.currency_id = '" . $currencyId . "'

                                                                            GROUP BY r.course_payment_receipt_id
                                                                            ORDER BY r.course_payment_receipt_id DESC
                                                                        ";

                                    $results         = Database::search($query);
                                    $results_count   = $results->num_rows;

                                    $cards = [
                                        [
                                            'title' => 'This month',
                                            'value' => number_format((float)$summary['current_amount'], 2),
                                            'icon' => 'mdi:currency-usd',
                                            'change' => '',
                                            'change_type' => 'neutral',
                                            'period' => 'Total',
                                            'chart' => []
                                        ],
                                        [
                                            'title' => 'Last month',
                                            'value' => number_format((float)$summary['previous_amount'], 2),
                                            'icon' => 'mdi:currency-usd',
                                            'change' => '',
                                            'change_type' => 'neutral',
                                            'period' => 'Total',
                                            'chart' => []
                                        ],
                                        [
                                            'title' => 'Payments This Month',
                                            'value' => $summary['current_payment_count'],
                                            'icon' => 'mdi:currency-usd',
                                            'change' => '',
                                            'change_type' => 'neutral',
                                            'period' => 'Total',
                                            'chart' => []
                                        ],
                                        [
                                            'title' => 'Awaiting Approval Payments',
                                            'value' => $summary['awaiting_payment_count'],
                                            'icon' => 'mdi:currency-usd',
                                            'change' => '',
                                            'change_type' => 'neutral',
                                            'period' => 'Total',
                                            'chart' => []
                                        ]
                                    ]
                                    ?>

                                    <div class="container-fluid p-0">
                                        <div class="row g-3">
                                            <?php foreach ($cards as $card) {
                                            ?>
                                                <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                                    <div class="stats-mini-card">
                                                        <div class="stats-mini-top">
                                                            <div class="stats-mini-left">
                                                                <div class="stats-mini-icon">
                                                                    <iconify-icon icon="<?= htmlspecialchars($card['icon']) ?>" width="16" height="16"></iconify-icon>
                                                                </div>
                                                                <span class="stats-mini-title"><?= htmlspecialchars($card['title']) ?></span>
                                                            </div>

                                                            <button type="button" class="stats-mini-menu">
                                                                <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                                            </button>
                                                        </div>

                                                        <div class="stats-mini-body">
                                                            <div class="stats-mini-content">
                                                                <div class="stats-mini-value"><?= htmlspecialchars($card['value']) ?></div>

                                                                <div class="stats-mini-meta">
                                                                    <?php if ($card['change'] !== '') { ?>
                                                                        <span class="stats-mini-change <?= htmlspecialchars($card['change_type']) ?>">
                                                                            <?= htmlspecialchars($card['change']) ?>
                                                                        </span>
                                                                    <?php } ?>
                                                                    <span class="stats-mini-period"><?= htmlspecialchars($card['period']) ?></span>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($card['chart'])) { ?>
                                                                <div class="stats-mini-chart" data-chart='<?= json_encode($card['chart']) ?>'></div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="row g-3">
                                            <div class="col-12 col-xl-6 p-4">
                                                <div class="row p-4 component-theme">
                                                    <span class="sub-topic">6-Month Trend (<?= htmlspecialchars($currencyCode) ?>)</span>
                                                    <canvas id="currencyTrendChart" class="w-100 mt-3"></canvas>
                                                </div>

                                            </div>

                                            <div class="col-12 col-xl-6 p-4">
                                                <div class="row p-4 component-theme">
                                                    <span class="sub-topic">Branch-wise Breakdown (<?= htmlspecialchars($currencyCode) ?>)</span>
                                                    <canvas id="branchBreakdownChart" class="w-100 mt-3"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme mb-4">
                                        <span class="sub-topic">Branch-wise Breakdown (<?= htmlspecialchars($currencyCode) ?>)</span>

                                        <div class="table-responsive-wrapper mt-4">
                                            <table class="table bl-solid m-0 p-0 g-0">
                                                <thead>
                                                    <tr>
                                                        <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Branch</th>
                                                        <?php foreach ($last3MonthLabels as $mLabel): ?>
                                                            <th class="py-3 bg-table-header text-14 main-text fw-medium"><?= htmlspecialchars($mLabel) ?></th>
                                                        <?php endforeach; ?>
                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">3-Month Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($branchTotals as $branchName => $months): ?>
                                                        <tr>
                                                            <td class="px-4 py-2 align-middle text-14 main-text">
                                                                <span class="d-inline-block hwr-12 br-50 me-2" style="background-color: <?= htmlspecialchars($branchColors[$branchName]) ?>;"></span>
                                                                <?= htmlspecialchars($branchName) ?>
                                                            </td>
                                                            <?php foreach ($months as $amt): ?>
                                                                <td class="py-2 align-middle text-14 main-text"><?= number_format($amt, 2) ?></td>
                                                            <?php endforeach; ?>
                                                            <td class="py-2 align-middle text-14 main-text fw-medium"><?= number_format(array_sum($months), 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0">
                                            <span class="sub-topic">Students Paid — This Month (<?= htmlspecialchars($currencyCode) ?>)</span>
                                            <div class="col-12 my-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">

                                                            <span class="text-14 dark-theme fw-medium p-0 m-0"><?php echo $results_count; ?> Payments</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-7 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleCollectionReportPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-12 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" onclick="genarateCollectionReport(this)">
                                                            <span class="text-14 fw-semibold">Export Report</span>
                                                            <iconify-icon icon="line-md:download-loop" width="26" height="26" class="text-white px-3 pe-0"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="collapse" id="filter">
                                                        <div class="card card-body border-0 p-0">
                                                            <div class="col-12">
                                                                <div class="row gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="branch-selector" class="form-select custom-select mt-2" onchange="loadFaculties(),handleCollectionReportPagination();">
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
                                                                        <select name="" id="faculty-selector" disabled class="form-select custom-select mt-2" onchange="loadProgrammes(),handleCollectionReportPagination();">
                                                                            <option value="0" selected>- Select Faculty -</option>
                                                                        </select>
                                                                        <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes(),handleCollectionReportPagination();">
                                                                            <option value="0" selected>- Select Programme -</option>
                                                                        </select>
                                                                        <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="loadLevels(),handleCollectionReportPagination();">
                                                                            <option value="0" selected>- Select Intake -</option>
                                                                        </select>
                                                                        <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="counsellor-selector" class="form-select custom-select mt-2" onchange="handleCollectionReportPagination('filter')">
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
                                                                        <select name="" id="currency-selector" class="form-select custom-select mt-2" disabled onchange="handleCollectionReportPagination('filter')">
                                                                            <option value="<?= $currencyId ?>" selected><?= $currencyCode ?></option>
                                                                           
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
                                                                            onblur="if(!this.value)this.type='text'" onchange="handleCollectionReportPagination('filter')">
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none" id="to-date-container">
                                                                        <input type="text"
                                                                            id="to-date"
                                                                            class="form-control custom-input mt-2"
                                                                            placeholder="To Date"
                                                                            onfocus="this.type='date'"
                                                                            onblur="if(!this.value)this.type='text'" onchange="handleCollectionReportPagination('filter')">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" id="show-result-selector" onchange="handleCollectionReportPagination('filter')">
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
                                                        <div class="table-responsive" id="collection-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="collection-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th class="px-4 py-3 bg-table-header main-text fw-medium align-content-center">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                <span class="text-14 main-text fw-medium mx-2">Branch</span>
                                                                            </div>
                                                                        </th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleCollectionReportPagination('sortByDate')">
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Receipt No</th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleCollectionReportPagination('sortByName')">
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <span class="text-14 main-text fw-medium me-2">Student Name</span>
                                                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Foreign Amount</th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">LKR Amount</th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Counsellor</th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment Type / Program</th>
                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Exchange Rate</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="collection-table-data">
                                                                    <?php
                                                                    /*
                                                                         * ONE ROW PER RECEIPT (course_payment_receipts).
                                                                         * All course_payment_has_payments lines under the same
                                                                         * receipt are aggregated (SUM / GROUP_CONCAT / MIN / MAX).
                                                                         */

                                                                    $result_per_page = 10;
                                                                    $pageno          = 1;
                                                                    $page_results    = ($pageno - 1) * $result_per_page;
                                                                    $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;

                                                                    $receipts = Database::search($query . " LIMIT $result_per_page OFFSET $page_results");

                                                                    if ($receipts && $receipts->num_rows > 0):
                                                                        while ($data = $receipts->fetch_assoc()):

                                                                            $baseCurrencyId = (int)($data['base_currency_id'] ?? 0);

                                                                            /* NULL raw paid currency = "paid in the course's own
                                                                                   currency" (i.e. same as base), not LKR by default. */
                                                                            $rawPaidCurrencyId = $data['raw_paid_currency_id'] !== null ? (int)$data['raw_paid_currency_id'] : null;
                                                                            $paidCurrencyId    = $rawPaidCurrencyId ?? $baseCurrencyId;

                                                                            $totalPaidAmount = (float)$data['total_paid_amount'];

                                                                            $exchangeRateId = !empty($data['exchange_rate_id']) ? (int)$data['exchange_rate_id'] : null;
                                                                            $inlineRate     = isset($data['inline_exchange_rate']) && $data['inline_exchange_rate'] !== null ? (float)$data['inline_exchange_rate'] : null;

                                                                            $paidIsLkr = ($paidCurrencyId === $LKR_CURRENCY_ID);
                                                                            $baseIsLkr = ($baseCurrencyId === $LKR_CURRENCY_ID);
                                                                            $isAnyForeign = !$paidIsLkr || !$baseIsLkr;

                                                                            $foreignAmount = null;
                                                                            $lkrAmount     = null;
                                                                            $foreignCode   = null;
                                                                            $foreignDp     = 2;
                                                                            $conv          = ['rate' => null, 'mode' => 'none'];

                                                                            if (!$isAnyForeign) {
                                                                                /* Both base and paid are LKR — nothing foreign here */
                                                                                $lkrAmount = $totalPaidAmount;
                                                                            } elseif (!$baseIsLkr && $paidCurrencyId === $baseCurrencyId) {
                                                                                /* Paid directly in the course's own foreign currency
                                                                                       (e.g. base EUR, paid EUR) — no conversion ever
                                                                                       happened, so there's no genuine LKR figure to show. */
                                                                                $foreignAmount = $totalPaidAmount;
                                                                                $foreignCode   = htmlspecialchars($data['base_currency_code']);
                                                                                $foreignDp     = (int)$data['base_decimal_places'];
                                                                                $lkrAmount     = null;
                                                                            } elseif ($paidIsLkr) {
                                                                                /* Paid in LKR cash, but the course is priced in a
                                                                                       foreign currency -> convert LKR back to base */
                                                                                $lkrAmount   = $totalPaidAmount;
                                                                                $conv        = resolveLkrConversion($baseCurrencyId, $exchangeRateId, $inlineRate);
                                                                                $foreignAmount = lkrToForeign($lkrAmount, $conv);
                                                                                $foreignCode = htmlspecialchars($data['base_currency_code']);
                                                                                $foreignDp   = (int)$data['base_decimal_places'];
                                                                            } else {
                                                                                /* Paid in a foreign currency directly */
                                                                                $foreignAmount = $totalPaidAmount;
                                                                                $conv          = resolveLkrConversion($paidCurrencyId, $exchangeRateId, $inlineRate);
                                                                                $lkrAmount     = foreignToLkr($foreignAmount, $conv);
                                                                                $foreignCode   = htmlspecialchars($data['paid_currency_code']);
                                                                                $foreignDp     = (int)$data['paid_decimal_places'];
                                                                            }

                                                                            $counsellorName = trim(($data['counsellor_first_name'] ?? '') . ' ' . ($data['counsellor_last_name'] ?? ''));
                                                                            if ($counsellorName === '') {
                                                                                $counsellorName = '—';
                                                                            }
                                                                    ?>
                                                                            <tr>
                                                                                <!-- Branch -->
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                        <span class="text-14 main-text mx-2"><?= htmlspecialchars($data['branch']) ?></span>
                                                                                    </div>
                                                                                </td>

                                                                                <!-- Paid Date -->
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text">
                                                                                        <?= $data['paid_date'] ? date('Y-m-d', strtotime($data['paid_date'])) : 'N/A' ?>
                                                                                    </span>
                                                                                </td>

                                                                                <!-- Receipt No -->
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['receipt_number']) ?></span>
                                                                                </td>

                                                                                <!-- Student Name -->
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?= htmlspecialchars($data['title'] . '.' . $data['first_name'] . ' ' . $data['last_name']) ?></span>
                                                                                </td>

                                                                                <!-- Foreign Amount: shown whenever EITHER base or paid currency is foreign -->
                                                                                <td class="py-2 align-middle">
                                                                                    <?php if ($isAnyForeign && !$paidIsLkr && $foreignAmount !== null): ?>
                                                                                        <span class="text-14 main-text"><?= $foreignCode . ' ' . number_format($foreignAmount, $foreignDp) ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="text-14 sub-text">—</span>
                                                                                    <?php endif; ?>
                                                                                </td>

                                                                                <!-- LKR Amount: blank when paid == base foreign currency (no conversion happened) -->
                                                                                <td class="py-2 align-middle">
                                                                                    <?php if ($lkrAmount !== null): ?>
                                                                                        <span class="text-14 main-text fw-medium">LKR <?= number_format($lkrAmount, 2) ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="text-14 sub-text">—</span>
                                                                                    <?php endif; ?>
                                                                                </td>

                                                                                <!-- Counsellor -->
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?= htmlspecialchars($counsellorName) ?></span>
                                                                                </td>

                                                                                <!-- Payment Type / Program (multi-value) -->
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?= htmlspecialchars($data['payment_types']) ?></span>
                                                                                </td>

                                                                                <!-- Exchange Rate: shown whenever EITHER base or paid currency is foreign -->
                                                                                <td class="py-2 align-middle">
                                                                                    <?php if ($isAnyForeign && $conv['rate'] !== null): ?>
                                                                                        <span class="text-14 main-text"><?= number_format($conv['rate'], 2) ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="text-14 sub-text">—</span>
                                                                                    <?php endif; ?>
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleCollectionReportPagination('previousPage')">
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
                                                                                    $ac2 = $isActive ? '' : "onclick=\"handleCollectionReportPagination('goToPage', '{$i}')\"";
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCollectionReportPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

            <script>
                // Trend chart data + dynamic Y-axis, same pattern as your setLineChart() helper:
                // max = data max + 10% buffer (rounded up), stepSize = max / 5.
                const trendChartData = <?= json_encode(array_values($trendMap)) ?>;
                const trendDataMax = Math.max(...trendChartData, 0);
                const trendBuffer = trendDataMax * 0.1;
                const trendChartMax = Math.ceil(trendDataMax + trendBuffer) || 5;
                const trendChartStepSize = Math.ceil(trendChartMax / 5);

                new Chart(document.getElementById('currencyTrendChart'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_values($monthLabels)) ?>,
                        datasets: [{
                            label: '<?= htmlspecialchars($currencyCode) ?> Income',
                            data: trendChartData,
                            borderColor: getComputedStyle(document.documentElement).getPropertyValue('--dark-theme') || '#02708E',
                            backgroundColor: 'rgba(2,112,142,0.1)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: trendChartMax,
                                ticks: {
                                    stepSize: trendChartStepSize
                                }
                            }
                        }
                    }
                });

                // Branch chart datasets are ALWAYS populated now (zero-filled per branch),
                // so this always renders instead of being conditionally skipped.
                const branchChartDatasets = <?= json_encode($branchChartDatasets) ?>;

                // Same max/stepSize pattern as setLineChart(), but taken across ALL branch bars
                // (every dataset's data array) since it's a grouped chart, not a single series.
                const branchAllValues = branchChartDatasets.flatMap(ds => ds.data);
                const branchDataMax = Math.max(...branchAllValues, 0);
                const branchBuffer = branchDataMax * 0.1;
                const branchChartMax = Math.ceil(branchDataMax + branchBuffer) || 5;
                const branchChartStepSize = Math.ceil(branchChartMax / 5);

                new Chart(document.getElementById('branchBreakdownChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_values($last3MonthLabels)) ?>,
                        datasets: branchChartDatasets
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 10,
                                    boxHeight: 10
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (item) => ' ' + item.dataset.label + ': ' + item.formattedValue + ' <?= htmlspecialchars($currencyCode) ?>'
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                max: branchChartMax,
                                ticks: {
                                    stepSize: branchChartStepSize
                                }
                            }
                        }
                    }
                });
            </script>
        </body>

        </html>
<?php
    }
}
?>