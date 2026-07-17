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
                    `s`.`student_id`,
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

                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id'])) {
                    $ifHasPermissions = true;
                }

                if ($inquiry_id != 0 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 15))) {
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
                        $taxFactor = 1 + ($latestTaxRate / 100);

                        // ============================================================
                        // HELPER: resolve exchange rate for course_payment_has_payments
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
                        // CP-level discount
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
                        // Fetch receipts
                        // ============================================================
                        $receipts = Database::search("
                            SELECT
                                cpp.*,
                                pm.payment_method,
                                pm.payment_method_id,
                                ps2.payment_status,
                                ps2.payment_status_id,
                                cur.code AS paid_currency_code,
                                cur.decimal_places AS paid_decimal_places,
                                cpp.currency_exchange_rate,
                                cpp.tax_rate,
                                cpp.tax_amount
                            FROM course_payment_has_payments AS cpp
                            INNER JOIN payment_method AS pm  ON cpp.payment_method_payment_method_id = pm.payment_method_id
                            INNER JOIN payment_status AS ps2 ON cpp.payment_status_payment_status_id = ps2.payment_status_id
                            LEFT JOIN currencies AS cur      ON cpp.currencies_currency_id = cur.currency_id
                            WHERE cpp.course_payments_course_payments_id = '" . (int)$paymentData['course_payments_id'] . "'
                            AND ps2.payment_status_id = 3
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
                                    $totalDiscount += convertToCourseAmount($rec['discount_amount'] ?? 0, $rate);
                                    $totalPenalty  += convertToCourseAmount($rec['panelty_amount']  ?? 0, $rate);
                                    $totalTax      += convertToCourseAmount($rec['tax_amount']       ?? 0, $rate);
                                }
                            }
                        }

                        // ============================================================
                        // BALANCE CALCULATION - CORRECTED FOR NEW TAX MODEL
                        // ============================================================
                        $basePayable    = $paymentData['amount'] - $totalDiscount + $totalPenalty;

                        // NEW TAX MODEL: tax is calculated on final amount
                        // base = final * (1 - taxRate/100)
                        // tax = final * (taxRate/100)
                        $taxRateDecimal = $latestTaxRate / 100;
                        $basePortion = 1 - $taxRateDecimal;

                        if ($basePortion > 0) {
                            $paidBaseTotal = $convertedPaidTotal * $basePortion;
                        } else {
                            $paidBaseTotal = 0;
                        }

                        $remainingBase  = max(0, $basePayable - $paidBaseTotal);

                        // Calculate remaining tax: remainingBase * (taxRate / (100 - taxRate))
                        if ($basePortion > 0) {
                            $remainingTax = $remainingBase * ($latestTaxRate / (100 - $latestTaxRate));
                        } else {
                            $remainingTax = 0;
                        }

                        $balance        = $remainingBase + $remainingTax;
                        $paidAmount     = $convertedPaidTotal;

                        // ============================================================
                        // Exchange rate: course_currency → LKR
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
                                WHERE cer.`base_currency_id`   = '" . $paymentData['currency_id'] . "'
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

                        // LKR equivalents for the summary panel
                        $balanceLKR     = $balance        * $exchangeRateValue;
                        $payableAmtLKR  = ($basePayable + ($basePayable * ($latestTaxRate / (100 - $latestTaxRate)))) * $exchangeRateValue;
                        $paidAmountLKR  = $paidAmount     * $exchangeRateValue;
                        $discountAmtLKR = $totalDiscount  * $exchangeRateValue;
                        $taxAmtLKR      = $totalTax       * $exchangeRateValue;
                        $penaltyAmtLKR  = $paymentData['panelty_amount_lkr']
                            + ($totalPenalty - $paneltyPaymentsConverted) * $exchangeRateValue;

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

                            /* ── Currency label badge (next to field labels) ── */
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

                            /* ── Convert hint below inputs ── */
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
                                border-radius: 10px;
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
                                border-left: 4px solid var(--dark-theme);
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

                            .overpay-panel-title iconify-icon {
                                color: var(--dark-theme);
                            }

                            .overpay-explain {
                                padding: 10px 16px;
                                font-size: 12px;
                                line-height: 1.5;
                                color: var(--sub-text-color);
                                background: var(--color-background-secondary, #f0f4f8);
                                border-bottom: 1px solid var(--border-color);
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
                                color: var(--dark-theme);
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
                                color: var(--dark-theme);
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

                                            <!-- Skeleton -->
                                            <div class="container p-4 m-0" id="skeleton-list">
                                                <?php
                                                include "./includes/components/skeleton-header.php";
                                                include "./includes/components/skeleton-add-edit.php";
                                                ?>
                                            </div>

                                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                                <div class="row p-4 m-0 g-0">

                                                    <!-- Breadcrumb -->
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
                                                                                    <span class="amount-value"><?= $paymentData['currency_code'] ?> <?= number_format($balance, 2); ?></span>
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
                                                                                    <span class="detail-value"><?= $paymentData['currency_code'] ?> <?= number_format($paymentData['amount'], 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Type</span>
                                                                                    <span class="detail-value"><?= $paymentData["payment_type"]; ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Due Date</span>
                                                                                    <span class="detail-value"><?= $paymentData["due_date"] ?? '--/--/----'; ?></span>
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
                                                                                            echo $paymentData['currency_code'] . " " . number_format($paymentData["discount"], 2) . " | " . $paymentData["discount_type"];
                                                                                        } else {
                                                                                            echo number_format($paymentData["discount"]) . "% | " . $paymentData["discount_type"];
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
                                                                                    <span class="detail-value"><?= $paymentData['currency_code'] ?> <?= number_format($totalDiscount, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Penalty Amount</span>
                                                                                    <span class="detail-value"><?= $paymentData['currency_code'] ?> <?= number_format($totalPenalty, 2) ?>
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
                                                                                    <span class="detail-value"><?= $paymentData['currency_code'] ?> <?= number_format($totalTax, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Payable Amount</span>
                                                                                    <span class="detail-value"><?= $paymentData['currency_code'] ?> <?= number_format($payableAmtLKR / $exchangeRateValue, 2) ?>
                                                                                        <small class="d-block" style="font-size:11px;opacity:.6;">Base <?= number_format($basePayable, 2) ?> + Tax <?= number_format($basePayable * ($latestTaxRate / (100 - $latestTaxRate)), 2) ?></small>
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
                                                                                    <span class="detail-value"><?= $paymentData['currency_code'] ?> <?= number_format($paidAmount, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Balance / Next Installment</span>
                                                                                    <span class="detail-value"><?= $paymentData['currency_code'] ?> <?= number_format($balance, 2) ?>
                                                                                        <small class="d-block" style="font-size:11px;opacity:.6;">Base <?= number_format($remainingBase, 2) ?> + Tax <?= number_format($remainingTax, 2) ?></small>
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
                                                                                            <div class="detail-content"><span class="detail-label">Option</span><span class="detail-value"><?= $paymentData["panelty_payment_option"] ?></span></div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                                </svg></div>
                                                                                            <div class="detail-content"><span class="detail-label">Type</span><span class="detail-value"><?= $panelty["panelty_payments_type"] ?></span></div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                                    <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                                </svg></div>
                                                                                            <div class="detail-content"><span class="detail-label">Amount</span><span class="detail-value">LKR <?= number_format($panelty["amount"], 2); ?></span></div>
                                                                                        </div>
                                                                                        <?php if (!empty($panelty["days"])): ?>
                                                                                            <div class="detail-item">
                                                                                                <div class="detail-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                                                    </svg></div>
                                                                                                <div class="detail-content"><span class="detail-label">Days</span><span class="detail-value"><?= $panelty["days"]; ?></span></div>
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

                                                                            <!-- Header + currency toggle -->
                                                                            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                                                                <h4 class="sub-topic m-0">Add Receipt</h4>
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

                                                                            <!-- LKR Summary Panel (shown when mode = lkr) -->
                                                                            <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?>
                                                                                <div class="col-12" id="lkr-summary-panel" style="display:none;">
                                                                                    <div class="lkr-panel">
                                                                                        <div class="lkr-panel-top">
                                                                                            <div class="lkr-panel-top-left"><iconify-icon icon="mdi:currency-usd" width="16" height="16"></iconify-icon> LKR equivalent summary</div>
                                                                                            <span class="lkr-panel-rate-note">@ <span id="panel-rate-display"><?= number_format($exchangeRateValue, 2) ?></span> per <?= htmlspecialchars($paymentData['currency_code']) ?></span>
                                                                                        </div>
                                                                                        <div class="lkr-panel-grid">
                                                                                            <div class="lkr-panel-item"><span class="lkr-item-label">Total Amount</span><span class="lkr-item-value" id="lkr-amount-val">LKR <?= number_format($paymentData['amount'] * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub" id="lkr-amount-sub"><?= $paymentData['currency_code'] ?> <?= number_format($paymentData['amount'], 2) ?></span></div>
                                                                                            <div class="lkr-panel-item"><span class="lkr-item-label">Total Discount</span><span class="lkr-item-value" id="lkr-discount-val">LKR <?= number_format($discountAmtLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-discount-sub"><?= $paymentData['currency_code'] ?> <?= number_format($totalDiscount, 2) ?></span></div>
                                                                                            <div class="lkr-panel-item"><span class="lkr-item-label">Total Penalty</span><span class="lkr-item-value" id="lkr-penalty-val">LKR <?= number_format($penaltyAmtLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-penalty-sub"><?= $paymentData['currency_code'] ?> <?= number_format($totalPenalty, 2) ?></span></div>
                                                                                            <div class="lkr-panel-item"><span class="lkr-item-label">Total Tax</span><span class="lkr-item-value" id="lkr-tax-val">LKR <?= number_format($taxAmtLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-tax-sub"><?= $paymentData['currency_code'] ?> <?= number_format($totalTax, 2) ?></span></div>
                                                                                            <div class="lkr-panel-item"><span class="lkr-item-label">Total Payable</span><span class="lkr-item-value" id="lkr-payable-val">LKR <?= number_format($payableAmtLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-payable-sub"><?= $paymentData['currency_code'] ?> <?= number_format($payableAmtLKR / $exchangeRateValue, 2) ?></span></div>
                                                                                            <div class="lkr-panel-item"><span class="lkr-item-label">Total Paid</span><span class="lkr-item-value" id="lkr-paid-val">LKR <?= number_format($paidAmountLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-paid-sub"><?= $paymentData['currency_code'] ?> <?= number_format($paidAmount, 2) ?></span></div>
                                                                                            <div class="lkr-panel-item balance-item"><span class="lkr-item-label">Balance Due</span><span class="lkr-item-value" id="lkr-balance-display">LKR <?= number_format($balanceLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-balance-sub"><?= $paymentData['currency_code'] ?> <?= number_format($balance, 2) ?></span></div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <!-- Row 1: Discount | Penalty | Balance | Method -->
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label class="text-14 main-text">Discount Given Amount <span class="currency-label-badge" id="discount-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="discount-amount" value="0" step="0.01" min="0">
                                                                                <div class="text-12 text-danger" id="discount-amount-feedback"></div>
                                                                                <span class="convert-hint" id="discount-lkr-hint">≈ LKR 0.00</span>
                                                                                <span class="convert-hint d-none" id="discount-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                                            </div>

                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label class="text-14 main-text">Penalty Added Amount <span class="currency-label-badge" id="penalty-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="penalty-amount" value="0" step="0.01" min="0">
                                                                                <div class="text-12 text-danger" id="penalty-amount-feedback"></div>
                                                                                <span class="convert-hint" id="penalty-lkr-hint">≈ LKR 0.00</span>
                                                                                <span class="convert-hint d-none" id="penalty-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                                            </div>

                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label class="text-14 main-text">Balance (Next Installment) <span class="currency-label-badge" id="balance-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="balance-amount" disabled value="<?= number_format($balance, 2) ?>">
                                                                                <div class="text-12 text-danger" id="balance-amount-feedback"></div>
                                                                                <span class="convert-hint" id="balance-lkr-hint">≈ LKR <?= number_format($balanceLKR, 2) ?></span>
                                                                                <span class="convert-hint d-none" id="balance-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($balance, 2) ?></span>
                                                                                <span class="text-12 sub-text mt-1" id="balance-breakdown-hint">Base <?= number_format($remainingBase, 2) ?> + Tax <?= number_format($remainingTax, 2) ?></span>
                                                                            </div>

                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label class="text-14 main-text">Payment Method</label>
                                                                                <select name="payment_method" class="form-select custom-select mt-2" id="payment-method">
                                                                                    <option value="0">- Select -</option>
                                                                                    <?php
                                                                                    $methods = Database::search("SELECT * FROM `payment_method`");
                                                                                    while ($method = $methods->fetch_assoc()) { ?>
                                                                                        <option value="<?= $method["payment_method_id"] ?>"><?= $method["payment_method"] ?></option>
                                                                                    <?php } ?>
                                                                                </select>
                                                                                <div class="text-12 text-danger" id="payment-method-feedback"></div>
                                                                            </div>

                                                                            <!-- Row 2: Paid Amount | Date | Tax | Rate -->
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label class="text-14 main-text">Paid Amount (Tax-Inclusive) <span class="currency-label-badge" id="paid-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="paid-amount" step="0.01" min="0" placeholder="Amount including tax">
                                                                                <div class="text-12 text-danger" id="paid-amount-feedback"></div>
                                                                                <span class="convert-hint" id="paid-lkr-hint">≈ LKR 0.00</span>
                                                                                <span class="convert-hint d-none" id="paid-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                                                <span class="text-12 sub-text mt-1" style="color:#d97706;">Includes tax automatically</span>
                                                                            </div>

                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label class="text-14 main-text">Paid Date</label>
                                                                                <input type="date" class="form-control custom-input mt-2" id="paid-date" value="<?= date("Y-m-d") ?>">
                                                                                <div class="text-12 text-danger" id="paid-date-feedback"></div>
                                                                                <?php if (!$isBaseCurrencyLKR): ?>
                                                                                    <span class="text-12 sub-text mt-1" id="rate-status">Rate loaded for today</span>
                                                                                <?php endif; ?>
                                                                            </div>

                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label class="text-14 main-text">Tax Amount <span class="currency-label-badge" style="background:rgba(245,158,11,.12);color:#d97706;border-color:rgba(245,158,11,.3);">Auto-Calculated</span></label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="tax-amount-display-input" value="0.00" disabled tabindex="-1" style="background:rgba(245,158,11,.06);border-color:rgba(245,158,11,.3);color:#d97706;">
                                                                                <div class="text-12 text-danger" id="tax-amount-feedback"></div>
                                                                                <span class="convert-hint" id="tax-amount-lkr-hint">≈ LKR 0.00</span>
                                                                                <span class="convert-hint d-none" id="tax-amount-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                                                <span class="text-12 sub-text mt-1" style="color:#d97706;">Latest: <?= number_format($latestTaxRate, 2) ?>%</span>
                                                                            </div>

                                                                            <?php if (!$isBaseCurrencyLKR): ?>
                                                                                <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                    <label class="text-14 main-text d-flex align-items-center gap-2">Custom Exchange Rate <span class="currency-label-badge">1 <?= htmlspecialchars($paymentData['currency_code']) ?> = ? LKR</span></label>
                                                                                    <input type="number" class="form-control custom-input mt-2" id="custom-exchange-rate" value="<?= number_format($exchangeRateValue, 2, '.', '') ?>" step="0.0001" min="0.0001" placeholder="e.g. 300.0000">
                                                                                    <span class="text-12 sub-text mt-1">Override auto-fetched rate if needed</span>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <div class="col-lg-3"></div>
                                                                            <?php endif; ?>

                                                                            <!-- ── Overpayment Panel (shown/hidden by JS) ── -->
                                                                            <div class="col-12" id="overpay-panel-wrapper" style="display:none;">
                                                                                <div class="overpay-panel">
                                                                                    <div class="overpay-panel-title">
                                                                                        <iconify-icon icon="mdi:arrow-right-circle-outline" width="16" height="16"></iconify-icon>
                                                                                        Overpayment — Will Be Applied to Next Installment
                                                                                    </div>
                                                                                    <div class="overpay-explain">
                                                                                        The paid amount covers this installment in full. The extra amount shown below is automatically carried forward as credit and will reduce the student's next installment balance.
                                                                                    </div>
                                                                                    <div class="overpay-grid">
                                                                                        <div class="overpay-item">
                                                                                            <span class="overpay-label">Current Installment Payable</span>
                                                                                            <span class="overpay-value" id="overpay-current">—</span>
                                                                                            <span class="overpay-sub">Will be fully settled</span>
                                                                                        </div>
                                                                                        <div class="overpay-item">
                                                                                            <span class="overpay-label">Overpayment Amount</span>
                                                                                            <span class="overpay-value" id="overpay-amount">—</span>
                                                                                            <span class="overpay-sub">Credited to next installment</span>
                                                                                        </div>
                                                                                        <div class="overpay-item">
                                                                                            <span class="overpay-label">Next Installment Breakdown</span>
                                                                                            <span class="overpay-value" id="overpay-next-breakdown">—</span>
                                                                                            <span class="overpay-sub" id="overpay-next-sub">—</span>
                                                                                            <span class="overpay-total" id="overpay-next-total">—</span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="col-12"></div>

                                                                            <!-- Row 3: Slip | Note -->
                                                                            <div class="col-12 col-lg-6 d-flex flex-column">
                                                                                <label class="text-14 main-text">Payment Slip</label>
                                                                                <?php if ($paymentData['payment_type_id'] == 18):
                                                                                    $paymnetSlips = Database::search("
                                                                                        SELECT * FROM students_identity_documents
                                                                                        WHERE students_student_id = '" . $paymentData['student_id'] . "'
                                                                                        AND `students_identity_documents_type_id` = '8'
                                                                                        ORDER BY date_added DESC LIMIT 1
                                                                                    ");
                                                                                    if ($paymnetSlips->num_rows > 0):
                                                                                        $paymentSlipData = $paymnetSlips->fetch_assoc(); ?>
                                                                                        <div class="col-12 my-3 file-item">
                                                                                            <div class="p-3 bl-solid cursor-pointer" onclick="window.open('<?= Project::getBaseUrl() ?>/assets/uploads/student-identity-documents/payment-slips/<?= $paymentSlipData['document'] ?>', '_blank')">
                                                                                                <div class="row">
                                                                                                    <div class="col-2"><img src="assets/imgs/icons/image.png" alt=""></div>
                                                                                                    <div class="col-8">
                                                                                                        <div class="file-name">
                                                                                                            <div class="name"><?= $paymentSlipData['document'] ?></div>
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
                                                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                                        <span>Drag & Drop your files here</span>
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

                                                                                    <!-- Save -->
                                                                                    <div class="col-12 d-flex justify-content-end">
                                                                                        <button class="custom-btn col-12 col-lg-4"
                                                                                            onclick="uploadStudentPaymentReceipt(this,'<?= $paymentData['course_payments_id'] ?>',getCurrentCurrencyData())">
                                                                                            Save Payment
                                                                                        </button>
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
                                        </div>
                                    </div>
                                </div>

                                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                                <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>

                                <script>
                                    // ════════════════════════════════════════════════════════════════════════════
                                    // TAX CALCULATION CORRECTED v8 - Tax as % of final (tax-inclusive) amount
                                    // ════════════════════════════════════════════════════════════════════════════

                                    const LATEST_TAX_RATE = <?= (float)$latestTaxRate ?>;
                                    const COURSE_AMOUNT = <?= (float)$paymentData['amount'] ?>;
                                    const EXISTING_TOTAL_DISCOUNT = <?= (float)$totalDiscount ?>;
                                    const EXISTING_TOTAL_PENALTY = <?= (float)$totalPenalty ?>;
                                    const EXISTING_TOTAL_TAX = <?= (float)$totalTax ?>;
                                    const EXISTING_PAID_TOTAL = <?= (float)$convertedPaidTotal ?>;
                                    const baseCurrencyId = <?= (int)$paymentData['currency_id'] ?>;
                                    const lkrCurrencyId = <?= (int)$lkrCurrencyId ?>;
                                    const currencyCode = "<?= htmlspecialchars($paymentData['currency_code']) ?>";
                                    const isBaseCurrencyLKR = <?= $isBaseCurrencyLKR ? 'true' : 'false' ?>;

                                    // Epsilon: prevents display-rounding false-positive overpay
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

                                    // ── Helpers ──────────────────────────────────────────────────────────────

                                    function fmt(n) {
                                        return parseFloat(n || 0).toLocaleString('en-US', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }

                                    /**
                                     * ✅ FIX: matches PHP's round($n, 2) behaviour.
                                     * The backend rounds to 2 decimals INSIDE extractTaxFromInclusive(),
                                     * calculateTaxInclusiveAmount() and calculateTaxFromBase() before any
                                     * further math is done with the result. Previously the JS versions of
                                     * these functions did not round at all until the very end (fmt() for
                                     * display only), so intermediate values stayed at full float precision
                                     * on screen while the backend was rounding at each step. After a
                                     * currency conversion (×391.52 etc.) that tiny difference becomes
                                     * several rupees, which is why the on-screen "Current Installment
                                     * Payable" (and everything derived from it, like the overpayment
                                     * amount) didn't match what actually got saved.
                                     */
                                    function round2(n) {
                                        return Math.round((n + Number.EPSILON) * 100) / 100;
                                    }

                                    /**
                                     * NEW TAX MODEL: Extract base and tax from tax-inclusive amount
                                     * base = final * (1 - taxRate/100)
                                     * tax = final * (taxRate/100)
                                     */
                                    function extractBaseAndTax(taxInclusiveAmount, taxRate) {
                                        if (taxRate <= 0 || taxInclusiveAmount <= 0) {
                                            return {
                                                base: taxInclusiveAmount,
                                                tax: 0
                                            };
                                        }

                                        const taxRateDecimal = taxRate / 100;
                                        const basePortion = 1 - taxRateDecimal;

                                        if (basePortion <= 0) {
                                            return {
                                                base: 0,
                                                tax: taxInclusiveAmount
                                            };
                                        }

                                        // Rounded to 2dp here — mirrors PHP's extractTaxFromInclusive()
                                        const base = round2(taxInclusiveAmount * basePortion);
                                        const tax = round2(taxInclusiveAmount * taxRateDecimal);

                                        return {
                                            base: base,
                                            tax: tax
                                        };
                                    }

                                    /**
                                     * NEW TAX MODEL: Calculate tax-inclusive amount from base
                                     * final = base / (1 - taxRate/100)
                                     */
                                    function calculateTaxInclusiveAmount(baseAmount, taxRate) {
                                        if (taxRate <= 0 || baseAmount <= 0) {
                                            return baseAmount;
                                        }

                                        const taxRateDecimal = taxRate / 100;
                                        const divisor = 1 - taxRateDecimal;

                                        if (divisor <= 0) {
                                            console.error('Invalid tax rate - would be >= 100%');
                                            return baseAmount;
                                        }

                                        // Rounded to 2dp here — mirrors PHP's calculateTaxInclusiveAmount()
                                        return round2(baseAmount / divisor);
                                    }

                                    /**
                                     * NEW TAX MODEL: Calculate tax amount from base
                                     * tax = base * (taxRate / (100 - taxRate))
                                     */
                                    function calculateTaxAmount(baseAmount, taxRate) {
                                        if (taxRate <= 0 || baseAmount <= 0) {
                                            return 0;
                                        }

                                        if (taxRate >= 100) {
                                            console.error('Invalid tax rate - must be less than 100%');
                                            return 0;
                                        }

                                        // Rounded to 2dp here — mirrors PHP's calculateTaxFromBase()
                                        return round2(baseAmount * (taxRate / (100 - taxRate)));
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

                                    // ── LKR equivalent summary panel (recomputed on rate change) ────────────────

                                    function updateLkrSummaryPanel() {
                                        if (isBaseCurrencyLKR || !exchangeRate) return;

                                        const basePayable = Math.max(0, COURSE_AMOUNT - EXISTING_TOTAL_DISCOUNT + EXISTING_TOTAL_PENALTY);
                                        const taxRateDecimal = LATEST_TAX_RATE / 100;
                                        const basePortion = 1 - taxRateDecimal;
                                        const paidBaseTotal = EXISTING_PAID_TOTAL * basePortion;
                                        const remainingBase = Math.max(0, basePayable - paidBaseTotal);
                                        const remainingTax = basePortion > 0 ? remainingBase * (LATEST_TAX_RATE / (100 - LATEST_TAX_RATE)) : 0;
                                        const balance = remainingBase + remainingTax;
                                        const payableAmt = basePayable + (basePayable * (LATEST_TAX_RATE / (100 - LATEST_TAX_RATE)));

                                        const setVal = (idPrefix, baseCurrencyValue) => {
                                            const valEl = document.getElementById(idPrefix + '-val');
                                            if (valEl) valEl.textContent = 'LKR ' + fmt(baseCurrencyValue * exchangeRate);
                                            const subEl = document.getElementById(idPrefix + '-sub');
                                            if (subEl) subEl.textContent = currencyCode + ' ' + fmt(baseCurrencyValue);
                                        };

                                        setVal('lkr-amount', COURSE_AMOUNT);
                                        setVal('lkr-discount', EXISTING_TOTAL_DISCOUNT);
                                        setVal('lkr-penalty', EXISTING_TOTAL_PENALTY);
                                        setVal('lkr-tax', EXISTING_TOTAL_TAX);
                                        setVal('lkr-payable', payableAmt);
                                        setVal('lkr-paid', EXISTING_PAID_TOTAL);

                                        const balLkrEl = document.getElementById('lkr-balance-display');
                                        if (balLkrEl) balLkrEl.textContent = 'LKR ' + fmt(balance * exchangeRate);
                                        const balSubEl = document.getElementById('lkr-balance-sub');
                                        if (balSubEl) balSubEl.textContent = currencyCode + ' ' + fmt(balance);
                                    }

                                    // ── Overpayment panel ────────────────────────────────────────────────────

                                    function showOverpaymentMessage(currentPayable, paidAmount, overpayTotal, overpayBase, overpayTax) {
                                        const displayLabel = (!isBaseCurrencyLKR && modalCurrentMode === 'lkr') ? 'LKR' : currencyCode;
                                        const wrapper = document.getElementById('overpay-panel-wrapper');
                                        if (!wrapper) return;

                                        document.getElementById('overpay-current').textContent =
                                            fmt(currentPayable) + ' ' + displayLabel;

                                        document.getElementById('overpay-amount').textContent =
                                            fmt(overpayTotal) + ' ' + displayLabel;

                                        document.getElementById('overpay-next-breakdown').textContent =
                                            fmt(overpayTotal) + ' ' + displayLabel;

                                        document.getElementById('overpay-next-sub').textContent =
                                            displayLabel + ' ' + fmt(overpayBase) + ' course fee' +
                                            ' + ' + displayLabel + ' ' + fmt(overpayTax) +
                                            ' tax (' + LATEST_TAX_RATE.toFixed(2) + '%)';

                                        const totalEl = document.getElementById('overpay-next-total');
                                        if (totalEl) {
                                            totalEl.textContent = 'Total: ' + displayLabel + ' ' + fmt(overpayBase + overpayTax);
                                        }

                                        wrapper.style.display = 'block';
                                    }

                                    function removeOverpaymentMessage() {
                                        const wrapper = document.getElementById('overpay-panel-wrapper');
                                        if (wrapper) wrapper.style.display = 'none';
                                    }

                                    // ── Main recalculate ─────────────────────────────────────────────────────

                                    function recalculate() {
                                        const rawDiscount = parseFloat(discountInput.value) || 0;
                                        const rawPenalty = parseFloat(penaltyInput.value) || 0;
                                        const rawPaid = parseFloat(paidInput?.value) || 0;

                                        if (isBaseCurrencyLKR || !exchangeRate) {
                                            return recalculateSingleCurrency(rawDiscount, rawPenalty, rawPaid);
                                        }

                                        return recalculateMultiCurrency(rawDiscount, rawPenalty, rawPaid);
                                    }

                                    function recalculateSingleCurrency(rawDiscount, rawPenalty, rawPaid) {
                                        const totalDiscount = EXISTING_TOTAL_DISCOUNT + rawDiscount;
                                        const totalPenalty = EXISTING_TOTAL_PENALTY + rawPenalty;
                                        const basePayable = Math.max(0, COURSE_AMOUNT - totalDiscount + totalPenalty);

                                        // What's owed BEFORE this payment (base amount, excluding tax)
                                        const taxRateDecimal = LATEST_TAX_RATE / 100;
                                        const basePortion = 1 - taxRateDecimal;
                                        const paidBaseBeforeThis = EXISTING_PAID_TOTAL * basePortion;
                                        const balBeforeBase = Math.max(0, basePayable - paidBaseBeforeThis);

                                        // Balance BEFORE this payment (with tax)
                                        const balBeforeWithTax = calculateTaxInclusiveAmount(balBeforeBase, LATEST_TAX_RATE);

                                        // Extract tax from paid amount
                                        const paidCalc = extractBaseAndTax(rawPaid, LATEST_TAX_RATE);

                                        // Total paid so far (in base terms)
                                        const totalPaidBaseSoFar = paidBaseBeforeThis + paidCalc.base;
                                        const remainBase = Math.max(0, basePayable - totalPaidBaseSoFar);
                                        const remainTotal = calculateTaxInclusiveAmount(remainBase, LATEST_TAX_RATE);

                                        // Update tax display
                                        if (taxAmountDisplay) taxAmountDisplay.value = fmt(paidCalc.tax);

                                        // Check for overpayment
                                        if (rawPaid > 0 && rawPaid > balBeforeWithTax + OVERPAY_EPSILON) {
                                            const overpayAmt = rawPaid - balBeforeWithTax;
                                            const overpayCalc = extractBaseAndTax(overpayAmt, LATEST_TAX_RATE);

                                            balanceInput.value = '0.00';
                                            if (balBreakdownEl) balBreakdownEl.textContent = 'Fully paid — overpayment credited to next installment';
                                            showOverpaymentMessage(balBeforeWithTax, rawPaid, overpayAmt, overpayCalc.base, overpayCalc.tax);
                                            setHint('balance-lkr-hint', 'LKR', 0);
                                            setHint('balance-base-hint', currencyCode, 0, false);

                                        } else if (rawPaid > 0) {
                                            balanceInput.value = fmt(remainTotal);
                                            if (balBreakdownEl) {
                                                const remainTaxAmount = calculateTaxAmount(remainBase, LATEST_TAX_RATE);
                                                balBreakdownEl.textContent =
                                                    'Paid: base ' + fmt(paidCalc.base) + ' + tax ' + fmt(paidCalc.tax) +
                                                    ' | Remaining: base ' + fmt(remainBase) + ' + tax ' + fmt(remainTaxAmount) +
                                                    ' = ' + fmt(remainTotal);
                                            }
                                            removeOverpaymentMessage();
                                            setHint('balance-lkr-hint', 'LKR', remainTotal);
                                            setHint('balance-base-hint', currencyCode, remainTotal, false);

                                        } else {
                                            // No paid amount entered
                                            const balTaxAmount = calculateTaxAmount(balBeforeBase, LATEST_TAX_RATE);
                                            balanceInput.value = fmt(balBeforeWithTax);
                                            if (balBreakdownEl) {
                                                balBreakdownEl.textContent = 'Base ' + fmt(balBeforeBase) + ' + Tax ' + fmt(balTaxAmount);
                                            }
                                            removeOverpaymentMessage();
                                            setHint('balance-lkr-hint', 'LKR', balBeforeWithTax);
                                            setHint('balance-base-hint', currencyCode, balBeforeWithTax, false);
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

                                        // What's owed BEFORE this payment
                                        const taxRateDecimal = LATEST_TAX_RATE / 100;
                                        const basePortion = 1 - taxRateDecimal;
                                        const paidBaseBeforeThis = EXISTING_PAID_TOTAL * basePortion;
                                        const balBeforeBase = Math.max(0, basePayableBase - paidBaseBeforeThis);

                                        // Balance BEFORE this payment with tax (in base currency)
                                        const balBeforeWithTax_baseCcy = calculateTaxInclusiveAmount(balBeforeBase, LATEST_TAX_RATE);

                                        // Convert to display currency
                                        const balBeforeDisplayWithTax = toDisplay(balBeforeWithTax_baseCcy);

                                        // Extract tax using corrected formula
                                        const paidCalc = extractBaseAndTax(paidBase, LATEST_TAX_RATE);
                                        const taxDisplay = toDisplay(paidCalc.tax);

                                        if (taxAmountDisplay) taxAmountDisplay.value = fmt(taxDisplay);

                                        // Total paid so far in base terms
                                        const totalPaidBaseSoFar = paidBaseBeforeThis + paidCalc.base;
                                        const remainBase = Math.max(0, basePayableBase - totalPaidBaseSoFar);
                                        const remainTotal = calculateTaxInclusiveAmount(remainBase, LATEST_TAX_RATE);

                                        // Check for overpayment
                                        if (rawPaid > 0 && rawPaid > balBeforeDisplayWithTax + OVERPAY_EPSILON) {
                                            // FIX: overpayDisplay is already tax-inclusive in the display currency.
                                            // Splitting it into base/tax must use extractBaseAndTax directly on that
                                            // tax-inclusive figure (currency conversion doesn't change the tax split
                                            // proportions), NOT convert-then-gross-up-again — that double counted tax
                                            // and inflated the overpayment total (previous bug).
                                            const overpayDisplay = rawPaid - balBeforeDisplayWithTax;
                                            const overpayCalc = extractBaseAndTax(overpayDisplay, LATEST_TAX_RATE);

                                            balanceInput.value = '0.00';
                                            if (balBreakdownEl) balBreakdownEl.textContent = 'Fully paid — overpayment credited to next installment';

                                            showOverpaymentMessage(
                                                balBeforeDisplayWithTax,
                                                rawPaid,
                                                overpayDisplay,
                                                overpayCalc.base,
                                                overpayCalc.tax
                                            );
                                            setHint('balance-base-hint', currencyCode, 0, false);
                                            setHint('balance-lkr-hint', 'LKR', 0, false);

                                        } else if (rawPaid > 0) {
                                            const balDisplay = toDisplay(remainTotal);
                                            balanceInput.value = fmt(balDisplay);
                                            if (balBreakdownEl) {
                                                const remainTaxBase = calculateTaxAmount(remainBase, LATEST_TAX_RATE);
                                                balBreakdownEl.textContent =
                                                    'Paid: base ' + fmt(toDisplay(paidCalc.base)) + ' + tax ' + fmt(taxDisplay) +
                                                    ' | Remaining: base ' + fmt(toDisplay(remainBase)) + ' + tax ' + fmt(toDisplay(remainTaxBase)) +
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
                                            // No paid amount
                                            const balDisplay = toDisplay(balBeforeWithTax_baseCcy);
                                            balanceInput.value = fmt(balDisplay);
                                            if (balBreakdownEl) {
                                                const balTaxBase = calculateTaxAmount(balBeforeBase, LATEST_TAX_RATE);
                                                balBreakdownEl.textContent = 'Base ' + fmt(toDisplay(balBeforeBase)) + ' + Tax ' + fmt(toDisplay(balTaxBase));
                                            }
                                            removeOverpaymentMessage();
                                            if (modalCurrentMode === 'lkr') {
                                                setHint('balance-base-hint', currencyCode, balBeforeWithTax_baseCcy, false);
                                                setHint('balance-lkr-hint', 'LKR', toDisplay(balBeforeWithTax_baseCcy), false);
                                            } else {
                                                setHint('balance-lkr-hint', 'LKR', toDisplay(balBeforeWithTax_baseCcy));
                                                setHint('balance-base-hint', currencyCode, balBeforeWithTax_baseCcy, false);
                                            }
                                        }

                                        // Update other hints based on currency mode
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

                                    // ── Currency switch ──────────────────────────────────────────────────────

                                    function switchCurrency(mode) {
                                        if (isBaseCurrencyLKR) return;
                                        modalCurrentMode = mode;

                                        document.getElementById('btn-base-currency').classList.toggle('active', mode === 'base');
                                        document.getElementById('btn-lkr-currency').classList.toggle('active', mode === 'lkr');

                                        const lkrPanel = document.getElementById('lkr-summary-panel');
                                        if (lkrPanel) lkrPanel.style.display = mode === 'lkr' ? 'block' : 'none';

                                        // Update field currency-label badges
                                        const label = mode === 'lkr' ? 'LKR' : currencyCode;
                                        ['discount', 'penalty', 'balance', 'paid', 'tax-amount'].forEach(id => {
                                            const el = document.getElementById(id + '-currency-label');
                                            if (el) el.textContent = label;
                                        });

                                        // Toggle hint visibility
                                        document.querySelectorAll('[id$="-lkr-hint"]').forEach(el => el.classList.toggle('d-none', mode === 'lkr'));
                                        document.querySelectorAll('[id$="-base-hint"]').forEach(el => el.classList.toggle('d-none', mode === 'base'));

                                        // Convert existing input values to new currency
                                        if (exchangeRate && !isBaseCurrencyLKR) {
                                            [discountInput, penaltyInput, paidInput].forEach(el => {
                                                if (!el) return;
                                                const v = parseFloat(el.value) || 0;
                                                if (!v) return;
                                                el.value = mode === 'lkr' ?
                                                    (v * exchangeRate).toFixed(2) :
                                                    (v / exchangeRate).toFixed(2);
                                            });
                                        }
                                        recalculate();
                                    }

                                    // ── Date change: fetch rate for selected date ────────────────────────────

                                    function onPaidDateChange() {
                                        if (isBaseCurrencyLKR || !paidDateInput.value) return;
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
                                                updateLkrSummaryPanel();
                                                recalculate();
                                            })
                                            .catch(() => {
                                                setRateStatus('error', 'Could not fetch rate.');
                                                updateLkrSummaryPanel();
                                                recalculate();
                                            });
                                    }

                                    // ── Custom rate override ─────────────────────────────────────────────────

                                    function onCustomRateChange() {
                                        const val = parseFloat(customRateInput.value);
                                        if (!isNaN(val) && val > 0) {
                                            exchangeRate = val;
                                            exchangeRateId = null;
                                            const pr = document.getElementById('panel-rate-display');
                                            if (pr) pr.textContent = val.toFixed(2);
                                            const rb = document.getElementById('live-rate-text');
                                            if (rb) rb.textContent = `1 ${currencyCode} = LKR ${val.toFixed(2)} (custom)`;
                                            setRateStatus('info', `Custom rate: 1 ${currencyCode} = LKR ${val.toFixed(2)}`);
                                            updateLkrSummaryPanel();
                                            recalculate();
                                        }
                                    }

                                    // ── Build currency data object for form submission ───────────────────────

                                    function getCurrentCurrencyData() {
                                        const customRateEl = document.getElementById('custom-exchange-rate');
                                        const customRateVal = customRateEl ? parseFloat(customRateEl.value) : NaN;
                                        const isCustomRate = !isNaN(customRateVal) && customRateVal > 0 && customRateVal !== exchangeRate;
                                        const finalRate = (customRateEl && !isNaN(customRateVal) && customRateVal > 0) ? customRateVal : exchangeRate;
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

                                    // ── Event listeners ──────────────────────────────────────────────────────

                                    discountInput.addEventListener('input', recalculate);
                                    penaltyInput.addEventListener('input', recalculate);
                                    if (paidInput) paidInput.addEventListener('input', recalculate);
                                    if (paidDateInput) paidDateInput.addEventListener('change', onPaidDateChange);
                                    if (customRateInput) customRateInput.addEventListener('input', onCustomRateChange);

                                    // Initial calculation
                                    recalculate();
                                    console.log('✓ Payment Tax Calculation v9 CORRECTED | Tax:', LATEST_TAX_RATE + '% | Model: Tax as % of final amount | Overpayment double-tax bug fixed | LKR summary panel now reacts to rate changes');
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