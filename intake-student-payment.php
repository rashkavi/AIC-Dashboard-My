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

            $id = $_GET['payment-id'];
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

                                                                                                `cp`.`amount`, `cp`.`discount`, `cp`.`due_date`,
                                                                                                `pt`.`payment_type`,
                                                                                                `dt`.`discount_type`, `dt`.`discount_type_id`,
                                                                                                `c`.`code`, `c`.`decimal_places`,
                                                                                                `c`.`currency_id` AS `course_currency_id`,
                                                                                                IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                                                IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                                                IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
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
                                                                                            LEFT JOIN (
                                                                                                SELECT course_payments_course_payments_id,
                                                                                                    SUM(discount_amount) AS total_discount,
                                                                                                    SUM(panelty_amount)  AS total_sub_panelty_amount
                                                                                                FROM course_payment_has_payments
                                                                                                WHERE payment_status_payment_status_id IN (2,3)
                                                                                                GROUP BY course_payments_course_payments_id
                                                                                            ) cpp ON cp.course_payments_id = cpp.course_payments_course_payments_id
                                                                                             WHERE `cp`.`course_payments_id` = '" . $id . "';
                                                                                        ";

            $paymentPlan = Database::search($detailQuery);

            if ($paymentPlan->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $paymentData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $paymentData['intake_id']))) {
                    header("Location: ./faculties");
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

                    $totalDiscount    = $discountAmount + $paymentData['total_discount'];
                    $totalPenalty     = $paymentData['panelty_amount'] + $paymentData['total_sub_panelty_amount'];
                    $payableAmount    = $paymentData['amount'] - $totalDiscount + $totalPenalty;

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
                            $rate = resolveExchangeRate(null, $rec, $paymentData['course_currency_id']);
                            $rec['converted_paid']       = convertToCourseAmount($rec['paid_amount'], $rate);
                            $rec['is_foreign_currency']  = ($rate !== null);
                            $rec['currency_exchange_rate']    = $rate;
                            $convertedPaidTotal += $rec['converted_paid'];
                            $receiptRows[] = $rec;
                            if ($rec['payment_status_id'] == 3) $totalPaidAmount += $rec['converted_paid'];
                        }
                    }

                    $balance = $payableAmount - $convertedPaidTotal;
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
                                    load_side_menu::load("Course Overview");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Students");
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
                                                                <span class="main-topic lh-lg">Student Payment</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $paymentData['faculty_id'] ?>'><?= $paymentData['faculty']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $paymentData['programme_id'] ?>'><?= $paymentData['programme']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $paymentData['intake_id'] ?>'><?= $paymentData['intake']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo ucwords(strtolower($paymentData['title'] . " " . $paymentData['first_name'] . " " . $paymentData['last_name'])); ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payments</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <?php
                                                            if ($paymentData['payment_status_id'] > 1) {
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $paymentData['intake_id'])) {
                                                            ?>
                                                                    <button class="custom-btn w-100" type="button" onclick="openUpdatePaymentStatusModal();">
                                                                        Update Payment Status
                                                                    </button>
                                                            <?php
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="row p-4 m-0 g-0 component-theme w-100">
                                                        <div class="col-12">
                                                            <div class="payment-timeline">
                                                                <div class="timeline-progress">
                                                                    <?php
                                                                    $query = "SELECT * FROM `payment_status`";
                                                                    $progress = 0;
                                                                    $progressColor = "";

                                                                    switch ($paymentData['payment_status_id']) {
                                                                        case 1:
                                                                            $progressColor = "completed";
                                                                            $progress = 100 / 3 * 0;
                                                                            $query .= " WHERE `payment_status_id` IN (1,2,3)";
                                                                            break;
                                                                        case 2:
                                                                            $progressColor = "completed";
                                                                            $progress = 100 / 3 * 1;
                                                                            $query .= " WHERE `payment_status_id` IN (1,2,3)";
                                                                            break;
                                                                        case 3:
                                                                            $progressColor = "accepted";
                                                                            $progress = 100 / 3 * 3;
                                                                            $query .= " WHERE `payment_status_id` IN (1,2,3)";
                                                                            break;
                                                                        case 4:
                                                                            $progressColor = "reject";
                                                                            $progress = 100 / 3 * 2;
                                                                            $query .= " WHERE `payment_status_id` IN (1,2,4)";
                                                                            break;
                                                                    }
                                                                    ?>
                                                                    <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                                </div>
                                                                <div class="timeline-items">
                                                                    <?php
                                                                    $icons = ["majesticons:clock", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve", "lsicon:refresh-done-filled"];
                                                                    $paymentStatus = Database::search($query);
                                                                    while ($status = $paymentStatus->fetch_assoc()) {
                                                                        $timelineItemColor = "";
                                                                        $bubbleAction = "";

                                                                        if ($paymentData['payment_status_id'] >= $status['payment_status_id']) {
                                                                            if ($paymentData['payment_status_id'] == 4 || $paymentData['payment_status_id'] == 7) {
                                                                                $timelineItemColor = "reject";
                                                                            } else if ($paymentData['payment_status_id'] == 9) {
                                                                                $timelineItemColor = "accepted";
                                                                            } else {
                                                                                if ($paymentData['payment_status_id']  ==  $status['payment_status_id']) {
                                                                                    $bubbleAction = "pulse";
                                                                                } else {
                                                                                    $timelineItemColor = "completed";
                                                                                }
                                                                            }
                                                                        }
                                                                    ?>
                                                                        <div class="timeline-item <?= $timelineItemColor ?>">
                                                                            <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                                <iconify-icon icon="<?= $icons[$status['payment_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                            </div>
                                                                            <div class="timeline-label">
                                                                                <span><?= $status['payment_status'] ?></span>
                                                                                <div class="status-detail">Initial state</div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>

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
                                                                                <span class="amount-value"> <?= number_format($balance, 2); ?> <?= $paymentData['code'] ?></span>
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
                                                                                <span class="detail-value"><?= number_format($discountAmount + $paymentData["total_discount"], 2) ?></span>
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
                                                                                <span class="detail-value"><?= number_format($paymentData["panelty_amount"] + $paymentData["total_sub_panelty_amount"], 2) ?></span>
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
                                                                                <span class="detail-value"><?= number_format($totalPaidAmount, 2) ?></span>
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
                                                                    if ($paymentData['discount_note'] != null) {
                                                                    ?>
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <!-- Type -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="hugeicons:note" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Note</span>
                                                                                    <p class="detail-value"><?= $paymentData['discount_note'] ?></p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }

                                                                    ?>


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
                                                                                            <span class="detail-value">LKR <?= number_format($panelty["amount"], 00); ?></span>
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

                                                        <div class="accordion mt-4 br-0" id="accordionExample">
                                                            <?php
                                                            $paidPayments = Database::search("SELECT * FROM `course_payment_has_payments` 
                                                            INNER JOIN `payment_method` ON `course_payment_has_payments`.`payment_method_payment_method_id` = `payment_method`.`payment_method_id`
                                                            WHERE `course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "'");
                                                            if ($paidPayments->num_rows > 0) {
                                                                for ($i = 0; $i < $paidPayments->num_rows; $i++) {
                                                                    $payment = $paidPayments->fetch_assoc();

                                                            ?>
                                                                    <div class="accordion-item br-0">
                                                                        <h2 class="accordion-header">
                                                                            <button class="accordion-button br-0 <?= $i == 0 ? "" : "collapsed" ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>" aria-expanded="true" aria-controls="collapse<?= $i ?>">
                                                                                Receipt : #R<?= $payment["course_payment_has_payment_id"] ?>
                                                                            </button>
                                                                        </h2>
                                                                        <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i == 0 ? "show" : "" ?>" data-bs-parent="#accordionExample">
                                                                            <div class="accordion-body">
                                                                                <div class="col-12">
                                                                                    <div class="row">
                                                                                        <div class="col-12">
                                                                                            <div class="row g-4">
                                                                                                <h4 class="sub-topic">Receipt Details</h4>
                                                                                                <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                                    <label for="" class="text-14 main-text">Discount Given Amount</label>
                                                                                                    <input type="text" class="form-control custom-input mt-2" id="discount-amount" disabled value="<?= $payment["discount_amount"] ?>">
                                                                                                    <div class="text-12 text-danger" id="discount-amount-feedback"></div>
                                                                                                </div>
                                                                                                <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                                    <label for="" class="text-14 main-text">Penalty Add Amount</label>
                                                                                                    <input type="text" class="form-control custom-input mt-2" disabled value="<?= $payment["panelty_amount"] ?>">
                                                                                                </div>

                                                                                                <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                                    <label for="" class="text-14 main-text">Payment Method</label>
                                                                                                    <select name="payment_method" class="form-select custom-select mt-2" disabled id="payment-method">
                                                                                                        <option value="0">- Select -</option>
                                                                                                        <?php
                                                                                                        $methods = Database::search("SELECT * FROM `payment_method`");
                                                                                                        while ($method = $methods->fetch_assoc()) {
                                                                                                        ?>
                                                                                                            <option value="<?= $method["payment_method_id"] ?>" <?= $payment["payment_method_payment_method_id"] == $method["payment_method_id"] ? "selected" : "" ?>><?= $method["payment_method"] ?></option>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </select>
                                                                                                    <div class="text-12 text-danger" id="balance-amount-feedback"></div>
                                                                                                </div>
                                                                                                <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                                    <label for="" class="text-14 main-text">Paid Amount</label>
                                                                                                    <input type="text" class="form-control custom-input mt-2" id="paid-amount" disabled value="<?= $payment["paid_amount"] ?>">
                                                                                                    <div class="text-12 text-danger" id="paid-amount-feedback"></div>
                                                                                                </div>
                                                                                                <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                                    <label for="" class="text-14 main-text">Paid Date</label>
                                                                                                    <input type="date" class="form-control custom-input mt-2" id="paid-date" disabled value="<?= $payment["date_paid"] ?>">
                                                                                                    <div class="text-12 text-danger" id="paid-date-feedback"></div>
                                                                                                </div>

                                                                                            </div>
                                                                                        </div>

                                                                                        <?php

                                                                                        $paymnetSlips = Database::search("SELECT * FROM `course_payment_slips` WHERE `course_payment_has_payment_id` = '" . $payment['course_payment_has_payment_id'] . "'");
                                                                                        if ($paymnetSlips->num_rows > 0) {
                                                                                        ?>
                                                                                            <div class="col-12">
                                                                                                <div class="row p-4 m-0 g-0 align-content-center bl-solid mt-4">
                                                                                                    <span class="main-topic lh-lg">Payment Slips</span>
                                                                                                    <div class="accordion accordion-flush" id="accordionParent">
                                                                                                        <?php
                                                                                                        while ($paymentSlip = $paymnetSlips->fetch_assoc()) {
                                                                                                            $slipVerifier = new SlipVerifier();
                                                                                                            $fileType = $slipVerifier->checkFileType($paymentSlip['slip_name']);
                                                                                                            $bankAccNo = '';
                                                                                                            $fileUrl = "";
                                                                                                            if ($payment['payment_method_id'] == 3) {
                                                                                                                $bank = Database::search("SELECT `acc_no` FROM `bank`");
                                                                                                                if ($bank->num_rows > 0) {
                                                                                                                    $bankAccNo = $bank->fetch_assoc()['acc_no'];
                                                                                                                }
                                                                                                            } else {
                                                                                                                $bank = Database::search("SELECT `acc_no` FROM `bank` WHERE `bank_id`='" . $payment['bank_bank_id'] . "'");
                                                                                                                if ($bank->num_rows > 0) {
                                                                                                                    $bankAccNo = $bank->fetch_assoc()['acc_no'];
                                                                                                                }
                                                                                                            }

                                                                                                            if (file_exists("./assets/uploads/payment-slips/" . $paymentSlip['slip_name']) == true) {
                                                                                                                $fileUrl = Project::getBaseUrl() . "/assets/uploads/payment-slips/" . $paymentSlip['slip_name'];
                                                                                                            } else {
                                                                                                                $fileUrl = Project::getBasePortalUrl() . "/assets/uploads/payment-slips/" . $paymentSlip['slip_name'];
                                                                                                            }

                                                                                                            $fileData = $slipVerifier->verifySlip($paymentData['refference'], $bankAccNo, $payment['paid_amount'], $fileUrl);

                                                                                                            if ($fileType != 'unsupported') {
                                                                                                        ?>
                                                                                                                <div class="accordion-item mt-4">
                                                                                                                    <h2 class="accordion-header bl-solid">
                                                                                                                        <button class="accordion-button collapsed d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse-<?= $paymentSlip['course_payment_slip_id'] ?>" aria-expanded="false" aria-controls="flush-collapse-<?= $paymentSlip['course_payment_slip_id'] ?>">
                                                                                                                            <img src="./assets/imgs/icons/<?= $fileType ?>.png" class="img-fluid pe-4" alt="Payment Slip">
                                                                                                                            <?php
                                                                                                                            if ($fileData['verified'] == true) {
                                                                                                                            ?>
                                                                                                                                <iconify-icon icon="ph:seal-check-duotone" width="40" height="40" class="text-dark-green"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                                    <span class="text-dark-green text-16 fw-semibold">Payment slip successfully verified</span>
                                                                                                                                    <span class="text-12">Transaction details have been authenticated.</span>
                                                                                                                                </div>
                                                                                                                            <?php
                                                                                                                            } else {
                                                                                                                            ?>
                                                                                                                                <iconify-icon icon="ant-design:close-circle-twotone" width="40" height="40" class="text-dark-red"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                                    <span class="text-dark-red text-16 fw-semibold">Unable to verify payment</span>
                                                                                                                                    <span class="text-12">The uploaded slip appears to be invalid, duplicated, or altered.</span>
                                                                                                                                </div>
                                                                                                                            <?php
                                                                                                                            }
                                                                                                                            ?>
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
                                                                                                                                                    <?php
                                                                                                                                                    if ($fileData['reference'] == true) {
                                                                                                                                                    ?>
                                                                                                                                                        <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                                                                            <span class="text-dark-green text-16 fw-semibold">Reference Number</span>
                                                                                                                                                            <span class="text-12">Reference number verified and matches the transaction record.</span>
                                                                                                                                                        </div>
                                                                                                                                                    <?php
                                                                                                                                                    } else {
                                                                                                                                                    ?>
                                                                                                                                                        <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                                                                            <span class="text-dark-red text-16 fw-semibold">Reference Number</span>
                                                                                                                                                            <span class="text-12">Reference number could not be matched with any recorded transaction.</span>
                                                                                                                                                        </div>
                                                                                                                                                    <?php
                                                                                                                                                    }
                                                                                                                                                    ?>
                                                                                                                                                </div>
                                                                                                                                            </div>
                                                                                                                                            <div class="col-12 mt-">
                                                                                                                                                <div class="d-flex align-items-start">
                                                                                                                                                    <?php
                                                                                                                                                    if ($fileData['date'] != null) {

                                                                                                                                                        $slipDate = !empty($fileData['date'])
                                                                                                                                                            ? date('Y-m-d', strtotime($fileData['date']))
                                                                                                                                                            : null;

                                                                                                                                                        $paidDate = !empty($payment['date_paid'])
                                                                                                                                                            ? date('Y-m-d', strtotime($payment['date_paid']))
                                                                                                                                                            : null;

                                                                                                                                                        if ($slipDate == $paidDate) {
                                                                                                                                                    ?>
                                                                                                                                                            <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                                                                <span class="text-dark-green text-16 fw-semibold">Payment Date</span>
                                                                                                                                                                <span class="text-12">Payment date successfully matched with bank transaction details.</span>
                                                                                                                                                            </div>
                                                                                                                                                        <?php
                                                                                                                                                        } else {
                                                                                                                                                        ?>
                                                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                                            <div class="ms-3 d-flex flex-column">
                                                                                                                                                                <span class="text-dark-red text-16 fw-semibold">Payment Date</span>
                                                                                                                                                                <span class="text-12">Payment date is missing or does not correspond with the system records.</span>
                                                                                                                                                            </div>
                                                                                                                                                        <?php
                                                                                                                                                        }
                                                                                                                                                    } else {
                                                                                                                                                        ?>
                                                                                                                                                        <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                                                                            <span class="text-dark-red text-16 fw-semibold">Payment Date</span>
                                                                                                                                                            <span class="text-12">Payment date is missing or does not correspond with the system records.</span>
                                                                                                                                                        </div>
                                                                                                                                                    <?php
                                                                                                                                                    }
                                                                                                                                                    ?>
                                                                                                                                                </div>
                                                                                                                                            </div>
                                                                                                                                            <div class="col-12">
                                                                                                                                                <div class="d-flex align-items-start">
                                                                                                                                                    <?php
                                                                                                                                                    if ($fileData['amount'] != null) {
                                                                                                                                                    ?>
                                                                                                                                                        <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                                                                            <span class="text-dark-green text-16 fw-semibold">Payment Amount</span>
                                                                                                                                                            <span class="text-12"><?= $payment['paid_amount'] ?> Payment amount confirmed and accurately reflected in the slip.</span>
                                                                                                                                                        </div>
                                                                                                                                                    <?php
                                                                                                                                                    } else {
                                                                                                                                                    ?>
                                                                                                                                                        <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                                                                            <span class="text-dark-red text-16 fw-semibold">Payment Amount</span>
                                                                                                                                                            <span class="text-12">Mismatch detected in the declared and recorded payment amount.</span>
                                                                                                                                                        </div>
                                                                                                                                                    <?php
                                                                                                                                                    }
                                                                                                                                                    ?>
                                                                                                                                                </div>
                                                                                                                                            </div>
                                                                                                                                            <div class="col-12">
                                                                                                                                                <div class="d-flex align-items-start">
                                                                                                                                                    <?php
                                                                                                                                                    if ($fileData['account'] == true) {
                                                                                                                                                    ?>
                                                                                                                                                        <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                                                                            <span class="text-dark-green text-16 fw-semibold">Account Number</span>
                                                                                                                                                            <span class="text-12">Institution account number is correct and consistent with transaction details.</span>
                                                                                                                                                        </div>
                                                                                                                                                    <?php
                                                                                                                                                    } else {
                                                                                                                                                    ?>
                                                                                                                                                        <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                                                                            <span class="text-dark-red text-16 fw-semibold">Account Number</span>
                                                                                                                                                            <span class="text-12">Incorrect or unrecognized account number used for this transaction.</span>
                                                                                                                                                        </div>
                                                                                                                                                    <?php
                                                                                                                                                    }
                                                                                                                                                    ?>
                                                                                                                                                </div>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                    </div>
                                                                                                                                    <div class="col-12 col-xl-6 mt-3 mt-xl-0">
                                                                                                                                        <div class="row justify-content-end align-items-center">
                                                                                                                                            <?php
                                                                                                                                            if ($fileData['verified'] == true) {
                                                                                                                                            ?>
                                                                                                                                                <div class="alert alert-success br-0" role="alert">
                                                                                                                                                    ✅ This payment slip has been successfully verified and is valid.
                                                                                                                                                </div>
                                                                                                                                            <?php
                                                                                                                                            } else {
                                                                                                                                            ?>
                                                                                                                                                <div class="alert alert-warning br-0" role="alert">
                                                                                                                                                    ⚠️ This payment slip has not been verified yet. Please review it carefully before approval.
                                                                                                                                                </div>
                                                                                                                                            <?php
                                                                                                                                            }
                                                                                                                                            ?>
                                                                                                                                            <button class="custom-btn col-4" type="button" onclick="window.open('<?= $fileUrl; ?>', '_blank');">View</button>
                                                                                                                                        </div>
                                                                                                                                    </div>
                                                                                                                                </div>
                                                                                                                            </div>
                                                                                                                        </div>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                        <?php
                                                                                                            }
                                                                                                        }
                                                                                                        ?>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        <?php
                                                                                        }

                                                                                        ?>


                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            <?php
                                                                }
                                                            }

                                                            ?>

                                                        </div>
                                                        <div class="col-12 mt-4">
                                                            <div class="row p-0 m-0 g-0">
                                                                <div class="col-12 mb-4">
                                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                <?php
                                                                                $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `payment_logs` WHERE `course_payments_course_payments_id`='" . $paymentData['course_payments_id'] . "'");
                                                                                $data = $logs_count->fetch_assoc();
                                                                                ?>
                                                                                <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> Logs</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12">
                                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                                        <div class="overflow-x-auto p-0 m-0 g-0" id="users-table-container">
                                                                            <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                            </div>
                                                                                        </th>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Installment</th>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>

                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="user-table-data">
                                                                                    <?php
                                                                                    $query = "SELECT *,`payment_logs`.`note` AS `payment_note` FROM `payment_logs`
                                                                    INNER JOIN `users` ON `payment_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    LEFT JOIN `course_payment_has_payments` ON `payment_logs`.`course_payment_has_payment_id` = `course_payment_has_payments`.`course_payment_has_payment_id`
                                                                    WHERE `payment_logs`.`course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "' ORDER BY `date_updated` DESC";

                                                                                    $users = Database::search($query);
                                                                                    if ($users->num_rows > 0) {
                                                                                        while ($data = $users->fetch_assoc()) {

                                                                                    ?>
                                                                                            <tr>
                                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["user_id"]; ?>" onchange="checkRowEvents(<?= $data['user_id']; ?>);">
                                                                                                        <?php
                                                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                                        ?>
                                                                                                            <img src="./assets/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                        <?php
                                                                                                        } else {
                                                                                                            $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                            $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                        ?>
                                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                        <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["course_payment_has_payment_id"] == null ? "N/A" : "#R" . $data["course_payment_has_payment_id"]; ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_updated"]; ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["payment_note"] == null ? "N/A" : $data["payment_note"]; ?></span></td>
                                                                                            </tr>
                                                                                        <?php
                                                                                        }
                                                                                    } else {
                                                                                        ?>
                                                                                        <tr class="justify-content-center align-middle">
                                                                                            <td colspan="13" class="align-middle justify-content-center">
                                                                                                <div class="d-flex flex-column align-items-center">
                                                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
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
                                            <footer>
                                                <?php include './includes/footer.php' ?>
                                            </footer>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        if ($paymentData['payment_status_id'] > 1) {
                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $paymentData['intake_id'])) {
                        ?>
                                <div class="modal fade" id="updatePaymentStatus" tabindex="-1" role="dialog" aria-labelledby="updatePaymentStatusTitle" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLongTitle">Update Payment Status</h5>
                                            </div>
                                            <div class="modal-body">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Payment Status</label>
                                                            <select class="form-select custom-select mt-2" id="status-selector">
                                                                <?php
                                                                $status = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` IN (3,4)");
                                                                while ($data = $status->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["payment_status_id"]; ?>" <?php echo $data["payment_status_id"] == $paymentData["payment_status_id"] ? "selected" : ""; ?>><?php echo $data["payment_status"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Note</label>
                                                            <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                                            <span id="note-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="col-12 col-md-4 custom-btn" onclick="updatePaymentStatus(<?= $paymentData['course_payments_id'] ?>);">Update</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php
                            }
                        }
                        ?>


                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Animate progress bar
                                const progressBar = document.querySelector('.progress-bar');
                                setTimeout(() => {
                                    progressBar.style.width = <?= $progress ?>;
                                }, 300);

                                // Add hover effects
                                const bubbles = document.querySelectorAll('.timeline-bubble');
                                bubbles.forEach(bubble => {
                                    bubble.addEventListener('mouseenter', function() {
                                        this.style.transform = 'scale(1.15)';
                                    });
                                    bubble.addEventListener('mouseleave', function() {
                                        if (!this.closest('.timeline-item').classList.contains('active')) {
                                            this.style.transform = 'scale(1)';
                                        }
                                    });
                                });
                            });
                        </script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>