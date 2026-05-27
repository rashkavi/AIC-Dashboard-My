<?php
require_once("./includes/connection.php");
require_once("./includes/Project.php");

if (!isset($_GET["receipt-id"]) || empty($_GET["receipt-id"])) {
    die("Invalid receipt");
}

$receiptId = base64_decode(str_replace("/", "", $_GET["receipt-id"]));

if (!$receiptId || !is_numeric($receiptId)) {
    die("Invalid receipt ID");
}

// ── Fetch receipt + course payment + student + currency ────────────────────
$receiptRs = Database::search("
    SELECT
        cpp.course_payment_has_payment_id,
        cpp.paid_amount,
        cpp.discount_amount,
        cpp.panelty_amount,
        cpp.date_paid,
        cpp.note,
        cpp.currencies_currency_id,
        cpp.currency_exchange_rate,
        cpp.currency_exchange_rate_id,

        cur_paid.code           AS paid_currency_code,
        cur_paid.decimal_places AS paid_decimal_places,

        cp.course_payments_id,
        cp.amount               AS course_amount,
        cp.due_date,
        cp.discount,
        cp.discount_type_discount_type_id,
        cp.refference,

        pt.payment_type,
        pm.payment_method,
        pm.payment_method_id,

        ld.first_name,
        ld.last_name,
        ld.email,

        s.student_id,
        s.mobile,

        t.title,

        p.programme,
        i.intake,
        f.faculty,

        c.code          AS currency_code,
        c.decimal_places,
        c.currency_id

    FROM course_payment_has_payments cpp

    INNER JOIN course_payments cp
        ON cpp.course_payments_course_payments_id = cp.course_payments_id

    INNER JOIN payment_type pt
        ON cp.payment_type_payment_type_id = pt.payment_type_id

    INNER JOIN payment_method pm
        ON cpp.payment_method_payment_method_id = pm.payment_method_id

    INNER JOIN payment_plan_has_students_has_intakes pphsi
        ON cp.payment_plan_has_students_has_intakes_id = pphsi.payment_plan_has_students_has_intakes_id

    INNER JOIN students_has_intakes shi
        ON pphsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

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

    INNER JOIN payment_category pc
        ON pphsi.payment_category_payment_category_id = pc.payment_category_id

    INNER JOIN currencies c
        ON pc.currencies_currency_id = c.currency_id

    LEFT JOIN currencies cur_paid
        ON cpp.currencies_currency_id = cur_paid.currency_id

    WHERE cpp.course_payment_has_payment_id = '" . Database::escape($receiptId) . "'
    LIMIT 1
");

if ($receiptRs->num_rows == 0) {
    die("Receipt not found");
}

$data = $receiptRs->fetch_assoc();

// ── Currency helpers (mirrors File 1 exactly) ──────────────────────────────
function resolveExchangeRate(array $row, int $courseCurrencyId): ?float
{
    if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrencyId) {
        return null;
    }
    if (!empty($row['currency_exchange_rate']) && (float)$row['currency_exchange_rate'] > 0) {
        return (float)$row['currency_exchange_rate'];
    }
    if (!empty($row['currency_exchange_rate_id'])) {
        $rateRow = Database::search(
            "SELECT `rate` AS `currency_exchange_rate` FROM `currency_exchange_rates`
             WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1"
        );
        if ($rateRow && $rateRow->num_rows > 0) {
            $r = $rateRow->fetch_assoc();
            if (!empty($r['currency_exchange_rate']) && (float)$r['currency_exchange_rate'] > 0) {
                return (float)$r['currency_exchange_rate'];
            }
        }
    }
    return null;
}

function convertToCourseAmount(float $paidAmount, ?float $exchangeRate): float
{
    if ($exchangeRate === null || $exchangeRate <= 0) return $paidAmount;
    return $paidAmount / $exchangeRate;
}

// ── Core identifiers ───────────────────────────────────────────────────────
$lkrCurrencyId     = 1;
$courseCurrencyId  = (int)$data['currency_id'];
$isBaseCurrencyLKR = ($courseCurrencyId === $lkrCurrencyId);
$currencyCode      = htmlspecialchars($data['currency_code']);
$decimal           = (int)$data['decimal_places'];

// ── Receipt-level exchange rate (receipt currency → course currency) ───────
$receiptExchangeRate = resolveExchangeRate($data, $courseCurrencyId);
$receiptIsForeign    = ($receiptExchangeRate !== null);

// ── Amounts converted to course currency ──────────────────────────────────
$paidInCourse     = convertToCourseAmount((float)$data['paid_amount'],     $receiptExchangeRate);
$discountInCourse = convertToCourseAmount((float)$data['discount_amount'], $receiptExchangeRate);
$penaltyInCourse  = convertToCourseAmount((float)$data['panelty_amount'],  $receiptExchangeRate);

// ── Course-level discount ──────────────────────────────────────────────────
$cpDiscountAmount = ($data['discount_type_discount_type_id'] == 3)
    ? (float)$data['course_amount'] / 100 * (float)$data['discount']
    : (float)$data['discount'];

// ── Summary totals ─────────────────────────────────────────────────────────
$totalPayable = (float)$data['course_amount'] - $cpDiscountAmount + $penaltyInCourse;
$dueAmount    = max(0, $totalPayable - $paidInCourse);

// ── Course → LKR exchange rate ─────────────────────────────────────────────
$lkrExchangeRate      = null;
$lkrExchangeRateValue = 1.0;

if (!$isBaseCurrencyLKR) {
    $lkrRateResult = Database::search("
        SELECT cer.rate, cer.effective_date, cer.currency_exchange_rate_id,
               c_base.code AS base_code, c_target.code AS target_code
        FROM currency_exchange_rates cer
        INNER JOIN currencies c_base   ON cer.base_currency_id   = c_base.currency_id
        INNER JOIN currencies c_target ON cer.target_currency_id = c_target.currency_id
        WHERE cer.base_currency_id   = '" . $courseCurrencyId . "'
          AND cer.target_currency_id = '" . $lkrCurrencyId . "'
        ORDER BY cer.effective_date DESC, cer.effective_time DESC
        LIMIT 1
    ");
    if ($lkrRateResult && $lkrRateResult->num_rows > 0) {
        $lkrExchangeRate      = $lkrRateResult->fetch_assoc();
        $lkrExchangeRateValue = (float)$lkrExchangeRate['rate'];
    }
}

// ── LKR equivalents ────────────────────────────────────────────────────────
$paidLKR         = $paidInCourse     * $lkrExchangeRateValue;
$discountLKR     = $discountInCourse * $lkrExchangeRateValue;
$penaltyLKR      = $penaltyInCourse  * $lkrExchangeRateValue;
$totalPayableLKR = $totalPayable     * $lkrExchangeRateValue;
$dueLKR          = $dueAmount        * $lkrExchangeRateValue;
$courseAmtLKR    = (float)$data['course_amount'] * $lkrExchangeRateValue;

// ── Display helpers ────────────────────────────────────────────────────────
$studentName = $data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name'];
$receiptNo   = 'REC-' . str_pad($data['course_payment_has_payment_id'], 6, '0', STR_PAD_LEFT);
$datePaid    = date('d M Y', strtotime($data['date_paid']));

// Payment method detection for checkboxes
$pmLower = strtolower($data['payment_method']);
$modes = [
    'Cash'        => $pmLower === 'cash',
    'CHQ'         => $pmLower === 'chq' || $pmLower === 'cheque',
    'TT'          => $pmLower === 'tt' || $pmLower === 'bank transfer' || strpos($pmLower, 'transfer') !== false,
    'Credit Card' => str_contains($pmLower, 'card'),
    'Draft'       => $pmLower === 'draft',
];

date_default_timezone_set('Asia/Colombo');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Payment Receipt <?= $receiptNo ?> | <?= Project::getBaseName() ?></title>
    <link rel="stylesheet" href="./assets/css/payment-receipt.css?v=<?= Project::getVersion() ?>">
</head>
<body>

<div class="print-btn">
    <button onclick="window.print()">⬇ Download / Print Receipt</button>
</div>

<div class="page">
<div class="receipt">

    <!-- Top accent bar -->
    <div class="receipt-accent-bar"></div>

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="receipt-header">
        <div class="logo-area">
            <img src="./assets/imgs/<?= Project::getInstituteLogo() ?>" class="logo" alt="<?= htmlspecialchars(Project::getBaseName()) ?>">
            <div class="org-info">
                <div class="org-name"><?= htmlspecialchars(Project::getBaseName()) ?></div>
                <div class="org-tagline">Official Payment Receipt</div>
            </div>
        </div>
        <div class="contact-area">
            No.154, Havelock Road, Thimbirigasyaya, Colombo 05<br>
            <strong>Tel:</strong> +94 114 325 864 &nbsp;|&nbsp; <strong>Email:</strong> accounts@aicedu.lk
        </div>
    </div>

    <!-- ── Title band ───────────────────────────────────────────────────────── -->
    <div class="receipt-title-band">
        <span class="doc-type">Receipt</span>
        <div class="receipt-meta">
            <div class="meta-item">Reg. No &nbsp;<strong><?= htmlspecialchars($data['student_id']) ?></strong></div>
            <div class="meta-item">Receipt No &nbsp;<strong><?= $receiptNo ?></strong></div>
            <div class="meta-item">Date &nbsp;<strong><?= $datePaid ?></strong></div>
        </div>
    </div>

    <!-- ── Currency notices (only when non-LKR or foreign receipt) ──────────── -->
    <?php if (!$isBaseCurrencyLKR || $receiptIsForeign): ?>
    <div class="currency-notice-bar">
        <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
            <span class="notice-item">
                <span class="notice-icon">⇄</span>
                LKR equivalent rate:
                <strong>1 <?= $currencyCode ?> = LKR <?= number_format($lkrExchangeRateValue, 2) ?></strong>
                &nbsp;(<?= date('d M Y', strtotime($lkrExchangeRate['effective_date'])) ?>)
            </span>
        <?php elseif (!$isBaseCurrencyLKR): ?>
            <span class="notice-warn">⚠ No LKR exchange rate available — amounts shown in <?= $currencyCode ?> only</span>
        <?php endif; ?>
        <?php if ($receiptIsForeign): ?>
            <span class="notice-item">
                <span class="notice-icon">↔</span>
                Payment received in
                <strong><?= htmlspecialchars($data['paid_currency_code'] ?? '') ?></strong>
                — converted to <?= $currencyCode ?> at rate
                <strong><?= number_format($receiptExchangeRate, 4) ?></strong>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Body ─────────────────────────────────────────────────────────────── -->
    <div class="receipt-body">

        <!-- Received from -->
        <div class="received-from-row">
            <span class="rf-label">Received with thanks from</span>
            <span class="rf-value"><?= htmlspecialchars($studentName) ?></span>
        </div>

        <!-- Amount hero -->
        <div class="amount-hero">
            <div>
                <div class="ah-label">Amount Paid (<?= $currencyCode ?>)</div>
                <div class="ah-main">
                    <?= $currencyCode ?> <?= number_format($paidInCourse, $decimal) ?>
                    <?php if ($receiptIsForeign): ?>
                        <span class="ah-fx">
                            <?= htmlspecialchars($data['paid_currency_code'] ?? '') ?>
                            <?= number_format((float)$data['paid_amount'], $decimal) ?>
                            @ <?= number_format($receiptExchangeRate, 4) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                    <div class="ah-lkr">≈ LKR <?= number_format($paidLKR, 2) ?></div>
                <?php endif; ?>
            </div>
            <div class="ah-badge">
                <div class="ah-receipt-no"><?= $receiptNo ?></div>
                <div class="ah-date"><?= $datePaid ?></div>
            </div>
        </div>

        <!-- Two column: info + fee table -->
        <div class="two-col-grid">

            <!-- Left: student & payment info -->
            <div>
                <div class="section-label">Student &amp; Payment Details</div>
                <table class="info-table">
                    <tr>
                        <td>Programme</td>
                        <td><?= htmlspecialchars($data['programme']) ?></td>
                    </tr>
                    <tr>
                        <td>Intake</td>
                        <td><?= htmlspecialchars($data['intake']) ?></td>
                    </tr>
                    <tr>
                        <td>Faculty</td>
                        <td><?= htmlspecialchars($data['faculty']) ?></td>
                    </tr>
                    <tr>
                        <td>Student ID</td>
                        <td><span class="mono-val"><?= htmlspecialchars($data['student_id']) ?></span></td>
                    </tr>
                    <tr>
                        <td>Contact</td>
                        <td><?= htmlspecialchars($data['mobile']) ?></td>
                    </tr>
                    <tr>
                        <td>Payment Method</td>
                        <td><?= htmlspecialchars($data['payment_method']) ?></td>
                    </tr>
                    <tr>
                        <td>Reference No</td>
                        <td><span class="mono-val"><?= htmlspecialchars($data['refference'] ?? '-') ?></span></td>
                    </tr>
                    <?php if ($receiptIsForeign): ?>
                    <tr>
                        <td>Exchange Rate</td>
                        <td class="fx-val">
                            1 <?= $currencyCode ?>
                            = <?= htmlspecialchars($data['base_currency_code'] ?? '') ?> <?= number_format($receiptExchangeRate, 2) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($data['due_date'])): ?>
                    <tr>
                        <td>Due Date</td>
                        <td><span class="mono-val"><?= htmlspecialchars($data['due_date']) ?></span></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Right: fee breakdown -->
            <div>
                <div class="section-label">Fee Breakdown</div>
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Type of Fee</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Course instalment -->
                        <tr>
                            <td><?= htmlspecialchars($data['payment_type']) ?></td>
                            <td>
                                <?= $currencyCode ?> <?= number_format((float)$data['course_amount'], $decimal) ?>
                                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                                    <span class="td-lkr">≈ LKR <?= number_format($courseAmtLKR, 2) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Discount -->
                        <tr>
                            <td>Discount</td>
                            <td>
                                <?= $currencyCode ?> <?= number_format($discountInCourse, $decimal) ?>
                                <?php if ($receiptIsForeign): ?>
                                    <span class="fx-pill">
                                        <?= htmlspecialchars($data['paid_currency_code'] ?? '') ?>
                                        <?= number_format((float)$data['discount_amount'], $decimal) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                                    <span class="td-lkr">≈ LKR <?= number_format($discountLKR, 2) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Penalty -->
                        <tr>
                            <td>Penalty</td>
                            <td>
                                <?= $currencyCode ?> <?= number_format($penaltyInCourse, $decimal) ?>
                                <?php if ($receiptIsForeign): ?>
                                    <span class="fx-pill">
                                        <?= htmlspecialchars($data['paid_currency_code'] ?? '') ?>
                                        <?= number_format((float)$data['panelty_amount'], $decimal) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                                    <span class="td-lkr">≈ LKR <?= number_format($penaltyLKR, 2) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Total Paid -->
                        <tr class="total-row">
                            <td>Total Paid</td>
                            <td>
                                <?= $currencyCode ?> <?= number_format($paidInCourse, $decimal) ?>
                                <?php if ($receiptIsForeign): ?>
                                    <span class="fx-pill">
                                        <?= htmlspecialchars($data['paid_currency_code'] ?? '') ?>
                                        <?= number_format((float)$data['paid_amount'], $decimal) ?>
                                        @ <?= number_format($receiptExchangeRate, 4) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                                    <span class="td-lkr">≈ LKR <?= number_format($paidLKR, 2) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Payment mode ──────────────────────────────────────────────────── -->
        <div class="payment-mode-row">
            <div class="pm-label">Payment Mode</div>
            <div class="pm-options">
                <?php foreach ($modes as $label => $active): ?>
                    <div class="pm-option <?= $active ? 'active' : '' ?>">
                        <div class="pm-check"><?= $active ? '✓' : '' ?></div>
                        <?= htmlspecialchars($label) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Summary strip ────────────────────────────────────────────────── -->
        <div class="summary-strip">
            <div class="ss-cell highlight">
                <div class="ss-label">Total Amount</div>
                <div class="ss-value">
                    <?= $currencyCode ?> <?= number_format($totalPayable, $decimal) ?>
                </div>
                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                    <div class="ss-lkr">≈ LKR <?= number_format($totalPayableLKR, 2) ?></div>
                <?php endif; ?>
            </div>
            <div class="ss-cell">
                <div class="ss-label">Paid Amount</div>
                <div class="ss-value paid">
                    <?= $currencyCode ?> <?= number_format($paidInCourse, $decimal) ?>
                </div>
                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                    <div class="ss-lkr">≈ LKR <?= number_format($paidLKR, 2) ?></div>
                <?php endif; ?>
                <?php if ($receiptIsForeign): ?>
                    <div class="ss-lkr" style="color:var(--badge-txt);">
                        <?= htmlspecialchars($data['paid_currency_code'] ?? '') ?>
                        <?= number_format((float)$data['paid_amount'], $decimal) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="ss-cell">
                <div class="ss-label">Due Amount</div>
                <div class="ss-value due">
                    <?= $currencyCode ?> <?= number_format($dueAmount, $decimal) ?>
                </div>
                <?php if (!$isBaseCurrencyLKR && $lkrExchangeRate): ?>
                    <div class="ss-lkr">≈ LKR <?= number_format($dueLKR, 2) ?></div>
                <?php endif; ?>
            </div>
            <div class="ss-cell">
                <div class="ss-label">Due Date</div>
                <div class="ss-value" style="font-size:12px;">
                    <?= htmlspecialchars($data['due_date'] ?? '—') ?>
                </div>
            </div>
        </div>

    </div><!-- /receipt-body -->

    <!-- ── Disclaimer ─────────────────────────────────────────────────────── -->
    <div class="receipt-disclaimer">
        Payments made will not be refundable or transferable under any circumstances.
        Receipt is valid subject to realization of cheque. Taxes or bank charges may be applied where required.
    </div>

    <!-- ── Footer ─────────────────────────────────────────────────────────── -->
    <div class="receipt-footer">
        <div class="footer-left">Generated: <?= date('Y-m-d H:i A') ?></div>
        <div class="footer-verified">
            <div class="verified-dot"></div>
            Official Document
        </div>
        <div class="footer-right"><?= htmlspecialchars(Project::getBaseName()) ?></div>
    </div>

    <!-- Bottom accent bar -->
    <div class="receipt-bottom-bar"></div>

</div><!-- /receipt -->
</div><!-- /page -->

</body>
</html>
