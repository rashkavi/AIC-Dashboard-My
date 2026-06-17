<?php
require_once './includes/UserClass.php';
require_once './includes/SlipVerifier.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
            header("Location: ./faculties");
        } else {
            date_default_timezone_set('Asia/Colombo');
            $id = $_GET['payment-id'];

            $inquiry_id = 0;
            if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['inquiry-id']) && !empty($_GET['inquiry-id'])) {
                $inquiry_id = $_GET['inquiry-id'];
            }

            $detailQuery = "
                SELECT
                    `cp`.`course_payments_id`,
                    `cp`.`refference`, `cp`.`amount`, `cp`.`discount`, `cp`.`due_date`,
                    `cp`.`panelty_payment_option_panelty_payment_option_id`,
                    `cp`.`discount_note`,
                    `ps`.`payment_status`, `ps`.`payment_status_id`,
                    `i`.`intake_id`, `i`.`intake`,
                    `pr`.`programme_id`, `pr`.`programme`,
                    `f`.`faculty_id`, `f`.`faculty`,
                    `ld`.`first_name`, `ld`.`last_name`,
                    `t`.`title`,
                    `pt`.`payment_type`,
                    `pt`.`payment_type_id`,
                    `dt`.`discount_type`, `dt`.`discount_type_id`,
                    `c`.`code` AS `currency_code`, `c`.`decimal_places`,
                    `c`.`currency_id`,
                    `ppo`.`panelty_payment_option`,
                    IFNULL(pp.panelty_amount, 0) AS panelty_amount_lkr
                FROM `course_payments` AS `cp`
                INNER JOIN `payment_type` AS `pt`     ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
                INNER JOIN `discount_type` AS `dt`    ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                INNER JOIN `payment_status` AS `ps`   ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                INNER JOIN `payment_category` AS `pc` ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                INNER JOIN `currencies` AS `c`        ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                INNER JOIN `students_has_intakes` AS `shi`
                                                    ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                INNER JOIN `intakes` AS `i`           ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                INNER JOIN `programmes` AS `pr`       ON `i`.`programmes_programme_id` = `pr`.`programme_id`
                INNER JOIN `faculties` AS `f`         ON `pr`.`faculties_faculty_id` = `f`.`faculty_id`
                INNER JOIN `students` AS `s`          ON `shi`.`students_student_id` = `s`.`student_id`
                INNER JOIN `login_details` AS `ld`    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                INNER JOIN `title` AS `t`             ON `s`.`title_title_id` = `t`.`title_id`
                INNER JOIN `panelty_payment_option` AS `ppo`
                                                    ON `cp`.`panelty_payment_option_panelty_payment_option_id` = `ppo`.`panelty_payment_option_id`
                LEFT JOIN (
                    SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                    FROM panelty_payments GROUP BY course_payments_course_payments_id
                ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                WHERE `cp`.`course_payments_id` = '" . $id . "';
            ";

            $paymentPlan = Database::search($detailQuery);

            if ($paymentPlan->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $paymentData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $ifHasPermissions = false;
                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id'])) {
                    $ifHasPermissions = true;
                }
                if ($inquiry_id != 0 && (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 15))) {
                    $ifHasPermissions = true;
                }
                if (!$ifHasPermissions) {
                    $inquiry_id == 0 ? header("Location: ./faculties") : header("Location: ./inquiries");
                } else {
                    if ($paymentData['payment_status_id'] != 1) {
                        header("Location: ./batch-student-payments?student-id=" . $paymentData['students_has_intakes_id']);
                    } else {

                        // ============================================================
                        // FETCH LATEST TAX RATE FROM DATABASE
                        // ============================================================
                        $latestTaxRateQuery = Database::search("
                            SELECT `tax_rate` AS `rate` FROM `tax_rates`
                            ORDER BY `date_added` DESC LIMIT 1
                        ");
                        $latestTaxRate = 0;
                        if ($latestTaxRateQuery && $latestTaxRateQuery->num_rows > 0) {
                            $taxRateRow    = $latestTaxRateQuery->fetch_assoc();
                            $latestTaxRate = (float)$taxRateRow['rate'];
                        }

                        // ============================================================
                        // HELPERS
                        // ============================================================
                        function resolveExchangeRate($db, $row, $courseCurrencyId)
                        {
                            if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrencyId) {
                                return null;
                            }
                            if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) {
                                return (float)$row['currency_exchange_rate'];
                            }
                            if (!empty($row['currency_exchange_rate_id'])) {
                                $rateRow = Database::search(
                                    "SELECT `rate` AS `currency_exchange_rate` FROM `currency_exchange_rates`
                                     WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1"
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

                        function convertToCourseAmount($paidAmount, $exchangeRate)
                        {
                            if ($exchangeRate === null || $exchangeRate <= 0) return (float)$paidAmount;
                            return (float)$paidAmount / $exchangeRate;
                        }

                        function convertLkrPenaltyToCourseCurrency($lkrAmount, $courseCurrencyId)
                        {
                            static $lkrCurrencyId = null;
                            if ($lkrCurrencyId === null) {
                                $res = Database::search("SELECT `currency_id` FROM `currencies` WHERE `code` = 'LKR' LIMIT 1");
                                $lkrCurrencyId = ($res && $res->num_rows > 0) ? (int)$res->fetch_assoc()['currency_id'] : 0;
                            }
                            if ($lkrCurrencyId === 0 || $courseCurrencyId == $lkrCurrencyId) {
                                return (float)$lkrAmount;
                            }
                            $rateRow = Database::search(
                                "SELECT `rate` FROM `currency_exchange_rates`
                                 WHERE `base_currency_id` = '" . (int)$courseCurrencyId . "'
                                 ORDER BY `currency_exchange_rate_id` DESC LIMIT 1"
                            );
                            if ($rateRow && $rateRow->num_rows > 0) {
                                $rate = (float)$rateRow->fetch_assoc()['rate'];
                                if ($rate > 0) return (float)$lkrAmount / $rate;
                            }
                            return (float)$lkrAmount;
                        }

                        // ============================================================
                        // CP-LEVEL DISCOUNT
                        // ============================================================
                        $discountAmount = ($paymentData['discount_type_id'] == 3)
                            ? $paymentData['amount'] / 100 * $paymentData['discount']
                            : (float)$paymentData['discount'];

                        $paneltyPaymentsConverted = convertLkrPenaltyToCourseCurrency(
                            $paymentData['panelty_amount_lkr'],
                            $paymentData['currency_id']
                        );

                        $totalDiscount = $discountAmount;
                        $totalPenalty  = $paneltyPaymentsConverted;
                        $totalTax      = 0;

                        // ============================================================
                        // FETCH RECEIPTS
                        // ============================================================
                        $receipts = Database::search("
                            SELECT
                                cpp.*,
                                pm.payment_method,
                                pm.payment_method_id,
                                ps2.payment_status,
                                ps2.payment_status_id,
                                cpp.tax_amount,
                                cpp.tax_rate,
                                cur.code AS paid_currency_code,
                                cur.decimal_places AS paid_decimal_places,
                                cpp.currency_exchange_rate
                            FROM course_payment_has_payments AS cpp
                            INNER JOIN payment_method AS pm  ON cpp.payment_method_payment_method_id = pm.payment_method_id
                            INNER JOIN payment_status AS ps2 ON cpp.payment_status_payment_status_id = ps2.payment_status_id
                            LEFT JOIN currencies AS cur      ON cpp.currencies_currency_id = cur.currency_id
                            WHERE cpp.course_payments_course_payments_id = '" . (int)$paymentData['course_payments_id'] . "'
                            AND ps2.payment_status_id IN (2, 3)
                            GROUP BY cpp.course_payment_has_payment_id
                        ");

                        $convertedPaidTotal = 0;
                        $receiptRows        = [];
                        $totalPaidAmount    = 0;

                        if ($receipts && $receipts->num_rows > 0) {
                            while ($rec = $receipts->fetch_assoc()) {
                                $rate = resolveExchangeRate(null, $rec, $paymentData['currency_id']);
                                $rec['converted_paid']         = convertToCourseAmount($rec['paid_amount'], $rate);
                                $rec['is_foreign_currency']    = ($rate !== null);
                                $rec['currency_exchange_rate'] = $rate;
                                $convertedPaidTotal += $rec['converted_paid'];
                                $receiptRows[]       = $rec;

                                if ($rec['payment_status_id'] == 3) {
                                    $totalPaidAmount += $rec['converted_paid'];
                                }
                                if (in_array($rec['payment_status_id'], [2, 3])) {
                                    $totalDiscount += convertToCourseAmount((float)($rec['discount_amount'] ?? 0), $rate);
                                    $totalPenalty  += convertToCourseAmount((float)($rec['panelty_amount']  ?? 0), $rate);
                                    $totalTax      += convertToCourseAmount((float)($rec['tax_amount']       ?? 0), $rate);
                                }
                            }
                        }

                        // ============================================================
                        // BALANCE CALCULATION — tax-inclusive model
                        // tax is % of final (tax-inclusive) amount
                        // base = final * (1 - taxRate/100)
                        // ============================================================
                        $basePayable    = $paymentData['amount'] - $totalDiscount + $totalPenalty;
                        $taxRateDecimal = $latestTaxRate / 100;
                        $basePortion    = 1 - $taxRateDecimal;

                        $paidBaseTotal  = ($basePortion > 0) ? $convertedPaidTotal * $basePortion : 0;
                        $remainingBase  = max(0, $basePayable - $paidBaseTotal);
                        $remainingTax   = ($basePortion > 0 && $latestTaxRate > 0)
                            ? $remainingBase * ($latestTaxRate / (100 - $latestTaxRate))
                            : 0;

                        $balance    = $remainingBase + $remainingTax;
                        $paidAmount = $convertedPaidTotal;

                        // ============================================================
                        // EXCHANGE RATE: course_currency → LKR
                        // ============================================================
                        $lkrCurrencyId     = 1;
                        $exchangeRate      = null;
                        $exchangeRateValue = 1;
                        $exchangeRateId    = 0;

                        if ($paymentData['currency_id'] != $lkrCurrencyId) {
                            $rateResult = Database::search("
                                SELECT
                                    cer.`rate`,
                                    cer.`effective_date`,
                                    cer.`effective_time`,
                                    c_base.`code`   AS base_code,
                                    c_base.`symbol` AS base_symbol,
                                    c_target.`code` AS target_code,
                                    cer.`currency_exchange_rate_id`
                                FROM `currency_exchange_rates` cer
                                INNER JOIN `currencies` c_base   ON cer.`base_currency_id`   = c_base.`currency_id`
                                INNER JOIN `currencies` c_target ON cer.`target_currency_id` = c_target.`currency_id`
                                WHERE cer.`base_currency_id`   = '" . (int)$paymentData['currency_id'] . "'
                                  AND cer.`target_currency_id` = '" . $lkrCurrencyId . "'
                                ORDER BY cer.`effective_date` DESC, cer.`effective_time` DESC
                                LIMIT 1
                            ");
                            if ($rateResult->num_rows > 0) {
                                $exchangeRate      = $rateResult->fetch_assoc();
                                $exchangeRateValue = (float)$exchangeRate['rate'];
                                $exchangeRateId    = $exchangeRate['currency_exchange_rate_id'] ?? null;
                            }
                        }

                        // LKR equivalents for summary panels
                        $taxOnBasePayable = ($basePortion > 0 && $latestTaxRate > 0)
                            ? $basePayable * ($latestTaxRate / (100 - $latestTaxRate))
                            : 0;
                        $totalPayableWithTax = $basePayable + $taxOnBasePayable;

                        $balanceLKR     = $balance             * $exchangeRateValue;
                        $payableAmtLKR  = $totalPayableWithTax * $exchangeRateValue;
                        $paidAmountLKR  = $paidAmount          * $exchangeRateValue;
                        $discountAmtLKR = $totalDiscount       * $exchangeRateValue;
                        $penaltyAmtLKR  = $paymentData['panelty_amount_lkr']
                            + ($totalPenalty - $paneltyPaymentsConverted) * $exchangeRateValue;
                        $taxAmtLKR      = $totalTax            * $exchangeRateValue;

                        $isBaseCurrencyLKR = ($paymentData['currency_id'] == $lkrCurrencyId);
?>
                        <!DOCTYPE html>
                        <html lang="en">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>Add Receipt to Student Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>
                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
                        </head>

                        <style>
                            /* ── Currency toggle ── */
                            .currency-toggle {
                                display: flex;
                                border: 0.5px solid var(--border-color);
                                border-radius: 8px;
                                overflow: hidden;
                            }

                            .currency-toggle-btn {
                                padding: 5px 14px;
                                font-size: 12px;
                                font-weight: 500;
                                border: none;
                                background: transparent;
                                color: var(--sub-text-color);
                                cursor: pointer;
                                transition: background 0.15s, color 0.15s;
                            }

                            .currency-toggle-btn.active {
                                background: var(--dark-theme);
                                color: #fff;
                            }

                            /* ── Exchange rate badge ── */
                            .exchange-rate-badge {
                                display: inline-flex;
                                align-items: center;
                                gap: 5px;
                                padding: 4px 10px;
                                border-radius: 20px;
                                background: var(--color-background-secondary, #f0f4f8);
                                border: 1px solid var(--border-color);
                                font-size: 11.5px;
                                color: var(--sub-text-color);
                            }

                            .rate-unavailable {
                                border-color: #f5c6cb;
                                background: #fff5f5;
                                color: #a32d2d;
                            }

                            /* ── Currency label badge ── */
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

                            /* ── Convert hint ── */
                            .convert-hint {
                                font-size: 11.5px;
                                color: var(--sub-text-color);
                                margin-top: 3px;
                                opacity: 0.8;
                            }

                            /* ── LKR Summary Panel ── */
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
                                font-size: 16px;
                                font-weight: 500;
                                color: rgba(255, 255, 255, 0.9);
                            }

                            .lkr-panel-top-left iconify-icon {
                                color: rgba(255, 255, 255, 0.6);
                            }

                            .lkr-panel-rate-note {
                                font-size: 13px;
                                color: rgba(255, 255, 255, 0.5);
                            }

                            .lkr-panel-grid {
                                display: grid;
                                grid-template-columns: repeat(7, minmax(0, 1fr));
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
                                font-size: 12px;
                                font-weight: 500;
                                letter-spacing: 0.04em;
                                text-transform: uppercase;
                                color: rgba(255, 255, 255, 0.5);
                            }

                            .lkr-item-value {
                                font-size: 16px;
                                font-weight: 500;
                                color: rgba(255, 255, 255, 0.9);
                            }

                            .lkr-item-sub {
                                font-size: 12px;
                                color: rgba(255, 255, 255, 0.35);
                            }

                            .lkr-panel-item.balance-item .lkr-item-value {
                                color: #5DCAA5;
                            }

                            /* ── Overpayment Panel ── */
                            .overpay-panel {
                                background: var(--color-background-secondary, #f0f4f8);
                                border: 1px solid var(--border-color);
                                border-radius: 10px;
                                overflow: hidden;
                            }

                            .overpay-panel-title {
                                display: flex;
                                align-items: center;
                                gap: 8px;
                                padding: 11px 16px;
                                font-size: 11.5px;
                                font-weight: 600;
                                letter-spacing: 0.05em;
                                text-transform: uppercase;
                                color: var(--sub-text-color);
                                border-bottom: 1px solid var(--border-color);
                                background: var(--color-background-secondary, #f0f4f8);
                            }

                            .overpay-grid {
                                display: grid;
                                grid-template-columns: repeat(3, 1fr);
                            }

                            .overpay-item {
                                display: flex;
                                flex-direction: column;
                                gap: 3px;
                                padding: 14px 18px;
                                border-right: 1px solid var(--border-color);
                            }

                            .overpay-item:last-child {
                                border-right: none;
                            }

                            .overpay-label {
                                font-size: 11px;
                                font-weight: 600;
                                text-transform: uppercase;
                                letter-spacing: 0.04em;
                                color: var(--sub-text-color);
                            }

                            .overpay-value {
                                font-size: 18px;
                                font-weight: 700;
                                color: var(--main-text-color, #1f2937);
                                margin-top: 2px;
                            }

                            .overpay-sub {
                                font-size: 12px;
                                color: var(--sub-text-color);
                                opacity: 0.75;
                                margin-top: 2px;
                            }

                            .overpay-total {
                                font-size: 12px;
                                font-weight: 600;
                                color: var(--main-text-color, #1f2937);
                                margin-top: 6px;
                                padding-top: 6px;
                                border-top: 1px dashed var(--border-color);
                            }

                            /* ── Responsive ── */
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

                                .overpay-grid {
                                    grid-template-columns: 1fr;
                                }

                                .overpay-item {
                                    border-right: none;
                                    border-bottom: 1px solid var(--border-color);
                                }

                                .overpay-item:last-child {
                                    border-bottom: none;
                                }
                            }
                        </style>

                        <body class="overflow-hidden">
                            <div class="notifications"></div>
                            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                                <div id="appContainer" class="row p-0">
                                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                        <?php
                                        include "includes/side-menu.php";
                                        $inquiry_id == 0 ? load_side_menu::load("Course Overview") : load_side_menu::load("Payments");
                                        ?>
                                    </div>
                                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 p-0 m-0 g-0">
                                                <?php
                                                include "./includes/header.php";
                                                $inquiry_id == 0 ? selectItem::setSelected("Course Overview") : selectItem::setSelected("Payments");
                                                ?>
                                            </div>
                                            <div class="container p-4 m-0" id="skeleton-list">
                                                <?php
                                                include "./includes/components/skeleton-header.php";
                                                include "./includes/components/skeleton-add-edit.php";
                                                ?>
                                            </div>
                                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                                <div class="row p-4 m-0 g-0">

                                                    <!-- ── Breadcrumb ── -->
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 col-md-10">
                                                                <div class="row">
                                                                    <span class="main-topic lh-lg">Add Receipt to Student Payment</span>
                                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                        <ol class="breadcrumb m-0">
                                                                            <?php if ($inquiry_id == 0): ?>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $paymentData['faculty_id'] ?>'><?= $paymentData['faculty']; ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $paymentData['programme_id'] ?>'>...</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='batch-details?batch-id=<?= $paymentData['intake_id'] ?>'><?= $paymentData['intake']; ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo ucwords(strtolower($paymentData['title'] . " " . $paymentData['first_name'] . " " . $paymentData['last_name'])); ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payments</a></li>
                                                                            <?php else: ?>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Payments</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./inquiries-registration-payments">Inquiry Registration Payments</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./inquiry-payment-plan?inquiry-id=<?= $inquiry_id ?>">Inquiry Payment Plan</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="#">Add Receipt to Student Payment</a></li>
                                                                            <?php endif; ?>
                                                                        </ol>
                                                                    </nav>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12">
                                                        <div class="row p-4 m-0 g-0 component-theme w-100">

                                                            <!-- ── Payment Summary Card ── -->
                                                            <div class="payment-card-container mt-4">
                                                                <div class="payment-card">
                                                                    <div class="card-background"></div>
                                                                    <div class="card-content">
                                                                        <div class="payment-header reference-section">
                                                                            <div class="reference-section">
                                                                                <span class="reference-label">PAYMENT REFERENCE</span>
                                                                                <h2 class="reference-number"><?= $paymentData["refference"]; ?></h2>
                                                                                <hr>
                                                                            </div>
                                                                            <div class="amount-section">
                                                                                <div class="amount-display">
                                                                                    <span class="amount-label">Total Balance Amount (Next Installment)</span>
                                                                                    <span class="amount-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($balance, $paymentData['decimal_places']); ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Row 1 -->
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Amount</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($paymentData['amount'], $paymentData['decimal_places']) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Type</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData["payment_type"]); ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Due Date</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData["due_date"] ?? '--/--/----'); ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 6.586V2zm3.5 4a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" />
                                                                                        <path d="M1.293 7.793A1 1 0 0 1 1 7.086V2a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l.043-.043-7.457-7.457z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Discount</span>
                                                                                    <span class="detail-value">
                                                                                        <?php
                                                                                        if ($paymentData["discount_type_id"] == 1) {
                                                                                            echo "No Discount";
                                                                                        } elseif ($paymentData["discount_type_id"] == 2) {
                                                                                            echo htmlspecialchars($paymentData['currency_code']) . " " . number_format($paymentData["discount"], $paymentData['decimal_places']) . " | " . htmlspecialchars($paymentData["discount_type"]);
                                                                                        } else {
                                                                                            echo number_format($paymentData["discount"], 0) . "% | " . htmlspecialchars($paymentData["discount_type"]);
                                                                                        }
                                                                                        ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Row 2 -->
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Discount</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalDiscount, $paymentData['decimal_places']) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Penalty Amount</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalPenalty, $paymentData['decimal_places']) ?>
                                                                                        <?php if (!$isBaseCurrencyLKR && $paymentData['panelty_amount_lkr'] > 0): ?>
                                                                                            <small class="d-block" style="font-size:11px;opacity:.6;">(LKR <?= number_format($paymentData['panelty_amount_lkr'], 2) ?> converted)</small>
                                                                                        <?php endif; ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Tax (paid receipts)</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalTax, $paymentData['decimal_places']) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Payable Amount</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalPayableWithTax, $paymentData['decimal_places']) ?>
                                                                                        <small class="d-block" style="font-size:11px;opacity:.6;">Base <?= number_format($basePayable, $paymentData['decimal_places']) ?> + Tax <?= number_format($taxOnBasePayable, $paymentData['decimal_places']) ?></small>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Paid Amount</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($paidAmount, $paymentData['decimal_places']) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Balance / Next Installment</span>
                                                                                    <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($balance, $paymentData['decimal_places']) ?>
                                                                                        <small class="d-block" style="font-size:11px;opacity:.6;">Base <?= number_format($remainingBase, $paymentData['decimal_places']) ?> + Tax <?= number_format($remainingTax, $paymentData['decimal_places']) ?></small>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <?php if ($paymentData['panelty_payment_option_panelty_payment_option_id'] != 1): ?>
                                                                            <span class="reference-label my-3">PENALTY PAYMENT ENABLED</span>
                                                                            <?php
                                                                            $panelties = Database::search("
                                                        SELECT * FROM `course_payments_penalties`
                                                        INNER JOIN `panelty_payments_type`
                                                            ON `course_payments_penalties`.`panelty_payment_type_panelty_payment_type_id` = `panelty_payments_type`.`panelty_payments_type_id`
                                                        WHERE `course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "'
                                                    ");
                                                                            if ($panelties->num_rows > 0):
                                                                                while ($panelty = $panelties->fetch_assoc()): ?>
                                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                                </svg></div>
                                                                                            <div class="detail-content"><span class="detail-label">Option</span><span class="detail-value"><?= htmlspecialchars($paymentData["panelty_payment_option"] ?? ''); ?></span></div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                                </svg></div>
                                                                                            <div class="detail-content"><span class="detail-label">Type</span><span class="detail-value"><?= htmlspecialchars($panelty["panelty_payments_type"] ?? ''); ?></span></div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                                    <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                                </svg></div>
                                                                                            <div class="detail-content"><span class="detail-label">Amount</span><span class="detail-value">LKR <?= number_format($panelty["amount"] ?? 0, 2); ?></span></div>
                                                                                        </div>
                                                                                        <?php if (!empty($panelty["days"])): ?>
                                                                                            <div class="detail-item">
                                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                                                    </svg></div>
                                                                                                <div class="detail-content"><span class="detail-label">Days</span><span class="detail-value"><?= htmlspecialchars($panelty["days"] ?? ''); ?></span></div>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                            <?php endwhile;
                                                                            endif; ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- ── Add Receipt Form ── -->
                                                            <div class="col-12 p-0 mt-4">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <div class="col-12">
                                                                        <div class="row g-4">

                                                                            <!-- ── Header + currency toggle ── -->
                                                                            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-3">

                                                                                <?php if (!$isBaseCurrencyLKR): ?>
                                                                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                                                                        <span class="text-14 main-text fw-medium">Payment Currency:</span>
                                                                                        <div class="currency-toggle">
                                                                                            <button type="button" class="currency-toggle-btn active" id="btn-base-currency" onclick="switchCurrency('base')"><?= htmlspecialchars($paymentData['currency_code']) ?></button>
                                                                                            <button type="button" class="currency-toggle-btn" id="btn-lkr-currency" onclick="switchCurrency('lkr')">LKR</button>
                                                                                        </div>
                                                                                        <?php if ($exchangeRate): ?>
                                                                                            <div class="exchange-rate-badge">
                                                                                                <iconify-icon icon="mdi:swap-horizontal" width="14" height="14"></iconify-icon>
                                                                                                <span id="live-rate-text">1 <?= htmlspecialchars($paymentData['currency_code']) ?> = LKR <?= number_format($exchangeRateValue, 2) ?> (<?= date('d M Y', strtotime($exchangeRate['effective_date'])) ?>)</span>
                                                                                            </div>
                                                                                        <?php else: ?>
                                                                                            <div class="exchange-rate-badge rate-unavailable">
                                                                                                <iconify-icon icon="mdi:alert-outline" width="14" height="14"></iconify-icon>
                                                                                                <span>No exchange rate available</span>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>

                                                                            <!-- ── Base Currency Summary Panel ── -->
                                                                            <?php if (!$isBaseCurrencyLKR): ?>
                                                                                <div class="col-12" id="base-summary-panel">
                                                                                    <div class="lkr-panel">
                                                                                        <div class="lkr-panel-top">
                                                                                            <div class="lkr-panel-top-left">
                                                                                                <iconify-icon icon="mdi:currency-usd" width="16" height="16"></iconify-icon>
                                                                                                Currency Summary (<?= htmlspecialchars($paymentData['currency_code']) ?>)
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="lkr-panel-grid">
                                                                                            <div class="lkr-panel-item">
                                                                                                <span class="lkr-item-label">Total Amount</span>
                                                                                                <span class="lkr-item-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($paymentData['amount'], $paymentData['decimal_places']) ?></span>
                                                                                            </div>
                                                                                            <div class="lkr-panel-item">
                                                                                                <span class="lkr-item-label">Total Discount</span>
                                                                                                <span class="lkr-item-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalDiscount, $paymentData['decimal_places']) ?></span>
                                                                                            </div>
                                                                                            <div class="lkr-panel-item">
                                                                                                <span class="lkr-item-label">Total Penalty</span>
                                                                                                <span class="lkr-item-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalPenalty, $paymentData['decimal_places']) ?></span>
                                                                                            </div>
                                                                                            <div class="lkr-panel-item">
                                                                                                <span class="lkr-item-label">Total Tax</span>
                                                                                                <span class="lkr-item-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalTax, $paymentData['decimal_places']) ?></span>
                                                                                            </div>
                                                                                            <div class="lkr-panel-item">
                                                                                                <span class="lkr-item-label">Total Payable</span>
                                                                                                <span class="lkr-item-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalPayableWithTax, $paymentData['decimal_places']) ?></span>
                                                                                            </div>
                                                                                            <div class="lkr-panel-item">
                                                                                                <span class="lkr-item-label">Total Paid</span>
                                                                                                <span class="lkr-item-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($paidAmount, $paymentData['decimal_places']) ?></span>
                                                                                            </div>
                                                                                            <div class="lkr-panel-item balance-item">
                                                                                                <span class="lkr-item-label">Balance Due</span>
                                                                                                <span class="lkr-item-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($balance, $paymentData['decimal_places']) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <!-- ── LKR Equivalent Panel ── -->
                                                                                <?php if ($exchangeRate): ?>
                                                                                    <div class="col-12" id="lkr-summary-panel" style="display:none;">
                                                                                        <div class="lkr-panel">
                                                                                            <div class="lkr-panel-top">
                                                                                                <div class="lkr-panel-top-left">
                                                                                                    <iconify-icon icon="mdi:currency-usd" width="16" height="16"></iconify-icon>
                                                                                                    LKR Equivalent Summary
                                                                                                </div>
                                                                                                <span class="lkr-panel-rate-note">@ <span id="panel-rate-display"><?= number_format($exchangeRateValue, 2) ?></span> per <?= htmlspecialchars($paymentData['currency_code']) ?></span>
                                                                                            </div>
                                                                                            <div class="lkr-panel-grid">
                                                                                                <div class="lkr-panel-item">
                                                                                                    <span class="lkr-item-label">Total Amount</span>
                                                                                                    <span class="lkr-item-value">LKR <?= number_format($paymentData['amount'] * $exchangeRateValue, 2) ?></span>
                                                                                                    <span class="lkr-item-sub"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($paymentData['amount'], $paymentData['decimal_places']) ?></span>
                                                                                                </div>
                                                                                                <div class="lkr-panel-item">
                                                                                                    <span class="lkr-item-label">Total Discount</span>
                                                                                                    <span class="lkr-item-value">LKR <?= number_format($discountAmtLKR, 2) ?></span>
                                                                                                    <span class="lkr-item-sub"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalDiscount, $paymentData['decimal_places']) ?></span>
                                                                                                </div>
                                                                                                <div class="lkr-panel-item">
                                                                                                    <span class="lkr-item-label">Total Penalty</span>
                                                                                                    <span class="lkr-item-value">LKR <?= number_format($penaltyAmtLKR, 2) ?></span>
                                                                                                    <span class="lkr-item-sub"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalPenalty, $paymentData['decimal_places']) ?></span>
                                                                                                </div>
                                                                                                <div class="lkr-panel-item">
                                                                                                    <span class="lkr-item-label">Total Tax</span>
                                                                                                    <span class="lkr-item-value">LKR <?= number_format($taxAmtLKR, 2) ?></span>
                                                                                                    <span class="lkr-item-sub"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalTax, $paymentData['decimal_places']) ?></span>
                                                                                                </div>
                                                                                                <div class="lkr-panel-item">
                                                                                                    <span class="lkr-item-label">Total Payable</span>
                                                                                                    <span class="lkr-item-value">LKR <?= number_format($payableAmtLKR, 2) ?></span>
                                                                                                    <span class="lkr-item-sub"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($totalPayableWithTax, $paymentData['decimal_places']) ?></span>
                                                                                                </div>
                                                                                                <div class="lkr-panel-item">
                                                                                                    <span class="lkr-item-label">Total Paid</span>
                                                                                                    <span class="lkr-item-value">LKR <?= number_format($paidAmountLKR, 2) ?></span>
                                                                                                    <span class="lkr-item-sub"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($paidAmount, $paymentData['decimal_places']) ?></span>
                                                                                                </div>
                                                                                                <div class="lkr-panel-item balance-item">
                                                                                                    <span class="lkr-item-label">Balance Due</span>
                                                                                                    <span class="lkr-item-value">LKR <?= number_format($balanceLKR, 2) ?></span>
                                                                                                    <span class="lkr-item-sub"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($balance, $paymentData['decimal_places']) ?></span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            <?php endif; ?>

                                                                            <h4 class="sub-topic mt-4">Add Receipt</h4>

                                                                            <!-- ── Payment Slip + Note ── -->
                                                                            <div class="col-12 col-lg-6 d-flex flex-column">
                                                                                <label class="text-14 main-text">Payment Slip</label>
                                                                                <?php if ($inquiry_id != 0 && $paymentData['payment_type_id'] == 18):
                                                                                    $paymnetSlips = Database::search("
                                                                                            SELECT * FROM students_identity_documents
                                                                                            WHERE students_student_id = '" . $inquiry_id . "'
                                                                                            AND `students_identity_documents_type_id` = '8'
                                                                                            ORDER BY date_added DESC LIMIT 1
                                                                                        ");
                                                                                    if ($paymnetSlips->num_rows > 0):
                                                                                        $paymentSlipData = $paymnetSlips->fetch_assoc(); ?>
                                                                                        <div class="col-12 my-3 file-item">
                                                                                            <div class="p-3 bl-solid cursor-pointer"
                                                                                                onclick="window.open('<?= Project::getBaseUrl() ?>/assets/uploads/student-identity-documents/payment-slips/<?= htmlspecialchars($paymentSlipData['document']) ?>', '_blank')">
                                                                                                <div class="row">
                                                                                                    <div class="col-2"><img src="assets/imgs/icons/image.png" alt="payment slip"></div>
                                                                                                    <div class="col-8">
                                                                                                        <div class="file-name">
                                                                                                            <div class="name"><?= htmlspecialchars($paymentSlipData['document']); ?></div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    <?php endif; ?>
                                                                                    <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2 d-none" id="drop-section">
                                                                                    <?php else: ?>
                                                                                        <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                                        <?php endif; ?>
                                                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud upload">
                                                                                        <span>Drag &amp; Drop your files here</span>
                                                                                        <span>OR</span>
                                                                                        <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                                        <input type="file" class="d-none" id="file-selector-input" accept="image/*,application/pdf">
                                                                                        <div class="col-12 px-1 mt-4">
                                                                                            <div class="row" id="list-section"></div>
                                                                                        </div>
                                                                                        </div>
                                                                                        <div class="mt-2" id="file-feedback"></div>
                                                                                    </div>

                                                                                    <div class="col-12 col-lg-6 d-flex flex-column">
                                                                                        <label class="text-14 main-text">Note</label>
                                                                                        <textarea class="form-control custom-input mt-2" id="note" rows="8"></textarea>
                                                                                        <div class="text-12 text-danger" id="note-feedback"></div>
                                                                                    </div>

                                                                                    <!-- ── Save button ── -->
                                                                                    <div class="col-12 d-flex justify-content-end">
                                                                                        <button class="custom-btn col-12 col-lg-4"
                                                                                            onclick="uploadInquiryStudentPaymentReceipt(this,'<?= $paymentData['course_payments_id'] ?>')">
                                                                                            Save Payment
                                                                                        </button>
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
                                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                                <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>

                                <script>
                                    // ════════════════════════════════════════════════════════════════════════
                                    // ADMIN PAYMENT RECEIPT — Tax-Inclusive Model v1
                                    // Tax = % of final (tax-inclusive) amount
                                    // base = final × (1 − taxRate/100)
                                    // ════════════════════════════════════════════════════════════════════════

                                    const LATEST_TAX_RATE = <?= (float)$latestTaxRate ?>;
                                    const COURSE_AMOUNT = <?= (float)$paymentData['amount'] ?>;
                                    const EXISTING_TOTAL_DISCOUNT = <?= (float)$totalDiscount ?>;
                                    const EXISTING_TOTAL_PENALTY = <?= (float)$totalPenalty ?>;
                                    const EXISTING_PAID_TOTAL = <?= (float)$convertedPaidTotal ?>;
                                    const baseCurrencyId = <?= (int)$paymentData['currency_id'] ?>;
                                    const lkrCurrencyId = <?= (int)$lkrCurrencyId ?>;
                                    const currencyCode = "<?= htmlspecialchars($paymentData['currency_code']) ?>";
                                    const isBaseCurrencyLKR = <?= $isBaseCurrencyLKR ? 'true' : 'false' ?>;
                                    const OVERPAY_EPSILON = 0.005;

                                    let exchangeRate = <?= $isBaseCurrencyLKR ? 'null' : (float)$exchangeRateValue ?>;
                                    let exchangeRateId = <?= $exchangeRateId ? (int)$exchangeRateId : 'null' ?>;
                                    let modalCurrentMode = 'base';

                                    // DOM refs
                                    const discountInput = document.getElementById('discount-amount');
                                    const penaltyInput = document.getElementById('penalty-amount');
                                    const balanceInput = document.getElementById('balance-amount');
                                    const paidInput = document.getElementById('paid-amount');
                                    const taxAmountDisplay = document.getElementById('tax-amount-display-input');
                                    const paidDateInput = document.getElementById('paid-date');
                                    const customRateInput = document.getElementById('custom-exchange-rate');
                                    const rateStatusEl = document.getElementById('rate-status');
                                    const balBreakdownEl = document.getElementById('balance-breakdown-hint');

                                    // ── Helpers ──────────────────────────────────────────────────────────

                                    function fmt(n) {
                                        return parseFloat(n || 0).toLocaleString('en-US', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }

                                    /**
                                     * Extract base and tax from a tax-inclusive amount.
                                     * base = final × (1 − taxRate/100)
                                     * tax  = final × (taxRate/100)
                                     */
                                    function extractBaseAndTax(taxInclusiveAmount, taxRate) {
                                        if (taxRate <= 0 || taxInclusiveAmount <= 0) return {
                                            base: taxInclusiveAmount,
                                            tax: 0
                                        };
                                        const basePortion = 1 - (taxRate / 100);
                                        if (basePortion <= 0) return {
                                            base: 0,
                                            tax: taxInclusiveAmount
                                        };
                                        return {
                                            base: taxInclusiveAmount * basePortion,
                                            tax: taxInclusiveAmount * (taxRate / 100)
                                        };
                                    }

                                    /**
                                     * Calculate tax-inclusive amount from a base amount.
                                     * final = base / (1 − taxRate/100)
                                     */
                                    function calculateTaxInclusiveAmount(baseAmount, taxRate) {
                                        if (taxRate <= 0 || baseAmount <= 0) return baseAmount;
                                        const divisor = 1 - (taxRate / 100);
                                        if (divisor <= 0) return baseAmount;
                                        return baseAmount / divisor;
                                    }

                                    /**
                                     * Tax on a base amount.
                                     * tax = base × taxRate / (100 − taxRate)
                                     */
                                    function calculateTaxAmount(baseAmount, taxRate) {
                                        if (taxRate <= 0 || baseAmount <= 0 || taxRate >= 100) return 0;
                                        return baseAmount * (taxRate / (100 - taxRate));
                                    }

                                    function setHint(id, label, value, isApprox = true) {
                                        const el = document.getElementById(id);
                                        if (!el) return;
                                        el.textContent = (isApprox ? '≈ ' : '= ') + label + ' ' + fmt(value);
                                    }

                                    function setRateStatus(type, msg) {
                                        if (!rateStatusEl) return;
                                        const cls = {
                                            loading: 'sub-text',
                                            success: 'text-dark-green',
                                            warning: 'text-warning',
                                            error: 'text-dark-red',
                                            info: 'text-primary'
                                        };
                                        rateStatusEl.className = 'text-12 mt-1 ' + (cls[type] || 'sub-text');
                                        rateStatusEl.textContent = msg;
                                    }

                                    // ── Overpayment panel ────────────────────────────────────────────────

                                    function showOverpaymentMessage(currentPayable, paidAmount, overpayTotal, overpayBase, overpayTax) {
                                        const displayLabel = (!isBaseCurrencyLKR && modalCurrentMode === 'lkr') ? 'LKR' : currencyCode;
                                        const wrapper = document.getElementById('overpay-panel-wrapper');
                                        if (!wrapper) return;
                                        document.getElementById('overpay-current').textContent = fmt(currentPayable) + ' ' + displayLabel;
                                        document.getElementById('overpay-amount').textContent = fmt(overpayTotal) + ' ' + displayLabel;
                                        document.getElementById('overpay-next-breakdown').textContent = fmt(overpayBase) + ' ' + displayLabel;
                                        document.getElementById('overpay-next-sub').textContent =
                                            displayLabel + ' ' + fmt(overpayBase) + ' course fee + ' +
                                            displayLabel + ' ' + fmt(overpayTax) + ' tax (' + LATEST_TAX_RATE.toFixed(2) + '%)';
                                        const totalEl = document.getElementById('overpay-next-total');
                                        if (totalEl) totalEl.textContent = 'Total: ' + displayLabel + ' ' + fmt(overpayBase + overpayTax);
                                        wrapper.style.display = 'block';
                                    }

                                    function removeOverpaymentMessage() {
                                        const wrapper = document.getElementById('overpay-panel-wrapper');
                                        if (wrapper) wrapper.style.display = 'none';
                                    }

                                    // ── Main recalculate ────────────────────────────────────────────────

                                    function recalculate() {
                                        const rawDiscount = parseFloat(discountInput?.value) || 0;
                                        const rawPenalty = parseFloat(penaltyInput?.value) || 0;
                                        const rawPaid = parseFloat(paidInput?.value) || 0;

                                        if (isBaseCurrencyLKR || !exchangeRate) {
                                            recalculateSingleCurrency(rawDiscount, rawPenalty, rawPaid);
                                        } else {
                                            recalculateMultiCurrency(rawDiscount, rawPenalty, rawPaid);
                                        }
                                    }

                                    function recalculateSingleCurrency(rawDiscount, rawPenalty, rawPaid) {
                                        const totalDiscount = EXISTING_TOTAL_DISCOUNT + rawDiscount;
                                        const totalPenalty = EXISTING_TOTAL_PENALTY + rawPenalty;
                                        const basePayable = Math.max(0, COURSE_AMOUNT - totalDiscount + totalPenalty);
                                        const basePortion = 1 - (LATEST_TAX_RATE / 100);
                                        const paidBaseBefore = EXISTING_PAID_TOTAL * basePortion;
                                        const balBeforeBase = Math.max(0, basePayable - paidBaseBefore);
                                        const balBeforeTotal = calculateTaxInclusiveAmount(balBeforeBase, LATEST_TAX_RATE);
                                        const paidCalc = extractBaseAndTax(rawPaid, LATEST_TAX_RATE);

                                        if (taxAmountDisplay) taxAmountDisplay.value = fmt(paidCalc.tax);

                                        const totalPaidBase = paidBaseBefore + paidCalc.base;
                                        const remainBase = Math.max(0, basePayable - totalPaidBase);
                                        const remainTotal = calculateTaxInclusiveAmount(remainBase, LATEST_TAX_RATE);

                                        if (rawPaid > 0 && rawPaid > balBeforeTotal + OVERPAY_EPSILON) {
                                            const overpay = rawPaid - balBeforeTotal;
                                            const overpayCalc = extractBaseAndTax(overpay, LATEST_TAX_RATE);
                                            balanceInput.value = '0.00';
                                            if (balBreakdownEl) balBreakdownEl.textContent = 'Fully paid — overpayment credited to next installment';
                                            showOverpaymentMessage(balBeforeTotal, rawPaid, overpay, overpayCalc.base, overpayCalc.tax);
                                            setHint('balance-lkr-hint', 'LKR', 0);
                                            setHint('balance-base-hint', currencyCode, 0, false);
                                        } else if (rawPaid > 0) {
                                            balanceInput.value = fmt(remainTotal);
                                            if (balBreakdownEl) {
                                                balBreakdownEl.textContent =
                                                    'Paid: base ' + fmt(paidCalc.base) + ' + tax ' + fmt(paidCalc.tax) +
                                                    ' | Remaining: base ' + fmt(remainBase) + ' + tax ' + fmt(calculateTaxAmount(remainBase, LATEST_TAX_RATE)) +
                                                    ' = ' + fmt(remainTotal);
                                            }
                                            removeOverpaymentMessage();
                                            setHint('balance-lkr-hint', 'LKR', remainTotal);
                                            setHint('balance-base-hint', currencyCode, remainTotal, false);
                                        } else {
                                            balanceInput.value = fmt(balBeforeTotal);
                                            if (balBreakdownEl) balBreakdownEl.textContent = 'Base ' + fmt(balBeforeBase) + ' + Tax ' + fmt(calculateTaxAmount(balBeforeBase, LATEST_TAX_RATE));
                                            removeOverpaymentMessage();
                                            setHint('balance-lkr-hint', 'LKR', balBeforeTotal);
                                            setHint('balance-base-hint', currencyCode, balBeforeTotal, false);
                                        }
                                    }

                                    function recalculateMultiCurrency(rawDiscount, rawPenalty, rawPaid) {
                                        const toBase = v => modalCurrentMode === 'lkr' ? v / exchangeRate : v;
                                        const toDisplay = v => modalCurrentMode === 'lkr' ? v * exchangeRate : v;

                                        const discountBase = toBase(rawDiscount);
                                        const penaltyBase = toBase(rawPenalty);
                                        const paidBase = toBase(rawPaid);

                                        const totalDiscountBase = EXISTING_TOTAL_DISCOUNT + discountBase;
                                        const totalPenaltyBase = EXISTING_TOTAL_PENALTY + penaltyBase;
                                        const basePayableBase = Math.max(0, COURSE_AMOUNT - totalDiscountBase + totalPenaltyBase);

                                        const basePortion = 1 - (LATEST_TAX_RATE / 100);
                                        const paidBaseBefore = EXISTING_PAID_TOTAL * basePortion;
                                        const balBeforeBase = Math.max(0, basePayableBase - paidBaseBefore);
                                        const balBeforeTotal = calculateTaxInclusiveAmount(balBeforeBase, LATEST_TAX_RATE);
                                        const balBeforeDisplay = toDisplay(balBeforeTotal);

                                        const paidCalc = extractBaseAndTax(paidBase, LATEST_TAX_RATE);
                                        const taxDisplay = toDisplay(paidCalc.tax);

                                        if (taxAmountDisplay) taxAmountDisplay.value = fmt(taxDisplay);

                                        const totalPaidBase = paidBaseBefore + paidCalc.base;
                                        const remainBase = Math.max(0, basePayableBase - totalPaidBase);
                                        const remainTotal = calculateTaxInclusiveAmount(remainBase, LATEST_TAX_RATE);

                                        if (rawPaid > 0 && rawPaid > balBeforeDisplay + OVERPAY_EPSILON) {
                                            const overpayDisplay = rawPaid - balBeforeDisplay;
                                            const overpayBase_v = toBase(overpayDisplay);
                                            const overpayTax_v = calculateTaxAmount(overpayBase_v, LATEST_TAX_RATE);
                                            balanceInput.value = '0.00';
                                            if (balBreakdownEl) balBreakdownEl.textContent = 'Fully paid — overpayment credited to next installment';
                                            showOverpaymentMessage(balBeforeDisplay, rawPaid, toDisplay(overpayBase_v + overpayTax_v), toDisplay(overpayBase_v), toDisplay(overpayTax_v));
                                            setHint('balance-base-hint', currencyCode, 0, false);
                                            setHint('balance-lkr-hint', 'LKR', 0, false);
                                        } else if (rawPaid > 0) {
                                            const balDisplay = toDisplay(remainTotal);
                                            balanceInput.value = fmt(balDisplay);
                                            if (balBreakdownEl) {
                                                balBreakdownEl.textContent =
                                                    'Paid: base ' + fmt(toDisplay(paidCalc.base)) + ' + tax ' + fmt(taxDisplay) +
                                                    ' | Remaining: base ' + fmt(toDisplay(remainBase)) + ' + tax ' + fmt(toDisplay(calculateTaxAmount(remainBase, LATEST_TAX_RATE))) +
                                                    ' = ' + fmt(balDisplay);
                                            }
                                            removeOverpaymentMessage();
                                            if (modalCurrentMode === 'lkr') {
                                                setHint('balance-base-hint', currencyCode, remainTotal, false);
                                                setHint('balance-lkr-hint', 'LKR', toDisplay(remainTotal), false);
                                            } else {
                                                setHint('balance-lkr-hint', 'LKR', toDisplay(remainTotal));
                                                setHint('balance-base-hint', currencyCode, remainTotal, false);
                                            }
                                        } else {
                                            const balDisplay = toDisplay(balBeforeTotal);
                                            balanceInput.value = fmt(balDisplay);
                                            if (balBreakdownEl) balBreakdownEl.textContent = 'Base ' + fmt(toDisplay(balBeforeBase)) + ' + Tax ' + fmt(toDisplay(calculateTaxAmount(balBeforeBase, LATEST_TAX_RATE)));
                                            removeOverpaymentMessage();
                                            if (modalCurrentMode === 'lkr') {
                                                setHint('balance-base-hint', currencyCode, balBeforeTotal, false);
                                                setHint('balance-lkr-hint', 'LKR', toDisplay(balBeforeTotal), false);
                                            } else {
                                                setHint('balance-lkr-hint', 'LKR', toDisplay(balBeforeTotal));
                                                setHint('balance-base-hint', currencyCode, balBeforeTotal, false);
                                            }
                                        }

                                        // Hints for other fields
                                        if (modalCurrentMode === 'lkr') {
                                            setHint('discount-base-hint', currencyCode, discountBase, false);
                                            setHint('penalty-base-hint', currencyCode, penaltyBase, false);
                                            setHint('paid-base-hint', currencyCode, paidBase, false);
                                            setHint('tax-amount-base-hint', currencyCode, paidCalc.tax, false);
                                            setHint('discount-lkr-hint', 'LKR', rawDiscount, false);
                                            setHint('penalty-lkr-hint', 'LKR', rawPenalty, false);
                                            setHint('paid-lkr-hint', 'LKR', rawPaid, false);
                                            setHint('tax-amount-lkr-hint', 'LKR', taxDisplay, false);
                                        } else {
                                            setHint('discount-lkr-hint', 'LKR', toDisplay(rawDiscount));
                                            setHint('penalty-lkr-hint', 'LKR', toDisplay(rawPenalty));
                                            setHint('paid-lkr-hint', 'LKR', toDisplay(rawPaid));
                                            setHint('tax-amount-lkr-hint', 'LKR', toDisplay(paidCalc.tax));
                                            setHint('discount-base-hint', currencyCode, rawDiscount, false);
                                            setHint('penalty-base-hint', currencyCode, rawPenalty, false);
                                            setHint('paid-base-hint', currencyCode, paidBase, false);
                                            setHint('tax-amount-base-hint', currencyCode, paidCalc.tax, false);
                                        }
                                    }

                                    // ── Currency switch ──────────────────────────────────────────────────

                                    function switchCurrency(mode) {
                                        if (isBaseCurrencyLKR) return;
                                        modalCurrentMode = mode;

                                        document.getElementById('btn-base-currency')?.classList.toggle('active', mode === 'base');
                                        document.getElementById('btn-lkr-currency')?.classList.toggle('active', mode === 'lkr');

                                        const lkrPanel = document.getElementById('lkr-summary-panel');
                                        if (lkrPanel) lkrPanel.style.display = mode === 'lkr' ? 'block' : 'none';

                                        const label = mode === 'lkr' ? 'LKR' : currencyCode;
                                        ['discount', 'penalty', 'balance', 'paid', 'tax-amount'].forEach(id => {
                                            const el = document.getElementById(id + '-currency-label');
                                            if (el) el.textContent = label;
                                        });

                                        document.querySelectorAll('[id$="-lkr-hint"]').forEach(el => el.classList.toggle('d-none', mode === 'lkr'));
                                        document.querySelectorAll('[id$="-base-hint"]').forEach(el => el.classList.toggle('d-none', mode === 'base'));

                                        if (exchangeRate && !isBaseCurrencyLKR) {
                                            [discountInput, penaltyInput, paidInput].forEach(el => {
                                                if (!el) return;
                                                const v = parseFloat(el.value) || 0;
                                                if (!v) return;
                                                el.value = mode === 'lkr' ? (v * exchangeRate).toFixed(2) : (v / exchangeRate).toFixed(2);
                                            });
                                        }
                                        recalculate();
                                    }

                                    // ── Date change: fetch rate for selected date ────────────────────────

                                    function onPaidDateChange() {
                                        if (isBaseCurrencyLKR || !paidDateInput?.value) return;
                                        setRateStatus('loading', 'Fetching exchange rate for selected date...');
                                        fetch(`api/getExchangeRate.php?base=${baseCurrencyId}&target=${lkrCurrencyId}&date=${paidDateInput.value}`)
                                            .then(r => r.json())
                                            .then(data => {
                                                if (data.rate) {
                                                    exchangeRate = parseFloat(data.rate);
                                                    exchangeRateId = data.currency_exchange_rate_id;
                                                    if (customRateInput) customRateInput.value = exchangeRate.toFixed(2);
                                                    const rb = document.getElementById('live-rate-text');
                                                    if (rb) rb.textContent = `1 ${currencyCode} = LKR ${exchangeRate.toFixed(2)} (${data.effective_date})`;
                                                    const pr = document.getElementById('panel-rate-display');
                                                    if (pr) pr.textContent = exchangeRate.toFixed(2);
                                                    setRateStatus('success', `Rate updated: 1 ${currencyCode} = LKR ${exchangeRate.toFixed(2)}`);
                                                } else {
                                                    setRateStatus('warning', `No rate found. Using last known (${exchangeRate?.toFixed(2)}).`);
                                                }
                                                recalculate();
                                            })
                                            .catch(() => {
                                                setRateStatus('error', 'Could not fetch rate.');
                                                recalculate();
                                            });
                                    }

                                    // ── Custom rate override ─────────────────────────────────────────────

                                    function onCustomRateChange() {
                                        if (!customRateInput) return;
                                        const val = parseFloat(customRateInput.value);
                                        if (!isNaN(val) && val > 0) {
                                            exchangeRate = val;
                                            exchangeRateId = null;
                                            const pr = document.getElementById('panel-rate-display');
                                            if (pr) pr.textContent = val.toFixed(2);
                                            const rb = document.getElementById('live-rate-text');
                                            if (rb) rb.textContent = `1 ${currencyCode} = LKR ${val.toFixed(2)} (custom)`;
                                            setRateStatus('info', `Custom rate: 1 ${currencyCode} = LKR ${val.toFixed(2)}`);
                                            recalculate();
                                        }
                                    }

                                    // ── Build currency data for form submission ──────────────────────────

                                    function getCurrentCurrencyData() {
                                        const customRateVal = customRateInput ? parseFloat(customRateInput.value) : NaN;
                                        const isCustomRate = !isNaN(customRateVal) && customRateVal > 0 && customRateVal !== exchangeRate;
                                        const finalRate = (customRateInput && !isNaN(customRateVal) && customRateVal > 0) ? customRateVal : exchangeRate;
                                        const finalRateId = isCustomRate ? null : exchangeRateId;

                                        const rawPaid = parseFloat(paidInput?.value) || 0;
                                        const toBase = v => (!isBaseCurrencyLKR && modalCurrentMode === 'lkr' && finalRate) ? v / finalRate : v;
                                        const paidBase = toBase(rawPaid);
                                        const calc = extractBaseAndTax(paidBase, LATEST_TAX_RATE);

                                        return {
                                            mode: modalCurrentMode,
                                            baseCurrencyId: baseCurrencyId,
                                            exchangeRate: finalRate,
                                            isLKR: isBaseCurrencyLKR || modalCurrentMode === 'lkr',
                                            selectedCurrencyId: (isBaseCurrencyLKR || modalCurrentMode === 'base') ? baseCurrencyId : lkrCurrencyId,
                                            exchangeRateId: (isBaseCurrencyLKR || modalCurrentMode === 'base') ? null : finalRateId,
                                            isCustomRate: isCustomRate,
                                            customRateValue: isCustomRate ? customRateVal : null,
                                            taxRate: LATEST_TAX_RATE,
                                            taxAmount: calc.tax,
                                        };
                                    }

                                    // ── Event listeners ──────────────────────────────────────────────────

                                    if (discountInput) discountInput.addEventListener('input', recalculate);
                                    if (penaltyInput) penaltyInput.addEventListener('input', recalculate);
                                    if (paidInput) paidInput.addEventListener('input', recalculate);
                                    if (paidDateInput) paidDateInput.addEventListener('change', onPaidDateChange);
                                    if (customRateInput) customRateInput.addEventListener('input', onCustomRateChange);

                                    // Initial calculation
                                    recalculate();
                                    console.log('✓ Admin Receipt Tax v1 | Tax:', LATEST_TAX_RATE + '% | Model: Tax as % of final amount');
                                </script>
                        </body>

                        </html>
<?php
                    }
                }
            }
        }
    }
}
?>