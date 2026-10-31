<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$currencyId = isset($_POST['currency']) ? (int)$_POST['currency'] : 0;
$year       = isset($_POST['year'])     ? (int)$_POST['year']     : 0;
$month      = isset($_POST['month'])    ? (int)$_POST['month']    : 0;

if ($currencyId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid currency']);
    exit;
}
if ($month < 1 || $month > 12) {
    $month = 0;
}
if ($month > 0 && $year <= 0) {
    $year = (int)date('Y');
}

$dateCond = '';
if ($year > 0)  $dateCond .= " AND YEAR(cpp.date_paid) = " . $year;
if ($month > 0) $dateCond .= " AND MONTH(cpp.date_paid) = " . $month;

$monthNames = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

$period = 'All Time';
if ($year > 0 && $month > 0) {
    $period = $monthNames[$month] . ' ' . $year;
} elseif ($year > 0) {
    $period = (string)$year;
}

// Zero-fill every branch
$totals = [];
$allBranches = Database::search("SELECT branch_id, branch FROM branches ORDER BY branch");
while ($b = $allBranches->fetch_assoc()) {
    $totals[$b['branch']] = ['initial' => 0, 'subsequent' => 0];
}

$rs = Database::search("
    SELECT
        b.branch AS branch_name,
        CASE
            WHEN (pc.payment_category_id = 4 AND cp.payment_type_payment_type_id = 20)
              OR (pc.payment_category_id != 4 AND cp.payment_type_payment_type_id = 18)
            THEN 'initial'
            ELSE 'subsequent'
        END AS payment_bucket,
        SUM(cpp.paid_amount) AS total
    FROM course_payment_has_payments cpp
    INNER JOIN course_payments cp
        ON cpp.course_payments_course_payments_id = cp.course_payments_id
    INNER JOIN payment_plan_has_students_has_intakes ppsi
        ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category pc
        ON ppsi.payment_category_payment_category_id = pc.payment_category_id
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
      " . $dateCond . "
    GROUP BY b.branch_id, b.branch, payment_bucket
");

while ($row = $rs->fetch_assoc()) {
    if (!isset($totals[$row['branch_name']])) {
        $totals[$row['branch_name']] = ['initial' => 0, 'subsequent' => 0];
    }
    $totals[$row['branch_name']][$row['payment_bucket']] = (float)$row['total'];
}

$labels = array_keys($totals);
$initial = [];
$subsequent = [];
foreach ($labels as $l) {
    $initial[]    = $totals[$l]['initial'];
    $subsequent[] = $totals[$l]['subsequent'];
}

echo json_encode([
    'success'    => true,
    'period'     => $period,
    'labels'     => $labels,
    'initial'    => $initial,
    'subsequent' => $subsequent
]);
