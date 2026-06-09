<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/SlipVerifier.php';
require_once "./includes/connection.php";
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
            header("Location: ./payments");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
                header("Location: ./dashboard");
            } else {
                $id = $_GET['payment-id'];

                $payment = Database::search("
                    SELECT * FROM `course_payment_has_payments`
                    INNER JOIN `payment_method` pm ON `course_payment_has_payments`.`payment_method_payment_method_id` = pm.payment_method_id
                    INNER JOIN `currencies` c ON `course_payment_has_payments`.`currencies_currency_id` = c.currency_id
                    WHERE `course_payment_has_payment_id` = '" . $id . "'
                ");

                if ($payment->num_rows == 0) {
                    header("Location: ./payments");
                } else {
                    $coursepaymentData = $payment->fetch_assoc();

                    $paymentPlan = Database::search("
                        SELECT
                            cp.course_payments_id,
                            ps.payment_status,
                            ps.payment_status_id,
                            pgp.payment_type,
                            cp.amount,
                            cp.discount,
                            cp.discount_note,
                            cp.panelty_payment_option_panelty_payment_option_id,
                            dt.discount_type,
                            dt.discount_type_id,
                            cp.due_date,
                            cp.refference,
                            i.intake_id,
                            p.programme_id,
                            f.faculty_id,
                            i.intake,
                            p.programme,
                            f.faculty,
                            t.title,
                            ld.first_name,
                            ld.last_name,
                            ld.email,
                            s.mobile,
                            s.student_id,
                            ppo.panelty_payment_option,
                            c.code         AS currency_code,
                            c.decimal_places,
                            c.currency_id,
                            IFNULL(pp.panelty_amount, 0) AS panelty_amount
                        FROM course_payments cp
                        INNER JOIN payment_status ps
                            ON cp.payment_status_payment_status_id = ps.payment_status_id
                        INNER JOIN panelty_payment_option ppo
                            ON cp.panelty_payment_option_panelty_payment_option_id = ppo.panelty_payment_option_id
                        INNER JOIN discount_type dt
                            ON cp.discount_type_discount_type_id = dt.discount_type_id
                        INNER JOIN payment_type pgp
                            ON cp.payment_type_payment_type_id = pgp.payment_type_id
                        INNER JOIN payment_plan_has_students_has_intakes phsi
                            ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id
                        INNER JOIN payment_category pc
                            ON phsi.payment_category_payment_category_id = pc.payment_category_id
                        INNER JOIN currencies c
                            ON pc.currencies_currency_id = c.currency_id
                        INNER JOIN students_has_intakes shi
                            ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
                        INNER JOIN students s
                            ON shi.students_student_id = s.student_id
                        INNER JOIN login_details ld
                            ON s.login_details_login_details_id = ld.login_details_id
                        INNER JOIN title t
                            ON s.title_title_id = t.title_id
                        INNER JOIN intakes i
                            ON shi.intakes_intake_id = i.intake_id
                        INNER JOIN programmes p
                            ON i.programmes_programme_id = p.programme_id
                        INNER JOIN faculties f
                            ON p.faculties_faculty_id = f.faculty_id
                        LEFT JOIN (
                            SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                            FROM panelty_payments
                            GROUP BY course_payments_course_payments_id
                        ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                        WHERE cp.course_payments_id = '" . $coursepaymentData['course_payments_course_payments_id'] . "'
                    ");

                    if ($paymentPlan->num_rows == 0) {
                        header("Location: ./students");
                    } else {
                        $paymentData = $paymentPlan->fetch_assoc();

                        // ════════════════════════════════════════════════════════════════════
                        // FETCH LATEST TAX RATE
                        // ════════════════════════════════════════════════════════════════════
                        $taxRateQuery = Database::search("
                            SELECT `tax_rate` AS `rate` FROM `tax_rates`
                            ORDER BY `date_added` DESC LIMIT 1
                        ");
                        $latestTaxRate = 0;
                        if ($taxRateQuery && $taxRateQuery->num_rows > 0) {
                            $taxRateRow    = $taxRateQuery->fetch_assoc();
                            $latestTaxRate = (float)$taxRateRow['rate'];
                        }

                        // ── Currency helpers ─────────────────────────────────────────────
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

                        $lkrCurrencyId     = 1;
                        $isBaseCurrencyLKR = ($paymentData['currency_id'] == $lkrCurrencyId);

                        // ── CP-level discount ────────────────────────────────────────────
                        $discountAmountCP = ($paymentData['discount_type_id'] == 3)
                            ? $paymentData['amount'] / 100 * $paymentData['discount']
                            : (float)$paymentData['discount'];

                        $totalDiscount = $discountAmountCP;
                        $totalPenalty  = (float)$paymentData['panelty_amount'];
                        $totalTax      = 0;

                        // ── Exchange rate for display ─────────────────────────────────────
                        $exchangeRate      = null;
                        $exchangeRateValue = 1;
                        $exchangeRateId    = 0;

                        if (!$isBaseCurrencyLKR) {
                            $rateResult = Database::search("
                                SELECT
                                    cer.rate,
                                    cer.effective_date,
                                    cer.effective_time,
                                    c_base.code   AS base_code,
                                    c_base.symbol AS base_symbol,
                                    c_target.code AS target_code,
                                    cer.currency_exchange_rate_id
                                FROM currency_exchange_rates cer
                                INNER JOIN currencies c_base   ON cer.base_currency_id   = c_base.currency_id
                                INNER JOIN currencies c_target ON cer.target_currency_id = c_target.currency_id
                                WHERE cer.base_currency_id   = '" . $paymentData['currency_id'] . "'
                                  AND cer.target_currency_id = '" . $lkrCurrencyId . "'
                                ORDER BY cer.effective_date DESC, cer.effective_time DESC
                                LIMIT 1
                            ");
                            if ($rateResult->num_rows > 0) {
                                $exchangeRate      = $rateResult->fetch_assoc();
                                $exchangeRateValue = (float)$exchangeRate['rate'];
                                $exchangeRateId    = $exchangeRate['currency_exchange_rate_id'] ?? null;
                            }
                        }

                        // ── Fetch all receipts ────────────────────────────────────────────
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
                                cpp.tax_rate,
                                cpp.tax_amount
                            FROM course_payment_has_payments AS cpp
                            INNER JOIN payment_method AS pm  ON cpp.payment_method_payment_method_id = pm.payment_method_id
                            INNER JOIN payment_status AS ps2 ON cpp.payment_status_payment_status_id = ps2.payment_status_id
                            LEFT  JOIN currencies     AS cur ON cpp.currencies_currency_id           = cur.currency_id
                            WHERE cpp.course_payments_course_payments_id = '" . (int)$paymentData['course_payments_id'] . "'
                            GROUP BY cpp.course_payment_has_payment_id
                        ");

                        // ════════════════════════════════════════════════════════════════════
                        // TAX MODEL: Tax is a percentage of paid_amount (inclusive)
                        //   tax     = paid_amount × taxRate%
                        //   base    = paid_amount - tax  = paid_amount × (1 - taxRate%)
                        //
                        // So stored paid_amount already contains tax.
                        // We accumulate the FULL paid_amount for "total paid" display.
                        // For balance: balance = payable - total_paid
                        // Where: payable = (course_amount - discount + penalty) + tax_on_payable
                        //        tax_on_payable = payable_before_tax × taxRate%
                        // ════════════════════════════════════════════════════════════════════
                        $convertedPaidTotal = 0;
                        $receiptRows        = [];
                        $totalReceiptDisc   = 0;
                        $totalReceiptPen    = 0;

                        if ($receipts && $receipts->num_rows > 0) {
                            while ($rec = $receipts->fetch_assoc()) {
                                $rate = resolveExchangeRate($rec, $paymentData['currency_id']);

                                // paid_amount is stored tax-inclusive → convert to course currency
                                $rec['converted_paid']         = convertToCourseAmount($rec['paid_amount'], $rate);
                                $rec['is_foreign_currency']    = ($rate !== null);
                                $rec['currency_exchange_rate'] = $rate;

                                $convertedPaidTotal += $rec['converted_paid'];
                                $receiptRows[]       = $rec;

                                // Accumulate only pending(2) or confirmed(3) receipts
                                if ($rec['payment_status_id'] == 2 || $rec['payment_status_id'] == 3) {
                                    $totalReceiptDisc += convertToCourseAmount((float)($rec['discount_amount'] ?? 0), $rate);
                                    $totalReceiptPen  += convertToCourseAmount((float)($rec['panelty_amount'] ?? 0), $rate);
                                    $totalTax         += convertToCourseAmount((float)($rec['tax_amount'] ?? 0), $rate);
                                }
                            }
                        }

                        $totalDiscount += $totalReceiptDisc;
                        $totalPenalty  += $totalReceiptPen;

                        // ── Payable Calculation ───────────────────────────────────────────
                        // Base payable = course_amount - discount + penalty
                        // Tax on payable = basePayable × latestTaxRate%
                        // Total payable  = basePayable + tax
                        $basePayable   = max(0, $paymentData['amount'] - $totalDiscount + $totalPenalty);
                        $taxOnPayable  = round($basePayable * $latestTaxRate / 100, 4);
                        $payableAmount = $basePayable + $taxOnPayable;

                        // Balance = totalPayable - all paid (including tax in paid amounts)
                        $balance    = $payableAmount - $convertedPaidTotal;
                        $paidAmount = $convertedPaidTotal;

                        // ── Next installment data (for overpayment display) ───────────────
                        $nextInstallmentData = null;
                        if ($balance < 0) {
                            // Overpayment: next installment amount
                            $overpayAmount = abs($balance);
                            $nextTax       = round($overpayAmount * $latestTaxRate / 100, 4);
                            $nextBase      = $overpayAmount - $nextTax;
                            $nextInstallmentData = [
                                'overpay'  => $overpayAmount,
                                'base'     => $nextBase,
                                'tax'      => $nextTax,
                            ];
                            $balance = 0; // current installment fully paid
                        }

                        // ── LKR equivalents for display ───────────────────────────────────
                        $balanceLKR     = $balance       * $exchangeRateValue;
                        $payableAmtLKR  = $payableAmount * $exchangeRateValue;
                        $paidAmountLKR  = $paidAmount    * $exchangeRateValue;
                        $discountAmtLKR = $totalDiscount * $exchangeRateValue;
                        $penaltyAmtLKR  = $totalPenalty  * $exchangeRateValue;
                        $taxAmtLKR      = $taxOnPayable  * $exchangeRateValue;

                        $currencyCode = htmlspecialchars($paymentData['currency_code']);

                        // ── This receipt's details ────────────────────────────────────────
                        $receiptRate         = resolveExchangeRate($coursepaymentData, $paymentData['currency_id']);
                        $receiptConvertedAmt = convertToCourseAmount($coursepaymentData['paid_amount'], $receiptRate);
                        $receiptIsForeign    = ($receiptRate !== null);

                        // Tax model: tax = paid × rate%  →  base = paid × (1 - rate%)
                        $receiptTaxRate    = (float)($coursepaymentData['tax_rate']   ?? $latestTaxRate);
                        $receiptPaidAmt    = (float)$coursepaymentData['paid_amount'];
                        $receiptTaxAmount  = $coursepaymentData['tax_amount'];
                        $receiptBaseAmount = $receiptPaidAmt - $receiptTaxAmount;
                        $receiptTaxConverted = convertToCourseAmount($receiptTaxAmount, $receiptRate);

                        function formatDateSafe($date, $outputFormat = 'Y-m-d')
                        {
                            if (empty($date)) return null;
                            try {
                                $d = new DateTime($date);
                                return $d->format($outputFormat);
                            } catch (Exception $e) {
                                $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd.m.Y', 'm/d/Y', 'm-d-Y'];
                                foreach ($formats as $format) {
                                    $d = DateTime::createFromFormat($format, $date);
                                    if ($d !== false) return $d->format($outputFormat);
                                }
                            }
                            return null;
                        }
?>
                        <!DOCTYPE html>
                        <html lang="en">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>Student Payment Details - Admin Dashboard | <?= Project::getBaseName() ?></title>
                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/translations.js?= Project::getVersion() ?>"></script>
                            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
                        </head>

                        <style>
                            /* ── LKR Summary Panel ──────────────────────────────────────────────── */
                            .lkr-panel {
                                background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
                                overflow: hidden;
                                margin-bottom: 20px;
                                border-radius: 8px;
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

                            /* ── Overpayment / Next Installment Panel ───────────────────────────── */
                            .overpay-panel {
                                border-left: 4px solid #3b82f6;
                                background: rgba(59, 130, 246, 0.07);
                                border-radius: 0 8px 8px 0;
                                padding: 14px 18px;
                                margin: 12px 0 0;
                            }

                            .overpay-panel-title {
                                font-size: 12px;
                                font-weight: 700;
                                color: #2563eb;
                                text-transform: uppercase;
                                letter-spacing: .06em;
                                margin-bottom: 10px;
                                display: flex;
                                align-items: center;
                                gap: 6px;
                            }

                            .overpay-grid {
                                display: grid;
                                grid-template-columns: repeat(3, minmax(0, 1fr));
                                gap: 10px;
                            }

                            .overpay-item {
                                display: flex;
                                flex-direction: column;
                                gap: 2px;
                            }

                            .overpay-label {
                                font-size: 11px;
                                color: var(--sub-text-color);
                                text-transform: uppercase;
                                letter-spacing: .04em;
                            }

                            .overpay-value {
                                font-size: 15px;
                                font-weight: 600;
                                color: #2563eb;
                            }

                            .overpay-sub {
                                font-size: 11px;
                                color: var(--sub-text-color);
                            }

                            /* ── Currency toggle ───────────────────────────────────────────────── */
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

                            /* ── Exchange rate badge ─────────────────────────────────────────────── */
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

                            /* ── Currency / Tax label badge ─────────────────────────────────────── */
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

                            .tax-badge {
                                background: rgba(245, 158, 11, .12);
                                color: #d97706;
                                border-color: rgba(245, 158, 11, .3);
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
                            }

                            /* ── Readonly-style tax input ────────────────────────────────────────── */
                            .tax-readonly {
                                background: rgba(245, 158, 11, .06) !important;
                                border-color: rgba(245, 158, 11, .3) !important;
                                color: #d97706 !important;
                                cursor: not-allowed;
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

                                .overpay-grid {
                                    grid-template-columns: repeat(2, minmax(0, 1fr));
                                }
                            }
                        </style>

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

                                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100">
                                                <div class="row p-4 m-0 g-0">

                                                    <!-- ── Breadcrumb ──────────────────────────────────────────── -->
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 col-md-10">
                                                                <div class="row">
                                                                    <span class="main-topic lh-lg" data-i18n="payment-details">Payment Details</span>
                                                                    <nav aria-label="breadcrumb">
                                                                        <ol class="breadcrumb m-0">
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='payments'>Payments</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./students-payments'>Student Payments</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./students-scheduled-payments'>Students Scheduled Payments</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $paymentData['title'] . "." . $paymentData['first_name'] . " " . $paymentData['last_name'] . " - #" . $paymentData['course_payments_id'] ?></a></li>
                                                                        </ol>
                                                                    </nav>
                                                                </div>
                                                            </div>
                                                            <?php
                                                            $receiptCheck = Database::search("
                                        SELECT cpr.receipt_number, cpr.course_payment_receipt_id
                                        FROM course_payment_has_payments cpp
                                        INNER JOIN course_payment_receipts cpr
                                            ON cpp.course_payment_receipts_course_payment_receipt_id = cpr.course_payment_receipt_id
                                        WHERE cpp.course_payment_has_payment_id = '" . $coursepaymentData['course_payment_has_payment_id'] . "'
                                        LIMIT 1
                                    ");
                                                            if ($receiptCheck && $receiptCheck->num_rows > 0):
                                                                $receiptInfo = $receiptCheck->fetch_assoc();
                                                            ?>
                                                                <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" type="button"
                                                                        onclick="openReceiptModal(<?= $receiptInfo['course_payment_receipt_id'] ?>)">
                                                                        View Receipt & Send
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ((SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0)) && $coursepaymentData['payment_status_payment_status_id'] == 2): ?>
                                                                <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" type="button" data-bs-target="#updatePaymentStatus" data-bs-toggle="modal">
                                                                        Update Payment Status
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <!-- ── Main content ──────────────────────────────────────────── -->
                                                    <div class="col-12">
                                                        <div class="row p-4 m-0 g-0 component-theme w-100">

                                                            <!-- Payment timeline -->
                                                            <div class="col-12">
                                                                <div class="payment-timeline">
                                                                    <div class="timeline-progress">
                                                                        <?php
                                                                        $query = "SELECT * FROM `payment_status`";
                                                                        $progress = 0;
                                                                        $progressColor = "";
                                                                        switch ($coursepaymentData['payment_status_payment_status_id']) {
                                                                            case 2:
                                                                                $progressColor = "completed";
                                                                                $progress = 50;
                                                                                $query .= " WHERE `payment_status_id` IN (1,2)";
                                                                                break;
                                                                            case 3:
                                                                                $progressColor = "accepted";
                                                                                $progress = 100;
                                                                                $query .= " WHERE `payment_status_id` IN (1,3)";
                                                                                break;
                                                                            case 4:
                                                                                $progressColor = "reject";
                                                                                $progress = 100;
                                                                                $query .= " WHERE `payment_status_id` IN (1,4)";
                                                                                break;
                                                                        }
                                                                        ?>
                                                                        <div class="progress-bar <?= $progressColor ?>" style="width:<?= $progress ?>%"></div>
                                                                    </div>
                                                                    <div class="timeline-items">
                                                                        <?php
                                                                        $icons = ["majesticons:clock", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve"];
                                                                        $paymentStatus = Database::search($query);
                                                                        while ($st = $paymentStatus->fetch_assoc()) {
                                                                            $timelineItemColor = "";
                                                                            $bubbleAction = "";
                                                                            if ($coursepaymentData['payment_status_payment_status_id'] >= $st['payment_status_id']) {
                                                                                if ($coursepaymentData['payment_status_payment_status_id'] == 4) {
                                                                                    $timelineItemColor = "reject";
                                                                                } elseif ($coursepaymentData['payment_status_payment_status_id'] == 3) {
                                                                                    $timelineItemColor = "accepted";
                                                                                } else {
                                                                                    $timelineItemColor = ($coursepaymentData['payment_status_payment_status_id'] == $st['payment_status_id']) ? "" : "completed";
                                                                                    $bubbleAction      = ($coursepaymentData['payment_status_payment_status_id'] == $st['payment_status_id']) ? "pulse" : "";
                                                                                }
                                                                            }
                                                                        ?>
                                                                            <div class="timeline-item <?= $timelineItemColor ?>">
                                                                                <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                                    <iconify-icon icon="<?= $icons[$st['payment_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                                </div>
                                                                                <div class="timeline-label"><span><?= $st['payment_status'] ?></span></div>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Exchange rate badge -->
                                                            <?php if (!$isBaseCurrencyLKR): ?>
                                                                <div class="col-12 mt-3 d-flex align-items-center gap-3 flex-wrap">
                                                                    <?php if ($exchangeRate): ?>
                                                                        <div class="exchange-rate-badge">
                                                                            <iconify-icon icon="mdi:swap-horizontal" width="14" height="14"></iconify-icon>
                                                                            <span>1 <?= $currencyCode ?> = LKR <?= number_format($exchangeRateValue, 2) ?>
                                                                                (<?= date('d M Y', strtotime($exchangeRate['effective_date'])) ?>)</span>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div class="exchange-rate-badge rate-unavailable">
                                                                            <iconify-icon icon="mdi:alert-outline" width="14" height="14"></iconify-icon>
                                                                            <span>No exchange rate available — amounts shown in <?= $currencyCode ?> only</span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- LKR Equivalent Summary Panel -->
                                                            <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?>
                                                                <div class="col-12 mt-3">
                                                                    <div class="lkr-panel">
                                                                        <div class="lkr-panel-top">
                                                                            <div class="lkr-panel-top-left">
                                                                                <iconify-icon icon="mdi:currency-usd" width="16" height="16"></iconify-icon>
                                                                                LKR equivalent summary
                                                                            </div>
                                                                            <span class="lkr-panel-rate-note">@ <?= number_format($exchangeRateValue, 2) ?> per <?= $currencyCode ?></span>
                                                                        </div>
                                                                        <div class="lkr-panel-grid" style="grid-template-columns:repeat(7,minmax(0,1fr));">
                                                                            <div class="lkr-panel-item">
                                                                                <span class="lkr-item-label">Total Amount</span>
                                                                                <span class="lkr-item-value">LKR <?= number_format($paymentData['amount'] * $exchangeRateValue, 2) ?></span>
                                                                                <span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($paymentData['amount'], 2) ?></span>
                                                                            </div>
                                                                            <div class="lkr-panel-item">
                                                                                <span class="lkr-item-label">Total Discount</span>
                                                                                <span class="lkr-item-value">LKR <?= number_format($discountAmtLKR, 2) ?></span>
                                                                                <span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($totalDiscount, 2) ?></span>
                                                                            </div>
                                                                            <div class="lkr-panel-item">
                                                                                <span class="lkr-item-label">Total Penalty</span>
                                                                                <span class="lkr-item-value">LKR <?= number_format($penaltyAmtLKR, 2) ?></span>
                                                                                <span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($totalPenalty, 2) ?></span>
                                                                            </div>
                                                                            <div class="lkr-panel-item">
                                                                                <span class="lkr-item-label">Total Tax</span>
                                                                                <span class="lkr-item-value">LKR <?= number_format($taxAmtLKR, 2) ?></span>
                                                                                <span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($taxOnPayable, 2) ?></span>
                                                                            </div>
                                                                            <div class="lkr-panel-item">
                                                                                <span class="lkr-item-label">Total Payable</span>
                                                                                <span class="lkr-item-value">LKR <?= number_format($payableAmtLKR, 2) ?></span>
                                                                                <span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($payableAmount, 2) ?></span>
                                                                            </div>
                                                                            <div class="lkr-panel-item">
                                                                                <span class="lkr-item-label">Total Paid</span>
                                                                                <span class="lkr-item-value">LKR <?= number_format($paidAmountLKR, 2) ?></span>
                                                                                <span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($paidAmount, 2) ?></span>
                                                                            </div>
                                                                            <div class="lkr-panel-item balance-item">
                                                                                <span class="lkr-item-label">Balance Due</span>
                                                                                <span class="lkr-item-value">LKR <?= number_format($balanceLKR, 2) ?></span>
                                                                                <span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($balance, 2) ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- ── Payment summary card ──────────────────────────────── -->
                                                            <div class="payment-card-container mt-4">
                                                                <div class="payment-card">
                                                                    <div class="card-background"></div>
                                                                    <div class="card-content">
                                                                        <div class="payment-header reference-section">
                                                                            <div class="reference-section">
                                                                                <span class="reference-label">PAYMENT REFERENCE</span>
                                                                                <h2 class="reference-number"><?= $paymentData["refference"] ?></h2>
                                                                                <hr>
                                                                            </div>
                                                                            <div class="amount-section">
                                                                                <div class="amount-display">
                                                                                    <span class="amount-label">Total Balance Amount</span>
                                                                                    <span class="amount-value"><?= $currencyCode ?> <?= $balance > 0 ? number_format($balance, 2) : '0.00' ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate && $balance > 0): ?>
                                                                                        <small class="amount-label" style="font-size:12px;opacity:.7;">≈ LKR <?= number_format($balanceLKR, 2) ?></small>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <span class="reference-label my-3">STUDENT DETAILS</span>
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item align-items-start">
                                                                                <div class="detail-icon"><iconify-icon icon="icons8:student" width="20" height="20"></iconify-icon></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Student</span>
                                                                                    <span class="detail-value"><?= $paymentData['student_id'] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData['title'] . "." . $paymentData['first_name'] . " " . $paymentData['last_name'] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData['email'] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData['mobile'] ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item align-items-start">
                                                                                <div class="detail-icon"><iconify-icon icon="carbon:batch-job" width="20" height="20"></iconify-icon></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Batch</span>
                                                                                    <span class="detail-value"><?= $paymentData["faculty"] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData["programme"] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData["intake"] ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <span class="reference-label my-3">PAYMENT DETAILS</span>
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Amount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($paymentData['amount'], 2) ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?>
                                                                                        <span class="detail-lkr-hint">≈ LKR <?= number_format($paymentData['amount'] * $exchangeRateValue, 2) ?></span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Type</span>
                                                                                    <span class="detail-value"><?= $paymentData["payment_type"] ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Due Date</span>
                                                                                    <span class="detail-value"><?= $paymentData["due_date"] ?? '--/--/----' ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Discount</span>
                                                                                    <span class="detail-value">
                                                                                        <?php
                                                                                        if ($paymentData["discount_type_id"] == 1) echo "No Discount";
                                                                                        elseif ($paymentData["discount_type_id"] == 2) echo $currencyCode . " " . number_format($paymentData["discount"], 2) . " | " . $paymentData["discount_type"];
                                                                                        else echo number_format($paymentData["discount"]) . "% | " . $paymentData["discount_type"];
                                                                                        ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Discount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($totalDiscount, 2) ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($discountAmtLKR, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Penalty</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($totalPenalty, 2) ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($penaltyAmtLKR, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Amount
                                                                                        <span class="currency-label-badge tax-badge"><?= number_format($latestTaxRate, 2) ?>%</span>
                                                                                    </span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($taxOnPayable, 2) ?></span>
                                                                                    <span class="detail-lkr-hint">On base: <?= $currencyCode ?> <?= number_format($basePayable, 2) ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($taxAmtLKR, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Payable Amount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($payableAmount, 2) ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($payableAmtLKR, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Paid Amount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($paidAmount, 2) ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($paidAmountLKR, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Balance</span>
                                                                                    <?php if ($balance > 0): ?>
                                                                                        <span class="detail-value"><?= $currencyCode ?> <?= number_format($balance, 2) ?></span>
                                                                                        <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($balanceLKR, 2) ?></span><?php endif; ?>
                                                                                    <?php else: ?>
                                                                                        <span class="detail-value"><?= $currencyCode ?> 0.00</span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <!-- ════════════════════════════════════════════════════════════
                                                     OVERPAYMENT / NEXT INSTALLMENT PANEL
                                                     Shows when paid_amount > current installment payable
                                                ════════════════════════════════════════════════════════════ -->
                                                                        <?php if ($nextInstallmentData): ?>
                                                                            <div class="col-12 mt-2">
                                                                                <div class="overpay-panel">
                                                                                    <div class="overpay-panel-title">
                                                                                        <iconify-icon icon="mdi:arrow-right-circle-outline" width="16" height="16"></iconify-icon>
                                                                                        Overpayment Applied — Next Installment Credit
                                                                                    </div>
                                                                                    <div class="overpay-grid">
                                                                                        <div class="overpay-item">
                                                                                            <span class="overpay-label">Current Installment</span>
                                                                                            <span class="overpay-value"><?= $currencyCode ?> <?= number_format($payableAmount, 2) ?></span>
                                                                                            <span class="overpay-sub">Fully Paid ✓</span>
                                                                                        </div>
                                                                                        <div class="overpay-item">
                                                                                            <span class="overpay-label">Overpayment Amount</span>
                                                                                            <span class="overpay-value"><?= $currencyCode ?> <?= number_format($nextInstallmentData['overpay'], 2) ?></span>
                                                                                            <span class="overpay-sub">Applied to next installment</span>
                                                                                        </div>
                                                                                        <div class="overpay-item">
                                                                                            <span class="overpay-label">Next Installment Breakdown</span>
                                                                                            <span class="overpay-value" style="font-size:13px;">
                                                                                                <?= $currencyCode ?> <?= number_format($nextInstallmentData['base'], 2) ?>
                                                                                                <span style="font-size:11px;color:var(--sub-text-color);font-weight:400;">course fee</span>
                                                                                                + <?= $currencyCode ?> <?= number_format($nextInstallmentData['tax'], 2) ?>
                                                                                                <span>tax (<?= number_format($latestTaxRate, 2) ?>%)</span>
                                                                                            </span>
                                                                                            <span class="overpay-sub">Total: <?= $currencyCode ?> <?= number_format($nextInstallmentData['overpay'], 2) ?></span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>

                                                                        <!-- Penalty rules -->
                                                                        <?php
                                                                        if ($paymentData['panelty_payment_option_panelty_payment_option_id'] != 1):
                                                                            $panelties = Database::search("
                                                        SELECT * FROM `course_payments_penalties`
                                                        INNER JOIN `panelty_payments_type`
                                                            ON `course_payments_penalties`.`panelty_payment_type_panelty_payment_type_id` = `panelty_payments_type`.`panelty_payments_type_id`
                                                        WHERE `course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "'
                                                    ");
                                                                            if ($panelties->num_rows > 0):
                                                                        ?>
                                                                                <span class="reference-label my-3">PENALTY PAYMENT ENABLED</span>
                                                                                <?php while ($panelty = $panelties->fetch_assoc()): ?>
                                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-content"><span class="detail-label">Option</span><span class="detail-value"><?= $paymentData["panelty_payment_option"] ?></span></div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-content"><span class="detail-label">Type</span><span class="detail-value"><?= $panelty["panelty_payments_type"] ?></span></div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-content">
                                                                                                <span class="detail-label">Amount</span>
                                                                                                <span class="detail-value"><?= $currencyCode ?> <?= number_format($panelty["amount"], 2) ?></span>
                                                                                                <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($panelty["amount"] * $exchangeRateValue, 2) ?></span><?php endif; ?>
                                                                                            </div>
                                                                                        </div>
                                                                                        <?php if (!empty($panelty["days"])): ?>
                                                                                            <div class="detail-item">
                                                                                                <div class="detail-content"><span class="detail-label">Days</span><span class="detail-value"><?= $panelty["days"] ?></span></div>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                <?php endwhile; ?>
                                                                        <?php endif;
                                                                        endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- ── Receipt card ────────────────────────────────────── -->
                                                            <div class="payment-card-container mt-4">
                                                                <div class="payment-card">
                                                                    <div class="card-background-ongoing"></div>
                                                                    <div class="card-content">
                                                                        <?php
                                                                        $paidCurrCode         = !empty($coursepaymentData['code']) ? htmlspecialchars($coursepaymentData['code']) : $currencyCode;
                                                                        $paidIsDiffFromCourse = ($paidCurrCode !== $currencyCode);
                                                                        $recDiscConverted     = convertToCourseAmount((float)$coursepaymentData['discount_amount'], $receiptRate);
                                                                        $recPenConverted      = convertToCourseAmount((float)($coursepaymentData['panelty_amount'] ?? 0), $receiptRate);
                                                                        ?>
                                                                        <div class="payment-header reference-section">
                                                                            <div class="reference-section">
                                                                                <span class="reference-label">RECEIPT NO</span>
                                                                                <h2 class="reference-number"><?= $coursepaymentData["course_payment_has_payment_id"] ?></h2>
                                                                                <hr>
                                                                            </div>
                                                                            <div class="amount-section">
                                                                                <div class="amount-display">
                                                                                    <span class="amount-label">Paid Amount (Tax-Inclusive)</span>
                                                                                    <span class="amount-value"><?= $paidCurrCode ?> <?= number_format($receiptPaidAmt, 2) ?></span>
                                                                                    <?php if ($receiptTaxAmount > 0): ?>
                                                                                        <small class="amount-label" style="font-size:11px;opacity:.7;">
                                                                                            Course Fee: <?= $paidCurrCode ?> <?= number_format($receiptBaseAmount, 2) ?>
                                                                                            &nbsp;+&nbsp;
                                                                                            Tax (<?= number_format($receiptTaxRate, 2) ?>%): <?= $paidCurrCode ?> <?= number_format($receiptTaxAmount, 2) ?>
                                                                                        </small>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($paidIsDiffFromCourse): ?>
                                                                                        <small class="amount-label" style="font-size:12px;opacity:.7;">
                                                                                            = <?= $currencyCode ?> <?= number_format($receiptConvertedAmt, 2) ?>
                                                                                            <?php if ($receiptRate): ?>&nbsp;<span style="opacity:.6;">(@ <?= number_format($receiptRate, 2) ?>)</span><?php endif; ?>
                                                                                        </small>
                                                                                    <?php endif; ?>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?>
                                                                                        <small class="amount-label" style="font-size:12px;opacity:.6;">≈ LKR <?= number_format($receiptConvertedAmt * $exchangeRateValue, 2) ?></small>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <!-- Date Paid -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Date Paid</span>
                                                                                    <span class="detail-value"><?= date_format(date_create($coursepaymentData["date_paid"]), "Y-m-d h:i A") ?? '--/--/----' ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Discount -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Given Discount</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format((float)$coursepaymentData['discount_amount'], 2) ?></span>
                                                                                    <?php if ($paidIsDiffFromCourse): ?><span class="detail-lkr-hint">= <?= $currencyCode ?> <?= number_format($recDiscConverted, 2) ?></span><?php endif; ?>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($recDiscConverted * $exchangeRateValue, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Penalty -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Added Penalty</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format((float)($coursepaymentData['panelty_amount'] ?? 0), 2) ?></span>
                                                                                    <?php if ($paidIsDiffFromCourse): ?><span class="detail-lkr-hint">= <?= $currencyCode ?> <?= number_format($recPenConverted, 2) ?></span><?php endif; ?>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($recPenConverted * $exchangeRateValue, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Tax Rate -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Rate</span>
                                                                                    <span class="detail-value">
                                                                                        <?= $receiptTaxRate > 0 ? number_format($receiptTaxRate, 2) . '%' : 'N/A' ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Tax Amount (calculated from paid × rate%) -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Amount</span>
                                                                                    <span class="detail-value">
                                                                                        <?= $receiptTaxAmount > 0 ? $paidCurrCode . ' ' . number_format($receiptTaxAmount, 2) : 'N/A' ?>
                                                                                    </span>
                                                                                    <?php if ($receiptTaxAmount > 0): ?>
                                                                                        <span class="detail-lkr-hint">
                                                                                            <?= $paidCurrCode ?> <?= number_format($receiptPaidAmt, 2) ?> × <?= number_format($receiptTaxRate, 2) ?>% = <?= number_format($receiptTaxAmount, 2) ?>
                                                                                        </span>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($receiptTaxAmount > 0 && $paidIsDiffFromCourse): ?>
                                                                                        <span class="detail-lkr-hint">= <?= $currencyCode ?> <?= number_format($receiptTaxConverted, 2) ?></span>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($receiptTaxAmount > 0 && !$isBaseCurrencyLKR && $exchangeRate): ?>
                                                                                        <span class="detail-lkr-hint">≈ LKR <?= number_format($receiptTaxConverted * $exchangeRateValue, 2) ?></span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Course Fee (base) -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Course Fee (Base)</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format($receiptBaseAmount, 2) ?></span>
                                                                                    <span class="detail-lkr-hint"><?= $paidCurrCode ?> <?= number_format($receiptPaidAmt, 2) ?> − <?= number_format($receiptTaxAmount, 2) ?> tax</span>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Payment Method -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Payment Method</span>
                                                                                    <span class="detail-value"><?= $coursepaymentData['payment_method'] ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <?php if ($paidIsDiffFromCourse && $receiptRate): ?>
                                                                                <div class="detail-item">
                                                                                    <div class="detail-content">
                                                                                        <span class="detail-label">Exchange Rate Used</span>
                                                                                        <span class="detail-value">1 <?= $currencyCode ?> = <?= $paidCurrCode ?> <?= number_format($receiptRate, 2) ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item align-items-start">
                                                                                <div class="detail-icon"><iconify-icon icon="hugeicons:note" width="20"></iconify-icon></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Note</span>
                                                                                    <span class="detail-value"><?= $coursepaymentData['note'] == null ? "N/A" : $coursepaymentData['note'] ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- ── Payment slips ─────────────────────────────────────── -->
                                                            <?php
                                                            $paymnetSlips = Database::search("SELECT * FROM `course_payment_slips` WHERE `course_payment_has_payment_id` = '" . $coursepaymentData['course_payment_has_payment_id'] . "'");
                                                            if ($paymnetSlips->num_rows > 0):
                                                            ?>
                                                                <div class="col-12">
                                                                    <div class="row p-4 m-0 g-0 align-content-center bl-solid mt-4">
                                                                        <span class="main-topic lh-lg">Payment Slips</span>
                                                                        <div class="accordion accordion-flush" id="accordionParent">
                                                                            <?php while ($paymentSlip = $paymnetSlips->fetch_assoc()) {
                                                                                $slipVerifier = new SlipVerifier();
                                                                                $fileType     = $slipVerifier->checkFileType($paymentSlip['slip_name']);
                                                                                $bankAccNo    = '';
                                                                                if ($coursepaymentData['payment_method_id'] == 3) {
                                                                                    $bank = Database::search("SELECT `acc_no` FROM `bank`");
                                                                                    if ($bank->num_rows > 0) $bankAccNo = $bank->fetch_assoc()['acc_no'];
                                                                                } else {
                                                                                    $bank = Database::search("SELECT `acc_no` FROM `bank` WHERE `bank_id`='" . $coursepaymentData['bank_bank_id'] . "'");
                                                                                    if ($bank->num_rows > 0) $bankAccNo = $bank->fetch_assoc()['acc_no'];
                                                                                }
                                                                                $fileUrl  = file_exists("./assets/uploads/payment-slips/" . $paymentSlip['slip_name'])
                                                                                    ? Project::getBaseUrl()       . "/assets/uploads/payment-slips/" . $paymentSlip['slip_name']
                                                                                    : Project::getBasePortalUrl() . "/assets/uploads/payment-slips/" . $paymentSlip['slip_name'];
                                                                                $fileData = $slipVerifier->verifySlip($paymentData['refference'], $bankAccNo, $coursepaymentData['paid_amount'], $fileUrl);
                                                                                if ($fileType != 'unsupported'):
                                                                            ?>
                                                                                    <div class="accordion-item mt-4">
                                                                                        <h2 class="accordion-header bl-solid">
                                                                                            <button class="accordion-button collapsed d-flex align-items-center" type="button"
                                                                                                data-bs-toggle="collapse"
                                                                                                data-bs-target="#flush-collapse-<?= $paymentSlip['course_payment_slip_id'] ?>"
                                                                                                aria-expanded="false">
                                                                                                <img src="./assets/imgs/icons/<?= $fileType ?>.png" class="img-fluid pe-4" alt="Payment Slip">
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
                                                                                        <div id="flush-collapse-<?= $paymentSlip['course_payment_slip_id'] ?>" class="accordion-collapse collapse bl-solid-lrb" data-bs-parent="#accordionParent">
                                                                                            <div class="accordion-body">
                                                                                                <div class="col-12">
                                                                                                    <div class="row p-0 m-0">
                                                                                                        <hr>
                                                                                                        <div class="col-12 col-xl-6">
                                                                                                            <div class="row">
                                                                                                                <div class="col-12">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php if ($fileData['reference']): ?>
                                                                                                                            <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Reference Number</span><span class="text-12">Reference number verified and matches the transaction record.</span></div>
                                                                                                                        <?php else: ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Reference Number</span><span class="text-12">Reference number could not be matched.</span></div>
                                                                                                                        <?php endif; ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                                <div class="col-12 mt-2">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php
                                                                                                                        if ($fileData['date'] != null) {
                                                                                                                            $slipDate = date('Y-m-d', strtotime($fileData['date']));
                                                                                                                            $paidDate = date('Y-m-d', strtotime($coursepaymentData['date_paid']));
                                                                                                                            if ($slipDate == $paidDate): ?>
                                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date successfully matched.</span></div>
                                                                                                                            <?php else: ?>
                                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date does not match system records.</span></div>
                                                                                                                            <?php endif; ?>
                                                                                                                        <?php } else { ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date missing or does not match.</span></div>
                                                                                                                        <?php } ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                                <div class="col-12">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php if ($fileData['amount'] != null): ?>
                                                                                                                            <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Payment Amount</span><span class="text-12"><?= $coursepaymentData['paid_amount'] ?> — amount confirmed.</span></div>
                                                                                                                        <?php else: ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Amount</span><span class="text-12">Mismatch detected in payment amount.</span></div>
                                                                                                                        <?php endif; ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                                <div class="col-12">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php if ($fileData['account']): ?>
                                                                                                                            <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Account Number</span><span class="text-12">Account number correct.</span></div>
                                                                                                                        <?php else: ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Account Number</span><span class="text-12">Incorrect or unrecognized account number.</span></div>
                                                                                                                        <?php endif; ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                        <div class="col-12 col-xl-6 mt-3 mt-xl-0">
                                                                                                            <div class="row justify-content-end align-items-center">
                                                                                                                <?php if ($fileData['verified']): ?>
                                                                                                                    <div class="alert alert-success br-0" role="alert">✅ This payment slip has been successfully verified and is valid.</div>
                                                                                                                <?php else: ?>
                                                                                                                    <div class="alert alert-warning br-0" role="alert">⚠️ This payment slip has not been verified yet. Please review carefully before approval.</div>
                                                                                                                <?php endif; ?>
                                                                                                                <button class="custom-btn col-4" type="button" onclick="window.open('<?= $fileUrl ?>', '_blank');">View</button>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                            <?php endif;
                                                                            } ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- ── Payment logs ──────────────────────────────────────── -->
                                                            <div class="col-12 mt-4">
                                                                <div class="row p-0 m-0 g-0">
                                                                    <div class="col-12 mb-4">
                                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                    <?php
                                                                                    $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `payment_logs` WHERE `course_payments_course_payments_id`='" . $paymentData['course_payments_id'] . "'");
                                                                                    $logsData   = $logs_count->fetch_assoc();
                                                                                    ?>
                                                                                    <span class="text-14 dark-theme fw-medium"><?= $logsData['count'] ?> Logs</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <div class="table-responsive-wrapper">
                                                                            <div class="table-responsive" id="users-table-container">
                                                                                <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                                    <thead>
                                                                                        <tr class="br-t-5">
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                                <div class="d-flex flex-row align-items-center">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                    <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                                </div>
                                                                                            </th>
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody id="user-table-data">
                                                                                        <?php
                                                                                        $users = Database::search("
                                                                    SELECT * FROM `payment_logs`
                                                                    INNER JOIN `users` ON `payment_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    WHERE `course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "'
                                                                    ORDER BY `date_updated` DESC
                                                                ");
                                                                                        if ($users->num_rows > 0) {
                                                                                            while ($data = $users->fetch_assoc()):
                                                                                        ?>
                                                                                                <tr>
                                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check"
                                                                                                                id="check-user<?= $data["user_id"] ?>"
                                                                                                                onchange="checkRowEvents(<?= $data['user_id'] ?>);">
                                                                                                            <?php if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])): ?>
                                                                                                                <img src="./assets/imgs/users/<?= $data["profile_img"] ?>" class="hwr-40" alt="User image">
                                                                                                            <?php else:
                                                                                                                $fni = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                                $lni = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                            ?>
                                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $fni . $lni ?></div>
                                                                                                            <?php endif; ?>
                                                                                                            <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"] ?></span>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_updated"] ?></span></td>
                                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"] ?></span></td>
                                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"] ?></span></td>
                                                                                                </tr>
                                                                                            <?php endwhile;
                                                                                        } else { ?>
                                                                                            <tr>
                                                                                                <td colspan="13" class="align-middle justify-content-center">
                                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                    </div>
                                                                                                </td>
                                                                                            </tr>
                                                                                        <?php } ?>
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
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ════════════════════════════════════════════════════════════════════════
         UPDATE PAYMENT STATUS MODAL
    ════════════════════════════════════════════════════════════════════════════ -->
                            <div class="modal fade" id="updatePaymentStatus" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                                    <div class="modal-content component-theme">
                                        <div class="modal-header" style="border-bottom:1px solid var(--line-color);">
                                            <div class="d-flex align-items-center gap-3 flex-wrap w-100">
                                                <span class="text-16 main-text fw-semibold flex-grow-1">Update Payment Status</span>

                                                <?php if (!$isBaseCurrencyLKR): ?>
                                                    <span class="text-14 main-text fw-medium">Payment Currency:</span>
                                                    <div class="currency-toggle">
                                                        <button type="button" class="currency-toggle-btn active" id="modal-btn-base-currency" onclick="modalSwitchCurrency('base')"><?= $currencyCode ?></button>
                                                        <button type="button" class="currency-toggle-btn" id="modal-btn-lkr-currency" onclick="modalSwitchCurrency('lkr')">LKR</button>
                                                    </div>
                                                    <?php if ($exchangeRate): ?>
                                                        <div class="exchange-rate-badge" id="modal-rate-badge">
                                                            <iconify-icon icon="mdi:swap-horizontal" width="14" height="14"></iconify-icon>
                                                            <span id="modal-live-rate-text">1 <?= $currencyCode ?> = LKR <?= number_format($exchangeRateValue, 2) ?> (<?= date('d M Y', strtotime($exchangeRate['effective_date'])) ?>)</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="exchange-rate-badge rate-unavailable" id="modal-rate-badge">
                                                            <iconify-icon icon="mdi:alert-outline" width="14" height="14"></iconify-icon>
                                                            <span id="modal-live-rate-text">No exchange rate available</span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="row gx-4 gy-3">

                                                <!-- LKR Summary Panel (shown when LKR mode active) -->
                                                <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?>
                                                    <div class="col-12" id="modal-lkr-summary-panel" style="display:none;">
                                                        <div class="lkr-panel">
                                                            <div class="lkr-panel-top">
                                                                <div class="lkr-panel-top-left"><iconify-icon icon="mdi:currency-usd" width="16" height="16"></iconify-icon> LKR equivalent summary</div>
                                                                <span class="lkr-panel-rate-note">@ <span id="modal-panel-rate-display"><?= number_format($exchangeRateValue, 2) ?></span> per <?= $currencyCode ?></span>
                                                            </div>
                                                            <div class="lkr-panel-grid">
                                                                <div class="lkr-panel-item"><span class="lkr-item-label">Total Amount</span><span class="lkr-item-value">LKR <?= number_format($paymentData['amount'] * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($paymentData['amount'], 2) ?></span></div>
                                                                <div class="lkr-panel-item"><span class="lkr-item-label">Total Discount</span><span class="lkr-item-value" id="modal-lkr-discount-val">LKR <?= number_format($totalDiscount * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($totalDiscount, 2) ?></span></div>
                                                                <div class="lkr-panel-item"><span class="lkr-item-label">Total Penalty</span><span class="lkr-item-value" id="modal-lkr-penalty-val">LKR <?= number_format($totalPenalty * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($totalPenalty, 2) ?></span></div>
                                                                <div class="lkr-panel-item"><span class="lkr-item-label">Tax Amount</span><span class="lkr-item-value" id="modal-lkr-tax-val">LKR <?= number_format($taxOnPayable * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub" id="modal-lkr-tax-sub"><?= $currencyCode ?> <?= number_format($taxOnPayable, 2) ?></span></div>
                                                                <div class="lkr-panel-item"><span class="lkr-item-label">Total Payable</span><span class="lkr-item-value">LKR <?= number_format($payableAmount * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($payableAmount, 2) ?></span></div>
                                                                <div class="lkr-panel-item"><span class="lkr-item-label">Total Paid</span><span class="lkr-item-value">LKR <?= number_format($paidAmount * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub"><?= $currencyCode ?> <?= number_format($paidAmount, 2) ?></span></div>
                                                                <div class="lkr-panel-item balance-item"><span class="lkr-item-label">Balance Due</span><span class="lkr-item-value" id="modal-lkr-balance-val">LKR <?= number_format($balance * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub" id="modal-lkr-balance-sub"><?= $currencyCode ?> <?= number_format($balance, 2) ?></span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Row 1: Status | Method | Paid Date -->
                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text required">Payment Status</label>
                                                    <select class="form-select custom-select mt-2" id="status-selector">
                                                        <?php
                                                        $statusQ = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` IN (3,4)");
                                                        while ($sd = $statusQ->fetch_assoc()): ?>
                                                            <option value="<?= $sd['payment_status_id'] ?>" <?= $sd['payment_status_id'] == $coursepaymentData['payment_status_payment_status_id'] ? 'selected' : '' ?>><?= $sd['payment_status'] ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                    <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">Payment Method</label>
                                                    <select class="form-select custom-select mt-2" id="payment-method"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                        <option value="0">- Select -</option>
                                                        <?php
                                                        $methodsQ = Database::search("SELECT * FROM `payment_method` WHERE `payment_method_id` != 5");
                                                        while ($method = $methodsQ->fetch_assoc()): ?>
                                                            <option value="<?= $method['payment_method_id'] ?>" <?= $method['payment_method_id'] == $coursepaymentData['payment_method_payment_method_id'] ? 'selected' : '' ?>><?= $method['payment_method'] ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                    <div class="text-12 text-danger" id="payment-method-feedback"></div>
                                                </div>

                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">Paid Date</label>
                                                    <input type="date" class="form-control custom-input mt-2" id="modal-paid-date"
                                                        value="<?= substr($coursepaymentData['date_paid'], 0, 10) ?>"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                    <div class="text-12 text-danger" id="paid-date-feedback"></div>
                                                    <?php if (!$isBaseCurrencyLKR): ?>
                                                        <span class="text-12 sub-text mt-1" id="modal-rate-status">Rate loaded</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Row 2: Discount | Penalty | Tax (auto-calculated) -->
                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Discount Given
                                                        <span class="currency-label-badge" id="modal-discount-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="number" class="form-control custom-input mt-2" id="modal-discount-amount"
                                                        value="<?= $coursepaymentData['discount_amount'] ?? 0 ?>"
                                                        step="0.01" min="0"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                    <div class="text-12 text-danger" id="modal-discount-feedback"></div>
                                                    <span class="convert-hint" id="modal-discount-lkr-hint">≈ LKR <?= number_format(($coursepaymentData['discount_amount'] ?? 0) * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-discount-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Penalty Added
                                                        <span class="currency-label-badge" id="modal-penalty-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="number" class="form-control custom-input mt-2" id="modal-penalty-amount"
                                                        value="<?= $coursepaymentData['panelty_amount'] ?? 0 ?>"
                                                        step="0.01" min="0"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                    <div class="text-12 text-danger" id="modal-penalty-feedback"></div>
                                                    <span class="convert-hint" id="modal-penalty-lkr-hint">≈ LKR <?= number_format(($coursepaymentData['panelty_amount'] ?? 0) * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-penalty-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <!-- ════════════════════════════════════════════════
                             TAX — READ-ONLY, auto-calculated from paid amount
                             Formula: tax = paid_amount × taxRate%
                        ════════════════════════════════════════════════ -->
                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Tax Amount
                                                        <span class="currency-label-badge tax-badge" id="modal-tax-rate-badge"><?= number_format($latestTaxRate, 2) ?>%</span>
                                                        <span class="currency-label-badge" id="modal-tax-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="text" class="form-control custom-input tax-readonly mt-2" id="modal-tax-amount-display"
                                                        value="<?= number_format($receiptTaxAmount, 2) ?>"
                                                        readonly tabindex="-1">
                                                    <!-- Formula hint: paid × rate% = tax -->
                                                    <span class="convert-hint mt-1" style="color:#d97706;" id="modal-tax-formula-hint">
                                                        <?= $currencyCode ?> <?= number_format($receiptPaidAmt, 2) ?> × <?= number_format($latestTaxRate, 2) ?>% = <?= $currencyCode ?> <?= number_format($receiptTaxAmount, 2) ?>
                                                    </span>
                                                    <span class="convert-hint" id="modal-tax-lkr-hint">≈ LKR <?= number_format($receiptTaxAmount * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-tax-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <!-- Row 3: Paid Amount | Base Payable | Custom Rate -->
                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Total Paid Amount (incl. tax)
                                                        <span class="currency-label-badge" id="modal-paid-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="number" class="form-control custom-input mt-2" id="modal-paid-amount"
                                                        value="<?= $coursepaymentData['paid_amount'] ?>"
                                                        step="0.01" min="0"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                    <div class="text-12 text-danger" id="paid-amount-feedback"></div>
                                                    <span class="convert-hint" id="modal-paid-lkr-hint">≈ LKR <?= number_format($coursepaymentData['paid_amount'] * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-paid-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                    <!-- Auto hint: shows base + tax suggestion based on payable -->
                                                    <span class="convert-hint mt-1" style="color:#0ea5e9;" id="modal-paid-auto-hint">
                                                        Suggested: <?= $currencyCode ?> <?= number_format($payableAmount, 2) ?>
                                                        (<?= number_format($basePayable, 2) ?> + <?= number_format($taxOnPayable, 2) ?> tax)
                                                    </span>
                                                </div>

                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Base Payable (Course Fee)
                                                        <span class="currency-label-badge" id="modal-base-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="text" class="form-control custom-input mt-2" id="modal-base-payable-display"
                                                        value="<?= number_format($basePayable, 2) ?>" disabled>
                                                    <span class="convert-hint mt-1" id="modal-base-formula-hint">
                                                        paid − (paid × <?= number_format($latestTaxRate, 2) ?>%) = course fee
                                                    </span>
                                                </div>

                                                <?php if (!$isBaseCurrencyLKR): ?>
                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                        <label class="text-14 main-text d-flex align-items-center gap-2">
                                                            Custom Exchange Rate
                                                            <span class="currency-label-badge">1 <?= $currencyCode ?> = ? LKR</span>
                                                        </label>
                                                        <input type="number" class="form-control custom-input mt-2" id="modal-custom-rate"
                                                            value="<?= number_format($exchangeRateValue, 2, '.', '') ?>"
                                                            step="0.0001" min="0.0001">
                                                        <span class="text-12 sub-text mt-1">Override auto-fetched rate if needed</span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="col-12 col-lg-4"></div>
                                                <?php endif; ?>

                                                <!-- Balance -->
                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Calculated Balance
                                                        <span class="currency-label-badge" id="modal-balance-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="text" class="form-control custom-input mt-2" id="modal-balance-display"
                                                        value="<?= number_format($balance, 2) ?>" disabled>
                                                    <span class="convert-hint" id="modal-balance-lkr-hint">≈ LKR <?= number_format($balance * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-balance-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <!-- ════════════════════════════════════════════════
                             NEXT INSTALLMENT OVERPAYMENT PANEL (modal)
                             Appears dynamically when paid > payable
                        ════════════════════════════════════════════════ -->
                                                <div class="col-12" id="modal-overpay-panel" style="display:none;">
                                                    <div class="overpay-panel">
                                                        <div class="overpay-panel-title">
                                                            <iconify-icon icon="mdi:arrow-right-circle-outline" width="16" height="16"></iconify-icon>
                                                            Overpayment — Will Be Applied to Next Installment
                                                        </div>
                                                        <div class="overpay-grid">
                                                            <div class="overpay-item">
                                                                <span class="overpay-label">Current Installment Payable</span>
                                                                <span class="overpay-value" id="modal-overpay-current">—</span>
                                                                <span class="overpay-sub">Will be fully settled</span>
                                                            </div>
                                                            <div class="overpay-item">
                                                                <span class="overpay-label">Overpayment Amount</span>
                                                                <span class="overpay-value" id="modal-overpay-amount">—</span>
                                                                <span class="overpay-sub">Credited to next installment</span>
                                                            </div>
                                                            <div class="overpay-item">
                                                                <span class="overpay-label">Next Installment Breakdown</span>
                                                                <span class="overpay-value" id="modal-overpay-next-breakdown" style="font-size:13px;">—</span>
                                                                <span class="overpay-sub" id="modal-overpay-next-total">—</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Note -->
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text">Note</label>
                                                    <textarea class="form-control custom-input mt-2" id="note" rows="4"></textarea>
                                                    <span id="note-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="modal-footer" style="border-top:1px solid var(--line-color);">
                                            <button type="button" class="col-12 col-md-4 custom-btn"
                                                onclick="submitUpdatePaymentStatus(this, <?= $coursepaymentData['course_payment_has_payment_id'] ?>)">
                                                Update Payment Status
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Receipt Modal ─────────────────────────────────────────────────── -->
                            <?php if ($coursepaymentData['payment_status_payment_status_id'] == 3): ?>
                                <div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-centered">
                                        <div class="modal-content component-theme">
                                            <div class="modal-header" style="border-bottom:1px solid var(--line-color);flex-shrink:0;">
                                                <span class="text-16 main-text fw-semibold flex-grow-1">
                                                    <iconify-icon icon="mdi:receipt-text-outline" width="18" style="vertical-align:-3px;margin-right:6px;"></iconify-icon>
                                                    Payment Receipt
                                                </span>
                                                <div class="d-flex gap-2 me-3">
                                                    <button class="table-btn table-btn-primary py-2 px-3" onclick="downloadReceipt()">
                                                        <iconify-icon icon="mdi:download" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>Download PDF
                                                    </button>
                                                    <button class="table-btn table-btn-success py-2 px-3" onclick="sendReceiptEmail()" id="send-invoice-btn">
                                                        <iconify-icon icon="mdi:email-send-outline" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>Send Invoice
                                                    </button>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-0" id="receipt-modal-body" style="overflow:hidden;max-height:85vh;">
                                                <div style="text-align:center;padding:60px 20px;">
                                                    <div style="width:32px;height:32px;border:3px solid #e2e8f0;border-top-color:#1e3a5f;border-radius:50%;animation:rcptSpin .7s linear infinite;margin:0 auto 14px;"></div>
                                                    <span style="font-size:12px;color:#94a3b8;">Loading receipt…</span>
                                                </div>
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
                                /* ════════════════════════════════════════════════════════════════════════
       PHP-INJECTED CONSTANTS
    ════════════════════════════════════════════════════════════════════════ */
                                var COURSE_AMOUNT = <?= (float)$paymentData['amount'] ?>;
                                var FIXED_TAX_RATE = <?= (float)$latestTaxRate ?>; // latest from DB
                                var EXISTING_TOTAL_PAID = <?= (float)$paidAmount ?>; // already-paid total
                                var EXISTING_TOTAL_DISC = <?= (float)$totalDiscount ?>;
                                var EXISTING_TOTAL_PEN = <?= (float)$totalPenalty ?>;
                                var currencyCode = "<?= $currencyCode ?>";
                                var isBaseCurrencyLKR = <?= $isBaseCurrencyLKR ? 'true' : 'false' ?>;
                                var baseCurrencyId = <?= (int)$paymentData['currency_id'] ?>;
                                var lkrCurrencyId = 1;
                                var modalExchangeRateValue = <?= (float)$exchangeRateValue ?>;
                                var modalExchangeRate = <?= $isBaseCurrencyLKR ? 'null' : (float)$exchangeRateValue ?>;
                                var modalExchangeRateId = <?= $exchangeRateId ? (int)$exchangeRateId : 'null' ?>;
                                var modalCurrentMode = 'base';

                                var mGet = function(id) {
                                    return document.getElementById(id);
                                };

                                /* ════════════════════════════════════════════════════════════════════════
                                   TAX MODEL (matches backend & add-receipt page):
                                     tax  = paid_amount × taxRate%
                                     base = paid_amount − tax  = paid_amount × (1 − taxRate%)

                                   Example (taxRate = 2.5%):
                                     paid = 10,250  →  base = 10,000  →  tax = 250
                                     (Correct: base = paid ÷ (1 + rate%), tax = paid - base)
                                ════════════════════════════════════════════════════════════════════════ */
                                function calcTaxFromPaid(paid, rate) {
                                    if (rate <= 0 || paid <= 0) return {
                                        base: paid,
                                        tax: 0
                                    };
                                    var factor = 1 + (rate / 100);
                                    var base = paid / factor;
                                    var tax = paid - base;
                                    return {
                                        base: base,
                                        tax: tax
                                    };
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   MAIN MODAL RECALCULATE
                                   Called whenever: paid amount, discount, penalty, exchange rate changes
                                ════════════════════════════════════════════════════════════════════════ */
                                function modalRecalculate() {
                                    var rate = modalExchangeRate || modalExchangeRateValue || 1;

                                    function toBase(v) {
                                        return (!isBaseCurrencyLKR && modalCurrentMode === 'lkr' && rate) ? v / rate : v;
                                    }

                                    function toDisplay(v) {
                                        return (!isBaseCurrencyLKR && modalCurrentMode === 'lkr' && rate) ? v * rate : v;
                                    }

                                    var rawDisc = Math.max(0, parseFloat((mGet('modal-discount-amount') || {}).value) || 0);
                                    var rawPen = Math.max(0, parseFloat((mGet('modal-penalty-amount') || {}).value) || 0);
                                    var rawPaid = Math.max(0, parseFloat((mGet('modal-paid-amount') || {}).value) || 0);

                                    var discBase = toBase(rawDisc);
                                    var penBase = toBase(rawPen);
                                    var paidBase = toBase(rawPaid);

                                    // ── Payable for THIS installment ────────────────────────────────────
                                    // basePayable = course_amount - discount + penalty  (no tax yet)
                                    // tax         = basePayable × rate%
                                    // totalPayable = basePayable + tax
                                    var basePayable = Math.max(0, COURSE_AMOUNT - discBase + penBase);
                                    var taxOnPayable = basePayable * (FIXED_TAX_RATE / 100);
                                    var totalPayable = basePayable + taxOnPayable;

                                    // ── Tax extracted from the entered paid amount ──────────────────────
                                    // tax_from_paid = paid × rate%   (same model as above)
                                    var calcFromPaid = calcTaxFromPaid(paidBase, FIXED_TAX_RATE);
                                    var taxFromPaid = calcFromPaid.tax;
                                    var baseFromPaid = calcFromPaid.base;

                                    // ── Balance ─────────────────────────────────────────────────────────
                                    var balBase = totalPayable - paidBase; // can be negative (overpayment)

                                    // ── Update displays ─────────────────────────────────────────────────
                                    var taxDisplay = toDisplay(taxFromPaid);
                                    var baseDisplay = toDisplay(baseFromPaid);
                                    var balDisplay = toDisplay(balBase);
                                    var label = modalCurrentMode === 'lkr' ? 'LKR' : currencyCode;

                                    var taxEl = mGet('modal-tax-amount-display');
                                    if (taxEl) taxEl.value = taxDisplay.toFixed(2);

                                    var baseEl = mGet('modal-base-payable-display');
                                    if (baseEl) baseEl.value = baseDisplay.toFixed(2);

                                    // Tax formula hint
                                    setText('modal-tax-formula-hint',
                                        label + ' ' + fmt(toDisplay(paidBase)) + ' × ' + FIXED_TAX_RATE.toFixed(2) + '% = ' + label + ' ' + fmt(taxDisplay));

                                    // Auto hint (suggested payable)
                                    var totalPayableDisplay = toDisplay(totalPayable);
                                    var basePayableDisplay = toDisplay(basePayable);
                                    var taxPayableDisplay = toDisplay(taxOnPayable);
                                    setText('modal-paid-auto-hint',
                                        'Suggested: ' + label + ' ' + fmt(totalPayableDisplay) +
                                        ' (' + fmt(basePayableDisplay) + ' course + ' + fmt(taxPayableDisplay) + ' tax)');

                                    // Balance
                                    var balEl = mGet('modal-balance-display');
                                    if (balEl) balEl.value = Math.max(0, balDisplay).toFixed(2);

                                    // ── Overpayment detection ────────────────────────────────────────────
                                    if (paidBase > totalPayable + 0.001 && rawPaid > 0) {
                                        var overpayBase = paidBase - totalPayable;
                                        var overpayDisp = toDisplay(overpayBase);
                                        var nextCalc = calcTaxFromPaid(overpayBase, FIXED_TAX_RATE);
                                        var nextTaxDisp = toDisplay(nextCalc.tax);
                                        var nextBaseDisp = toDisplay(nextCalc.base);
                                        var nextTotalDisp = toDisplay(overpayBase);

                                        showModalOverpayPanel(
                                            toDisplay(totalPayable),
                                            overpayDisp,
                                            nextBaseDisp,
                                            nextTaxDisp,
                                            nextTotalDisp,
                                            label
                                        );
                                    } else {
                                        hideModalOverpayPanel();
                                    }

                                    // ── LKR hints ───────────────────────────────────────────────────────
                                    if (!isBaseCurrencyLKR && rate) {
                                        if (modalCurrentMode === 'lkr') {
                                            setHint('modal-discount-base-hint', currencyCode, discBase, false);
                                            setHint('modal-penalty-base-hint', currencyCode, penBase, false);
                                            setHint('modal-tax-base-hint', currencyCode, taxFromPaid, false);
                                            setHint('modal-balance-base-hint', currencyCode, Math.max(0, balBase), false);
                                            setHint('modal-paid-base-hint', currencyCode, paidBase, false);
                                            setHint('modal-discount-lkr-hint', 'LKR', rawDisc);
                                            setHint('modal-penalty-lkr-hint', 'LKR', rawPen);
                                            setHint('modal-tax-lkr-hint', 'LKR', taxDisplay);
                                            setHint('modal-balance-lkr-hint', 'LKR', Math.max(0, balDisplay));
                                            setHint('modal-paid-lkr-hint', 'LKR', rawPaid);
                                        } else {
                                            setHint('modal-discount-lkr-hint', 'LKR', rawDisc * rate);
                                            setHint('modal-penalty-lkr-hint', 'LKR', rawPen * rate);
                                            setHint('modal-tax-lkr-hint', 'LKR', taxFromPaid * rate);
                                            setHint('modal-balance-lkr-hint', 'LKR', Math.max(0, balBase) * rate);
                                            setHint('modal-paid-lkr-hint', 'LKR', rawPaid * rate);
                                        }

                                        // LKR summary panel updates
                                        setText('modal-lkr-balance-val', 'LKR ' + fmt(Math.max(0, balBase) * rate));
                                        setText('modal-lkr-balance-sub', currencyCode + ' ' + fmt(Math.max(0, balBase)));
                                        setText('modal-lkr-discount-val', 'LKR ' + fmt(discBase * rate));
                                        setText('modal-lkr-penalty-val', 'LKR ' + fmt(penBase * rate));
                                        setText('modal-lkr-tax-val', 'LKR ' + fmt(taxFromPaid * rate));
                                        setText('modal-lkr-tax-sub', currencyCode + ' ' + fmt(taxFromPaid));
                                    } else {
                                        // LKR course — clear LKR hints
                                        ['modal-discount-lkr-hint', 'modal-penalty-lkr-hint', 'modal-tax-lkr-hint',
                                            'modal-balance-lkr-hint', 'modal-paid-lkr-hint'
                                        ].forEach(function(id) {
                                            var el = mGet(id);
                                            if (el) el.textContent = '';
                                        });
                                    }
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   OVERPAYMENT PANEL HELPERS
                                ════════════════════════════════════════════════════════════════════════ */
                                function showModalOverpayPanel(currentPayable, overpay, nextBase, nextTax, nextTotal, label) {
                                    var panel = mGet('modal-overpay-panel');
                                    if (!panel) return;
                                    panel.style.display = 'block';
                                    setText('modal-overpay-current', label + ' ' + fmt(currentPayable));
                                    setText('modal-overpay-amount', label + ' ' + fmt(overpay));
                                    setText('modal-overpay-next-breakdown',
                                        label + ' ' + fmt(nextBase) + ' course fee + ' + label + ' ' + fmt(nextTax) +
                                        ' tax (' + FIXED_TAX_RATE.toFixed(2) + '%)');
                                    setText('modal-overpay-next-total', 'Total: ' + label + ' ' + fmt(nextTotal));
                                }

                                function hideModalOverpayPanel() {
                                    var panel = mGet('modal-overpay-panel');
                                    if (panel) panel.style.display = 'none';
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   CURRENCY SWITCH
                                ════════════════════════════════════════════════════════════════════════ */
                                function modalSwitchCurrency(mode) {
                                    if (isBaseCurrencyLKR) return;
                                    var rate = modalExchangeRate || modalExchangeRateValue || 1;
                                    modalCurrentMode = mode;

                                    var btnBase = mGet('modal-btn-base-currency');
                                    var btnLkr = mGet('modal-btn-lkr-currency');
                                    if (btnBase) btnBase.classList.toggle('active', mode === 'base');
                                    if (btnLkr) btnLkr.classList.toggle('active', mode === 'lkr');

                                    var panel = mGet('modal-lkr-summary-panel');
                                    if (panel) panel.style.display = mode === 'lkr' ? 'block' : 'none';

                                    var label = mode === 'lkr' ? 'LKR' : currencyCode;
                                    ['modal-discount-currency-label', 'modal-penalty-currency-label',
                                        'modal-balance-currency-label', 'modal-paid-currency-label',
                                        'modal-base-currency-label', 'modal-tax-currency-label'
                                    ].forEach(function(id) {
                                        var el = mGet(id);
                                        if (el && !el.classList.contains('tax-badge')) el.textContent = label;
                                    });

                                    document.querySelectorAll('[id$="-lkr-hint"]').forEach(function(el) {
                                        if (el.id.startsWith('modal-')) el.classList.toggle('d-none', mode === 'lkr');
                                    });
                                    document.querySelectorAll('[id$="-base-hint"]').forEach(function(el) {
                                        if (el.id.startsWith('modal-')) el.classList.toggle('d-none', mode === 'base');
                                    });

                                    // Convert editable inputs between base↔LKR
                                    ['modal-discount-amount', 'modal-penalty-amount', 'modal-paid-amount'].forEach(function(id) {
                                        var el = mGet(id);
                                        if (!el || el.disabled) return;
                                        var v = parseFloat(el.value) || 0;
                                        if (!v) return;
                                        el.value = mode === 'lkr' ? (v * rate).toFixed(2) : (v / rate).toFixed(2);
                                    });

                                    modalRecalculate();
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   PAID DATE CHANGE → fetch new exchange rate
                                ════════════════════════════════════════════════════════════════════════ */
                                function modalOnPaidDateChange() {
                                    if (isBaseCurrencyLKR) return;
                                    var dateEl = mGet('modal-paid-date');
                                    if (!dateEl || !dateEl.value) return;
                                    setModalRateStatus('loading', 'Fetching exchange rate for selected date…');

                                    fetch('api/getExchangeRate.php?base=' + baseCurrencyId + '&target=' + lkrCurrencyId + '&date=' + dateEl.value)
                                        .then(function(r) {
                                            return r.json();
                                        })
                                        .then(function(data) {
                                            if (data.rate) {
                                                modalExchangeRate = parseFloat(data.rate);
                                                modalExchangeRateId = data.currency_exchange_rate_id;
                                                var cr = mGet('modal-custom-rate');
                                                if (cr) cr.value = modalExchangeRate.toFixed(2);
                                                setText('modal-live-rate-text',
                                                    '1 ' + currencyCode + ' = LKR ' + modalExchangeRate.toFixed(2) + ' (' + data.effective_date + ')');
                                                var pr = mGet('modal-panel-rate-display');
                                                if (pr) pr.textContent = modalExchangeRate.toFixed(2);
                                                setModalRateStatus('success', 'Rate updated: 1 ' + currencyCode + ' = LKR ' + modalExchangeRate.toFixed(2));
                                            } else {
                                                setModalRateStatus('warning', 'No rate found. Using last known (' + (modalExchangeRate || modalExchangeRateValue).toFixed(2) + ').');
                                            }
                                            modalRecalculate();
                                        })
                                        .catch(function() {
                                            setModalRateStatus('error', 'Could not fetch rate.');
                                            modalRecalculate();
                                        });
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   CUSTOM RATE OVERRIDE
                                ════════════════════════════════════════════════════════════════════════ */
                                function modalOnCustomRateChange() {
                                    var el = mGet('modal-custom-rate');
                                    var val = el ? parseFloat(el.value) : NaN;
                                    if (!isNaN(val) && val > 0) {
                                        modalExchangeRate = val;
                                        modalExchangeRateId = null;
                                        var pr = mGet('modal-panel-rate-display');
                                        if (pr) pr.textContent = val.toFixed(2);
                                        setText('modal-live-rate-text', '1 ' + currencyCode + ' = LKR ' + val.toFixed(2) + ' (custom)');
                                        setModalRateStatus('info', 'Custom rate applied: 1 ' + currencyCode + ' = LKR ' + val.toFixed(2));
                                        modalRecalculate();
                                    }
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   HELPERS
                                ════════════════════════════════════════════════════════════════════════ */
                                function setHint(id, label, value, isApprox) {
                                    if (isApprox === undefined) isApprox = true;
                                    var el = mGet(id);
                                    if (el) el.textContent = (isApprox ? '≈ ' : '= ') + label + ' ' + fmt(value);
                                }

                                function setText(id, text) {
                                    var el = mGet(id);
                                    if (el) el.textContent = text;
                                }

                                function fmt(n) {
                                    return parseFloat(n || 0).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }

                                function setModalRateStatus(type, msg) {
                                    var el = mGet('modal-rate-status');
                                    if (!el) return;
                                    var cls = {
                                        loading: 'sub-text',
                                        success: 'text-dark-green',
                                        warning: 'text-warning',
                                        error: 'text-dark-red',
                                        info: 'sub-text'
                                    };
                                    el.className = 'text-12 mt-1 ' + (cls[type] || 'sub-text');
                                    el.textContent = msg;
                                }

                                /* ── Wire events on DOM ready ─────────────────────────────────────────── */
                                document.addEventListener('DOMContentLoaded', function() {

                                    // Progress bar animation
                                    var progressBar = document.querySelector('.progress-bar');
                                    if (progressBar) setTimeout(function() {
                                        progressBar.style.width = '<?= $progress ?>%';
                                    }, 300);

                                    document.querySelectorAll('.timeline-bubble').forEach(function(bubble) {
                                        bubble.addEventListener('mouseenter', function() {
                                            this.style.transform = 'scale(1.15)';
                                        });
                                        bubble.addEventListener('mouseleave', function() {
                                            this.style.transform = 'scale(1)';
                                        });
                                    });

                                    // Discount & penalty → recalculate
                                    ['modal-discount-amount', 'modal-penalty-amount'].forEach(function(id) {
                                        var el = mGet(id);
                                        if (el) el.addEventListener('input', modalRecalculate);
                                    });

                                    // Paid amount → recalculate tax & overpay
                                    var paidEl = mGet('modal-paid-amount');
                                    if (paidEl) paidEl.addEventListener('input', modalRecalculate);

                                    // Date & custom rate
                                    var paidDateEl = mGet('modal-paid-date');
                                    var customRateEl = mGet('modal-custom-rate');
                                    if (paidDateEl) paidDateEl.addEventListener('change', modalOnPaidDateChange);
                                    if (customRateEl) customRateEl.addEventListener('input', modalOnCustomRateChange);

                                    // Recalculate when modal opens
                                    var modal = document.getElementById('updatePaymentStatus');
                                    if (modal) modal.addEventListener('shown.bs.modal', function() {
                                        modalRecalculate();
                                    });
                                });
                            </script>

                            <!-- Receipt renderer script (unchanged from original) -->
                            <script>
                                var mGet = function(id) {
                                    return document.getElementById(id);
                                };

                                function fmt(n, dp) {
                                    dp = (dp === undefined) ? 2 : dp;
                                    return parseFloat(n || 0).toLocaleString('en-US', {
                                        minimumFractionDigits: dp,
                                        maximumFractionDigits: dp
                                    });
                                }

                                function esc(s) {
                                    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                                }
                                var currentReceiptId = null;

                                function openReceiptModal(receiptId) {
                                    currentReceiptId = receiptId;
                                    var body = mGet('receipt-modal-body');
                                    if (body) {
                                        body.style.overflow = 'hidden';
                                        body.innerHTML = '<div style="text-align:center;padding:80px 20px;"><div style="width:32px;height:32px;border:3px solid #e2e8f0;border-top-color:#1e3a5f;border-radius:50%;animation:rcptSpin .7s linear infinite;margin:0 auto 14px;"></div><span style="font-size:12px;color:#94a3b8;">Loading receipt…</span></div><style>@keyframes rcptSpin{to{transform:rotate(360deg);}}</style>';
                                    }
                                    var modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
                                    modal.show();
                                    fetch('api/getReceiptData.php?receipt_id=' + receiptId)
                                        .then(function(r) {
                                            return r.json();
                                        })
                                        .then(function(data) {
                                            if (data.error) {
                                                if (body) body.innerHTML = '<div style="text-align:center;padding:60px;color:#ef4444;font-size:12px;">' + esc(data.error) + '</div>';
                                                return;
                                            }
                                            renderReceiptHTML(data);
                                        })
                                        .catch(function() {
                                            if (body) body.innerHTML = '<div style="text-align:center;padding:60px;color:#ef4444;font-size:12px;">Failed to load receipt.</div>';
                                        });
                                }

                                function downloadReceipt() {
                                    var printArea = mGet('receipt-print-area');
                                    if (!printArea) return;
                                    var win = window.open('', '_blank');
                                    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Receipt</title><style>*{box-sizing:border-box;margin:0;padding:0;}body{font-family:"Segoe UI",system-ui,sans-serif;font-size:11px;background:#fff;color:#111827;}@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}@page{size:A4 portrait;margin:8mm 10mm;}}</style></head><body>' + printArea.outerHTML + '<script>window.onload=function(){setTimeout(function(){window.print();},400);window.onafterprint=function(){window.close();};};<\/script></body></html>');
                                    win.document.close();
                                }

                                function sendReceiptEmail() {
                                    if (!currentReceiptId) return;
                                    var btn = document.querySelector('#receiptPreviewModal .table-btn-success');
                                    var origHtml = btn ? btn.innerHTML : '';
                                    if (btn) {
                                        btn.disabled = true;
                                        btn.innerHTML = '<iconify-icon icon="mdi:loading" width="13" style="vertical-align:-2px;margin-right:4px;animation:rcptSpin .7s linear infinite;display:inline-block;"></iconify-icon>Sending…';
                                    }
                                    fetch('api/sendReceiptEmail.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                receipt_id: currentReceiptId
                                            })
                                        })
                                        .then(function(r) {
                                            return r.json();
                                        })
                                        .then(function(res) {
                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            if (res.done) showToast('success', res.done);
                                            else showToast('error', res.error || 'Failed to send email.');
                                        })
                                        .catch(function() {
                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            showToast('error', 'Network error — could not send email.');
                                        });
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                    MAIN RENDERER
                                 ════════════════════════════════════════════════════════════════════════════ */
                                function renderReceiptHTML(data) {
                                    var body = mGet('receipt-modal-body');

                                    /* ── Currency setup ──────────────────────────────────────────────────── */
                                    var cc = (data.course_currency || 'LKR').trim(); // invoice currency
                                    var isLKR = (cc === 'LKR');

                                    /* ── Payment table rows ──────────────────────────────────────────────── */
                                    var payRows = '';
                                    (data.payments || []).forEach(function(p, idx) {

                                        /* Determine the currency the student paid in */
                                        var paidCurr = (p.paid_currency || cc).trim();
                                        var isForeign = (paidCurr !== cc); // paid in a different currency than invoice
                                        var exRate = parseFloat(p.exchange_rate || 0);

                                        /*
                                         * PAID column
                                         * Primary  : always show what the student actually handed over (paid_currency + paid_amount)
                                         * Sub-note : if foreign, also show the course-currency equivalent
                                         */
                                        var paidMain, paidSub = '';
                                        if (isForeign && exRate > 0) {
                                            paidMain = paidCurr + ' ' + fmt(p.paid_amount);
                                            paidSub = subLine('= ' + cc + ' ' + fmt(p.paid_in_course) +
                                                ' &nbsp;(@ 1 ' + cc + ' = ' + paidCurr + ' ' + fmt(exRate) + ')');
                                        } else {
                                            /* Same currency — just show paid_in_course (already in cc) */
                                            paidMain = cc + ' ' + fmt(p.paid_in_course);
                                        }

                                        /*
                                         * TAX column  — tax_amount is stored in paid_currency;
                                         *               tax_course is the course-currency equivalent (from PHP)
                                         */
                                        var taxRateDisp = parseFloat(p.tax_rate || 0) > 0 ? fmt(p.tax_rate) + '%' : dash();
                                        var taxMain = dash();
                                        var taxSub = '';
                                        if (parseFloat(p.tax_course || p.tax_amount || 0) > 0) {
                                            taxMain = cc + ' ' + fmt(p.tax_course || p.tax_amount);
                                            if (isForeign && parseFloat(p.tax_amount || 0) > 0) {
                                                taxSub = subLine(paidCurr + ' ' + fmt(p.tax_amount));
                                            }
                                        }

                                        /* DISCOUNT & PENALTY — stored / returned in course currency */
                                        var discMain = parseFloat(p.discount_amount || 0) > 0 ?
                                            cc + ' ' + fmt(p.discount_amount) : dash();
                                        var penMain = parseFloat(p.penalty_course || 0) > 0 ?
                                            cc + ' ' + fmt(p.penalty_course) : dash();

                                        var bg = (idx % 2 === 0) ? '#ffffff' : '#f8fafc';

                                        payRows +=
                                            '<tr style="background:' + bg + ';">' +
                                            tdN(idx + 1) +
                                            tdB(esc(p.reference || '—')) +
                                            td(esc(p.payment_type || '—')) +
                                            td(p.date_paid ? p.date_paid.substring(0, 10) : '—', 'white-space:nowrap;') +
                                            td(esc(p.payment_method || '—')) +
                                            tdR(cc + ' ' + fmt(p.cp_amount)) +
                                            tdR(discMain) +
                                            tdR(penMain) +
                                            tdC(taxRateDisp) +
                                            tdR(taxMain + taxSub) +
                                            tdRB(paidMain + paidSub) +
                                            '</tr>';
                                    });

                                    /* ── Summary totals (always in course currency cc) ───────────────────── */
                                    var balanceDue = Math.max(0,
                                        (data.grand_total_amount || 0) -
                                        (data.grand_total_discount || 0) +
                                        (data.grand_total_penalty || 0) +
                                        (data.grand_total_tax || 0) -
                                        (data.grand_total_paid || 0)
                                    );

                                    var summaryRows = [
                                        ['Course Amount', cc + ' ' + fmt(data.grand_total_amount)],
                                        ['Total Discount', cc + ' ' + fmt(data.grand_total_discount)],
                                        ['Total Penalty', cc + ' ' + fmt(data.grand_total_penalty)],
                                        ['Total Tax', cc + ' ' + fmt(data.grand_total_tax)],
                                    ];

                                    var summaryHTML = summaryRows.map(function(row) {
                                        return '<tr>' +
                                            '<td style="' + STYL.sumLabel + '">' + row[0] + '</td>' +
                                            '<td style="' + STYL.sumVal + '">' + row[1] + '</td>' +
                                            '</tr>';
                                    }).join('');

                                    var balanceRow = balanceDue > 0.005 ?
                                        '<tr style="background:#fef2f2;">' +
                                        '<td style="' + STYL.sumLabel + 'color:#dc2626;">Balance Due</td>' +
                                        '<td style="' + STYL.sumVal + 'color:#dc2626;font-weight:700;">' +
                                        cc + ' ' + fmt(balanceDue) + '</td></tr>' :
                                        '<tr style="background:#f0fdf4;">' +
                                        '<td style="' + STYL.sumLabel + 'color:#16a34a;">Status</td>' +
                                        '<td style="' + STYL.sumVal + 'color:#16a34a;font-weight:700;">FULLY PAID</td></tr>';

                                    /* ── Exchange rate note (only when foreign-currency receipts exist) ──── */
                                    var exRateNote = buildExchangeNote(data, cc);

                                    /* ── Dates ───────────────────────────────────────────────────────────── */
                                    var recDate = data.date_added ? data.date_added.substring(0, 10) : '';

                                    /* ════════════════════════════════════════════════════════════════════════
                                       BUILD HTML
                                    ════════════════════════════════════════════════════════════════════════ */
                                    var html =
                                        /* ── Scoped print styles ── */
                                        '<style>' +
                                        '@keyframes rcptSpin{to{transform:rotate(360deg);}}' +
                                        '@media print{' +
                                        ' body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                                        ' @page{size:A4 portrait;margin:8mm 10mm;}' +
                                        ' .rcpt-no-print{display:none!important;}' +
                                        '}' +
                                        '#receipt-print-area table{page-break-inside:auto;}' +
                                        '#receipt-print-area tr{page-break-inside:avoid;}' +
                                        /* Fix modal scrollbar — only scroll the inner receipt wrapper */
                                        '#receipt-modal-body{overflow:hidden!important;}' +
                                        '#receipt-scroll-wrap{overflow-y:auto;overflow-x:hidden;' +
                                        ' max-height:calc(90vh - 120px);scrollbar-width:thin;' +
                                        ' scrollbar-color:#d1d5db #f9fafb;}' +
                                        '#receipt-scroll-wrap::-webkit-scrollbar{width:5px;}' +
                                        '#receipt-scroll-wrap::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px;}' +
                                        '#receipt-scroll-wrap::-webkit-scrollbar-track{background:#f9fafb;}' +
                                        '</style>' +

                                        /* ── Outer scroll wrapper ── */
                                        '<div id="receipt-scroll-wrap">' +

                                        /* ── Print area (A4-proportioned, small dense text) ── */
                                        '<div id="receipt-print-area" style="' +
                                        'font-family:\'Segoe UI\',system-ui,-apple-system,sans-serif;' +
                                        'font-size:11px;line-height:1.5;color:#111827;background:#fff;' +
                                        'max-width:880px;margin:0 auto;padding:28px 32px;">' +

                                        /* ════ HEADER ════ */
                                        '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">' +
                                        '<tr>' +
                                        '<td style="vertical-align:top;width:58%;">' +
                                        '<div style="display:flex-column;align-items:center;gap:12px;">' +
                                        '<img src="./assets/imgs/common/logo.png" alt="" style="height:44px;width:auto;" onerror="this.style.display=\'none\'">' +
                                        '<div>' +
                                        (data.branch_address ? '<div style="font-size:10px;color:#6b7280;margin-top:1px;">' + esc(data.branch_address) + '</div>' : '') +
                                        (data.branch_phone ? '<div style="font-size:10px;color:#6b7280;">Tel: ' + esc(data.branch_phone) + '</div>' : '') +
                                        (data.branch_email ? '<div style="font-size:10px;color:#6b7280;">' + esc(data.branch_email) + '</div>' : '') +
                                        '</div></div>' +
                                        '</td>' +
                                        '<td style="vertical-align:top;text-align:right;">' +
                                        '<div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.12em;margin-bottom:3px;">OFFICIAL PAYMENT RECEIPT</div>' +
                                        '<div style="font-size:20px;font-weight:800;color:#111827;letter-spacing:-.4px;">' + esc(data.receipt_number || '') + '</div>' +
                                        '<div style="font-size:10px;color:#6b7280;margin-top:2px;">Date: ' + recDate + '</div>' +
                                        '<div style="margin-top:6px;">' +
                                        '<span style="display:inline-block;background:#d1fae5;color:#065f46;' +
                                        'padding:2px 12px;border-radius:20px;font-size:9px;font-weight:700;' +
                                        'letter-spacing:.06em;border:1px solid #6ee7b7;">&#10003; APPROVED</span>' +
                                        '</div>' +
                                        '</td>' +
                                        '</tr>' +
                                        '</table>' +

                                        /* Divider */
                                        '<div style="height:2px;background:##F7F9FF;margin-bottom:18px;border-radius:1px;"></div>' +

                                        /* ════ BILL TO + PROGRAMME ════ */
                                        '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">' +
                                        '<tr>' +
                                        '<td style="width:50%;vertical-align:top;padding-right:10px;">' +
                                        boxOpen() +
                                        label('Bill To') +
                                        '<div style="font-size:13px;font-weight:700;color:#111827;">' +
                                        esc((data.student_title || '') + '. ' + (data.first_name || '') + ' ' + (data.last_name || '')) +
                                        '</div>' +
                                        '<div style="font-size:10px;color:#6b7280;margin-top:4px;line-height:1.8;">' +
                                        'ID: ' + esc(data.student_reg_id || '') + '<br>' +
                                        esc(data.email || '') + '<br>' + esc(data.mobile || '') +
                                        '</div>' +
                                        boxClose() +
                                        '</td>' +
                                        '<td style="width:50%;vertical-align:top;padding-left:10px;">' +
                                        boxOpen() +
                                        label('Programme Details') +
                                        '<div style="font-size:11px;font-weight:600;color:#111827;">' + esc(data.faculty || '') + '</div>' +
                                        '<div style="font-size:10px;color:#6b7280;margin-top:3px;line-height:1.8;">' +
                                        esc(data.programme || '') + '<br>' + esc(data.intake || '') +
                                        '</div>' +
                                        '<div style="font-size:9px;color:#9ca3af;margin-top:6px;padding-top:6px;border-top:1px solid #f3f4f6;">' +
                                        'Processed by: ' + esc((data.user_title || '') + '. ' + (data.user_fname || '') + ' ' + (data.user_lname || '')) +
                                        '</div>' +
                                        boxClose() +
                                        '</td>' +
                                        '</tr>' +
                                        '</table>' +

                                        /* ════ PAYMENT TABLE ════ */
                                        label('Payment Records') +
                                        '<div style="border:1px solid #e5e7eb;border-radius:3px;overflow:hidden;margin-bottom:18px;">' +
                                        '<div style="overflow-x:auto;">' +
                                        '<table style="width:100%;border-collapse:collapse;min-width:640px;">' +
                                        '<thead>' +
                                        '<tr style="background:#1e3a5f;">' +
                                        thH('#') + thH('Reference') + thH('Type') + thH('Date') + thH('Method') +
                                        thHR('Invoice Amt') + thHR('Discount') + thHR('Penalty') +
                                        thHC('Tax %') + thHR('Tax Amt') + thHRB('Paid') +
                                        '</tr>' +
                                        '</thead>' +
                                        '<tbody>' + (payRows || noDataRow()) + '</tbody>' +
                                        '</table>' +
                                        '</div>' +
                                        '</div>' +

                                        /* ════ SUMMARY + EXCHANGE NOTES ════ */
                                        '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">' +
                                        '<tr>' +

                                        /* Left — exchange rate notes (or empty if none) */
                                        '<td style="width:50%;vertical-align:top;padding-right:16px;">' +
                                        (exRateNote || '<div style="font-size:9px;color:#9ca3af;line-height:1.7;margin-top:4px;">' +
                                            'Payments made will not be refundable or transferable under any circumstances.<br>' +
                                            'This receipt is valid subject to realisation of cheque.<br>' +
                                            'Taxes or bank charges may be applied where required.' +
                                            '</div>') +
                                        '</td>' +

                                        /* Right — totals panel */
                                        '<td style="vertical-align:top;">' +
                                        '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:3px;overflow:hidden;">' +
                                        summaryHTML +
                                        '<tr style="background:#1e3a5f;">' +
                                        '<td style="padding:8px 14px;font-size:11px;font-weight:700;color:#fff;">TOTAL PAID</td>' +
                                        '<td style="padding:8px 14px;font-size:12px;font-weight:800;color:#fff;text-align:right;">' +
                                        cc + ' ' + fmt(data.grand_total_paid) +
                                        '</td>' +
                                        '</tr>' +
                                        balanceRow +
                                        '</table>' +
                                        '</td>' +

                                        '</tr>' +
                                        '</table>' +

                                        /* ════ FOOTER ════ */
                                        '<div style="border-top:1px dashed #d1d5db;padding-top:10px;' +
                                        'display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">' +
                                        '<div style="font-size:9px;color:#9ca3af;">' +
                                        'This is a computer-generated receipt and does not require a physical signature.' +
                                        '</div>' +
                                        '<div style="font-size:9px;color:#6b7280;text-align:right;">' +
                                        'Receipt No: <strong style="color:#374151;">' + esc(data.receipt_number || '') + '</strong>' +
                                        '&nbsp; | &nbsp;Generated: ' + recDate +
                                        '</div>' +
                                        '</div>' +

                                        /* ════ SYSTEM FOOTER ════ */
                                        '<div style="text-align:center;margin-top:14px;padding-top:10px;' +
                                        'border-top:1px solid #f3f4f6;">' +
                                        '<span style="font-size:9px;color:#c4c9d4;letter-spacing:.04em;">' +
                                        'ENLIXO LMS 2026 &copy; — All rights reserved' +
                                        '</span>' +
                                        '</div>' +

                                        '</div>' + /* /receipt-print-area */
                                        '</div>'; /* /receipt-scroll-wrap */

                                    if (body) {
                                        body.style.overflow = 'hidden'; /* parent stays hidden — scroll handled inside */
                                        body.innerHTML = html;
                                    }
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   EXCHANGE RATE NOTE BLOCK
                                ════════════════════════════════════════════════════════════════════════════ */
                                function buildExchangeNote(data, cc) {
                                    if (!data.payments || !data.payments.length) return '';
                                    var lines = [];
                                    var seen = {};
                                    data.payments.forEach(function(p) {
                                        var pc = (p.paid_currency || cc).trim();
                                        if (pc !== cc && p.exchange_rate && !seen[pc]) {
                                            seen[pc] = true;
                                            lines.push('1 ' + cc + ' = ' + pc + ' ' + fmt(p.exchange_rate) +
                                                (p.date_paid ? ' (' + p.date_paid.substring(0, 10) + ')' : ''));
                                        }
                                    });
                                    if (!lines.length) return '';
                                    return '<div style="border:1px solid #e5e7eb;border-radius:3px;padding:10px 12px;font-size:10px;color:#6b7280;line-height:2;">' +
                                        '<div style="font-size:8px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px;">Exchange Rates Used</div>' +
                                        lines.map(function(l) {
                                            return '&#8226; ' + l;
                                        }).join('<br>') +
                                        '</div>' +
                                        '<div style="font-size:9px;color:#9ca3af;line-height:1.7;margin-top:8px;">' +
                                        'Payments made will not be refundable or transferable under any circumstances.<br>' +
                                        'This receipt is valid subject to realisation of cheque.' +
                                        '</div>';
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   MINI STYLE CONSTANTS
                                ════════════════════════════════════════════════════════════════════════════ */
                                var STYL = {
                                    sumLabel: 'padding:6px 12px;font-size:10px;color:#374151;border-bottom:1px solid #e5e7eb;',
                                    sumVal: 'padding:6px 12px;font-size:10px;color:#111827;font-weight:500;text-align:right;border-bottom:1px solid #e5e7eb;'
                                };

                                /* ── reusable fragment helpers ── */
                                function boxOpen() {
                                    return '<div style="border:1px solid #e5e7eb;border-radius:3px;padding:12px 14px;">';
                                }

                                function boxClose() {
                                    return '</div>';
                                }

                                function label(t) {
                                    return '<div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;' +
                                        'letter-spacing:.1em;margin-bottom:7px;">' + t + '</div>';
                                }

                                function subLine(t) {
                                    return '<div style="font-size:9px;color:#6b7280;margin-top:2px;">' + t + '</div>';
                                }

                                function dash() {
                                    return '<span style="color:#d1d5db;">—</span>';
                                }

                                /* ── table cell helpers ── */
                                var C = 'padding:7px 8px;border-bottom:1px solid #f3f4f6;font-size:10px;vertical-align:top;';

                                function td(c, ex) {
                                    return '<td style="' + C + (ex || '') + '">' + c + '</td>';
                                }

                                function tdN(c) {
                                    return '<td style="' + C + 'color:#9ca3af;width:24px;">' + c + '</td>';
                                }

                                function tdB(c) {
                                    return '<td style="' + C + 'font-weight:600;color:#111827;">' + c + '</td>';
                                }

                                function tdR(c) {
                                    return '<td style="' + C + 'text-align:right;">' + c + '</td>';
                                }

                                function tdRB(c) {
                                    return '<td style="' + C + 'text-align:right;font-weight:700;color:#111827;">' + c + '</td>';
                                }

                                function tdC(c) {
                                    return '<td style="' + C + 'text-align:center;">' + c + '</td>';
                                }

                                var TH = 'padding:7px 8px;font-size:9px;font-weight:600;color:rgba(255,255,255,.9);letter-spacing:.04em;';

                                function thH(l) {
                                    return '<th style="' + TH + 'text-align:left;">' + l + '</th>';
                                }

                                function thHR(l) {
                                    return '<th style="' + TH + 'text-align:right;">' + l + '</th>';
                                }

                                function thHRB(l) {
                                    return '<th style="' + TH + 'text-align:right;color:#fff;">' + l + '</th>';
                                }

                                function thHC(l) {
                                    return '<th style="' + TH + 'text-align:center;">' + l + '</th>';
                                }

                                function noDataRow() {
                                    return '<tr><td colspan="11" style="text-align:center;padding:24px;color:#9ca3af;font-size:10px;">No payment records found</td></tr>';
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   DOWNLOAD PDF  (print-to-PDF via new window)
                                ════════════════════════════════════════════════════════════════════════════ */
                                function downloadReceipt() {
                                    var printArea = mGet('receipt-print-area');
                                    if (!printArea) return;

                                    var win = window.open('', '_blank');
                                    win.document.write(
                                        '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Receipt</title>' +
                                        '<style>' +
                                        '*{box-sizing:border-box;margin:0;padding:0;}' +
                                        'body{font-family:"Segoe UI",system-ui,sans-serif;font-size:11px;background:#fff;color:#111827;}' +
                                        '@media print{' +
                                        ' body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                                        ' @page{size:A4 portrait;margin:8mm 10mm;}' +
                                        '}' +
                                        '</style></head><body>' +
                                        printArea.outerHTML +
                                        '<script>window.onload=function(){setTimeout(function(){window.print();},400);' +
                                        'window.onafterprint=function(){window.close();};};<\/script>' +
                                        '</body></html>'
                                    );
                                    win.document.close();
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   SEND EMAIL  → ajax/sendReceiptEmail.php
                                ════════════════════════════════════════════════════════════════════════════ */
                                function sendReceiptEmail() {
                                    if (!currentReceiptId) return;

                                    var btn = document.querySelector('#receiptPreviewModal .table-btn-success');
                                    var origHtml = btn ? btn.innerHTML : '';

                                    if (btn) {
                                        btn.disabled = true;
                                        btn.innerHTML =
                                            '<iconify-icon icon="mdi:loading" width="13" ' +
                                            'style="vertical-align:-2px;margin-right:4px;' +
                                            'animation:rcptSpin .7s linear infinite;display:inline-block;"></iconify-icon>' +
                                            'Sending…';
                                    }

                                    fetch('api/sendReceiptEmail.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                receipt_id: currentReceiptId
                                            })
                                        })
                                        .then(function(r) {
                                            console.log(r.text());
                                            return r.json();
                                        })
                                        .then(function(res) {

                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            if (res.done) showToast('success', res.done);
                                            else showToast('error', res.error || 'Failed to send email.');
                                        })
                                        .catch(function() {
                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            showToast('error', 'Network error — could not send email.');
                                        });
                                }
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