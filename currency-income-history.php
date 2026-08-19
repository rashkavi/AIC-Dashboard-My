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

                                    // ---- Paid students list, this month, this currency ----
                                    // Matches the students.php field set (avatar, name, email, whatsapp)
                                    // so the table below can reuse the same row layout/style.
                                    $students = Database::search("
                                        SELECT
                                            s.student_id AS sId,
                                            ld.first_name,
                                            ld.last_name,
                                            ld.email,
                                            ld.whatsapp,
                                            s.profile_img,
                                            t.title,
                                            cpp.paid_amount,
                                            cpp.date_paid,
                                            cpp.course_payments_course_payments_id,
                                            cpp.course_payment_has_payment_id
                                        FROM course_payment_has_payments cpp
                                        INNER JOIN course_payments cp
                                            ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                        INNER JOIN payment_plan_has_students_has_intakes ppsi
                                            ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
                                        INNER JOIN students_has_intakes shi
                                            ON ppsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
                                        INNER JOIN students s
                                            ON shi.students_student_id = s.student_id
                                        INNER JOIN login_details ld
                                            ON s.login_details_login_details_id = ld.login_details_id
                                        LEFT JOIN title t
                                            ON s.title_title_id = t.title_id
                                        WHERE cpp.payment_status_payment_status_id = 3
                                         
                                          AND cpp.currencies_currency_id = " . $currencyId . "
                                          AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                                          AND YEAR(cpp.date_paid) = YEAR(CURDATE())
                                        ORDER BY cpp.date_paid DESC
                                    ");

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

                                    <div class="col-12 p-4 component-theme mb-4 mt-4">
                                        <span class="sub-topic">6-Month Trend (<?= htmlspecialchars($currencyCode) ?>)</span>
                                        <canvas id="currencyTrendChart" class="w-100 mt-3" height="90"></canvas>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <span class="sub-topic">Students Paid — This Month (<?= htmlspecialchars($currencyCode) ?>)</span>

                                        <div class="table-responsive-wrapper mt-3">
                                            <div class="table-responsive" id="currency-students-table-container">
                                                <table class="table bl-solid m-0 p-0 g-0" id="currency-students-table">
                                                    <thead>
                                                        <tr class="br-t-5">
                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                    <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                </div>
                                                            </th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount Paid</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Date Paid</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="currency-students-table-data">
                                                        <?php if ($students && $students->num_rows > 0): ?>
                                                            <?php while ($data = $students->fetch_assoc()): ?>
                                                                <tr>
                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["sId"]; ?>">
                                                                            <?php
                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                            ?>
                                                                                <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Student image">
                                                                            <?php
                                                                            } else {
                                                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                            ?>
                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                            <span class="text-14 main-text mx-2"><?= ($data['title'] ? $data['title'] . '.' : '') . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                        </div>
                                                                    </td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data["email"]); ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data["whatsapp"]); ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format((float)$data["paid_amount"], 2) ?> <?= htmlspecialchars($currencyCode) ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["date_paid"])); ?></span></td>
                                                                    <td class="py-3 align-middle">
                                                                        <div class="dropdown">
                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                            <ul class="dropdown-menu border-0 shadow br-5">
                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-details?student-id=<?= $data['sId']; ?>">
                                                                                        <iconify-icon icon="hugeicons:activity-01" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                        View Profile</a></li>
                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-payment?payment-id=<?= $data['course_payment_has_payment_id']; ?>">
                                                                                        <iconify-icon icon="material-symbols-light:payment-card-outline-rounded" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                        View Payment</a></li>
                                                                            </ul>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endwhile; ?>
                                                        <?php else: ?>
                                                            <tr class="justify-content-center align-middle">
                                                                <td colspan="6" class="align-middle justify-content-center">
                                                                    <div class="d-flex flex-column align-items-center py-4">
                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
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
                new Chart(document.getElementById('currencyTrendChart'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_values($monthLabels)) ?>,
                        datasets: [{
                            label: '<?= htmlspecialchars($currencyCode) ?> Income',
                            data: <?= json_encode(array_values($trendMap)) ?>,
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
                                beginAtZero: true
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