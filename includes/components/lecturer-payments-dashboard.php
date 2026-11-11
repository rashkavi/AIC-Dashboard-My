<?php
/*
 * Small lecturer payments dashboard (summary cards + latest 5 paid / scheduled / pending)
 * Used in lecturers-payments.php (admin, all lecturers) and my-payments.php (lecturer, own payments)
 *
 * $dashboardUserId => lecturer user id to filter, null for all lecturers
 * $dashboardLinks  => ['paid' => page, 'scheduled' => page, 'pendingLectures' => page, 'pendingAssignments' => page, 'paidDetails' => page]
 */

if (!function_exists('lecturerPaymentsDashboardInitials')) {
    function lecturerPaymentsDashboardInitials($fname, $lname)
    {
        return strtoupper(substr($fname, 0, 1)) . strtoupper(substr($lname, 0, 1));
    }
}

$dashboardUserFilter = "";
$dashboardLectureUserFilter = "";
if (!empty($dashboardUserId)) {
    $dashboardUserFilter = " AND `users_user_id` = '" . (int) $dashboardUserId . "'";
    $dashboardLectureUserFilter = " AND `users`.`user_id` = '" . (int) $dashboardUserId . "'";
}

/* ---------- summary ---------- */
$dashboardPaid = Database::search("SELECT COUNT(*) AS 'count', IFNULL(SUM(`amount`),0) AS 'total' FROM `lecturers_paid_payments` WHERE 1=1" . $dashboardUserFilter)->fetch_assoc();

$dashboardScheduledLectures = Database::search("SELECT COUNT(*) AS 'count', IFNULL(SUM(`amount`),0) AS 'total' FROM `lecturer_payments` WHERE `earning_status_earning_status_id` = '1'" . $dashboardUserFilter)->fetch_assoc();
$dashboardScheduledAssignments = Database::search("SELECT COUNT(*) AS 'count', IFNULL(SUM(`amount`),0) AS 'total' FROM `assignment_payments` WHERE `earning_status_earning_status_id` = '1'" . $dashboardUserFilter)->fetch_assoc();

$dashboardRejectedLectures = Database::search("SELECT COUNT(*) AS 'count', IFNULL(SUM(`amount`),0) AS 'total' FROM `lecturer_payments` WHERE `earning_status_earning_status_id` = '3'" . $dashboardUserFilter)->fetch_assoc();
$dashboardRejectedAssignments = Database::search("SELECT COUNT(*) AS 'count', IFNULL(SUM(`amount`),0) AS 'total' FROM `assignment_payments` WHERE `earning_status_earning_status_id` = '3'" . $dashboardUserFilter)->fetch_assoc();

/* pending lectures => held lectures that are not scheduled for payment yet */
$dashboardPendingLecturesQuery = "SELECT
        tt.date,
        tt.start_time,
        tt.end_time,
        tt.lecture_title,
        users.user_fname,
        users.user_lname,
        users.profile_img,
        title.title
    FROM time_table AS tt
    INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `tt`.`lecturers_lecturers_id`
    INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
    LEFT JOIN `lecturer_payments_has_lectures` ON `lecturer_payments_has_lectures`.`time_table_time_table_id` = `tt`.`time_table_id`
    WHERE tt.time_table_status_time_table_status_id = '1'
    AND tt.lecture_type_lecture_type_id != 3
    AND CONCAT(tt.date , ' ', tt.start_time) <= '" . date('Y-m-d H:i:s') . "'
    AND `lecturer_payments_has_lectures`.`lecturer_payments_has_lecture_id` IS NULL" . $dashboardLectureUserFilter . "
    GROUP BY tt.date, tt.start_time, tt.end_time, users.user_id, tt.lecture_title, users.user_fname, users.user_lname, users.profile_img, title.title";

$dashboardPendingLecturesCount = Database::search("SELECT COUNT(*) AS 'count' FROM (" . $dashboardPendingLecturesQuery . ") AS `pending_lectures`")->fetch_assoc()['count'];

/* pending assignments => marked assignments that are not paid and not in a scheduled / rejected payment */
$dashboardPendingAssignmentsQuery = "SELECT
        `assignments`.`assignment_code`,
        `assignments`.`assignment`,
        `users`.`user_fname`, `users`.`user_lname`, `users`.`profile_img`, `title`.`title`,
        `lecturers`.`per_assignment_fee`,
        COUNT(`lhsaa`.`lecturers_has_students_has_assignments_id`) AS unpaid_count,
        MAX(`sha`.`uploaded_at`) AS last_uploaded
    FROM `lecturers_has_students_has_assignments` AS `lhsaa`
    INNER JOIN `students_has_assignments` AS `sha` ON `lhsaa`.`students_has_assignments_students_has_assignments_id` = `sha`.`students_has_assignments_id`
    INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
    INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
    INNER JOIN `assignments` ON `assignments`.`assignment_id` = `sha`.`assignments_assignment_id`
    WHERE `lhsaa`.`submission_sts_submission_sts_id` = '2'" . $dashboardLectureUserFilter . "
    AND `lhsaa`.`lecturers_has_students_has_assignments_id` NOT IN (
        SELECT `apma`.`lecturers_has_students_has_assignments_id` FROM `assignment_payments_has_marked_assignments` AS `apma`
        INNER JOIN `assignment_payments` AS `ap` ON `ap`.`assignment_payments_id` = `apma`.`assignment_payments_id`
        WHERE `ap`.`earning_status_earning_status_id` = '2'
    )
    AND NOT EXISTS (
        SELECT 1 FROM `assignment_payments` AS `open_payment`
        WHERE `open_payment`.`lecturers_lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
        AND `open_payment`.`assignments_assignment_id` = `sha`.`assignments_assignment_id`
        AND `open_payment`.`earning_status_earning_status_id` IN ('1','3')
    )
    GROUP BY `lhsaa`.`lecturers_lecturers_id`, `sha`.`assignments_assignment_id`, `assignments`.`assignment_code`, `assignments`.`assignment`, `users`.`user_fname`, `users`.`user_lname`, `users`.`profile_img`, `title`.`title`, `lecturers`.`per_assignment_fee`";

$dashboardPendingAssignments = Database::search("SELECT COUNT(*) AS 'count', IFNULL(SUM(`unpaid_count` * `per_assignment_fee`),0) AS 'total' FROM (" . $dashboardPendingAssignmentsQuery . ") AS `pending_assignments`")->fetch_assoc();

$dashboardScheduledTotal = $dashboardScheduledLectures['total'] + $dashboardScheduledAssignments['total'];
$dashboardScheduledCount = $dashboardScheduledLectures['count'] + $dashboardScheduledAssignments['count'];
$dashboardRejectedTotal = $dashboardRejectedLectures['total'] + $dashboardRejectedAssignments['total'];
$dashboardRejectedCount = $dashboardRejectedLectures['count'] + $dashboardRejectedAssignments['count'];
$dashboardPendingCount = $dashboardPendingLecturesCount + $dashboardPendingAssignments['count'];

/* ---------- latest 5 lists ---------- */
$dashboardLatestPaid = Database::search("SELECT `lpp`.`lecturers_paid_payment_id`, `lpp`.`amount`, `lpp`.`date_paid`,
        `users`.`user_fname`, `users`.`user_lname`, `users`.`profile_img`, `title`.`title`
    FROM `lecturers_paid_payments` AS `lpp`
    INNER JOIN `users` ON `users`.`user_id` = `lpp`.`users_user_id`
    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
    WHERE 1=1" . str_replace("`users_user_id`", "`lpp`.`users_user_id`", $dashboardUserFilter) . "
    ORDER BY `lpp`.`date_paid` DESC, `lpp`.`lecturers_paid_payment_id` DESC LIMIT 5");

$dashboardLatestScheduled = Database::search("SELECT * FROM (
        SELECT 'Lecture' AS payment_type, `lp`.`lecturer_payments_id` AS payment_id, `lp`.`amount`, `lp`.`date_added`, `lp`.`users_user_id`
        FROM `lecturer_payments` AS `lp` WHERE `lp`.`earning_status_earning_status_id` = '1'
        UNION ALL
        SELECT 'Assignment' AS payment_type, `ap`.`assignment_payments_id` AS payment_id, `ap`.`amount`, `ap`.`date_added`, `ap`.`users_user_id`
        FROM `assignment_payments` AS `ap` WHERE `ap`.`earning_status_earning_status_id` = '1'
    ) AS `scheduled`
    INNER JOIN `users` ON `users`.`user_id` = `scheduled`.`users_user_id`
    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
    WHERE 1=1" . str_replace("`users_user_id`", "`scheduled`.`users_user_id`", $dashboardUserFilter) . "
    ORDER BY `scheduled`.`date_added` DESC LIMIT 5");

$dashboardLatestPendingLectures = Database::search($dashboardPendingLecturesQuery . " ORDER BY tt.date DESC, tt.start_time DESC LIMIT 5");
$dashboardLatestPendingAssignments = Database::search($dashboardPendingAssignmentsQuery . " ORDER BY `last_uploaded` DESC LIMIT 5");

$dashboardPendingRows = [];
while ($row = $dashboardLatestPendingLectures->fetch_assoc()) {
    $dashboardPendingRows[] = [
        'type' => 'Lecture',
        'title' => $row['lecture_title'],
        'date' => $row['date'] . ' ' . $row['start_time'],
        'amount' => null,
        'lecturer' => $row,
    ];
}
while ($row = $dashboardLatestPendingAssignments->fetch_assoc()) {
    $dashboardPendingRows[] = [
        'type' => 'Assignment',
        'title' => $row['assignment_code'] . ' - ' . $row['assignment'] . ' (' . $row['unpaid_count'] . ' marked)',
        'date' => $row['last_uploaded'],
        'amount' => $row['unpaid_count'] * $row['per_assignment_fee'],
        'lecturer' => $row,
    ];
}
usort($dashboardPendingRows, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$dashboardPendingRows = array_slice($dashboardPendingRows, 0, 5);

$dashboardShowLecturer = empty($dashboardUserId);
$dashboardPaidRows = [];
while ($row = $dashboardLatestPaid->fetch_assoc()) {
    $dashboardPaidRows[] = [
        'main' => $dashboardShowLecturer ? $row['title'] . "." . $row['user_fname'] . " " . $row['user_lname'] : "Payout #" . $row['lecturers_paid_payment_id'],
        'sub' => date("Y-m-d", strtotime($row['date_paid'])),
        'amount' => "LKR " . number_format($row['amount'], 2),
        'badge' => "Paid",
        'badge_class' => "table-btn-success",
        'link' => $dashboardLinks['paidDetails'] . "?payment-id=" . $row['lecturers_paid_payment_id'],
        'profile_img' => $row['profile_img'],
        'user_fname' => $row['user_fname'],
        'user_lname' => $row['user_lname'],
    ];
}

$dashboardScheduledRows = [];
while ($row = $dashboardLatestScheduled->fetch_assoc()) {
    $dashboardScheduledRows[] = [
        'main' => $dashboardShowLecturer ? $row['title'] . "." . $row['user_fname'] . " " . $row['user_lname'] : $row['payment_type'] . " Payment",
        'sub' => ($dashboardShowLecturer ? $row['payment_type'] . " - " : "") . date("Y-m-d", strtotime($row['date_added'])),
        'amount' => "LKR " . number_format($row['amount'], 2),
        'badge' => "Scheduled",
        'badge_class' => "table-btn-yellow",
        'link' => $row['payment_type'] == 'Assignment' ? "lecturer-assignment-payment?payment-id=" . $row['payment_id'] : ($dashboardShowLecturer ? "lecturer-scheduled-payments-details?lecturer-id=" . $row['users_user_id'] : $dashboardLinks['scheduled']),
        'profile_img' => $row['profile_img'],
        'user_fname' => $row['user_fname'],
        'user_lname' => $row['user_lname'],
    ];
}

$dashboardPendingListRows = [];
foreach ($dashboardPendingRows as $row) {
    $lecturer = $row['lecturer'];
    $dashboardPendingListRows[] = [
        'main' => $dashboardShowLecturer ? $lecturer['title'] . "." . $lecturer['user_fname'] . " " . $lecturer['user_lname'] : $row['title'],
        'sub' => ($dashboardShowLecturer ? $row['title'] . " - " : "") . date("Y-m-d", strtotime($row['date'])),
        'amount' => $row['amount'] === null ? "-" : "LKR " . number_format($row['amount'], 2),
        'badge' => $row['type'],
        'badge_class' => $row['type'] == 'Lecture' ? "table-btn-primary" : "table-btn-pink",
        'link' => $row['type'] == 'Lecture' ? $dashboardLinks['pendingLectures'] : $dashboardLinks['pendingAssignments'],
        'profile_img' => $lecturer['profile_img'],
        'user_fname' => $lecturer['user_fname'],
        'user_lname' => $lecturer['user_lname'],
    ];
}
/* ---------- charts ---------- */
$dashboardMonthLabels = [];
$dashboardMonthPaid = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
    $dashboardMonthLabels[$monthKey] = date('M Y', strtotime($monthKey . '-01'));
    $dashboardMonthPaid[$monthKey] = 0;
}
$dashboardMonthlyPaidResult = Database::search("SELECT DATE_FORMAT(`date_paid`, '%Y-%m') AS 'month', IFNULL(SUM(`amount`),0) AS 'total'
    FROM `lecturers_paid_payments`
    WHERE `date_paid` >= '" . array_key_first($dashboardMonthPaid) . "-01'" . $dashboardUserFilter . "
    GROUP BY DATE_FORMAT(`date_paid`, '%Y-%m')");
while ($row = $dashboardMonthlyPaidResult->fetch_assoc()) {
    if (isset($dashboardMonthPaid[$row['month']])) {
        $dashboardMonthPaid[$row['month']] = (float) $row['total'];
    }
}

$dashboardCompletedLectures = Database::search("SELECT IFNULL(SUM(`amount`),0) AS 'total' FROM `lecturer_payments` WHERE `earning_status_earning_status_id` = '2'" . $dashboardUserFilter)->fetch_assoc();
$dashboardCompletedAssignments = Database::search("SELECT IFNULL(SUM(`amount`),0) AS 'total' FROM `assignment_payments` WHERE `earning_status_earning_status_id` = '2'" . $dashboardUserFilter)->fetch_assoc();

$dashboardChartData = [
    'monthLabels' => array_values($dashboardMonthLabels),
    'monthPaid' => array_values($dashboardMonthPaid),
    'status' => [
        (float) $dashboardPaid['total'],
        (float) $dashboardScheduledTotal,
        (float) $dashboardPendingAssignments['total'],
        (float) $dashboardRejectedTotal,
    ],
    'lecture' => [(float) $dashboardCompletedLectures['total'], (float) $dashboardScheduledLectures['total']],
    'assignment' => [(float) $dashboardCompletedAssignments['total'], (float) $dashboardScheduledAssignments['total']],
];

$dashboardThisMonthPaid = $dashboardMonthPaid[date('Y-m')] ?? 0;
$dashboardLastPayout = Database::search("SELECT MAX(`date_paid`) AS 'last' FROM `lecturers_paid_payments` WHERE 1=1" . $dashboardUserFilter)->fetch_assoc()['last'];
$dashboardStatusLegend = [
    ['label' => 'Paid', 'color' => '#48CD50', 'value' => $dashboardChartData['status'][0]],
    ['label' => 'Scheduled', 'color' => '#AF52DE', 'value' => $dashboardChartData['status'][1]],
    ['label' => 'Pending', 'color' => '#FF9500', 'value' => $dashboardChartData['status'][2]],
    ['label' => 'Rejected', 'color' => '#D85E44', 'value' => $dashboardChartData['status'][3]],
];

/* one stat card */
if (!function_exists('lecturerPaymentsDashboardStat')) {
    function lecturerPaymentsDashboardStat($label, $value, $lines, $icon, $iconBg, $textClass)
    {
?>
        <div class="col-12 col-md-6 col-xl-3 d-flex align-items-stretch">
            <div class="component-theme p-3 w-100 h-100">
                <div class="d-flex flex-row align-items-center">
                    <div class="hwr-70 flex-shrink-0 <?= $iconBg ?> d-flex align-items-center justify-content-center"><iconify-icon icon="<?= $icon ?>" width="32" height="32" class="<?= $textClass ?>"></iconify-icon></div>
                    <div class="d-flex flex-column flex-grow-1 ps-3">
                        <div class="text-12 fw-bold <?= $textClass ?> text-uppercase mb-1"><?= $label ?></div>
                        <div class="stat-value mb-1 text-gray-800"><?= $value ?></div>
                        <?php foreach ($lines as $line) { ?>
                            <span class="text-12 sub-text"><?= $line ?></span>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }
}

/* one latest list card */
if (!function_exists('lecturerPaymentsDashboardList')) {
    function lecturerPaymentsDashboardList($topic, $count, $viewAllLinks, $rows, $showLecturer, $emptyText)
    {
    ?>
        <div class="col-12 col-xl-4 d-flex align-items-stretch">
            <div class="component-theme p-4 w-100 h-100 d-flex flex-column">
                <div class="d-flex flex-row align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center">
                        <span class="sub-topic"><?= $topic ?></span>
                        <span class="table-btn table-btn-primary text-12 px-2 ms-2"><?= $count ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <?php foreach ($viewAllLinks as $label => $link) { ?>
                            <a href="<?= $link ?>" class="text-14 dark-theme text-decoration-none fw-medium ms-3 text-nowrap"><?= $label ?> <iconify-icon icon="mingcute:right-line" width="14" height="14"></iconify-icon></a>
                        <?php } ?>
                    </div>
                </div>
                <?php
                if (count($rows) > 0) {
                    foreach ($rows as $row) {
                ?>
                        <div class="d-flex flex-row align-items-center py-2 border-bottom <?= !empty($row['link']) ? 'cursor-pointer' : '' ?>" <?= !empty($row['link']) ? "onclick=\"window.location.href='" . $row['link'] . "'\"" : '' ?>>
                            <?php if ($showLecturer) {
                                if (!empty($row['profile_img']) && file_exists('./assets/imgs/users/' . $row['profile_img'])) { ?>
                                    <img src="./assets/imgs/users/<?= $row['profile_img'] ?>" class="hwr-40 flex-shrink-0" alt="User image">
                                <?php } else { ?>
                                    <div class="d-flex hwr-40 flex-shrink-0 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= lecturerPaymentsDashboardInitials($row['user_fname'], $row['user_lname']) ?></div>
                                <?php }
                            } else { ?>
                                <div class="d-flex hwr-40 flex-shrink-0 <?= $row['badge_class'] ?> align-items-center justify-content-center"><iconify-icon icon="<?= $row['icon'] ?>" width="20" height="20"></iconify-icon></div>
                            <?php } ?>
                            <div class="d-flex flex-column flex-grow-1 px-2 overflow-hidden">
                                <span class="text-14 main-text text-truncate"><?= $row['main'] ?></span>
                                <span class="text-12 sub-text text-truncate"><?= $row['sub'] ?></span>
                            </div>
                            <div class="d-flex flex-column align-items-end flex-shrink-0">
                                <span class="text-14 main-text fw-medium"><?= $row['amount'] ?></span>
                                <span class="table-btn <?= $row['badge_class'] ?> text-12 px-2"><?= $row['badge'] ?></span>
                            </div>
                        </div>
                    <?php
                    }
                } else {
                    ?>
                    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 py-4 text-center">
                        <div class="hwr-40 bg-light-theme dark-theme d-flex align-items-center justify-content-center mb-2"><iconify-icon icon="solar:inbox-line-linear" width="22" height="22"></iconify-icon></div>
                        <span class="text-14 main-text"><?= $emptyText ?></span>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
<?php
    }
}

foreach ($dashboardPaidRows as $i => $row) {
    $dashboardPaidRows[$i]['icon'] = 'bx:money-withdraw';
}
foreach ($dashboardScheduledRows as $i => $row) {
    $dashboardScheduledRows[$i]['icon'] = 'mingcute:schedule-line';
}
foreach ($dashboardPendingListRows as $i => $row) {
    $dashboardPendingListRows[$i]['icon'] = $row['badge'] == 'Lecture' ? 'solar:presentation-graph-linear' : 'solar:document-text-linear';
}
?>
<div class="w-100 mb-4">
    <div class="row g-4">
        <?php
        lecturerPaymentsDashboardStat("Total Paid", "LKR " . number_format($dashboardPaid['total'], 2), [
            $dashboardPaid['count'] . " payouts" . ($dashboardLastPayout ? " · last on " . date("Y-m-d", strtotime($dashboardLastPayout)) : ""),
            "This month: LKR " . number_format($dashboardThisMonthPaid, 2),
        ], "bx:money-withdraw", "table-btn-green-blue", "text-dark-green");
        lecturerPaymentsDashboardStat("Scheduled", "LKR " . number_format($dashboardScheduledTotal, 2), [
            "Lectures: LKR " . number_format($dashboardScheduledLectures['total'], 2) . " (" . $dashboardScheduledLectures['count'] . ")",
            "Assignments: LKR " . number_format($dashboardScheduledAssignments['total'], 2) . " (" . $dashboardScheduledAssignments['count'] . ")",
        ], "mingcute:schedule-line", "table-btn-purple", "text-dark-purple");
        lecturerPaymentsDashboardStat("Pending", $dashboardPendingCount . " items", [
            $dashboardPendingLecturesCount . " lectures not scheduled yet",
            $dashboardPendingAssignments['count'] . " assignments · LKR " . number_format($dashboardPendingAssignments['total'], 2),
        ], "material-symbols:pending-outline", "table-btn-orange", "text-dark-orange");
        lecturerPaymentsDashboardStat("Rejected", "LKR " . number_format($dashboardRejectedTotal, 2), [
            "Lectures: " . $dashboardRejectedLectures['count'] . " · Assignments: " . $dashboardRejectedAssignments['count'],
            "LKR " . number_format($dashboardRejectedLectures['total'], 2) . " / LKR " . number_format($dashboardRejectedAssignments['total'], 2),
        ], "zondicons:close-outline", "table-btn-danger", "text-danger");
        ?>
    </div>

    <div class="row g-4 mt-0">
        <div class="col-12 col-xl-6 d-flex align-items-stretch">
            <div class="component-theme p-4 w-100">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="sub-topic">Paid - Last 6 Months</span>
                    <span class="text-14 sub-text">LKR <?= number_format(array_sum($dashboardChartData['monthPaid']), 2) ?></span>
                </div>
                <div class="mt-3" style="height: 260px;"><canvas id="lpdMonthlyPaidChart"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3 d-flex align-items-stretch">
            <div class="component-theme p-4 w-100">
                <span class="sub-topic">Amount by Status</span>
                <div class="mt-3 mx-auto" style="height: 160px; max-width: 160px;"><canvas id="lpdStatusChart"></canvas></div>
                <div class="mt-3">
                    <?php foreach ($dashboardStatusLegend as $item) { ?>
                        <div class="d-flex align-items-center justify-content-between py-1">
                            <span class="text-14 main-text d-flex align-items-center"><span class="d-inline-block rounded-circle me-2" style="width:10px;height:10px;background:<?= $item['color'] ?>"></span><?= $item['label'] ?></span>
                            <span class="text-14 main-text fw-medium">LKR <?= number_format($item['value'], 2) ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3 d-flex align-items-stretch">
            <div class="component-theme p-4 w-100">
                <span class="sub-topic">Lectures vs Assignments</span>
                <div class="mt-3" style="height: 260px;"><canvas id="lpdTypeChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-0">
        <?php
        lecturerPaymentsDashboardList("Latest Paid", $dashboardPaid['count'], ["View all" => $dashboardLinks['paid']], $dashboardPaidRows, $dashboardShowLecturer, "No payouts yet");
        lecturerPaymentsDashboardList("Latest Scheduled", $dashboardScheduledCount, ["View all" => $dashboardLinks['scheduled']], $dashboardScheduledRows, $dashboardShowLecturer, "Nothing scheduled right now");
        lecturerPaymentsDashboardList("Pending", $dashboardPendingCount, ["Lectures" => $dashboardLinks['pendingLectures'], "Assignments" => $dashboardLinks['pendingAssignments']], $dashboardPendingListRows, $dashboardShowLecturer, "Nothing pending");
        ?>
    </div>
</div>
<script>
    window.addEventListener('load', function() {
        if (typeof Chart === 'undefined') {
            return;
        }
        const lpdData = <?= json_encode($dashboardChartData) ?>;
        const lpdMoney = (value) => 'LKR ' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const lpdTooltip = {
            callbacks: {
                label: (ctx) => (ctx.dataset.label ? ctx.dataset.label + ': ' : (ctx.label ? ctx.label + ': ' : '')) + lpdMoney(ctx.parsed.y !== undefined ? ctx.parsed.y : ctx.parsed)
            }
        };

        new Chart(document.getElementById('lpdMonthlyPaidChart'), {
            type: 'bar',
            data: {
                labels: lpdData.monthLabels,
                datasets: [{ label: 'Paid', data: lpdData.monthPaid, backgroundColor: '#21409B', borderRadius: 6, maxBarThickness: 32 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: lpdTooltip },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });

        const lpdStatusEmpty = lpdData.status.every((value) => value === 0);
        new Chart(document.getElementById('lpdStatusChart'), {
            type: 'doughnut',
            data: {
                labels: lpdStatusEmpty ? ['No Data'] : ['Paid', 'Scheduled', 'Pending', 'Rejected'],
                datasets: [{
                    data: lpdStatusEmpty ? [1] : lpdData.status,
                    backgroundColor: lpdStatusEmpty ? ['#E5EAF7'] : ['#48CD50', '#AF52DE', '#FF9500', '#D85E44'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false }, tooltip: lpdStatusEmpty ? { enabled: false } : lpdTooltip }
            }
        });

        new Chart(document.getElementById('lpdTypeChart'), {
            type: 'bar',
            data: {
                labels: ['Completed', 'Scheduled'],
                datasets: [
                    { label: 'Lectures', data: lpdData.lecture, backgroundColor: '#3658C9', borderRadius: 6, maxBarThickness: 32 },
                    { label: 'Assignments', data: lpdData.assignment, backgroundColor: '#fd53bc', borderRadius: 6, maxBarThickness: 32 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' }, tooltip: lpdTooltip },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });
    });
</script>
