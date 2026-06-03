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
                                                                                                `cp`.`amount`, `cp`.`discount`, `cp`.`due_date`,
                                                                                                `pt`.`payment_type`,
                                                                                                `dt`.`discount_type`, `dt`.`discount_type_id`,
                                                                                                `c`.`code` AS `currency_code`, `c`.`decimal_places`,
                                                                                                `c`.`currency_id` ,
                                                                                                IFNULL(pp.panelty_amount, 0) AS panelty_amount
                                                                                               
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
                                                                                            INNER JOIN `intakes` AS `i`              ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                                                                            INNER JOIN `programmes` AS `pr`         ON `i`.`programmes_programme_id` = `pr`.`programme_id`
                                                                                            INNER JOIN `faculties` AS `f`          ON `pr`.`faculties_faculty_id` = `f`.`faculty_id`
                                                                                            INNER JOIN `students` AS `s`          ON `shi`.`students_student_id` = `s`.`student_id`
                                                                                            INNER JOIN `login_details` AS `ld`    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                                                            INNER JOIN `title` AS `t`            ON `s`.`title_title_id` = `t`.`title_id`
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

                        function resolveExchangeRate($db, $row, $courseCurrencyId)
                        {
                            // If payment currency is null or same as course currency → no conversion
                            if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrencyId) {
                                return null;
                            }
                            // 1) Use inline custom rate if present
                            if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) {
                                return (float)$row['currency_exchange_rate'];
                            }
                            // 2) Lookup from currency_exchange_rates table via currency_exchange_rate_id
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
                            return null; // cannot convert — treat as same currency
                        }

                        function convertToCourseAmount($paidAmount, $exchangeRate)
                        {
                            if ($exchangeRate === null || $exchangeRate <= 0) return (float)$paidAmount;
                            return (float)$paidAmount / $exchangeRate;
                        }

                        // CP-level discount
                        $discountAmount = ($paymentData['discount_type_id'] == 3)
                            ? $paymentData['amount'] / 100 * $paymentData['discount']
                            : $paymentData['discount'];

                        $totalDiscount    = $discountAmount;
                        $totalPenalty     = $paymentData['panelty_amount'];


                        // Fetch receipts for this payment
                        $receipts = Database::search("
                                                                                                    SELECT
                                                                                                        cpp.*,
                                                                                                        pm.payment_method,
                                                                                                        pm.payment_method_id,
                                                                                                        ps2.payment_status,
                                                                                                        ps2.payment_status_id,
                                                                                                        cur.code AS paid_currency_code,
                                                                                                        cur.decimal_places AS paid_decimal_places,
                                                                                                        cpp.currency_exchange_rate
                                                                                                    FROM course_payment_has_payments AS cpp
                                                                                                    INNER JOIN payment_method AS pm  ON cpp.payment_method_payment_method_id = pm.payment_method_id
                                                                                                    INNER JOIN payment_status AS ps2 ON cpp.payment_status_payment_status_id = ps2.payment_status_id
                                                                                                    LEFT JOIN currencies AS cur      ON cpp.currencies_currency_id = cur.currency_id
                                                                                                    WHERE cpp.course_payments_course_payments_id = '" . (int)$paymentData['course_payments_id'] . "'
                                                                                                    GROUP BY cpp.course_payment_has_payment_id
                                                                                                ");

                        // Compute converted paid total for this CP row
                        $convertedPaidTotal = 0;
                        $receiptRows = [];
                        $totalPaidAmount = 0;
                        if ($receipts && $receipts->num_rows > 0) {
                            while ($rec = $receipts->fetch_assoc()) {
                                $rate = resolveExchangeRate(null, $rec, $paymentData['currency_id']);
                                $rec['converted_paid']       = convertToCourseAmount($rec['paid_amount'], $rate);
                                $rec['is_foreign_currency']  = ($rate !== null);
                                $rec['currency_exchange_rate']    = $rate;
                                $convertedPaidTotal += $rec['converted_paid'];
                                $receiptRows[] = $rec;
                                if ($rec['payment_status_id'] == 3) $totalPaidAmount += $rec['converted_paid'];
                                if ($rec['payment_status_id'] == 2 || $rec['payment_status_id'] == 3) {
                                    $totalDiscount += convertToCourseAmount($rec['discount_amount'], $rate);
                                    $totalPenalty += convertToCourseAmount($rec['panelty_amount'], $rate);
                                };
                            }
                        }


                        $payableAmount    = $paymentData['amount'] - $totalDiscount + $totalPenalty;
                        $balance = $payableAmount - $convertedPaidTotal;
                        $paidAmount = $convertedPaidTotal;

                        $lkrCurrencyId = 1; // assuming LKR = currency_id 1, adjust if different

                        $exchangeRate = null;
                        $exchangeRateValue = 1;
                        $exchangeRateId = 0;

                        if ($paymentData['currency_id'] != $lkrCurrencyId) {
                            $rateResult = Database::search("
                                    SELECT 
                                        cer.`rate`,
                                        cer.`effective_date`,
                                        cer.`effective_time`,
                                        c_base.`code` AS base_code,
                                        c_base.`symbol` AS base_symbol,
                                        c_target.`code` AS target_code,
                                        cer.`currency_exchange_rate_id`
                                    FROM `currency_exchange_rates` cer
                                    INNER JOIN `currencies` c_base 
                                        ON cer.`base_currency_id` = c_base.`currency_id`
                                    INNER JOIN `currencies` c_target 
                                        ON cer.`target_currency_id` = c_target.`currency_id`
                                    WHERE cer.`base_currency_id` = '" . $paymentData['currency_id'] . "'
                                    AND cer.`target_currency_id` = '" . $lkrCurrencyId . "'
                                    ORDER BY cer.`effective_date` DESC, cer.`effective_time` DESC
                                    LIMIT 1
                                ");

                            if ($rateResult->num_rows > 0) {
                                $exchangeRate = $rateResult->fetch_assoc();
                                $exchangeRateValue = (float)$exchangeRate['rate'];
                                $exchangeRateId = $exchangeRate['currency_exchange_rate_id'] ?? null;
                            }
                        }


                        // Pre-calculate LKR equivalents
                        $balanceLKR      = $balance      * $exchangeRateValue;
                        $payableAmtLKR   = $payableAmount * $exchangeRateValue;
                        $paidAmountLKR   = $paidAmount   * $exchangeRateValue;
                        $discountAmtLKR  = $discountAmount * $exchangeRateValue;
                        $penaltyAmtLKR   = $paymentData['panelty_amount'] * $exchangeRateValue;

                        $isBaseCurrencyLKR = ($paymentData['currency_id'] == $lkrCurrencyId);
?>
                        <!DOCTYPE html>
                        <html lang="en">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>Add Receipt to Inquiry Student Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
                        </head>


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
                                                include "./includes/components/skeleton-add-edit.php"; ?>
                                            </div>
                                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                                <div class="row p-4 m-0 g-0">
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 col-md-10">
                                                                <div class="row">
                                                                    <span class="main-topic lh-lg">Add Receipt to Student Payment</span>
                                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                        <ol class="breadcrumb m-0">
                                                                            <?php
                                                                            if ($inquiry_id == 0) {
                                                                            ?>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $paymentData['faculty_id'] ?>'><?= $paymentData['faculty']; ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $paymentData['programme_id'] ?>'>...</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='batch-details?batch-id=<?= $paymentData['intake_id'] ?>'><?= $paymentData['intake']; ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo ucwords(strtolower($paymentData['title'] . " " . $paymentData['first_name'] . " " . $paymentData['last_name'])); ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payments</a></li>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                                    <ol class="breadcrumb m-0">
                                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Payments</a></li>
                                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./inquiries-registration-payments">Inquiry Registration Payments</a></li>
                                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./inquiry-payment-plan?inquiry-id=<?= $inquiry_id ?>">Inquiry Payment Plan</a></li>
                                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="#">Add Receipt to Student Payment</a></li>
                                                                                    </ol>
                                                                                </nav>
                                                                            <?php
                                                                            }
                                                                            ?>

                                                                        </ol>
                                                                    </nav>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="row p-4 m-0 g-0 component-theme w-100">
                                                            <div class="payment-card-container mt-4">
                                                                <div class="payment-card">
                                                                    <!-- Background elements -->
                                                                    <div class="card-background"></div>

                                                                    <!-- Card content -->
                                                                    <div class="card-content">
                                                                        <div class="payment-header reference-section">
                                                                            <!-- Reference on left -->
                                                                            <div class="reference-section">
                                                                                <span class="reference-label">PAYMENT REFERENCE</span>
                                                                                <h2 class="reference-number"><?= $paymentData["refference"]; ?></h2>
                                                                                <hr>
                                                                            </div>

                                                                            <!-- Amount on right -->
                                                                            <div class="amount-section">
                                                                                <div class="amount-display">
                                                                                    <span class="amount-label">Total Balance Amount</span>
                                                                                    <span class="amount-value"><?= $paymentData['currency_code']  ?> <?= number_format($balance, 2); ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <!-- Date -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Amount</span>
                                                                                    <span class="detail-value"><?= number_format($paymentData['amount'], 2) ?></span>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Type -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Type</span>
                                                                                    <span class="detail-value"><?= $paymentData["payment_type"]; ?></span>
                                                                                </div>
                                                                            </div>

                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Due Date</span>
                                                                                    <span class="detail-value"><?= $paymentData["due_date"] ?? '--/--/----'; ?></span>
                                                                                </div>
                                                                            </div>


                                                                            <!-- Discount -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 6.586V2zm3.5 4a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" />
                                                                                        <path d="M1.293 7.793A1 1 0 0 1 1 7.086V2a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l.043-.043-7.457-7.457z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Discount</span>
                                                                                    <span class="detail-value">
                                                                                        <?php
                                                                                        if ($paymentData["discount_type_id"] == 1) {
                                                                                            echo "No Discount";
                                                                                        } else if ($paymentData["discount_type_id"] == 2) {
                                                                                            echo "LKR " . number_format($paymentData["discount"], 2) . " | " . $paymentData["discount_type"];
                                                                                        } else {
                                                                                            echo  number_format($paymentData["discount"]) . "% | " . $paymentData["discount_type"];
                                                                                        }
                                                                                        ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>


                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <!-- Type -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Discount</span>
                                                                                    <span class="detail-value"><?= number_format($totalDiscount, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Penalty Amount</span>
                                                                                    <span class="detail-value"><?= number_format($totalPenalty, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Date -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Payable Amount</span>
                                                                                    <span class="detail-value"><?= number_format($payableAmount, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Paid Amount</span>
                                                                                    <span class="detail-value"><?= number_format($paidAmount, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Balance</span>
                                                                                    <span class="detail-value"><?= number_format($balance, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <?php
                                                                        if ($paymentData['panelty_payment_option_panelty_payment_option_id'] != 1) {

                                                                        ?>
                                                                            <span class="reference-label my-3">PENALTY PAYMENT ENABLED</span>
                                                                            <?php
                                                                            $panelties = Database::search("SELECT * FROM `course_payments_penalties`
                                                                        INNER JOIN `panelty_payments_type` ON `course_payments_penalties`.`panelty_payment_type_panelty_payment_type_id` = `panelty_payments_type`.`panelty_payments_type_id` 
                                                                        WHERE `course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "'");
                                                                            if ($panelties->num_rows > 0) {
                                                                                while ($panelty = $panelties->fetch_assoc()) {
                                                                            ?>
                                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                                        <!-- Date -->
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon">
                                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                                </svg>
                                                                                            </div>
                                                                                            <div class="detail-content">
                                                                                                <span class="detail-label">Option</span>
                                                                                                <span class="detail-value"><?= $paymentData["panelty_payment_option"] ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon">
                                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                                </svg>
                                                                                            </div>
                                                                                            <div class="detail-content">
                                                                                                <span class="detail-label">Type</span>
                                                                                                <span class="detail-value"><?= $panelty["panelty_payments_type"] ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <!-- Type -->
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-icon">
                                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                    <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                                    <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                                </svg>
                                                                                            </div>
                                                                                            <div class="detail-content">
                                                                                                <span class="detail-label">Amount</span>
                                                                                                <span class="detail-value"><?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($panelty["amount"], 00); ?></span>
                                                                                            </div>
                                                                                        </div>

                                                                                        <?php
                                                                                        if (!empty($panelty["days"])) {
                                                                                        ?>
                                                                                            <div class="detail-item">
                                                                                                <div class="detail-icon">
                                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                                                    </svg>
                                                                                                </div>
                                                                                                <div class="detail-content">
                                                                                                    <span class="detail-label">Days</span>
                                                                                                    <span class="detail-value"><?= $panelty["days"]; ?></span>
                                                                                                </div>
                                                                                            </div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>

                                                                                    </div>
                                                                        <?php
                                                                                }
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 p-0 mt-4">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <div class="col-12">
                                                                        <div class="row g-4">
                                                                            <!-- ── Header row: title + currency toggle ── -->
                                                                            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                                                                <h4 class="sub-topic m-0">Add Receipt</h4>
                                                                            </div>

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
                                                                                                onclick="window.open('<?= Project::getBaseUrl()  ?>/assets/uploads/student-identity-documents/payment-slips/<?= $paymentSlipData['document'] ?>', '_blank')">
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
                                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                                    <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
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