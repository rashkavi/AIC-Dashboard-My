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

                        // ════════════════════════════════════════════════════════════════════════
                        // FETCH LATEST TAX RATE
                        // ════════════════════════════════════════════════════════════════════════
                        $taxRateQuery = Database::search("
                            SELECT `tax_rate` AS `rate` FROM `tax_rates`
                            WHERE `tax_rate` IS NOT NULL AND `tax_rate` > 0
                            ORDER BY `date_added` DESC LIMIT 1
                        ");
                        $latestTaxRate = 2.50; // DEFAULT fallback
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

                        // ── CP-level discount (course currency) ──────────────────────────
                        $discountAmountCP = ($paymentData['discount_type_id'] == 3)
                            ? $paymentData['amount'] / 100 * $paymentData['discount']
                            : (float)$paymentData['discount'];

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
                            FROM `course_payment_has_payments` AS cpp
                            INNER JOIN `payment_method` AS pm  ON cpp.`payment_method_payment_method_id` = pm.`payment_method_id`
                            INNER JOIN `payment_status` AS ps2 ON cpp.`payment_status_payment_status_id` = ps2.`payment_status_id`
                            LEFT  JOIN `currencies`     AS cur ON cpp.`currencies_currency_id`           = cur.`currency_id`
                            WHERE cpp.`course_payments_course_payments_id` = '" . (int)$paymentData['course_payments_id'] . "'
                            AND ps2.`payment_status_id` IN (2, 3)
                            GROUP BY cpp.`course_payment_has_payment_id`
                        ");

                        // ════════════════════════════════════════════════════════════════════════
                        // ACCUMULATE PAID TOTALS — matches second file's correct model:
                        //   payableAmount = amount - totalDiscount + totalPenalty + totalTax
                        //   balance       = payableAmount - convertedPaidTotal
                        //
                        // paid_amount in each receipt is TAX-INCLUSIVE.
                        // Tax already collected is stored in tax_amount; we accumulate it
                        // and add it to payable so that balance stays consistent.
                        // ════════════════════════════════════════════════════════════════════════
                        $convertedPaidTotal = 0;
                        $receiptRows        = [];
                        $totalReceiptDisc   = 0;
                        $totalReceiptPen    = 0;
                        $totalReceiptTax    = 0; // ← accumulate tax from receipts

                        if ($receipts && $receipts->num_rows > 0) {
                            while ($rec = $receipts->fetch_assoc()) {
                                $rate = resolveExchangeRate($rec, $paymentData['currency_id']);

                                $rec['converted_paid']         = convertToCourseAmount((float)$rec['paid_amount'], $rate);
                                $rec['is_foreign_currency']    = ($rate !== null);
                                $rec['currency_exchange_rate'] = $rate;

                                $convertedPaidTotal += $rec['converted_paid'];
                                $receiptRows[]       = $rec;

                                if ($rec['payment_status_id'] == 2 || $rec['payment_status_id'] == 3) {
                                    $totalReceiptDisc += convertToCourseAmount((float)($rec['discount_amount'] ?? 0), $rate);
                                    $totalReceiptPen  += convertToCourseAmount((float)($rec['panelty_amount']  ?? 0), $rate);
                                    $totalReceiptTax  += convertToCourseAmount((float)($rec['tax_amount']      ?? 0), $rate);
                                }
                            }
                        }

                        $totalDiscount = $discountAmountCP + $totalReceiptDisc;
                        $totalPenalty  = (float)$paymentData['panelty_amount'] + $totalReceiptPen;
                        $totalTax      = $totalReceiptTax;

                        // ════════════════════════════════════════════════════════════════════════
                        // CORRECTED PAYABLE CALCULATION  (identical to second file's model)
                        //
                        //   netBase       = amount − totalDiscount + totalPenalty
                        //   payableAmount = netBase + totalTax
                        //   balance       = payableAmount − convertedPaidTotal
                        //
                        // taxOnPayable is the accumulated tax from receipts — NOT re-derived
                        // from the base with the rate. That would double-count tax.
                        // ════════════════════════════════════════════════════════════════════════
                        $netBase       = max(0, $paymentData['amount'] - $totalDiscount + $totalPenalty);
                        $taxOnPayable  = $totalTax;   // actual tax collected, from receipt rows
                        $payableAmount = $netBase + $taxOnPayable;
                        $balance       = $payableAmount - $convertedPaidTotal;
                        $paidAmount    = $convertedPaidTotal;

                        // ── Overpayment (next installment credit) ─────────────────────────
                        $nextInstallmentData = null;
                        if ($balance < 0) {
                            $overpayAmount = abs($balance);
                            // Extract tax from overpay (tax-inclusive model)
                            $taxRateDecimal = $latestTaxRate / 100;
                            $basePortion = 1 - $taxRateDecimal;
                            $nextBase = round($overpayAmount * $basePortion, 2);
                            $nextTax = round($overpayAmount * $taxRateDecimal, 2);
                            $nextInstallmentData = [
                                'overpay' => $overpayAmount,
                                'base'    => $nextBase,
                                'tax'     => $nextTax,
                            ];
                            $balance = 0;
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

                        $receiptTaxRate    = (float)($coursepaymentData['tax_rate']   ?? $latestTaxRate);
                        $receiptPaidAmt    = (float)$coursepaymentData['paid_amount'];
                        $receiptTaxAmount  = (float)($coursepaymentData['tax_amount'] ?? 0);
                        $receiptBaseAmount = $receiptPaidAmt - $receiptTaxAmount;
                        $receiptTaxConverted = convertToCourseAmount($receiptTaxAmount, $receiptRate);

                        // ── JS constants for modal (this receipt's stored values) ─────────
                        $jsCurrentReceiptPaid     = (float)$coursepaymentData['paid_amount'];
                        $jsCurrentReceiptDiscount = (float)($coursepaymentData['discount_amount'] ?? 0);
                        $jsCurrentReceiptPenalty  = (float)($coursepaymentData['panelty_amount']  ?? 0);
                        $jsCurrentReceiptTax      = (float)($coursepaymentData['tax_amount']      ?? 0);

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

                                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
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
                                                                                    <span class="detail-lkr-hint">Accumulated from receipts</span>
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

                                                            <!-- ── Receipt card ────────────────────────────────── -->
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
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Date Paid</span>
                                                                                    <span class="detail-value"><?= date_format(date_create($coursepaymentData["date_paid"]), "Y-m-d h:i A") ?? '--/--/----' ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Given Discount</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format((float)$coursepaymentData['discount_amount'], 2) ?></span>
                                                                                    <?php if ($paidIsDiffFromCourse): ?><span class="detail-lkr-hint">= <?= $currencyCode ?> <?= number_format($recDiscConverted, 2) ?></span><?php endif; ?>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($recDiscConverted * $exchangeRateValue, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Added Penalty</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format((float)($coursepaymentData['panelty_amount'] ?? 0), 2) ?></span>
                                                                                    <?php if ($paidIsDiffFromCourse): ?><span class="detail-lkr-hint">= <?= $currencyCode ?> <?= number_format($recPenConverted, 2) ?></span><?php endif; ?>
                                                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?><span class="detail-lkr-hint">≈ LKR <?= number_format($recPenConverted * $exchangeRateValue, 2) ?></span><?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Rate</span>
                                                                                    <span class="detail-value"><?= $receiptTaxRate > 0 ? number_format($receiptTaxRate, 2) . '%' : 'N/A' ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Amount</span>
                                                                                    <span class="detail-value"><?= $receiptTaxAmount > 0 ? $paidCurrCode . ' ' . number_format($receiptTaxAmount, 2) : 'N/A' ?></span>
                                                                                    <?php if ($receiptTaxAmount > 0): ?>
                                                                                        <span class="detail-lkr-hint">Extracted from paid (tax-inclusive)</span>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($receiptTaxAmount > 0 && $paidIsDiffFromCourse): ?>
                                                                                        <span class="detail-lkr-hint">= <?= $currencyCode ?> <?= number_format($receiptTaxConverted, 2) ?></span>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($receiptTaxAmount > 0 && !$isBaseCurrencyLKR && $exchangeRate): ?>
                                                                                        <span class="detail-lkr-hint">≈ LKR <?= number_format($receiptTaxConverted * $exchangeRateValue, 2) ?></span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Course Fee (Base)</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format($receiptBaseAmount, 2) ?></span>
                                                                                    <span class="detail-lkr-hint"><?= $paidCurrCode ?> <?= number_format($receiptPaidAmt, 2) ?> − <?= number_format($receiptTaxAmount, 2) ?> tax</span>
                                                                                </div>
                                                                            </div>
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
                            ════════════════════════════════════════════════════════════════════════ -->
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

                                                <!-- LKR Summary Panel -->
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

                                                <!-- Row 2: Discount | Penalty | Tax (read-only) -->
                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Discount Given
                                                        <span class="currency-label-badge" id="modal-discount-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="number" class="form-control custom-input mt-2" id="modal-discount-amount"
                                                        value="<?= $jsCurrentReceiptDiscount ?>"
                                                        step="0.01" min="0"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                    <div class="text-12 text-danger" id="modal-discount-feedback"></div>
                                                    <span class="convert-hint" id="modal-discount-lkr-hint">≈ LKR <?= number_format($jsCurrentReceiptDiscount * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-discount-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Penalty Added
                                                        <span class="currency-label-badge" id="modal-penalty-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="number" class="form-control custom-input mt-2" id="modal-penalty-amount"
                                                        value="<?= $jsCurrentReceiptPenalty ?>"
                                                        step="0.01" min="0"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                    <div class="text-12 text-danger" id="modal-penalty-feedback"></div>
                                                    <span class="convert-hint" id="modal-penalty-lkr-hint">≈ LKR <?= number_format($jsCurrentReceiptPenalty * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-penalty-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Tax Amount
                                                        <span class="currency-label-badge tax-badge" id="modal-tax-rate-badge"><?= number_format($latestTaxRate, 2) ?>%</span>
                                                        <span class="currency-label-badge" id="modal-tax-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="text" class="form-control custom-input tax-readonly mt-2" id="modal-tax-amount-display"
                                                        value="<?= number_format($jsCurrentReceiptTax, 2) ?>"
                                                        readonly tabindex="-1">
                                                    <span class="convert-hint mt-1" style="color:#d97706;" id="modal-tax-formula-hint">
                                                        Extracted from paid (tax-inclusive model)
                                                    </span>
                                                    <span class="convert-hint" id="modal-tax-lkr-hint">≈ LKR <?= number_format($jsCurrentReceiptTax * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-tax-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <!-- Row 3: Paid Amount | Net Base (display) | Custom Rate -->
                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Total Paid Amount (incl. tax)
                                                        <span class="currency-label-badge" id="modal-paid-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="number" class="form-control custom-input mt-2" id="modal-paid-amount"
                                                        value="<?= $jsCurrentReceiptPaid ?>"
                                                        step="0.01" min="0"
                                                        <?= SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) ? '' : 'disabled' ?>>
                                                    <div class="text-12 text-danger" id="paid-amount-feedback"></div>
                                                    <span class="convert-hint" id="modal-paid-lkr-hint">≈ LKR <?= number_format($jsCurrentReceiptPaid * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-paid-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                    <span class="convert-hint mt-1" style="color:#0ea5e9;" id="modal-paid-auto-hint">
                                                        Suggested: <?= $currencyCode ?> <?= number_format($payableAmount, 2) ?>
                                                    </span>
                                                </div>

                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text">
                                                        Net Base (Course Fee − Discount + Penalty)
                                                        <span class="currency-label-badge" id="modal-base-currency-label"><?= $currencyCode ?></span>
                                                    </label>
                                                    <input type="text" class="form-control custom-input mt-2" id="modal-base-payable-display"
                                                        value="<?= number_format($netBase, 2) ?>" disabled>
                                                    <span class="convert-hint mt-1" id="modal-base-formula-hint">
                                                        <?= $currencyCode ?> amount − discount + penalty
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
                                                        value="<?= number_format(max(0, $balance), 2) ?>" disabled>
                                                    <span class="convert-hint" id="modal-balance-lkr-hint">≈ LKR <?= number_format(max(0, $balance) * $exchangeRateValue, 2) ?></span>
                                                    <span class="convert-hint d-none" id="modal-balance-base-hint">= <?= $currencyCode ?> 0.00</span>
                                                </div>

                                                <!-- OVERPAYMENT PANEL -->
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
                                   
                                   These represent the FULL installment state at page-load time,
                                   including ALL receipts (status 2 and 3), converted to course currency.
                                   
                                   The modal allows editing THIS receipt's values before approval,
                                   so we subtract the current receipt's stored values and replace
                                   with the new ones the admin enters.
                                ════════════════════════════════════════════════════════════════════════ */
                                var COURSE_AMOUNT = <?= (float)$paymentData['amount'] ?>;
                                var FIXED_TAX_RATE = <?= (float)$latestTaxRate ?>;

                                // Total accumulated values from ALL receipts (incl. current pending)
                                var EXISTING_TOTAL_PAID = <?= (float)$convertedPaidTotal ?>;
                                var EXISTING_TOTAL_DISC = <?= (float)$totalDiscount ?>;
                                var EXISTING_TOTAL_PEN = <?= (float)$totalPenalty ?>;
                                var EXISTING_TOTAL_TAX = <?= (float)$totalReceiptTax ?>;

                                // This receipt's currently stored values (to subtract before recalc)
                                var CURRENT_RECEIPT_PAID = <?= (float)$jsCurrentReceiptPaid ?>;
                                var CURRENT_RECEIPT_DISCOUNT = <?= (float)$jsCurrentReceiptDiscount ?>;
                                var CURRENT_RECEIPT_PENALTY = <?= (float)$jsCurrentReceiptPenalty ?>;
                                var CURRENT_RECEIPT_TAX = <?= (float)$jsCurrentReceiptTax ?>;

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
                                   HELPER: Extract tax from a tax-inclusive paid amount
                                   Formula: tax = paid − (paid / (1 + rate/100))
                                ════════════════════════════════════════════════════════════════════════ */
                                function extractTaxFromPaid(paid, rate) {
                                    if (rate <= 0 || paid <= 0) return {
                                        base: paid,
                                        tax: 0
                                    };
                                    var taxRateDecimal = rate / 100;
                                    var basePortion = 1 - taxRateDecimal;
                                    var base = paid * basePortion;
                                    var tax = paid * taxRateDecimal;
                                    return {
                                        base: base,
                                        tax: tax
                                    };
                                }

                                function calculateTaxInclusiveAmount(base, rate) {
                                    if (rate <= 0) return base;
                                    var taxRateDecimal = rate / 100;
                                    var divisor = 1 - taxRateDecimal;
                                    if (divisor <= 0) return base;
                                    return base / divisor;
                                }

                                function calculateTaxAmount(base, rate) {
                                    if (rate <= 0) return 0;
                                    var taxRateFraction = rate / (100 - rate);
                                    return base * taxRateFraction;
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   CORRECTED MODAL RECALCULATE
                                   
                                   Model (identical to second file / correct PHP):
                                     newTotalDisc  = EXISTING_TOTAL_DISC  - CURRENT_DISC  + newDisc
                                     newTotalPen   = EXISTING_TOTAL_PEN   - CURRENT_PEN   + newPen
                                     newTotalPaid  = EXISTING_TOTAL_PAID  - CURRENT_PAID  + newPaid
                                     newTaxFromPaid = extractTax(newPaid, rate).tax
                                     newTotalTax   = EXISTING_TOTAL_TAX   - CURRENT_TAX   + newTaxFromPaid
                                     
                                     netBase       = COURSE_AMOUNT - newTotalDisc + newTotalPen
                                     payableAmount = netBase + newTotalTax
                                     balance       = payableAmount - newTotalPaid
                                ════════════════════════════════════════════════════════════════════════ */
                                function modalRecalculate() {
                                    var rate = modalExchangeRate || modalExchangeRateValue || 1;

                                    function toBase(v) {
                                        return (!isBaseCurrencyLKR && modalCurrentMode === 'lkr' && rate) ? v / rate : v;
                                    }

                                    function toDisplay(v) {
                                        return (!isBaseCurrencyLKR && modalCurrentMode === 'lkr' && rate) ? v * rate : v;
                                    }

                                    // ── Raw input values (in current display currency) ──────────────
                                    var rawDisc = Math.max(0, parseFloat((mGet('modal-discount-amount') || {}).value) || 0);
                                    var rawPen = Math.max(0, parseFloat((mGet('modal-penalty-amount') || {}).value) || 0);
                                    var rawPaid = Math.max(0, parseFloat((mGet('modal-paid-amount') || {}).value) || 0);

                                    // ── Convert to base/course currency ────────────────────────────
                                    var discBase = toBase(rawDisc);
                                    var penBase = toBase(rawPen);
                                    var paidBase = toBase(rawPaid);

                                    // ── Extract tax from new paid amount (tax-inclusive model) ─────
                                    var taxCalc = extractTaxFromPaid(paidBase, FIXED_TAX_RATE);
                                    var newTaxBase = taxCalc.tax;
                                    var newFeeBase = taxCalc.base;

                                    // ── Rebuild accumulated totals replacing this receipt ──────────
                                    var newTotalDisc = EXISTING_TOTAL_DISC - CURRENT_RECEIPT_DISCOUNT + discBase;
                                    var newTotalPen = EXISTING_TOTAL_PEN - CURRENT_RECEIPT_PENALTY + penBase;
                                    var newTotalPaid = EXISTING_TOTAL_PAID - CURRENT_RECEIPT_PAID + paidBase;
                                    var newTotalTax = EXISTING_TOTAL_TAX - CURRENT_RECEIPT_TAX + newTaxBase;

                                    // ── Payable = netBase + accumulated tax (matches second file) ──
                                    var netBase = Math.max(0, COURSE_AMOUNT - newTotalDisc + newTotalPen);
                                    var payableAmount = netBase + newTotalTax;
                                    var balBase = payableAmount - newTotalPaid;

                                    // ── Display values (convert to selected currency mode) ──────────
                                    var label = modalCurrentMode === 'lkr' ? 'LKR' : currencyCode;
                                    var taxDisplay = toDisplay(newTaxBase);
                                    var feeDisplay = toDisplay(newFeeBase);
                                    var balDisplay = toDisplay(balBase);
                                    var payDisplay = toDisplay(payableAmount);
                                    var netDisplay = toDisplay(netBase);

                                    // Tax field
                                    var taxEl = mGet('modal-tax-amount-display');
                                    if (taxEl) taxEl.value = taxDisplay.toFixed(2);

                                    // Net base field
                                    var baseEl = mGet('modal-base-payable-display');
                                    if (baseEl) baseEl.value = netDisplay.toFixed(2);

                                    // Tax formula hint
                                    setText('modal-tax-formula-hint',
                                        label + ' ' + fmt(toDisplay(paidBase)) +
                                        ' ÷ ' + (1 + FIXED_TAX_RATE / 100).toFixed(4) +
                                        ' → fee ' + fmt(feeDisplay) + ' + tax ' + fmt(taxDisplay));

                                    // Suggested paid amount hint
                                    setText('modal-paid-auto-hint',
                                        'Suggested: ' + label + ' ' + fmt(payDisplay) +
                                        ' (' + fmt(netDisplay) + ' base + ' + fmt(toDisplay(newTotalTax)) + ' tax)');

                                    // Balance field
                                    var balEl = mGet('modal-balance-display');
                                    if (balEl) balEl.value = Math.max(0, balDisplay).toFixed(2);

                                    // Net base formula hint
                                    setText('modal-base-formula-hint',
                                        label + ' ' + fmt(toDisplay(COURSE_AMOUNT)) +
                                        ' − ' + fmt(toDisplay(newTotalDisc)) + ' disc' +
                                        ' + ' + fmt(toDisplay(newTotalPen)) + ' pen' +
                                        ' = ' + fmt(netDisplay));

                                    // ── Overpayment detection ─────────────────────────────────────
                                    if (paidBase > payableAmount + 0.001 && rawPaid > 0) {
                                        var overpayBase = paidBase - payableAmount;
                                        var overpayDisp = toDisplay(overpayBase);
                                        // Extract tax from overpay (tax-inclusive)
                                        var ovCalc = extractTaxFromPaid(overpayBase, FIXED_TAX_RATE);
                                        var ovTaxDisp = toDisplay(ovCalc.tax);
                                        var ovFeeDisp = toDisplay(ovCalc.base);

                                        showModalOverpayPanel(payDisplay, overpayDisp, ovFeeDisp, ovTaxDisp, overpayDisp, label);
                                    } else {
                                        hideModalOverpayPanel();
                                    }

                                    // ── LKR hints ─────────────────────────────────────────────────
                                    if (!isBaseCurrencyLKR && rate) {
                                        if (modalCurrentMode === 'lkr') {
                                            setHint('modal-discount-base-hint', currencyCode, discBase, false);
                                            setHint('modal-penalty-base-hint', currencyCode, penBase, false);
                                            setHint('modal-tax-base-hint', currencyCode, newTaxBase, false);
                                            setHint('modal-balance-base-hint', currencyCode, Math.max(0, balBase), false);
                                            setHint('modal-paid-base-hint', currencyCode, paidBase, false);
                                        } else {
                                            setHint('modal-discount-lkr-hint', 'LKR', rawDisc * rate);
                                            setHint('modal-penalty-lkr-hint', 'LKR', rawPen * rate);
                                            setHint('modal-tax-lkr-hint', 'LKR', newTaxBase * rate);
                                            setHint('modal-balance-lkr-hint', 'LKR', Math.max(0, balBase) * rate);
                                            setHint('modal-paid-lkr-hint', 'LKR', rawPaid * rate);
                                        }

                                        setText('modal-lkr-balance-val', 'LKR ' + fmt(Math.max(0, balBase) * rate));
                                        setText('modal-lkr-balance-sub', currencyCode + ' ' + fmt(Math.max(0, balBase)));
                                        setText('modal-lkr-discount-val', 'LKR ' + fmt(newTotalDisc * rate));
                                        setText('modal-lkr-penalty-val', 'LKR ' + fmt(newTotalPen * rate));
                                        setText('modal-lkr-tax-val', 'LKR ' + fmt(newTotalTax * rate));
                                        setText('modal-lkr-tax-sub', currencyCode + ' ' + fmt(newTotalTax));
                                    } else {
                                        ['modal-discount-lkr-hint', 'modal-penalty-lkr-hint', 'modal-tax-lkr-hint',
                                            'modal-balance-lkr-hint', 'modal-paid-lkr-hint'
                                        ].forEach(function(id) {
                                            var el = mGet(id);
                                            if (el) el.textContent = '';
                                        });
                                    }
                                }

                                /* ════════════════════════════════════════════════════════════════════════
                                   HELPER FUNCTIONS
                                ════════════════════════════════════════════════════════════════════════ */
                                function showModalOverpayPanel(currentPayable, overpay, nextFee, nextTax, nextTotal, label) {
                                    var panel = mGet('modal-overpay-panel');
                                    if (!panel) return;
                                    panel.style.display = 'block';
                                    setText('modal-overpay-current', label + ' ' + fmt(currentPayable));
                                    setText('modal-overpay-amount', label + ' ' + fmt(overpay));
                                    setText('modal-overpay-next-breakdown',
                                        label + ' ' + fmt(nextFee) + ' course fee + ' +
                                        label + ' ' + fmt(nextTax) + ' tax (' + FIXED_TAX_RATE.toFixed(2) + '%)');
                                    setText('modal-overpay-next-total', 'Total: ' + label + ' ' + fmt(nextTotal));
                                }

                                function hideModalOverpayPanel() {
                                    var panel = mGet('modal-overpay-panel');
                                    if (panel) panel.style.display = 'none';
                                }

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

                                    ['modal-discount-amount', 'modal-penalty-amount', 'modal-paid-amount'].forEach(function(id) {
                                        var el = mGet(id);
                                        if (!el || el.disabled) return;
                                        var v = parseFloat(el.value) || 0;
                                        if (!v) return;
                                        el.value = mode === 'lkr' ? (v * rate).toFixed(2) : (v / rate).toFixed(2);
                                    });

                                    modalRecalculate();
                                }

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
                                   EVENT LISTENER SETUP
                                ════════════════════════════════════════════════════════════════════════ */
                                document.addEventListener('DOMContentLoaded', function() {

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

                                    ['modal-discount-amount', 'modal-penalty-amount', 'modal-paid-amount'].forEach(function(id) {
                                        var el = mGet(id);
                                        if (el) el.addEventListener('input', modalRecalculate);
                                    });

                                    var paidDateEl = mGet('modal-paid-date');
                                    var customRateEl = mGet('modal-custom-rate');
                                    if (paidDateEl) paidDateEl.addEventListener('change', modalOnPaidDateChange);
                                    if (customRateEl) customRateEl.addEventListener('input', modalOnCustomRateChange);

                                    var modal = document.getElementById('updatePaymentStatus');
                                    if (modal) modal.addEventListener('shown.bs.modal', function() {
                                        modalRecalculate();
                                    });
                                });
                            </script>

                            <script src="./assets/js/paymentReceiptRenderer.js?v=<?= Project::getVersion() ?>"></script>

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