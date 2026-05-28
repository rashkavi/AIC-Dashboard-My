<?php
require_once './includes/UserClass.php';
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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./users");
        } else {

            $id = $_GET['student-id'];

            $studentHasIntake = Database::search("SELECT
                `faculty`, `faculty_id`, `programme`, `programme_id`, `intake`, `intake_id`,
                `students_has_intakes`.`student_id`, `first_name`, `last_name`, `title`, `students_has_intakes_id`
            FROM `students_has_intakes`
            INNER JOIN `students`       ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `login_details`  ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
            INNER JOIN `title`          ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `intakes`        ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes`     ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties`      ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'");

            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./students");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole)
                    || UserPermissions::hasPermission('intake_payments', 10, $studentHasIntakeData['intake_id'])
                    || UserPermissions::hasPermission('intake_payments', 12, $studentHasIntakeData['intake_id'])
                    || UserPermissions::hasPermission('intake_payments', 13, $studentHasIntakeData['intake_id'])
                    || UserPermissions::hasPermission('intake_payments', 15, $studentHasIntakeData['intake_id']))) {
                    header("Location: ./faculties");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Intake Student Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>
                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php include "includes/side-menu.php";
                                    load_side_menu::load("Course Overview"); ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php include "./includes/header.php";
                                            selectItem::setSelected("Students"); ?>
                                        </div>
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-dashboards.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">

                                                <!-- Page Header -->
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Student Payments - #<?= $studentHasIntakeData['student_id'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $studentHasIntakeData['faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $studentHasIntakeData['programme_id'] ?>'><?= $studentHasIntakeData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $studentHasIntakeData['intake_id'] ?>'><?= $studentHasIntakeData['intake'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= ucwords(strtolower($studentHasIntakeData['title'] . "." . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name'])) ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $studentHasIntakeData['intake_id'])): ?>
                                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" type="button" onclick="location.href='add-payment-plan?student-id=<?= $id ?>'">Add Payment Plan</button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <?php

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

                                                // ============================================================
                                                // HELPER: convert a paid_amount to course currency
                                                // ============================================================
                                                function convertToCourseAmount($paidAmount, $exchangeRate)
                                                {
                                                    if ($exchangeRate === null || $exchangeRate <= 0) return (float)$paidAmount;
                                                    return (float)$paidAmount / $exchangeRate;
                                                }

                                                // ============================================================
                                                // SECTION 1: AWAITING PAYMENTS
                                                // paid_amount summed per payment, converted to course currency in PHP
                                                // ============================================================
                                                $awaitingQuery = "SELECT
                                                    `cp`.`course_payments_id`,
                                                    `ps`.`payment_status`,
                                                    `ps`.`payment_status_id`,
                                                    `cp`.`due_date`,
                                                    `cp`.`amount`,
                                                    `cp`.`discount`,
                                                    `cp`.`discount_type_discount_type_id`,
                                                    `shi`.`student_id`,
                                                    `ld`.`first_name`,
                                                    `ld`.`last_name`,
                                                    `t`.`title`,
                                                    `s`.`profile_img`,
                                                    `c`.`code`,
                                                    `c`.`decimal_places`,
                                                    `c`.`currency_id` AS `course_currency_id`,
                                                    `pt`.`payment_type`,
                                                    `pc`.`payment_category`,
                                                    IFNULL(pp.panelty_amount, 0) AS panelty_amount
                                                   
                                                FROM `course_payments` AS `cp`
                                                INNER JOIN `payment_status` AS `ps`      ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                                                        ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                INNER JOIN `payment_category` AS `pc`    ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                INNER JOIN `currencies` AS `c`           ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                INNER JOIN `payment_type` AS `pt`        ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
                                                INNER JOIN `students_has_intakes` AS `shi` ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                INNER JOIN `students` AS `s`             ON `shi`.`students_student_id` = `s`.`student_id`
                                                INNER JOIN `login_details` AS `ld`       ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                INNER JOIN `title` AS `t`               ON `s`.`title_title_id` = `t`.`title_id`
                                                LEFT JOIN (
                                                    SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                                                    FROM panelty_payments GROUP BY course_payments_course_payments_id
                                                ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                                               
                                                WHERE `shi`.`students_has_intakes_id` = '" . $id . "'
                                                AND `cp`.`payment_status_payment_status_id` = '1'
                                                GROUP BY `cp`.`course_payments_id`
                                                ORDER BY `cp`.`course_payments_id` DESC";

                                                $awaitingPayments = Database::search($awaitingQuery);

                                                // For each awaiting payment, fetch receipts and compute converted paid_amount
                                                $awaitingRows = [];
                                                if ($awaitingPayments && $awaitingPayments->num_rows > 0) {
                                                    while ($aRow = $awaitingPayments->fetch_assoc()) {

                                                        // Fetch receipts for this payment to calculate converted paid total
                                                        $receiptsTmp = Database::search("
                                                                SELECT paid_amount,
                                                                 currencies_currency_id,
                                                                  currency_exchange_rate,
                                                                   currency_exchange_rate_id,
                                                                     discount_amount,
                                                                      panelty_amount
                                                                FROM course_payment_has_payments
                                                                WHERE course_payments_course_payments_id = '" . (int)$aRow['course_payments_id'] . "'
                                                                AND payment_status_payment_status_id IN (2,3)
                                                            ");
                                                        $convertedPaid = 0;
                                                        $convertedDiscount = 0;
                                                        $convertedPenalty = 0;
                                                        if ($receiptsTmp && $receiptsTmp->num_rows > 0) {
                                                            while ($rt = $receiptsTmp->fetch_assoc()) {
                                                                $rate = resolveExchangeRate(null, $rt, $aRow['course_currency_id']);
                                                                $convertedPaid += convertToCourseAmount($rt['paid_amount'], $rate);
                                                                $convertedDiscount += convertToCourseAmount($rt['discount_amount'] ?? 0, $rate);
                                                                $convertedPenalty += convertToCourseAmount($rt['panelty_amount'] ?? 0, $rate);
                                                            }
                                                        }
                                                        $aRow['converted_paid_amount'] = $convertedPaid;
                                                        $aRow['converted_discount_amount'] = $convertedDiscount;
                                                        $aRow['converted_penalty_amount'] = $convertedPenalty;
                                                        $awaitingRows[] = $aRow;
                                                    }
                                                }

                                                if (!empty($awaitingRows)):
                                                ?>
                                                    <div class="col-12 p-4 component-theme mb-4" id="paymentsCollapse" data-bs-parent="#accordionParent">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 mb-4">
                                                                <span class="text-16 main-text fw-medium">Awaiting Payments</span>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="table-responsive-wrapper">
                                                                    <div class="table-responsive overflow-visible" id="payments-table-container">
                                                                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                                            <thead>
                                                                                <tr class="br-t-5">
                                                                                    <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                            <span class="text-14 main-text fw-medium mx-2">Payment</span>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Payment Category</th>
                                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Discount</th>
                                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Penalty Amount</th>
                                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Paid Amount</th>
                                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Balance</th>
                                                                                    <th class="py-3 bg-table-header">
                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                            <span class="text-14 main-text fw-medium">Status</span>
                                                                                            <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                    <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 10, $studentHasIntakeData['intake_id']) || UserPermissions::hasPermission('intake_payments', 11, $studentHasIntakeData['intake_id'])): ?>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    <?php endif; ?>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
                                                                                <?php foreach ($awaitingRows as $data):
                                                                                    $discountAmount = ($data['discount_type_discount_type_id'] == 3)
                                                                                        ? $data['amount'] / 100 * $data['discount']
                                                                                        : $data['discount'];
                                                                                    $totalDiscount  = $discountAmount + $data['converted_discount_amount'];
                                                                                    $totalPenalty   = $data['panelty_amount'] + $data['converted_penalty_amount'];
                                                                                    $payableAmount  = $data['amount'] - $totalDiscount + $totalPenalty;
                                                                                    $balance        = $payableAmount - $data['converted_paid_amount'];

                                                                                    $status_classes = ["0" => "table-btn-purple", "1" => "table-btn-yellow", "2" => "table-btn-orange", "3" => "table-btn-success", "4" => "table-btn-danger", "5" => "table-btn-orange", "6" => "table-btn-success", "7" => "table-btn-danger", "8" => "table-btn-success"];
                                                                                    $sbutton_class  = $status_classes[$data['payment_status_id']] ?? 'table-btn-yellow';
                                                                                ?>
                                                                                    <tr>
                                                                                        <td class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <span class="text-14 main-text mx-2"><?= $data['payment_type'] ?></span>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['payment_category'] ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] ?> <?= number_format($data['amount'], $data['decimal_places']) ?></span></td>
                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data['code'] ?> <?= number_format($totalDiscount, $data['decimal_places']) ?></span></td>
                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data['code'] ?> <?= number_format($totalPenalty, $data['decimal_places']) ?></span></td>
                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data['code'] ?> <?= number_format($data['converted_paid_amount'], $data['decimal_places']) ?></span></td>
                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data['code'] ?> <?= number_format($balance, $data['decimal_places']) ?></span></td>
                                                                                        <td class="pe-4 py-2 align-middle">
                                                                                            <button class="table-btn <?= $sbutton_class ?> w-100"><?= $data['payment_status'] ?></button>
                                                                                        </td>
                                                                                        <td class="py-2 pe-4 align-middle">
                                                                                            <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='intake-student-payment?payment-id=<?= $data['course_payments_id'] ?>'">View</button>
                                                                                        </td>
                                                                                        <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 10, $studentHasIntakeData['intake_id']) || UserPermissions::hasPermission('intake_payments', 11, $studentHasIntakeData['intake_id'])): ?>
                                                                                            <td class="px-4 py-3 align-middle">
                                                                                                <div class="dropdown dropup position-relative">
                                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5 z-3 position-absolute">
                                                                                                        <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 10, $studentHasIntakeData['intake_id'])): ?>
                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="add-receipt-to-student-payment?payment-id=<?= $data['course_payments_id'] ?>">
                                                                                                                    <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon> Add Reciept</a></li>
                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="update-student-payment?payment-id=<?= $data['course_payments_id'] ?>">
                                                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon> Edit</a></li>
                                                                                                        <?php endif; ?>
                                                                                                        <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 11, $studentHasIntakeData['intake_id'])): ?>
                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" onclick="removeRequestStudentPayment(<?= $data['course_payments_id'] ?>)">
                                                                                                                    <iconify-icon icon="material-symbols:delete-outline-rounded" width="20" height="20" class="sub-text me-3"></iconify-icon> Remove</a></li>
                                                                                                        <?php endif; ?>
                                                                                                    </ul>
                                                                                                </div>
                                                                                            </td>
                                                                                        <?php endif; ?>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Payment History -->
                                                <div class="col-12 px-4 py-3 pe-4 g-0 component-theme mb-10">
                                                    <div class="row p-0 m-0 g-0 align-content-center" id="accordionParent">
                                                        <span class="text-16 main-text fw-medium">Student Payment History</span>

                                                        <?php
                                                        // ============================================================
                                                        // SECTION 2: PAYMENT HISTORY SUMMARY per payment plan
                                                        // Paid amounts for completed/pending/rejected fetched per-receipt
                                                        // and converted to course currency via PHP helper
                                                        // ============================================================
                                                        $categoryQuery = "
                                                        SELECT
                                                            `pshi`.`payment_plan_has_students_has_intakes_id` AS `pphsiId`,
                                                            `pshi`.`amount`,
                                                            `pp`.`payment_plan`,
                                                            `pc`.`payment_category`,
                                                            `dt`.`discount_type`,
                                                            `pc`.`payment_category_id`,
                                                            `pshi`.`discount`,
                                                            `pshi`.`discount_type_discount_type_id`,
                                                            `c`.`code`,
                                                            `c`.`decimal_places`,
                                                            `c`.`currency_id` AS `course_currency_id`,
                                                            COALESCE(`pshi`.`amount`, 0) AS `total_amount`,
                                                            COALESCE(CASE
                                                                WHEN `pshi`.`discount_type_discount_type_id` = 2 THEN `pshi`.`discount`
                                                                WHEN `pshi`.`discount_type_discount_type_id` = 3 THEN (`pshi`.`amount` * `pshi`.`discount` / 100)
                                                                ELSE 0
                                                            END, 0) AS `ppshi_total_discount`,
                                                            IFNULL(SUM(CASE
                                                                WHEN `cp`.`discount_type_discount_type_id` = 2 THEN COALESCE(`cp`.`discount`, 0)
                                                                WHEN `cp`.`discount_type_discount_type_id` = 3 THEN (COALESCE(`cp`.`amount`, 0) * COALESCE(`cp`.`discount`, 0) / 100)
                                                                ELSE 0
                                                            END), 0) AS `cp_total_discount`,
                                                           
                                                            (
                                                                COALESCE(CASE
                                                                    WHEN `pshi`.`discount_type_discount_type_id` = 2 THEN `pshi`.`discount`
                                                                    WHEN `pshi`.`discount_type_discount_type_id` = 3 THEN (`pshi`.`amount` * `pshi`.`discount` / 100)
                                                                    ELSE 0
                                                                END, 0)
                                                                + IFNULL(SUM(CASE
                                                                    WHEN `cp`.`discount_type_discount_type_id` = 2 THEN COALESCE(`cp`.`discount`, 0)
                                                                    WHEN `cp`.`discount_type_discount_type_id` = 3 THEN (COALESCE(`cp`.`amount`, 0) * COALESCE(`cp`.`discount`, 0) / 100)
                                                                    ELSE 0
                                                                END), 0)
                                                               
                                                            ) AS `total_discounts`,
                                                            IFNULL(SUM(COALESCE(`ppn`.`panelty_amount`, 0)), 0) AS `main_total_panelty`,
                                                           
                                                            (
                                                                IFNULL(SUM(COALESCE(`ppn`.`panelty_amount`, 0)), 0)
                                                               
                                                            ) AS `total_panelties`,
                                                            (
                                                                COALESCE(`pshi`.`amount`, 0)
                                                                - (
                                                                    COALESCE(CASE
                                                                        WHEN `pshi`.`discount_type_discount_type_id` = 2 THEN `pshi`.`discount`
                                                                        WHEN `pshi`.`discount_type_discount_type_id` = 3 THEN (`pshi`.`amount` * `pshi`.`discount` / 100)
                                                                        ELSE 0
                                                                    END, 0)
                                                                    + IFNULL(SUM(CASE
                                                                        WHEN `cp`.`discount_type_discount_type_id` = 2 THEN COALESCE(`cp`.`discount`, 0)
                                                                        WHEN `cp`.`discount_type_discount_type_id` = 3 THEN (COALESCE(`cp`.`amount`, 0) * COALESCE(`cp`.`discount`, 0) / 100)
                                                                        ELSE 0
                                                                    END), 0)
                                                                    
                                                                )
                                                                + (
                                                                    IFNULL(SUM(COALESCE(`ppn`.`panelty_amount`, 0)), 0)
                                                                   
                                                                )
                                                            ) AS `payable_amount`
                                                        FROM `payment_plan_has_students_has_intakes` AS `pshi`
                                                        INNER JOIN `discount_type` AS `dt`    ON `pshi`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                                                        INNER JOIN `students_has_intakes` AS `shi` ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                        INNER JOIN `payment_plan` AS `pp`     ON `pshi`.`payment_plan_payment_plan_id` = `pp`.`payment_plan_id`
                                                        INNER JOIN `payment_category` AS `pc` ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                        INNER JOIN `currencies` AS `c`        ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                        LEFT JOIN `course_payments` AS `cp`   ON `pshi`.`payment_plan_has_students_has_intakes_id` = `cp`.`payment_plan_has_students_has_intakes_id`
                                                        LEFT JOIN (
                                                            SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                                                            FROM panelty_payments GROUP BY course_payments_course_payments_id
                                                        ) AS `ppn` ON `cp`.`course_payments_id` = `ppn`.`course_payments_course_payments_id`
                                                       
                                                        WHERE `shi`.`students_has_intakes_id` = '" . $id . "'
                                                        GROUP BY `pshi`.`payment_plan_has_students_has_intakes_id`
                                                        ORDER BY `pp`.`payment_plan_id` ASC
                                                    ";

                                                        $paymentsCategory = Database::search($categoryQuery);

                                                        // For each payment plan, compute currency-converted summary totals in PHP
                                                        function getSummaryAmounts($db, $pphsiId, $courseCurrencyId)
                                                        {
                                                            // completed = status 8
                                                            $discounts = 0;
                                                            $penalties = 0;
                                                            $completed = 0;
                                                            $taxAmounts = 0;
                                                            $res = Database::search("SELECT paid_amount,
                                                            discount_amount,panelty_amount,
                                                             currencies_currency_id, currency_exchange_rate,
                                                              currency_exchange_rate_id, tax_rate, tax_amount
                                                                            FROM course_payment_has_payments cpp
                                                                            INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                                                            WHERE cp.payment_plan_has_students_has_intakes_id = '" . (int)$pphsiId . "'
                                                                            AND cpp.payment_status_payment_status_id = 3");
                                                            if ($res && $res->num_rows > 0) {
                                                                while ($r = $res->fetch_assoc()) {
                                                                    $rate = resolveExchangeRate(null, $r, $courseCurrencyId);
                                                                    $completed += convertToCourseAmount($r['paid_amount'], $rate);
                                                                    $discounts += convertToCourseAmount($r['discount_amount'], $rate);
                                                                    $penalties += convertToCourseAmount($r['panelty_amount'], $rate);
                                                                    $taxAmounts += convertToCourseAmount($r['tax_amount'], $rate); // Assuming a flat 15% tax rate for demonstration; replace with actual tax logic as needed
                                                                }
                                                            }

                                                            // pending = status 2,5
                                                            $pending = 0;
                                                            $res2 = Database::search("SELECT paid_amount,discount_amount,panelty_amount, currencies_currency_id, currency_exchange_rate, currency_exchange_rate_id
                                                                    FROM course_payment_has_payments cpp
                                                                    INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                                                    WHERE cp.payment_plan_has_students_has_intakes_id = '" . (int)$pphsiId . "'
                                                                    AND cpp.payment_status_payment_status_id = 2");
                                                            if ($res2 && $res2->num_rows > 0) {
                                                                while ($r = $res2->fetch_assoc()) {
                                                                    $rate = resolveExchangeRate(null, $r, $courseCurrencyId);
                                                                    $pending += convertToCourseAmount($r['paid_amount'], $rate);
                                                                    $discounts += convertToCourseAmount($r['discount_amount'], $rate);
                                                                    $penalties += convertToCourseAmount($r['panelty_amount'], $rate);
                                                                }
                                                            }

                                                            // rejected = status 3,7
                                                            $rejected = 0;
                                                            $res3 = Database::search("SELECT paid_amount, currencies_currency_id, currency_exchange_rate, currency_exchange_rate_id
                                                                                FROM course_payment_has_payments cpp
                                                                                INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                                                                WHERE cp.payment_plan_has_students_has_intakes_id = '" . (int)$pphsiId . "'
                                                                                AND cpp.payment_status_payment_status_id = 4");
                                                            if ($res3 && $res3->num_rows > 0) {
                                                                while ($r = $res3->fetch_assoc()) {
                                                                    $rate = resolveExchangeRate(null, $r, $courseCurrencyId);
                                                                    $rejected += convertToCourseAmount($r['paid_amount'], $rate);
                                                                }
                                                            }

                                                            return ['completed' => $completed, 'pending' => $pending, 'rejected' => $rejected, 'discounts' => $discounts, 'penalties' => $penalties, 'taxAmounts' => $taxAmounts];
                                                        }

                                                        if ($paymentsCategory && $paymentsCategory->num_rows > 0):
                                                            while ($data = $paymentsCategory->fetch_assoc()):
                                                                // PPSHI discount
                                                                if ($data['discount_type_discount_type_id'] == 3) {
                                                                    $data['discount'] = $data['amount'] * $data['discount'] / 100;
                                                                }

                                                                // Get currency-converted summaries
                                                                $summary = getSummaryAmounts(null, $data['pphsiId'], $data['course_currency_id']);
                                                                $completed_payments          = $summary['completed'];
                                                                $awaiting_approval_payments  = $summary['pending'];
                                                                $rejected_payments           = $summary['rejected'];
                                                                $given_discounts = $summary['discounts'];
                                                                $given_penalties = $summary['penalties'];
                                                                $taxAmounts = $summary['taxAmounts'];
                                                                $awaiting_payments           = $data['payable_amount'] - $completed_payments - $given_discounts + $given_penalties + $taxAmounts;
                                                        ?>
                                                                <div class="col-12 bl-solid p-4 m-0 g-0 mt-3">
                                                                    <div class="row p-0 g-0 m-0">
                                                                        <div class="col-12">
                                                                            <div class="row">
                                                                                <div class="col-8 col-xl-8" data-bs-toggle="collapse" role="button"
                                                                                    data-bs-target="#collapse-<?= $data['pphsiId'] ?>"
                                                                                    aria-expanded="false" aria-controls="collapse-<?= $data['pphsiId'] ?>">
                                                                                    <p class="text-16 dark-color fw-bold p-0 m-0 g-0">
                                                                                        <?= $data['payment_category'] ?> - <?= $data['code'] ?> <?= number_format($data['amount'], $data['decimal_places']) ?>
                                                                                    </p>
                                                                                    <span class="text-14 sub-text p-0 mt-1 g-0">Payment Plan : </span>
                                                                                    <span class="text-14 sub-text mt-1 fw-medium"><?= $data['payment_plan'] ?></span>
                                                                                </div>
                                                                                <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 13, $studentHasIntakeData['intake_id'])): ?>
                                                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                        <div class="d-flex flex-row align-items-center justify-content-end m-0 p-0">
                                                                                            <button class="custom-danger-btn w-100" onclick="window.location.href='update-student-payment-plan?payment-paln-id=<?= $data['pphsiId'] ?>'">Update Payment Plan</button>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                        <div class="d-flex flex-row align-items-center justify-content-end m-0 p-0">
                                                                                            <button class="custom-btn w-100" onclick="window.location.href='add-payment-intake-student?student-id=<?= $id ?>&payment-plan-id=<?= $data['pphsiId'] ?>'">Add Payment</button>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>

                                                                        <div class="overflow-x-auto p-0 mt-3 g-0 collapse" id="collapse-<?= $data['pphsiId'] ?>"
                                                                            data-bs-target="#accordionParent" aria-expanded="false" aria-controls="accordionParent">

                                                                            <!-- Summary Cards -->
                                                                            <div class="p-0 mb-4 g-0">
                                                                                <div class="row g-4 d-flex">
                                                                                    <?php
                                                                                    $cards = [
                                                                                        ['label' => 'Payable Amount',       'val' => $data['payable_amount']],
                                                                                        ['label' => 'Settled Amount',       'val' => $completed_payments],
                                                                                        ['label' => 'Total Discount',       'val' => $data['total_discounts'] + $given_discounts],
                                                                                        ['label' => 'Total Penalty Amount', 'val' => $data['total_panelties'] + $given_penalties],
                                                                                        ['label' => 'Total Tax',            'val' => $taxAmounts],
                                                                                        ['label' => 'Awaiting Payments',    'val' => $awaiting_payments],
                                                                                        ['label' => 'Awaiting Approval',    'val' => $awaiting_approval_payments],
                                                                                        ['label' => 'Rejected Payment',     'val' => $rejected_payments],
                                                                                    ];
                                                                                    foreach ($cards as $card):
                                                                                    ?>
                                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                            <div class="bl-solid p-3 w-100">
                                                                                                <div class="d-flex flex-row align-items-center">
                                                                                                    <label class="flex-grow-1 text-16 sub-text"><?= $card['label'] ?></label>
                                                                                                    <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                                </div>
                                                                                                <div class="mt-2">
                                                                                                    <span class="text-24 dark-color fw-semibold lh-lg"><?= $data['code'] ?> <?= number_format($card['val'], $data['decimal_places']) ?></span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    <?php endforeach; ?>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Detail Table -->
                                                                            <div class="table-responsive-wrapper">
                                                                                <div class="table-responsive overflow-visible">
                                                                                    <table class="table bl-solid m-0 p-0 g-0">
                                                                                        <thead>
                                                                                            <tr class="br-t-5">
                                                                                                <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" onchange="tableCheckEvents()">
                                                                                                        <span class="text-14 main-text fw-medium mx-2">Payment</span>
                                                                                                    </div>
                                                                                                </th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Due Date</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Discount</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Penalty</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Tax</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Payable</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Paid Amount</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Date Paid</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Balance</th>
                                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Payment Method</th>
                                                                                                <th class="py-3 bg-table-header">
                                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                                        <span class="text-14 main-text fw-medium">Status</span>
                                                                                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                                    </div>
                                                                                                </th>
                                                                                                <th class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            <?php
                                                                                            // ============================================================
                                                                                            // SECTION 3: DETAIL ROWS per course_payment
                                                                                            // ============================================================
                                                                                            $detailQuery = "
                                                                                            SELECT
                                                                                                `cp`.`course_payments_id`,
                                                                                                `ps`.`payment_status`, `ps`.`payment_status_id`,
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
                                                                                            LEFT JOIN (
                                                                                                SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                                                                                                FROM panelty_payments GROUP BY course_payments_course_payments_id
                                                                                            ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                                                            LEFT JOIN (
                                                                                                SELECT course_payments_course_payments_id,
                                                                                                    SUM(discount_amount) AS total_discount,
                                                                                                    SUM(panelty_amount)  AS total_sub_panelty_amount
                                                                                                FROM course_payment_has_payments
                                                                                                WHERE payment_status_payment_status_id IN (2,3,5,6,8)
                                                                                                GROUP BY course_payments_course_payments_id
                                                                                            ) cpp ON cp.course_payments_id = cpp.course_payments_course_payments_id
                                                                                            WHERE `shi`.`students_has_intakes_id` = '" . $id . "'
                                                                                            AND `pshi`.`payment_category_payment_category_id` = '" . (int)$data['payment_category_id'] . "'
                                                                                            ORDER BY `cp`.`course_payments_id` DESC
                                                                                        ";

                                                                                            $detailResults  = Database::search($detailQuery);
                                                                                            $results_count  = $detailResults ? $detailResults->num_rows : 0;
                                                                                            $result_per_page = 100;
                                                                                            $pageno          = 1;
                                                                                            $page_results    = ($pageno - 1) * $result_per_page;
                                                                                            $detailQueryPaged = $detailQuery . " LIMIT " . $result_per_page . " OFFSET " . $page_results;
                                                                                            $pagedResults    = Database::search($detailQueryPaged);

                                                                                            if ($pagedResults && $pagedResults->num_rows > 0):
                                                                                                while ($row = $pagedResults->fetch_assoc()):

                                                                                                    // CP-level discount
                                                                                                    $discountAmount = ($row['discount_type_id'] == 3)
                                                                                                        ? $row['amount'] / 100 * $row['discount']
                                                                                                        : $row['discount'];

                                                                                                    $totalDiscount    = $discountAmount;
                                                                                                    $totalPenalty     = $row['panelty_amount'];

                                                                                                    // Fetch receipts for this payment
                                                                                                    $convertedPaidTotal = 0;
                                                                                                    $receiptRows = [];
                                                                                                    $totalPaidAmount = 0;

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
                                                                                                    WHERE cpp.course_payments_course_payments_id = '" . (int)$row['course_payments_id'] . "'
                                                                                                    GROUP BY cpp.course_payment_has_payment_id
                                                                                                ");
                                                                                                    if ($receipts && $receipts->num_rows > 0) {
                                                                                                        while ($rec = $receipts->fetch_assoc()) {
                                                                                                            $rate = resolveExchangeRate(null, $rec, $row['course_currency_id']);
                                                                                                            $rec['converted_paid']       = convertToCourseAmount($rec['paid_amount'], $rate);
                                                                                                            $rec['is_foreign_currency']  = ($rate !== null);
                                                                                                            $rec['currency_exchange_rate']    = $rate;
                                                                                                            $convertedPaidTotal += $rec['converted_paid'];
                                                                                                            $receiptRows[] = $rec;
                                                                                                            if ($rec['payment_status_id'] == 3) $totalPaidAmount += $rec['converted_paid'];
                                                                                                            if ($rec['payment_status_id'] == 2 || $rec['payment_status_id'] == 3) {
                                                                                                                $totalDiscount += convertToCourseAmount($rec['discount_amount'], $rate);
                                                                                                                $totalPenalty += convertToCourseAmount($rec['panelty_amount'], $rate);
                                                                                                                $taxAmounts += convertToCourseAmount($rec['tax_amount'], $rate);
                                                                                                            };
                                                                                                        }
                                                                                                    }


                                                                                                    $payableAmount    = $row['amount'] - $totalDiscount + $totalPenalty;
                                                                                                    $balance = $payableAmount - $convertedPaidTotal;
                                                                                                    $paidAmount = $convertedPaidTotal;

                                                                                                    $status_classes = ["0" => "table-btn-purple", "1" => "table-btn-yellow", "2" => "table-btn-orange", "3" => "table-btn-success", "4" => "table-btn-danger", "5" => "table-btn-success", "6" => "table-btn-success", "7" => "table-btn-danger", "9" => "table-btn-success"];
                                                                                                    $sbutton_class  = $status_classes[$row['payment_status_id']] ?? 'table-btn-yellow';
                                                                                            ?>
                                                                                                    <!-- CP Main Row -->
                                                                                                    <tr>
                                                                                                        <td class="px-4 py-3 pe-0 align-middle">
                                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                <span class="text-14 main-text mx-2"><?= $row['payment_type'] ?></span>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['due_date'] ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($row['amount'], $row['decimal_places']) ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($totalDiscount, $row['decimal_places']) ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($totalPenalty, $row['decimal_places']) ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($taxAmounts, $row['decimal_places']) ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($payableAmount, $row['decimal_places']) ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($convertedPaidTotal, $row['decimal_places']) ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($balance, $row['decimal_places']) ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"></span></td>
                                                                                                        <td class="py-3 align-middle">
                                                                                                            <button class="table-btn <?= $sbutton_class ?> w-100"><?= $row['payment_status'] ?></button>
                                                                                                        </td>
                                                                                                        <td class="px-4 py-3 align-middle">
                                                                                                            <div class="dropdown dropup position-relative">
                                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5 z-3 position-absolute">
                                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="intake-student-payment?payment-id=<?= $row['course_payments_id'] ?>">
                                                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon> View</a></li>
                                                                                                                    <?php
                                                                                                                    if (
                                                                                                                        SessionUser::ifUserIsPro($sessionUserRole)
                                                                                                                        || UserPermissions::validPermisisonType('payments', 0)
                                                                                                                        || UserPermissions::validPermisisonType('intake_payments', 24, $studentHasIntakeData['intake_id'] ?? null)
                                                                                                                    ):
                                                                                                                        if ($balance > 0): ?>
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="add-receipt-to-student-payment?payment-id=<?= $row['course_payments_id'] ?>">
                                                                                                                                    <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon> Add Reciept</a></li>
                                                                                                                        <?php endif; ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="update-student-payment?payment-id=<?= $row['course_payments_id'] ?>">
                                                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon> Edit</a></li>
                                                                                                                        <?php if (count($receiptRows) === 0): ?>
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" onclick="removeRequestStudentPayment(<?= $row['course_payments_id'] ?>)">
                                                                                                                                    <iconify-icon icon="material-symbols:delete-outline-rounded" width="20" height="20" class="sub-text me-3"></iconify-icon> Remove</a></li>
                                                                                                                        <?php endif; ?>
                                                                                                                    <?php endif; ?>
                                                                                                                </ul>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    </tr>

                                                                                                    <?php
                                                                                                    // Receipt sub-rows
                                                                                                    foreach ($receiptRows as $rec):
                                                                                                        $mclasses = ["1" => "table-btn-orange", "2" => "table-btn-purple", "3" => "table-btn-pink"];
                                                                                                        $mclass   = $mclasses[$rec['payment_method_id']] ?? 'table-btn-yellow';
                                                                                                        $sclasses = ["0" => "table-btn-purple", "1" => "table-btn-yellow", "2" => "table-btn-orange", "3" => "table-btn-success", "4" => "table-btn-danger", "5" => "table-btn-orange", "6" => "table-btn-success", "7" => "table-btn-danger", "8" => "table-btn-success"];
                                                                                                        $sclass   = $sclasses[$rec['payment_status_id']] ?? 'table-btn-yellow';
                                                                                                    ?>
                                                                                                        <!-- Receipt Sub-Row -->
                                                                                                        <tr>
                                                                                                            <td colspan="3" class="px-4 py-3 pe-0 align-middle">
                                                                                                                <div class="d-flex flex-row align-items-center justify-content-start w-100 p-0 g-0 m-0">
                                                                                                                    <iconify-icon icon="ci:arrow-sub-down-right" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                    <span class="text-14 mx-2 table-btn-success active text-white fw-semibold px-3 py-2">Receipt : #R<?= $rec['course_payment_has_payment_id'] ?></span>
                                                                                                                </div>
                                                                                                            </td>
                                                                                                            <!-- Discount -->
                                                                                                            <td class="py-2 align-middle text-end">
                                                                                                                <span class="text-14 main-text">
                                                                                                                    <?= ($rec['discount_amount'] == 0) ? '' : $rec['paid_currency_code'] . ' ' . number_format($rec['discount_amount'], $row['decimal_places']) ?>
                                                                                                                </span>
                                                                                                            </td>
                                                                                                            <!-- Penalty -->
                                                                                                            <td class="py-3 align-middle text-end">
                                                                                                                <span class="text-14 main-text">
                                                                                                                    <?= (empty($rec['panelty_amount']) || $rec['panelty_amount'] == 0) ? '' : $rec['paid_currency_code'] . ' ' . number_format($rec['panelty_amount'], $row['decimal_places']) ?>
                                                                                                                </span>
                                                                                                            </td>
                                                                                                            <td class="py-3 align-middle text-end">
                                                                                                                <span class="text-14 main-text">
                                                                                                                    <?= (empty($rec['tax_amount']) || $rec['tax_amount'] == 0) ? '' : $rec['paid_currency_code'] . ' ' . number_format($rec['tax_amount'], $row['decimal_places']) ?>
                                                                                                                </span>
                                                                                                            </td>
                                                                                                            <!-- Payable blank -->
                                                                                                            <td class="py-3 align-middle text-end"></td>
                                                                                                            <!-- Paid Amount: converted to course currency -->
                                                                                                            <td class="py-3 align-middle text-end">
                                                                                                                <span class="text-14 main-text">
                                                                                                                    <?php if ($rec['paid_amount'] != 0): ?>
                                                                                                                        <?= $rec['paid_currency_code'] . ' ' . number_format($rec['paid_amount'], $row['decimal_places']) ?>
                                                                                                                        <?php if ($rec['is_foreign_currency']): ?>
                                                                                                                            <br><small class="text-muted" title="Original payment amount">
                                                                                                                                (<?= htmlspecialchars($row['code'] ?? '') ?>
                                                                                                                                <?= number_format($rec['converted_paid'], $rec['paid_decimal_places'] ?? 2) ?>
                                                                                                                                @ <?= number_format($rec['currency_exchange_rate'] ?? 0, 2) ?>)
                                                                                                                            </small>
                                                                                                                        <?php endif; ?>
                                                                                                                    <?php endif; ?>
                                                                                                                </span>
                                                                                                            </td>
                                                                                                            <!-- Date Paid -->
                                                                                                            <td class="py-3 align-middle"><span class="text-14 main-text"><?= $rec['date_paid'] ?></span></td>
                                                                                                            <!-- Balance blank -->
                                                                                                            <td class="py-3 align-middle"></td>
                                                                                                            <!-- Payment Method -->
                                                                                                            <td class="pe-4 py-2 align-middle">
                                                                                                                <button class="table-btn <?= $mclass ?> w-100"><?= $rec['payment_method'] ?></button>
                                                                                                            </td>
                                                                                                            <!-- Status -->
                                                                                                            <td class="py-3 align-middle">
                                                                                                                <button class="table-btn <?= $sclass ?> w-100"><?= $rec['payment_status'] ?></button>
                                                                                                            </td>
                                                                                                            <!-- Action -->
                                                                                                            <td class="px-4 py-3 align-middle">
                                                                                                                <div class="dropdown dropup position-relative">
                                                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5 z-3 position-absolute">
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="intake-student-payment?payment-id=<?= $row['course_payments_id'] ?>">
                                                                                                                                <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon> View</a></li>
                                                                                                                    </ul>
                                                                                                                </div>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                <?php
                                                                                                    endforeach; // receipts
                                                                                                endwhile; // detail rows
                                                                                            else:
                                                                                                ?>
                                                                                                <tr class="justify-content-center align-middle">
                                                                                                    <td colspan="13" class="align-middle justify-content-center">
                                                                                                        <div class="d-flex flex-column align-items-center">
                                                                                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                        </div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            <?php endif; // detail rows 
                                                                                            ?>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            </div>
                                                                        </div><!-- end collapse -->
                                                                    </div>
                                                                </div><!-- end card -->
                                                            <?php
                                                            endwhile; // payment plans
                                                        else:
                                                            ?>
                                                            <div class="col-12 px-4 py-3 component-theme h-100">
                                                                <div class="row align-items-center justify-content-center h-100">
                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div><!-- end accordionParent -->
                                                </div><!-- end history card -->

                                            </div><!-- end row p-4 -->
                                            <div class="flex-grow-1"></div>
                                            <footer><?php include './includes/footer.php' ?></footer>
                                        </div><!-- end real-list -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                } // end permission check
            } // end student found
        } // end GET check
    } // end role check
} // end session check
?>