<?php
require_once './includes/UserClass.php';
require_once './includes/SlipVerifier.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if ($sessionUserRole == null) {
    header("Location: ./index");
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();

if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
    header("Location: ./faculties");
    exit;
}

$id = $_GET['payment-id'];

$detailQuery = "
    SELECT
        cp.course_payments_id,
        cp.refference, cp.amount, cp.discount, cp.due_date,
        cp.panelty_payment_option_panelty_payment_option_id,
        cp.discount_note,
        ps.payment_status, ps.payment_status_id,
        i.intake_id, i.intake,
        pr.programme_id, pr.programme,
        f.faculty_id, f.faculty,
        ld.first_name, ld.last_name,
        t.title,
        pt.payment_type,
        dt.discount_type, dt.discount_type_id,
        c.code AS course_currency_code,
        c.decimal_places,
        c.currency_id AS course_currency_id,
        ppo.panelty_payment_option,
        IFNULL(pp.panelty_amount, 0) AS panelty_amount_lkr
    FROM course_payments AS cp
    INNER JOIN payment_type     AS pt   ON cp.payment_type_payment_type_id              = pt.payment_type_id
    INNER JOIN discount_type    AS dt   ON cp.discount_type_discount_type_id            = dt.discount_type_id
    INNER JOIN payment_status   AS ps   ON cp.payment_status_payment_status_id          = ps.payment_status_id
    INNER JOIN panelty_payment_option AS ppo
        ON cp.panelty_payment_option_panelty_payment_option_id = ppo.panelty_payment_option_id
    INNER JOIN payment_plan_has_students_has_intakes AS pshi
        ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
    INNER JOIN payment_category AS pc   ON pshi.payment_category_payment_category_id   = pc.payment_category_id
    INNER JOIN currencies       AS c    ON pc.currencies_currency_id                   = c.currency_id
    INNER JOIN students_has_intakes AS shi
        ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN intakes          AS i    ON shi.intakes_intake_id                       = i.intake_id
    INNER JOIN programmes       AS pr   ON i.programmes_programme_id                   = pr.programme_id
    INNER JOIN faculties        AS f    ON pr.faculties_faculty_id                     = f.faculty_id
    INNER JOIN students         AS s    ON shi.students_student_id                     = s.student_id
    INNER JOIN login_details    AS ld   ON s.login_details_login_details_id            = ld.login_details_id
    INNER JOIN title            AS t    ON s.title_title_id                            = t.title_id
    LEFT JOIN (
        SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
        FROM panelty_payments
        GROUP BY course_payments_course_payments_id
    ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
    WHERE cp.course_payments_id = '" . $id . "'
";

$paymentPlan = Database::search($detailQuery);

if ($paymentPlan->num_rows == 0) {
    header("Location: ./faculties");
    exit;
}

$paymentData = $paymentPlan->fetch_assoc();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $paymentData['intake_id']))) {
    header("Location: ./faculties");
    exit;
}

/* ══════════════════════════════════════════════════════════
   CURRENCY HELPERS
══════════════════════════════════════════════════════════ */
function resolveExchangeRate($row, $courseCurrencyId)
{
    if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrencyId) {
        return null;
    }
    if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) {
        return (float)$row['currency_exchange_rate'];
    }
    if (!empty($row['currency_exchange_rate_id'])) {
        $rateRow = Database::search(
            "SELECT rate AS currency_exchange_rate FROM currency_exchange_rates
             WHERE currency_exchange_rate_id = '" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1"
        );
        if ($rateRow && $rateRow->num_rows > 0) {
            $r = $rateRow->fetch_assoc();
            if (!empty($r['currency_exchange_rate']) && $r['currency_exchange_rate'] > 0) {
                return (float)$r['currency_exchange_rate'];
            }
        }
    }
    return null;
}

function convertToCourseAmount($amount, $exchangeRate)
{
    if ($exchangeRate === null || $exchangeRate <= 0) return (float)$amount;
    return (float)$amount / $exchangeRate;
}

/* ── Course currency ── */
$courseCurrencyId   = (int)$paymentData['course_currency_id'];
$courseCurrencyCode = $paymentData['course_currency_code'];
$lkrCurrencyId      = 1;
$isCourseLKR        = ($courseCurrencyId === $lkrCurrencyId);

/* ── CP-level discount (always in course currency) ── */
$cpDiscount = ($paymentData['discount_type_id'] == 3)
    ? $paymentData['amount'] / 100 * $paymentData['discount']
    : (float)$paymentData['discount'];

/* ── Page-level exchange rate (course → LKR) ── */
$pageExchangeRate      = null;
$pageExchangeRateValue = 1;

if (!$isCourseLKR) {
    $rateResult = Database::search("
        SELECT cer.rate, cer.effective_date, cer.currency_exchange_rate_id,
               c_base.code AS base_code, c_target.code AS target_code
        FROM currency_exchange_rates cer
        INNER JOIN currencies c_base   ON cer.base_currency_id   = c_base.currency_id
        INNER JOIN currencies c_target ON cer.target_currency_id = c_target.currency_id
        WHERE cer.base_currency_id   = '$courseCurrencyId'
          AND cer.target_currency_id = '$lkrCurrencyId'
        ORDER BY cer.effective_date DESC, cer.effective_time DESC
        LIMIT 1
    ");
    if ($rateResult && $rateResult->num_rows > 0) {
        $pageExchangeRate      = $rateResult->fetch_assoc();
        $pageExchangeRateValue = (float)$pageExchangeRate['rate'];
    }
}

/* ── panelty_payments table is always LKR → convert to course currency ── */
$penaltyLKR      = (float)$paymentData['panelty_amount_lkr'];
$penaltyInCourse = $isCourseLKR
    ? $penaltyLKR
    : ($pageExchangeRateValue > 0 ? $penaltyLKR / $pageExchangeRateValue : $penaltyLKR);

/* ── Fetch all receipt rows ── */
$receipts = Database::search("
    SELECT
        cpp.*,
        pm.payment_method,
        pm.payment_method_id,
        ps2.payment_status,
        ps2.payment_status_id,
        cur.code           AS paid_currency_code,
        cur.decimal_places AS paid_decimal_places,
        cpp.currency_exchange_rate,
        cpp.currency_exchange_rate_id,
        cpp.tax_rate,
        cpp.tax_amount
    FROM course_payment_has_payments AS cpp
    INNER JOIN payment_method AS pm  ON cpp.payment_method_payment_method_id = pm.payment_method_id
    INNER JOIN payment_status AS ps2 ON cpp.payment_status_payment_status_id = ps2.payment_status_id
    LEFT  JOIN currencies     AS cur ON cpp.currencies_currency_id           = cur.currency_id
    WHERE cpp.course_payments_course_payments_id = '" . (int)$paymentData['course_payments_id'] . "'
    AND cpp.payment_status_payment_status_id IN (2, 3)
    GROUP BY cpp.course_payment_has_payment_id
    ORDER BY cpp.date_paid ASC
");

$convertedPaidTotal   = 0;
$totalPaidApproved    = 0;
$totalReceiptDiscount = 0;
$totalReceiptPenalty  = 0;  // panelty_amount in course_payment_has_payments (paid currency → course)
$totalReceiptTax      = 0;
$receiptRows          = [];

if ($receipts && $receipts->num_rows > 0) {
    while ($rec = $receipts->fetch_assoc()) {

        $recRate = resolveExchangeRate($rec, $courseCurrencyId);

        $rec['converted_paid']      = convertToCourseAmount($rec['paid_amount'],       $recRate);
        $rec['is_foreign_currency'] = ($recRate !== null);
        $rec['resolved_rate']       = $recRate;
        $rec['paid_currency_code']  = $rec['paid_currency_code'] ?: $courseCurrencyCode;

        /* Tax → course currency */
        $rec['tax_in_course']      = convertToCourseAmount((float)($rec['tax_amount']     ?? 0), $recRate);
        /* Discount → course currency */
        $rec['discount_in_course'] = convertToCourseAmount((float)($rec['discount_amount'] ?? 0), $recRate);
        /* Penalty in cpp table is in PAID currency → convert to course */
        $rec['penalty_in_course']  = convertToCourseAmount((float)($rec['panelty_amount']  ?? 0), $recRate);

        $convertedPaidTotal += $rec['converted_paid'];
        $receiptRows[]       = $rec;

        if (in_array((int)$rec['payment_status_id'], [2, 3])) {
            $totalReceiptDiscount += $rec['discount_in_course'];
            $totalReceiptPenalty  += $rec['penalty_in_course'];
            $totalReceiptTax      += $rec['tax_in_course'];
        }
        if ((int)$rec['payment_status_id'] === 3) {
            $totalPaidApproved += $rec['converted_paid'];
        }
    }
}

/* ── Final totals (all in course currency) ── */
$totalDiscount  = $cpDiscount + $totalReceiptDiscount;
$totalPenalty   = $penaltyInCourse + $totalReceiptPenalty;
$payableAmount  = $paymentData['amount'] - $totalDiscount + $totalPenalty + $totalReceiptTax;
$balance        = $payableAmount - $convertedPaidTotal;
$totalPaidAmount = $totalPaidApproved;

/* ── LKR equivalents ── */
$balanceLKR     = $balance          * $pageExchangeRateValue;
$payableAmtLKR  = $payableAmount    * $pageExchangeRateValue;
$paidAmtLKR     = $totalPaidAmount  * $pageExchangeRateValue;
$discountAmtLKR = $totalDiscount    * $pageExchangeRateValue;
$penaltyAmtLKR  = $totalPenalty     * $pageExchangeRateValue;
$taxAmtLKR      = $totalReceiptTax  * $pageExchangeRateValue;

/* ── Progress bar ── */
$progress      = 0;
$progressColor = '';
$statusQuery   = "SELECT * FROM payment_status";
switch ($paymentData['payment_status_id']) {
    case 1:
        $progressColor = 'completed';
        $progress      = 0;
        $statusQuery  .= " WHERE payment_status_id IN (1,2,3)";
        break;
    case 2:
        $progressColor = 'completed';
        $progress      = 33;
        $statusQuery  .= " WHERE payment_status_id IN (1,2,3)";
        break;
    case 3:
        $progressColor = 'accepted';
        $progress      = 100;
        $statusQuery  .= " WHERE payment_status_id IN (1,2,3)";
        break;
    case 4:
        $progressColor = 'reject';
        $progress      = 66;
        $statusQuery  .= " WHERE payment_status_id IN (1,2,4)";
        break;
    case 5:
        $progressColor = 'accepted';
        $progress      = 100;
        $statusQuery  .= " WHERE payment_status_id IN (1,2,4,5)";
        break;
    default:
        $statusQuery  .= " WHERE payment_status_id IN (1,2,3)";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Student Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

    <style>
        .currency-label-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            background: var(--light-theme, #eef2f7);
            color: var(--sub-text-color);
            vertical-align: middle;
            border: 1px solid var(--border-color);
        }

        .convert-hint {
            font-size: 11.5px;
            color: var(--sub-text-color);
            margin-top: 3px;
            opacity: 0.8;
        }

        .detail-lkr-hint {
            font-size: 11px;
            color: var(--sub-text-color);
            opacity: 0.75;
            margin-top: 2px;
            display: block;
        }

        .exchange-rate-banner {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--light-theme);
            border: 1px solid var(--line-color);
            font-size: 12px;
            margin-bottom: 0;
        }

        .lkr-panel {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .lkr-panel-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 18px;
            border-bottom: 0.5px solid rgba(255, 255, 255, 0.12);
        }

        .lkr-panel-top-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }

        .lkr-panel-rate-note {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
        }

        .lkr-panel-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }

        .lkr-panel-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 14px 18px;
            border-right: 0.5px solid rgba(255, 255, 255, 0.10);
        }

        .lkr-panel-item:last-child {
            border-right: none;
        }

        .lkr-item-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
        }

        .lkr-item-value {
            font-size: 15px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }

        .lkr-item-sub {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.35);
        }

        .lkr-panel-item.balance-item .lkr-item-value {
            color: #5DCAA5;
        }

        @media (max-width: 768px) {
            .lkr-panel-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .lkr-panel-item:nth-child(3) {
                border-right: none;
            }

            .lkr-panel-item {
                border-bottom: 0.5px solid rgba(255, 255, 255, 0.1);
            }
        }
    </style>
</head>

<body class="overflow-hidden">
    <div class="notifications"></div>
    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">

            <!-- Side Menu -->
            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include "includes/side-menu.php";
                load_side_menu::load("Course Overview");
                ?>
            </div>

            <!-- App Wrapper -->
            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                <div class="row p-0 m-0 g-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "./includes/header.php";
                        selectItem::setSelected("Students");
                        ?>
                    </div>

                    <!-- Skeleton -->
                    <div class="container p-4 m-0" id="skeleton-list">
                        <?php
                        include "./includes/components/skeleton-header.php";
                        include "./includes/components/skeleton-add-edit.php";
                        ?>
                    </div>

                    <!-- Real Content -->
                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                        <div class="row p-4 m-0 g-0">

                            <!-- ── Breadcrumb ── -->
                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 col-md-8">
                                        <span class="main-topic lh-lg">Student Payment</span>
                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                            <ol class="breadcrumb m-0">
                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $paymentData['faculty_id'] ?>'><?= htmlspecialchars($paymentData['faculty']) ?></a></li>
                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $paymentData['programme_id'] ?>'><?= htmlspecialchars($paymentData['programme']) ?></a></li>
                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $paymentData['intake_id'] ?>'><?= htmlspecialchars($paymentData['intake']) ?></a></li>
                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= ucwords(strtolower($paymentData['title'] . ' ' . $paymentData['first_name'] . ' ' . $paymentData['last_name'])) ?></a></li>
                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payments</a></li>
                                            </ol>
                                        </nav>
                                    </div>

                                </div>
                            </div>

                            <!-- ── Main Content ── -->
                            <div class="col-12">
                                <div class="row p-4 m-0 g-0 component-theme w-100">

                                    <!-- Timeline -->
                                    <div class="col-12">
                                        <div class="payment-timeline">
                                            <div class="timeline-progress">
                                                <div class="progress-bar <?= $progressColor ?>" style="width:0%;"></div>
                                            </div>
                                            <div class="timeline-items">
                                                <?php
                                                $icons = [
                                                    "majesticons:clock",
                                                    "material-symbols:pending-outline",
                                                    "mdi:snowflake-approve",
                                                    "fontisto:close",
                                                    "mdi:approve"
                                                ];
                                                $paymentStatusRows = Database::search($statusQuery);
                                                while ($st = $paymentStatusRows->fetch_assoc()):
                                                    $timelineItemColor = '';
                                                    $bubbleAction      = '';
                                                    if ($paymentData['payment_status_id'] >= $st['payment_status_id']) {
                                                        if ($paymentData['payment_status_id'] == 4) {
                                                            $timelineItemColor = 'reject';
                                                        } elseif ($paymentData['payment_status_id'] == 5) {
                                                            $timelineItemColor = 'accepted';
                                                        } else {
                                                            $timelineItemColor = ($paymentData['payment_status_id'] == $st['payment_status_id']) ? '' : 'completed';
                                                            $bubbleAction      = ($paymentData['payment_status_id'] == $st['payment_status_id']) ? 'pulse' : '';
                                                        }
                                                    }
                                                    $iconIdx = min($st['payment_status_id'] - 1, count($icons) - 1);
                                                ?>
                                                    <div class="timeline-item <?= $timelineItemColor ?>">
                                                        <div class="timeline-bubble <?= $bubbleAction ?>">
                                                            <iconify-icon icon="<?= $icons[$iconIdx] ?>" width="25" height="25"></iconify-icon>
                                                        </div>
                                                        <div class="timeline-label">
                                                            <span><?= $st['payment_status'] ?></span>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Exchange rate badge -->
                                    <?php if (!$isCourseLKR): ?>
                                        <div class="col-12 mb-3 d-flex align-items-center gap-3 flex-wrap">
                                            <?php if ($pageExchangeRate): ?>
                                                <div class="exchange-rate-banner">
                                                    <iconify-icon icon="mdi:swap-horizontal" width="14"></iconify-icon>
                                                    <span class="main-text">
                                                        1 <?= $courseCurrencyCode ?> = LKR <?= number_format($pageExchangeRateValue, 2) ?>
                                                        &nbsp;<span class="sub-text">(<?= date('d M Y', strtotime($pageExchangeRate['effective_date'])) ?>)</span>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="exchange-rate-banner" style="border-color:#f5c6cb;background:#fff5f5;">
                                                    <iconify-icon icon="mdi:alert-outline" width="14" style="color:#a32d2d;"></iconify-icon>
                                                    <span style="color:#a32d2d;">No exchange rate available — amounts shown in <?= $courseCurrencyCode ?> only</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- LKR Equivalent Summary Panel -->
                                    <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                        <div class="col-12">
                                            <div class="lkr-panel">
                                                <div class="lkr-panel-top">
                                                    <div class="lkr-panel-top-left">
                                                        <iconify-icon icon="mdi:currency-usd" width="16"></iconify-icon>
                                                        LKR equivalent summary
                                                    </div>
                                                    <span class="lkr-panel-rate-note">
                                                        @ <?= number_format($pageExchangeRateValue, 2) ?> per <?= $courseCurrencyCode ?>
                                                    </span>
                                                </div>
                                                <div class="lkr-panel-grid">
                                                    <div class="lkr-panel-item">
                                                        <span class="lkr-item-label">Total Amount</span>
                                                        <span class="lkr-item-value">LKR <?= number_format($paymentData['amount'] * $pageExchangeRateValue, 2) ?></span>
                                                        <span class="lkr-item-sub"><?= $courseCurrencyCode ?> <?= number_format($paymentData['amount'], 2) ?></span>
                                                    </div>
                                                    <div class="lkr-panel-item">
                                                        <span class="lkr-item-label">Total Discount</span>
                                                        <span class="lkr-item-value">LKR <?= number_format($discountAmtLKR, 2) ?></span>
                                                        <span class="lkr-item-sub"><?= $courseCurrencyCode ?> <?= number_format($totalDiscount, 2) ?></span>
                                                    </div>
                                                    <div class="lkr-panel-item">
                                                        <span class="lkr-item-label">Total Penalty</span>
                                                        <span class="lkr-item-value">LKR <?= number_format($penaltyAmtLKR, 2) ?></span>
                                                        <span class="lkr-item-sub"><?= $courseCurrencyCode ?> <?= number_format($totalPenalty, 2) ?></span>
                                                    </div>
                                                    <div class="lkr-panel-item">
                                                        <span class="lkr-item-label">Total Tax</span>
                                                        <span class="lkr-item-value">LKR <?= number_format($taxAmtLKR, 2) ?></span>
                                                        <span class="lkr-item-sub"><?= $courseCurrencyCode ?> <?= number_format($totalReceiptTax, 2) ?></span>
                                                    </div>
                                                    <div class="lkr-panel-item">
                                                        <span class="lkr-item-label">Total Paid</span>
                                                        <span class="lkr-item-value">LKR <?= number_format($paidAmtLKR, 2) ?></span>
                                                        <span class="lkr-item-sub"><?= $courseCurrencyCode ?> <?= number_format($totalPaidAmount, 2) ?></span>
                                                    </div>
                                                    <div class="lkr-panel-item balance-item">
                                                        <span class="lkr-item-label">Balance Due</span>
                                                        <span class="lkr-item-value">LKR <?= number_format($balanceLKR, 2) ?></span>
                                                        <span class="lkr-item-sub"><?= $courseCurrencyCode ?> <?= number_format($balance, 2) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>



                                    <!-- ── Payment Summary Card ── -->
                                    <div class="payment-card-container mt-4">
                                        <div class="payment-card">
                                            <div class="card-background"></div>
                                            <div class="card-content">

                                                <!-- Header -->
                                                <div class="payment-header reference-section">
                                                    <div class="reference-section">
                                                        <span class="reference-label">PAYMENT REFERENCE</span>
                                                        <h2 class="reference-number"><?= htmlspecialchars($paymentData['refference']) ?></h2>
                                                        <hr>
                                                    </div>
                                                    <div class="amount-section">
                                                        <div class="amount-display">
                                                            <span class="amount-label">Total Balance Amount</span>
                                                            <?php
                                                            if ($balance > 0) {
                                                            ?>

                                                                <span class="amount-value"><?= $courseCurrencyCode ?> <?= number_format($balance, 2) ?></span>
                                                                <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                    <small class="amount-label" style="font-size:12px;opacity:.7;">
                                                                        ≈ LKR <?= number_format($balanceLKR, 2) ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            <?php
                                                            } else {
                                                            ?><span class="amount-value"><?= $courseCurrencyCode ?> <?= number_format(0, 2) ?></span><?php
                                                                                                                                                    }
                                                                                                                                                        ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Row 1: Amount | Type | Due Date | Discount -->
                                                <div class="details-row d-flex flex-column flex-lg-row">

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:cash-multiple" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Total Amount</span>
                                                            <span class="detail-value"><?= $courseCurrencyCode ?> <?= number_format($paymentData['amount'], 2) ?></span>
                                                            <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                <span class="detail-lkr-hint">≈ LKR <?= number_format($paymentData['amount'] * $pageExchangeRateValue, 2) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:card-text-outline" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Type</span>
                                                            <span class="detail-value"><?= htmlspecialchars($paymentData['payment_type']) ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:calendar-outline" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Due Date</span>
                                                            <span class="detail-value"><?= $paymentData['due_date'] ?? '--/--/----' ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:tag-outline" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Discount</span>
                                                            <span class="detail-value">
                                                                <?php
                                                                if ($paymentData['discount_type_id'] == 1) {
                                                                    echo 'No Discount';
                                                                } elseif ($paymentData['discount_type_id'] == 2) {
                                                                    echo $courseCurrencyCode . ' ' . number_format($paymentData['discount'], 2) . ' | ' . $paymentData['discount_type'];
                                                                } else {
                                                                    echo number_format($paymentData['discount']) . '% | ' . $paymentData['discount_type'];
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                </div>

                                                <!-- Row 2: Total Discount | Penalty | Tax | Payable | Paid | Balance -->
                                                <div class="details-row d-flex flex-column flex-lg-row">

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:sale" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Total Discount</span>
                                                            <span class="detail-value"><?= $courseCurrencyCode ?> <?= number_format($totalDiscount, 2) ?></span>
                                                            <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                <span class="detail-lkr-hint">≈ LKR <?= number_format($discountAmtLKR, 2) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:alert-circle-outline" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Total Penalty</span>
                                                            <span class="detail-value"><?= $courseCurrencyCode ?> <?= number_format($totalPenalty, 2) ?></span>
                                                            <?php if (!$isCourseLKR): ?>
                                                                <span class="detail-lkr-hint">LKR <?= number_format($penaltyLKR, 2) ?> (stored as LKR)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:percent-outline" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Total Tax</span>
                                                            <span class="detail-value"><?= $courseCurrencyCode ?> <?= number_format($totalReceiptTax, 2) ?></span>
                                                            <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                <span class="detail-lkr-hint">≈ LKR <?= number_format($taxAmtLKR, 2) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:calculator-variant-outline" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Total Payable</span>
                                                            <span class="detail-value"><?= $courseCurrencyCode ?> <?= number_format($payableAmount, 2) ?></span>
                                                            <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                <span class="detail-lkr-hint">≈ LKR <?= number_format($payableAmtLKR, 2) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:cash-check" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Total Paid</span>
                                                            <span class="detail-value"><?= $courseCurrencyCode ?> <?= number_format($totalPaidAmount, 2) ?></span>
                                                            <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                <span class="detail-lkr-hint">≈ LKR <?= number_format($paidAmtLKR, 2) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mdi:scale-balance" width="16"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Balance</span>
                                                            <span class="detail-value"><?= $courseCurrencyCode ?> <?= number_format($balance, 2) ?></span>
                                                            <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                <span class="detail-lkr-hint">≈ LKR <?= number_format($balanceLKR, 2) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                </div>

                                                <!-- Discount Note -->
                                                <?php if (!empty($paymentData['discount_note'])): ?>
                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                        <div class="detail-item">
                                                            <div class="detail-icon"><iconify-icon icon="hugeicons:note" width="20"></iconify-icon></div>
                                                            <div class="detail-content">
                                                                <span class="detail-label">Note</span>
                                                                <p class="detail-value"><?= htmlspecialchars($paymentData['discount_note']) ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Penalty Rules -->
                                                <?php if ($paymentData['panelty_payment_option_panelty_payment_option_id'] != 1):
                                                    $panelties = Database::search("
                                                        SELECT cpp.*, ppt.panelty_payments_type
                                                        FROM course_payments_penalties cpp
                                                        INNER JOIN panelty_payments_type ppt
                                                            ON cpp.panelty_payment_type_panelty_payment_type_id = ppt.panelty_payments_type_id
                                                        WHERE cpp.course_payments_course_payments_id = '" . $paymentData['course_payments_id'] . "'
                                                    ");
                                                    if ($panelties && $panelties->num_rows > 0):
                                                ?>
                                                        <span class="reference-label my-3">PENALTY PAYMENT ENABLED</span>
                                                        <?php while ($panelty = $panelties->fetch_assoc()): ?>
                                                            <div class="details-row d-flex flex-column flex-lg-row">
                                                                <div class="detail-item">
                                                                    <div class="detail-content">
                                                                        <span class="detail-label">Option</span>
                                                                        <span class="detail-value"><?= htmlspecialchars($paymentData['panelty_payment_option']) ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="detail-item">
                                                                    <div class="detail-content">
                                                                        <span class="detail-label">Type</span>
                                                                        <span class="detail-value"><?= htmlspecialchars($panelty['panelty_payments_type']) ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="detail-item">
                                                                    <div class="detail-content">
                                                                        <span class="detail-label">Amount (LKR — stored)</span>
                                                                        <span class="detail-value">LKR <?= number_format($panelty['amount'], 2) ?></span>
                                                                        <?php if (!$isCourseLKR && $pageExchangeRateValue > 0): ?>
                                                                            <span class="detail-lkr-hint">≈ <?= $courseCurrencyCode ?> <?= number_format($panelty['amount'] / $pageExchangeRateValue, 2) ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <?php if (!empty($panelty['days'])): ?>
                                                                    <div class="detail-item">
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Days</span>
                                                                            <span class="detail-value"><?= $panelty['days'] ?></span>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endwhile; ?>
                                                <?php endif;
                                                endif; ?>

                                            </div>
                                        </div>
                                    </div>
                                    <!-- ── End Summary Card ── -->

                                    <!-- ══════════════════════════════════════
                                         RECEIPT ACCORDION
                                    ══════════════════════════════════════ -->
                                    <div class="accordion mt-4 br-0" id="accordionReceipts">
                                        <?php foreach ($receiptRows as $i => $rec):

                                            $paidCurrCode = $rec['paid_currency_code'];
                                            $isForeign    = $rec['is_foreign_currency'];
                                            $recRate      = $rec['resolved_rate'];
                                            $statusId     = (int)$rec['payment_status_id'];

                                            /* Status badge */
                                            $statusBadge = match ($statusId) {
                                                2 => '<span class="table-btn table-btn-orange py-1 px-2 ms-2" style="font-size:10px;">Pending Approval</span>',
                                                3 => '<span class="table-btn table-btn-success py-1 px-2 ms-2" style="font-size:10px;">Approved</span>',
                                                4 => '<span class="table-btn table-btn-danger py-1 px-2 ms-2" style="font-size:10px;">Rejected</span>',
                                                default => ''
                                            };

                                            /* Look up receipt number */
                                            $receiptLabel  = '#R' . $rec['course_payment_has_payment_id'];
                                            $receiptItemId = null;
                                            if (!empty($rec['course_payment_receipts_course_payment_receipt_id'])) {
                                                $receiptItemId = (int)$rec['course_payment_receipts_course_payment_receipt_id'];
                                                $rNoRow = Database::search("SELECT receipt_number FROM course_payment_receipts
                                                    WHERE course_payment_receipt_id = '$receiptItemId' LIMIT 1");
                                                if ($rNoRow && $rNoRow->num_rows > 0) {
                                                    $receiptLabel = $rNoRow->fetch_assoc()['receipt_number'];
                                                }
                                            }
                                        ?>
                                            <div class="accordion-item br-0">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button br-0 <?= $i > 0 ? 'collapsed' : '' ?>"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#receiptCollapse<?= $i ?>"
                                                        aria-expanded="<?= $i == 0 ? 'true' : 'false' ?>">
                                                        <span class="flex-grow-1 d-flex align-items-center flex-wrap gap-1">
                                                            <span class="fw-semibold">Receipt: <?= htmlspecialchars($receiptLabel) ?></span>
                                                            <?= $statusBadge ?>
                                                        </span>
                                                        <span class="text-14 fw-semibold me-3">
                                                            <?= $paidCurrCode ?> <?= number_format($rec['paid_amount'], 2) ?>
                                                            <?php if ($isForeign): ?>
                                                                <span class="text-12 sub-text fw-normal">
                                                                    (= <?= $courseCurrencyCode ?> <?= number_format($rec['converted_paid'], 2) ?>)
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </button>
                                                </h2>
                                                <div id="receiptCollapse<?= $i ?>"
                                                    class="accordion-collapse collapse <?= $i == 0 ? 'show' : '' ?>"
                                                    data-bs-parent="#accordionReceipts">
                                                    <div class="accordion-body">
                                                        <div class="row g-4">

                                                            <!-- Exchange rate banner -->
                                                            <?php if ($isForeign && $recRate): ?>
                                                                <div class="col-12">
                                                                    <div class="exchange-rate-banner">
                                                                        <iconify-icon icon="mdi:swap-horizontal" width="14"></iconify-icon>
                                                                        <span class="main-text">
                                                                            Exchange Rate Used:
                                                                            <strong>1 <?= $courseCurrencyCode ?> = <?= $paidCurrCode ?> <?= number_format($recRate, 4) ?></strong>
                                                                            &nbsp;|&nbsp;
                                                                            <?= $paidCurrCode ?> <?= number_format($rec['paid_amount'], 2) ?>
                                                                            = <?= $courseCurrencyCode ?> <?= number_format($rec['converted_paid'], 2) ?>
                                                                            <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                                ≈ LKR <?= number_format($rec['converted_paid'] * $pageExchangeRateValue, 2) ?>
                                                                            <?php endif; ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            <?php elseif (!$isCourseLKR && $pageExchangeRate && $statusId == 3): ?>
                                                                <!-- LKR payment on non-LKR course -->
                                                                <div class="col-12">
                                                                    <div class="exchange-rate-banner">
                                                                        <iconify-icon icon="mdi:information-outline" width="14"></iconify-icon>
                                                                        <span class="main-text">
                                                                            <?= $paidCurrCode ?> <?= number_format($rec['paid_amount'], 2) ?>
                                                                            @ page rate:
                                                                            ≈ LKR <?= number_format($rec['converted_paid'] * $pageExchangeRateValue, 2) ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Paid Amount -->
                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text">
                                                                    Paid Amount
                                                                    <span class="currency-label-badge"><?= $paidCurrCode ?></span>
                                                                </label>
                                                                <input type="text" class="form-control custom-input mt-2" disabled
                                                                    value="<?= number_format($rec['paid_amount'], 2) ?>">
                                                                <?php if ($isForeign): ?>
                                                                    <span class="convert-hint">= <?= $courseCurrencyCode ?> <?= number_format($rec['converted_paid'], 2) ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                    <span class="convert-hint">≈ LKR <?= number_format($rec['converted_paid'] * $pageExchangeRateValue, 2) ?></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Discount -->
                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text">
                                                                    Discount Given
                                                                    <span class="currency-label-badge"><?= $paidCurrCode ?></span>
                                                                </label>
                                                                <input type="text" class="form-control custom-input mt-2" disabled
                                                                    value="<?= number_format($rec['discount_amount'], 2) ?>">
                                                                <?php if ($isForeign): ?>
                                                                    <span class="convert-hint">= <?= $courseCurrencyCode ?> <?= number_format($rec['discount_in_course'], 2) ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                    <span class="convert-hint">≈ LKR <?= number_format($rec['discount_in_course'] * $pageExchangeRateValue, 2) ?></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Penalty (paid currency) -->
                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text">
                                                                    Penalty Added
                                                                    <span class="currency-label-badge"><?= $paidCurrCode ?></span>
                                                                </label>
                                                                <input type="text" class="form-control custom-input mt-2" disabled
                                                                    value="<?= number_format($rec['panelty_amount'] ?? 0, 2) ?>">
                                                                <?php if ($isForeign): ?>
                                                                    <span class="convert-hint">= <?= $courseCurrencyCode ?> <?= number_format($rec['penalty_in_course'], 2) ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!$isCourseLKR && $pageExchangeRate): ?>
                                                                    <span class="convert-hint">≈ LKR <?= number_format($rec['penalty_in_course'] * $pageExchangeRateValue, 2) ?></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Tax Rate -->
                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text">Tax Rate</label>
                                                                <input type="text" class="form-control custom-input mt-2" disabled
                                                                    value="<?= ($rec['tax_rate'] > 0) ? number_format($rec['tax_rate'], 2) . '%' : 'N/A' ?>">
                                                            </div>

                                                            <!-- Tax Amount -->
                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text">
                                                                    Tax Amount
                                                                    <span class="currency-label-badge"><?= $paidCurrCode ?></span>
                                                                </label>
                                                                <input type="text" class="form-control custom-input mt-2" disabled
                                                                    value="<?= ($rec['tax_amount'] > 0) ? number_format($rec['tax_amount'], 2) : 'N/A' ?>">
                                                                <?php if ($rec['tax_amount'] > 0 && $isForeign): ?>
                                                                    <span class="convert-hint">= <?= $courseCurrencyCode ?> <?= number_format($rec['tax_in_course'], 2) ?></span>
                                                                <?php endif; ?>
                                                                <?php if ($rec['tax_amount'] > 0 && !$isCourseLKR && $pageExchangeRate): ?>
                                                                    <span class="convert-hint">≈ LKR <?= number_format($rec['tax_in_course'] * $pageExchangeRateValue, 2) ?></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Payment Method -->
                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text">Payment Method</label>
                                                                <select class="form-select custom-select mt-2" disabled>
                                                                    <?php
                                                                    $methods = Database::search("SELECT * FROM payment_method");
                                                                    while ($method = $methods->fetch_assoc()): ?>
                                                                        <option value="<?= $method['payment_method_id'] ?>"
                                                                            <?= $rec['payment_method_payment_method_id'] == $method['payment_method_id'] ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($method['payment_method']) ?>
                                                                        </option>
                                                                    <?php endwhile; ?>
                                                                </select>
                                                            </div>

                                                            <!-- Paid Date -->
                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text">Paid Date</label>
                                                                <input type="date" class="form-control custom-input mt-2" disabled
                                                                    value="<?= $rec['date_paid'] ?>">
                                                            </div>

                                                            <!-- Note -->
                                                            <?php if (!empty($rec['note'])): ?>
                                                                <div class="col-12 d-flex flex-column">
                                                                    <label class="text-14 main-text">Note</label>
                                                                    <textarea class="form-control custom-input mt-2" rows="3" disabled><?= htmlspecialchars($rec['note']) ?></textarea>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- View Receipt button -->
                                                            <?php if ($statusId == 3 && $receiptItemId): ?>
                                                                <div class="col-12 d-flex justify-content-end">
                                                                    <button class="table-btn table-btn-primary py-2 px-4"
                                                                        onclick="openReceiptModal(<?= $receiptItemId ?>)">
                                                                        <iconify-icon icon="mdi:receipt-text-outline" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>
                                                                        View Receipt
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>

                                                        </div>

                                                        <!-- Payment Slips -->
                                                        <?php
                                                        $slips = Database::search("SELECT * FROM course_payment_slips
                                                            WHERE course_payment_has_payment_id = '" . (int)$rec['course_payment_has_payment_id'] . "'");
                                                        if ($slips && $slips->num_rows > 0): ?>
                                                            <div class="col-12 mt-4">
                                                                <div class="row p-4 m-0 g-0 align-content-center bl-solid">
                                                                    <span class="main-topic lh-lg">Payment Slips</span>
                                                                    <div class="accordion accordion-flush" id="slips<?= $i ?>">
                                                                        <?php while ($slip = $slips->fetch_assoc()):
                                                                            $slipVerifier = new SlipVerifier();
                                                                            $fileType     = $slipVerifier->checkFileType($slip['slip_name']);
                                                                            $bankAccNo    = '';

                                                                            if ($rec['payment_method_id'] == 3) {
                                                                                $bank = Database::search("SELECT acc_no FROM bank LIMIT 1");
                                                                            } else {
                                                                                $bank = Database::search("SELECT acc_no FROM bank WHERE bank_id='" . (int)$rec['bank_bank_id'] . "'");
                                                                            }
                                                                            if ($bank && $bank->num_rows > 0) $bankAccNo = $bank->fetch_assoc()['acc_no'];

                                                                            $fileUrl = file_exists("./assets/uploads/payment-slips/" . $slip['slip_name'])
                                                                                ? Project::getBaseUrl()       . "/assets/uploads/payment-slips/" . $slip['slip_name']
                                                                                : Project::getBasePortalUrl() . "/assets/uploads/payment-slips/" . $slip['slip_name'];

                                                                            $fileData = $slipVerifier->verifySlip($paymentData['refference'], $bankAccNo, $rec['paid_amount'], $fileUrl);

                                                                            if ($fileType !== 'unsupported'): ?>
                                                                                <div class="accordion-item mt-4">
                                                                                    <h2 class="accordion-header bl-solid">
                                                                                        <button class="accordion-button collapsed d-flex align-items-center" type="button"
                                                                                            data-bs-toggle="collapse"
                                                                                            data-bs-target="#slip-<?= $slip['course_payment_slip_id'] ?>">
                                                                                            <img src="./assets/imgs/icons/<?= $fileType ?>.png" class="img-fluid pe-4" alt="">
                                                                                            <?php if ($fileData['verified']): ?>
                                                                                                <iconify-icon icon="ph:seal-check-duotone" width="40" height="40" class="text-dark-green"></iconify-icon>
                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                    <span class="text-dark-green text-16 fw-semibold">Payment slip successfully verified</span>
                                                                                                    <span class="text-12">Transaction details have been authenticated.</span>
                                                                                                </div>
                                                                                            <?php else: ?>
                                                                                                <iconify-icon icon="ant-design:close-circle-twotone" width="40" height="40" class="text-dark-red"></iconify-icon>
                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                    <span class="text-dark-red text-16 fw-semibold">Unable to verify payment</span>
                                                                                                    <span class="text-12">The uploaded slip appears to be invalid, duplicated, or altered.</span>
                                                                                                </div>
                                                                                            <?php endif; ?>
                                                                                        </button>
                                                                                    </h2>
                                                                                    <div id="slip-<?= $slip['course_payment_slip_id'] ?>" class="accordion-collapse collapse bl-solid-lrb">
                                                                                        <div class="accordion-body">
                                                                                            <div class="row p-0 m-0">
                                                                                                <hr>
                                                                                                <div class="col-12 col-xl-6">

                                                                                                    <div class="d-flex align-items-start mb-3">
                                                                                                        <?php if ($fileData['reference']): ?>
                                                                                                            <iconify-icon icon="gg:check-o" width="25" class="text-dark-green"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-green text-16 fw-semibold">Reference Number</span>
                                                                                                                <span class="text-12">Reference number verified and matches the transaction record.</span>
                                                                                                            </div>
                                                                                                        <?php else: ?>
                                                                                                            <iconify-icon icon="gg:close-o" width="25" class="text-dark-red"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-red text-16 fw-semibold">Reference Number</span>
                                                                                                                <span class="text-12">Reference number could not be matched.</span>
                                                                                                            </div>
                                                                                                        <?php endif; ?>
                                                                                                    </div>

                                                                                                    <div class="d-flex align-items-start mb-3">
                                                                                                        <?php
                                                                                                        $slipDate = $fileData['date'] ? date('Y-m-d', strtotime($fileData['date'])) : null;
                                                                                                        $paidDate = $rec['date_paid'] ? date('Y-m-d', strtotime($rec['date_paid'])) : null;
                                                                                                        if ($slipDate && $slipDate === $paidDate): ?>
                                                                                                            <iconify-icon icon="gg:check-o" width="25" class="text-dark-green"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-green text-16 fw-semibold">Payment Date</span>
                                                                                                                <span class="text-12">Payment date matched with bank transaction details.</span>
                                                                                                            </div>
                                                                                                        <?php else: ?>
                                                                                                            <iconify-icon icon="gg:close-o" width="25" class="text-dark-red"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-red text-16 fw-semibold">Payment Date</span>
                                                                                                                <span class="text-12">Date mismatch or missing.</span>
                                                                                                            </div>
                                                                                                        <?php endif; ?>
                                                                                                    </div>

                                                                                                    <div class="d-flex align-items-start mb-3">
                                                                                                        <?php if ($fileData['amount'] != null): ?>
                                                                                                            <iconify-icon icon="gg:check-o" width="25" class="text-dark-green"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-green text-16 fw-semibold">Payment Amount</span>
                                                                                                                <span class="text-12"><?= $rec['paid_amount'] ?> — amount confirmed in the slip.</span>
                                                                                                            </div>
                                                                                                        <?php else: ?>
                                                                                                            <iconify-icon icon="gg:close-o" width="25" class="text-dark-red"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-red text-16 fw-semibold">Payment Amount</span>
                                                                                                                <span class="text-12">Mismatch detected in the payment amount.</span>
                                                                                                            </div>
                                                                                                        <?php endif; ?>
                                                                                                    </div>

                                                                                                    <div class="d-flex align-items-start">
                                                                                                        <?php if ($fileData['account']): ?>
                                                                                                            <iconify-icon icon="gg:check-o" width="25" class="text-dark-green"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-green text-16 fw-semibold">Account Number</span>
                                                                                                                <span class="text-12">Account number is correct and consistent.</span>
                                                                                                            </div>
                                                                                                        <?php else: ?>
                                                                                                            <iconify-icon icon="gg:close-o" width="25" class="text-dark-red"></iconify-icon>
                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                <span class="text-dark-red text-16 fw-semibold">Account Number</span>
                                                                                                                <span class="text-12">Incorrect or unrecognized account number.</span>
                                                                                                            </div>
                                                                                                        <?php endif; ?>
                                                                                                    </div>

                                                                                                </div>
                                                                                                <div class="col-12 col-xl-6 mt-3 mt-xl-0">
                                                                                                    <div class="row justify-content-end align-items-center">
                                                                                                        <?php if ($fileData['verified']): ?>
                                                                                                            <div class="alert alert-success br-0">✅ This payment slip has been successfully verified.</div>
                                                                                                        <?php else: ?>
                                                                                                            <div class="alert alert-warning br-0">⚠️ Review carefully before approval.</div>
                                                                                                        <?php endif; ?>
                                                                                                        <button class="custom-btn col-4" type="button"
                                                                                                            onclick="window.open('<?= $fileUrl ?>', '_blank');">View</button>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                        <?php endif;
                                                                        endwhile; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <!-- ── End Receipt Accordion ── -->

                                    <!-- ── Payment Logs ── -->
                                    <div class="col-12 mt-4">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 mb-3">
                                                <?php
                                                $logsCount = Database::search("SELECT COUNT(*) AS cnt FROM payment_logs
                                                    WHERE course_payments_course_payments_id='" . $paymentData['course_payments_id'] . "'");
                                                $logsData  = $logsCount->fetch_assoc();
                                                ?>
                                                <div class="d-flex custom-div align-items-center justify-content-center" style="width:120px;">
                                                    <span class="text-14 dark-theme fw-medium"><?= $logsData['cnt'] ?> Logs</span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive">
                                                        <table class="table bl-solid m-0 p-0 g-0">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                            <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                        </div>
                                                                    </th>
                                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Installment</th>
                                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $logs = Database::search("
                                                                    SELECT pl.*, pl.note AS payment_note,u.user_id, u.user_fname, u.user_lname, u.profile_img,
                                                                           t.title, cpp.course_payment_has_payment_id
                                                                    FROM payment_logs pl
                                                                    INNER JOIN users u ON pl.users_user_id = u.user_id
                                                                    INNER JOIN title t ON u.title_title_id = t.title_id
                                                                    LEFT  JOIN course_payment_has_payments cpp
                                                                        ON pl.course_payment_has_payment_id = cpp.course_payment_has_payment_id
                                                                    WHERE pl.course_payments_course_payments_id = '" . $paymentData['course_payments_id'] . "'
                                                                    ORDER BY pl.date_updated DESC
                                                                ");
                                                                if ($logs && $logs->num_rows > 0):
                                                                    while ($log = $logs->fetch_assoc()): ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check"
                                                                                        id="check-user<?= $log['user_id'] ?>"
                                                                                        onchange="checkRowEvents(<?= $log['user_id'] ?>);">
                                                                                    <?php if (!empty($log['profile_img']) && file_exists('./assets/imgs/users/' . $log['profile_img'])): ?>
                                                                                        <img src="./assets/imgs/users/<?= $log['profile_img'] ?>" class="hwr-40" alt="User image">
                                                                                    <?php else:
                                                                                        $fni = strtoupper(substr($log['user_fname'], 0, 1));
                                                                                        $lni = strtoupper(substr($log['user_lname'], 0, 1)); ?>
                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $fni . $lni ?></div>
                                                                                    <?php endif; ?>
                                                                                    <span class="text-14 main-text mx-2"><?= htmlspecialchars($log['title'] . '.' . $log['user_fname'] . ' ' . $log['user_lname']) ?></span>
                                                                                </div>
                                                                            </td>
                                                                            <td class="px-4 py-2 align-middle">
                                                                                <span class="text-14 main-text">
                                                                                    <?= $log['course_payment_has_payment_id'] == null ? 'N/A' : '#R' . $log['course_payment_has_payment_id'] ?>
                                                                                </span>
                                                                            </td>
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $log['date_updated'] ?></span></td>
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($log['log'] ?? '') ?></span></td>
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $log['payment_note'] == null ? 'N/A' : htmlspecialchars($log['payment_note']) ?></span></td>
                                                                        </tr>
                                                                    <?php endwhile;
                                                                else: ?>
                                                                    <tr>
                                                                        <td colspan="5" class="align-middle text-center py-4">
                                                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data">
                                                                        </td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
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
                    <!-- End Real Content -->

                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         RECEIPT PREVIEW MODAL
    ══════════════════════════════════════════════ -->
    <div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content component-theme">
                <div class="modal-header" style="border-bottom:1px solid var(--line-color);">
                    <span class="text-16 main-text fw-semibold flex-grow-1">
                        <iconify-icon icon="mdi:receipt-text-outline" width="18" style="vertical-align:-3px;margin-right:6px;"></iconify-icon>
                        Payment Receipt
                    </span>
                    <div class="d-flex gap-2 me-3">
                        <button class="table-btn table-btn-primary py-2 px-3" onclick="downloadReceipt()">
                            <iconify-icon icon="mdi:download" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>
                            Download PDF
                        </button>
                        <button class="table-btn table-btn-success py-2 px-3" onclick="sendReceiptEmail()">
                            <iconify-icon icon="mdi:email-send-outline" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>
                            Send Email
                        </button>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="receipt-modal-body">
                    <div class="text-center py-5 sub-text text-14">
                        <iconify-icon icon="mdi:loading" width="30" style="color:var(--line-color);display:block;margin:0 auto 10px;"></iconify-icon>
                        Loading receipt…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Payment Status Modal -->
    <?php if ($paymentData['payment_status_id'] > 1 && (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $paymentData['intake_id']))): ?>
        <div class="modal fade" id="updatePaymentStatusModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content component-theme">
                    <div class="modal-header" style="border-bottom:1px solid var(--line-color);">
                        <h5 class="modal-title main-text fw-semibold">Update Payment Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row gx-4 gy-3">
                            <div class="col-12 d-flex flex-column">
                                <label class="text-14 main-text required">Payment Status</label>
                                <select class="form-select custom-select mt-2" id="status-selector">
                                    <?php
                                    $statusOptions = Database::search("SELECT * FROM payment_status WHERE payment_status_id IN (3,4)");
                                    while ($opt = $statusOptions->fetch_assoc()): ?>
                                        <option value="<?= $opt['payment_status_id'] ?>"
                                            <?= $opt['payment_status_id'] == $paymentData['payment_status_id'] ? 'selected' : '' ?>>
                                            <?= $opt['payment_status'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                            </div>
                            <div class="col-12 d-flex flex-column">
                                <label class="text-14 main-text">Note</label>
                                <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                <span id="note-feedback" class="text-dark-red text-12"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid var(--line-color);">
                        <button type="button" class="col-12 col-md-4 custom-btn"
                            onclick="updatePaymentStatus(<?= $paymentData['course_payments_id'] ?>);">
                            Update
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

    <script>
        /* ── Progress bar ── */
        document.addEventListener('DOMContentLoaded', function() {
            var pb = document.querySelector('.progress-bar');
            if (pb) setTimeout(function() {
                pb.style.width = '<?= $progress ?>%';
            }, 300);

            document.querySelectorAll('.timeline-bubble').forEach(function(bubble) {
                bubble.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.15)';
                });
                bubble.addEventListener('mouseleave', function() {
                    if (!this.closest('.timeline-item').classList.contains('active'))
                        this.style.transform = 'scale(1)';
                });
            });
        });

        /* ══════════════════════════════════════════════
           RECEIPT MODAL
        ══════════════════════════════════════════════ */
        var currentReceiptId = null;

        function openReceiptModal(receiptId) {
            currentReceiptId = receiptId;
            var body = document.getElementById('receipt-modal-body');
            if (body) {
                body.innerHTML =
                    '<div class="text-center py-5 sub-text text-14">' +
                    '<iconify-icon icon="mdi:loading" width="30" style="color:var(--line-color);display:block;margin:0 auto 10px;"></iconify-icon>' +
                    'Loading receipt…</div>';
            }
            var modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
            modal.show();

            fetch('ajax/getReceipt.php?receipt_id=' + receiptId)
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.error) {
                        body.innerHTML = '<div class="text-center py-5 text-14 sub-text">' + data.error + '</div>';
                        return;
                    }
                    renderReceiptHTML(data);
                })
                .catch(function() {
                    body.innerHTML = '<div class="text-center py-5 text-14 sub-text">Failed to load receipt.</div>';
                });
        }

        function renderReceiptHTML(data) {
            var body = document.getElementById('receipt-modal-body');
            var cc = data.course_currency || 'LKR';
            var fmtAmt = function(n) {
                return parseFloat(n || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };

            var payRows = '';
            data.payments.forEach(function(p, idx) {
                var foreignNote = p.is_foreign ?
                    '<span style="font-size:10px;opacity:.6;display:block;">' +
                    p.paid_currency + ' ' + fmtAmt(p.paid_amount) +
                    ' @ ' + fmtAmt(p.exchange_rate) + '</span>' :
                    '';
                var penNote = p.penalty_course > 0 ?
                    '<span style="font-size:10px;opacity:.6;display:block;">(LKR ' + fmtAmt(p.penalty_lkr) + ' stored)</span>' :
                    '';

                payRows +=
                    '<tr>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;">' + (idx + 1) + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;">' + p.reference + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;">' + p.payment_type + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;">' + (p.date_paid || '—') + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;">' + p.payment_method + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;text-align:right;">' + cc + ' ' + fmtAmt(p.cp_amount) + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;text-align:right;">' + cc + ' ' + fmtAmt(p.discount_amount) + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;text-align:right;">' + cc + ' ' + fmtAmt(p.penalty_course) + penNote + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;text-align:right;">' + (p.tax_rate > 0 ? p.tax_rate + '%' : '—') + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;text-align:right;">' + cc + ' ' + fmtAmt(p.tax_course) + '</td>' +
                    '<td style="padding:10px 14px;border-bottom:1px solid #e8ecf0;text-align:right;font-weight:600;">' +
                    cc + ' ' + fmtAmt(p.paid_in_course) + foreignNote + '</td>' +
                    '</tr>';
            });

            var html =
                '<div id="receipt-print-area" style="font-family:\'Segoe UI\',Arial,sans-serif;color:#1a1a2e;background:#fff;padding:40px;">' +

                '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;">' +
                '<div>' +
                '<div style="font-size:26px;font-weight:700;color:#1a1a2e;letter-spacing:-0.5px;">' + (data.branch_name || 'Institution') + '</div>' +
                '<div style="font-size:12px;color:#6b7280;margin-top:4px;line-height:1.7;">' +
                (data.branch_address ? data.branch_address + '<br>' : '') +
                (data.branch_phone ? 'Tel: ' + data.branch_phone + '<br>' : '') +
                (data.branch_email ? data.branch_email : '') +
                '</div>' +
                '</div>' +
                '<div style="text-align:right;">' +
                '<div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.08em;">Payment Receipt</div>' +
                '<div style="font-size:24px;font-weight:700;color:#2563eb;margin:4px 0;">' + data.receipt_number + '</div>' +
                '<div style="font-size:12px;color:#6b7280;">Issued: ' + (data.date_added ? data.date_added.substring(0, 10) : '') + '</div>' +
                '<div style="margin-top:8px;display:inline-block;background:#dcfce7;color:#16a34a;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;">APPROVED</div>' +
                '</div>' +
                '</div>' +

                '<div style="height:3px;background:linear-gradient(90deg,#2563eb,#7c3aed);margin-bottom:28px;border-radius:2px;"></div>' +

                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">' +
                '<div style="background:#f8fafc;padding:16px 20px;border-radius:8px;">' +
                '<div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px;">Bill To</div>' +
                '<div style="font-size:15px;font-weight:700;color:#1a1a2e;">' + (data.student_title || '') + '. ' + (data.first_name || '') + ' ' + (data.last_name || '') + '</div>' +
                '<div style="font-size:12px;color:#6b7280;margin-top:4px;">ID: ' + (data.student_reg_id || '') + '</div>' +
                '<div style="font-size:12px;color:#6b7280;">' + (data.email || '') + '</div>' +
                '<div style="font-size:12px;color:#6b7280;">' + (data.mobile || '') + '</div>' +
                '</div>' +
                '<div style="background:#f8fafc;padding:16px 20px;border-radius:8px;">' +
                '<div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px;">Programme Details</div>' +
                '<div style="font-size:13px;font-weight:600;color:#1a1a2e;">' + (data.faculty || '') + '</div>' +
                '<div style="font-size:12px;color:#6b7280;margin-top:2px;">' + (data.programme || '') + '</div>' +
                '<div style="font-size:12px;color:#6b7280;">' + (data.intake || '') + '</div>' +
                '<div style="font-size:11px;color:#9ca3af;margin-top:6px;">Processed by: ' + (data.user_title || '') + '. ' + (data.user_fname || '') + ' ' + (data.user_lname || '') + '</div>' +
                '</div>' +
                '</div>' +

                '<div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px;">Payment Details</div>' +
                '<div style="overflow-x:auto;">' +
                '<table style="width:100%;border-collapse:collapse;font-size:12px;">' +
                '<thead>' +
                '<tr style="background:#1e3a5f;color:#fff;">' +
                '<th style="padding:10px 14px;text-align:left;font-weight:600;">#</th>' +
                '<th style="padding:10px 14px;text-align:left;font-weight:600;">Reference</th>' +
                '<th style="padding:10px 14px;text-align:left;font-weight:600;">Type</th>' +
                '<th style="padding:10px 14px;text-align:left;font-weight:600;">Date</th>' +
                '<th style="padding:10px 14px;text-align:left;font-weight:600;">Method</th>' +
                '<th style="padding:10px 14px;text-align:right;font-weight:600;">Amount</th>' +
                '<th style="padding:10px 14px;text-align:right;font-weight:600;">Discount</th>' +
                '<th style="padding:10px 14px;text-align:right;font-weight:600;">Penalty</th>' +
                '<th style="padding:10px 14px;text-align:right;font-weight:600;">Tax %</th>' +
                '<th style="padding:10px 14px;text-align:right;font-weight:600;">Tax Amt</th>' +
                '<th style="padding:10px 14px;text-align:right;font-weight:600;">Paid</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>' + payRows + '</tbody>' +
                '</table>' +
                '</div>' +

                '<div style="display:flex;justify-content:flex-end;margin-top:24px;">' +
                '<div style="min-width:320px;">' +
                '<div style="display:flex;justify-content:space-between;padding:8px 14px;font-size:12px;border-bottom:1px solid #e8ecf0;">' +
                '<span style="color:#6b7280;">Total Discount</span>' +
                '<span>' + cc + ' ' + fmtAmt(data.grand_total_discount) + '</span>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;padding:8px 14px;font-size:12px;border-bottom:1px solid #e8ecf0;">' +
                '<span style="color:#6b7280;">Total Penalty</span>' +
                '<span>' + cc + ' ' + fmtAmt(data.grand_total_penalty) + '</span>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;padding:8px 14px;font-size:12px;border-bottom:1px solid #e8ecf0;">' +
                '<span style="color:#6b7280;">Total Tax</span>' +
                '<span>' + cc + ' ' + fmtAmt(data.grand_total_tax) + '</span>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;padding:12px 14px;font-size:15px;font-weight:700;background:#1e3a5f;color:#fff;border-radius:0 0 6px 6px;">' +
                '<span>Total Paid</span>' +
                '<span>' + cc + ' ' + fmtAmt(data.grand_total_paid) + '</span>' +
                '</div>' +
                '</div>' +
                '</div>' +

                '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e8ecf0;font-size:11px;color:#9ca3af;text-align:center;">' +
                'This is a computer-generated receipt and does not require a physical signature.<br>' +
                'Receipt No: <strong style="color:#6b7280;">' + data.receipt_number + '</strong>' +
                '</div>' +

                '</div>';

            body.innerHTML = html;
        }

        function downloadReceipt() {
            var printArea = document.getElementById('receipt-print-area');
            if (!printArea) return;
            var win = window.open('', '_blank');
            win.document.write(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt</title>' +
                '<style>body{margin:0;padding:0;} @media print{body{-webkit-print-color-adjust:exact;}}</style>' +
                '</head><body>' + printArea.outerHTML +
                '<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>' +
                '</body></html>'
            );
            win.document.close();
        }

        function sendReceiptEmail() {
            if (!currentReceiptId) return;
            var btn = document.querySelector('#receiptPreviewModal .table-btn-success');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<iconify-icon icon="mdi:loading" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon> Sending…';
            }
            fetch('ajax/sendReceiptEmail.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        receipt_id: currentReceiptId
                    }),
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<iconify-icon icon="mdi:email-send-outline" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon> Send Email';
                    }
                    if (res.done) showToast('success', res.done);
                    else showToast('error', res.error || 'Failed to send email.');
                })
                .catch(function() {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<iconify-icon icon="mdi:email-send-outline" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon> Send Email';
                    }
                    showToast('error', 'Something went wrong.');
                });
        }
    </script>
</body>

</html>