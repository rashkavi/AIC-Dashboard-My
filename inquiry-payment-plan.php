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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['inquiry-id']) && !empty($_GET['inquiry-id']))) {
            header("Location: ./users");
        } else {

            $id = $_GET['inquiry-id'];

            $studentHasIntake = Database::search("SELECT
                `faculty`,
                `faculty_id`,
                `programme`,
                `programme_id`,
                `intake`,
                `intake_id`,
                `students_has_intakes`.`student_id`,
                `first_name`,
                `last_name`,
                `title`,
                `students_has_intakes_id`
            FROM `students_has_intakes`
            INNER JOIN `students` 
                ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `login_details`
                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`    
            INNER JOIN `title` 
                ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `intakes` 
                ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `students_has_intakes`.`students_student_id` = '" . $id . "'
        ");


            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./students");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) ||  UserPermissions::validPermisisonType('leads') || UserPermissions::validPermisisonType('payments'))) {
                    header("Location: ./faculties");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Inquiry Payment Plan - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("Payments");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Payments");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-dashboards.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Student Payments - #<?= $studentHasIntakeData['student_id'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $studentHasIntakeData['faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $studentHasIntakeData['programme_id'] ?>'><?= $studentHasIntakeData['programme']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $studentHasIntakeData['intake_id'] ?>'><?= $studentHasIntakeData['intake']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo ucwords(strtolower($studentHasIntakeData['title'] . "." . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name'])); ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $studentHasIntakeData['intake_id'])) {
                                                        ?>
                                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" type="button" onclick="location.href='add-payment-plan?student-id=<?= $id ?>'">
                                                                    Add Payment Plan
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <?php
                                                $query = "SELECT
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
                                                                        `pt`.`payment_type`,
                                                                        `pc`.`payment_category`,
                                                                        IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                        IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                                                        IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                        IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                                        FROM `course_payments` AS `cp`
                                                                    INNER JOIN `payment_status` AS `ps` 
                                                                        ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                   
                                                                    INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                                                                        ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                        INNER JOIN `payment_category` AS `pc`
                                                                        ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                                        INNER JOIN `currencies` AS `c`
                                                                        ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                                    INNER JOIN `payment_type` AS `pt`
                                                                        ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`    
                                                                    INNER JOIN `students_has_intakes` AS `shi` 
                                                                        ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                    INNER JOIN `students` AS `s` 
                                                                        ON `shi`.`students_student_id` = `s`.`student_id`
                                                                    INNER JOIN `login_details` AS `ld`
                                                                        ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                                                                    INNER JOIN `title` AS `t` 
                                                                        ON `s`.`title_title_id` = `t`.`title_id`
                                                                   LEFT JOIN (
                                                                        SELECT
                                                                            course_payments_course_payments_id,
                                                                            SUM(amount) AS panelty_amount
                                                                        FROM panelty_payments
                                                                        GROUP BY course_payments_course_payments_id
                                                                    ) pp
                                                                        ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                                
                                                                    LEFT JOIN (
                                                                        SELECT
                                                                            course_payments_course_payments_id,
                                                                            SUM(paid_amount) AS paid_amount,
                                                                            SUM(discount_amount) AS total_discount,
                                                                            SUM(panelty_amount) AS total_sub_panelty_amount
                                                                        FROM course_payment_has_payments
                                                                        WHERE payment_status_payment_status_id IN (2,3)
                                                                        GROUP BY course_payments_course_payments_id
                                                                    ) cpp
                                                                        ON cp.course_payments_id = cpp.course_payments_course_payments_id 
                                                                    WHERE `shi`.`students_student_id` = '" . $id . "'
                                                                    AND `cp`.`payment_status_payment_status_id` = '1'
                                                                    GROUP BY `cp`.`course_payments_id`
                                                                    ORDER BY `cp`.`course_payments_id` DESC
                                                                    ";

                                                $payments = Database::search($query);

                                                if ($payments->num_rows > 0) {
                                                ?>
                                                    <div class="col-12 p-4 component-theme mb-4" id="paymentsCollapse" data-bs-parent="#accordionParent">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 mb-4">
                                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                    <span class="text-16 main-text fw-medium">Awaiting Payments</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <div class="table-responsive-wrapper">
                                                                        <div class="table-responsive overflow-visible" id="payments-table-container">
                                                                            <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                <span class="text-14 main-text fw-medium mx-2">Payment</span>
                                                                                            </div>
                                                                                        </th>

                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment Category</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Discount</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Penalty Amount</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid Amount</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Balance</th>
                                                                                        <th scope="col" class="py-3 bg-table-header">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <span class="text-14 main-text fw-medium">Status</span>
                                                                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                            </div>
                                                                                        </th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                        <?php
                                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 10, $studentHasIntakeData['intake_id']) || UserPermissions::hasPermission('intake_payments', 11, $studentHasIntakeData['intake_id'])) {
                                                                                        ?>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                        <?php
                                                                                        }
                                                                                        ?>

                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="user-table-data">
                                                                                    <?php
                                                                                    if ($payments->num_rows > 0) {
                                                                                        while ($data = $payments->fetch_assoc()) {
                                                                                            $discountAmount = $data["discount"];
                                                                                            if ($data["discount_type_discount_type_id"] == 3) {
                                                                                                $discountAmount = $data["amount"] / 100 * $data["discount"];
                                                                                            }
                                                                                            $payableAmount = $data["amount"] - $discountAmount - $data["total_discount"] + $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                                                                            $balance = $payableAmount - $data["paid_amount"];

                                                                                            $totalDiscount = $data["total_discount"] + $discountAmount;
                                                                                            $totalPenalty = $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                                                                    ?>
                                                                                            <tr>
                                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["payment_type"]; ?></span>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["payment_category"]; ?></span></td>
                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["code"] ?> <?= number_format($data["amount"], $data["decimal_places"]); ?></span></td>
                                                                                                <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] ?> <?= number_format($totalDiscount, $data["decimal_places"]); ?></span></td>
                                                                                                <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] ?> <?= number_format($totalPenalty, $data["decimal_places"]); ?></span></td>
                                                                                                <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] ?> <?= number_format($data["paid_amount"], $data["decimal_places"]); ?></span></td>
                                                                                                <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] ?> <?= number_format($balance, $data["decimal_places"]); ?></span></td>

                                                                                                <td class="pe-4 py-2 align-middle">
                                                                                                    <?php
                                                                                                    $status_classes = [
                                                                                                        "0"     => "table-btn-purple",
                                                                                                        "1"  => "table-btn-yellow",
                                                                                                        "2"  => "table-btn-orange",
                                                                                                        "3"  => "table-btn-success",
                                                                                                        "4"  => "table-btn-danger",
                                                                                                        "5"  => "table-btn-orange",
                                                                                                        "6"  => "table-btn-success",
                                                                                                        "7"  => "table-btn-danger",
                                                                                                        "8"  => "table-btn-success",
                                                                                                    ];
                                                                                                    $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                                                                    ?>
                                                                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["payment_status"]; ?></button>
                                                                                                </td>

                                                                                                <td class="py-2 pe-4 align-middle">
                                                                                                    <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='intake-student-payment?payment-id=<?= $data['course_payments_id'] ?>'">View</button>
                                                                                                </td>
                                                                                                <?php
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 10) ||  UserPermissions::hasPermission('payments', 11)) {
                                                                                                ?>
                                                                                                    <td class="px-4 py-3 align-middle">
                                                                                                        <div class="dropdown dropup position-relative">
                                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20"
                                                                                                                class="justify-content-center sub-text cursor-pointer"
                                                                                                                id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                                            </iconify-icon>

                                                                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5 z-3 position-absolute"
                                                                                                                aria-labelledby="dropdownMenuButton2">

                                                                                                                <?php
                                                                                                                if (
                                                                                                                    SessionUser::ifUserIsPro($sessionUserRole) ||
                                                                                                                    UserPermissions::hasPermission('payments', 10) || UserPermissions::validPermisisonType('leads')
                                                                                                                ) {
                                                                                                                ?>
                                                                                                                    <li>
                                                                                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                            href="add-receipt-to-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>&inquiry-id=<?= $id ?>">
                                                                                                                            <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                            Add Reciept
                                                                                                                        </a>
                                                                                                                    </li>

                                                                                                                <?php
                                                                                                                }
                                                                                                                if (
                                                                                                                    SessionUser::ifUserIsPro($sessionUserRole) ||
                                                                                                                    UserPermissions::hasPermission('payments', 10)
                                                                                                                ) {
                                                                                                                ?>
                                                                                                                    <li>
                                                                                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                            href="update-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>">
                                                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                            Edit
                                                                                                                        </a>
                                                                                                                    </li>
                                                                                                                <?php
                                                                                                                }
                                                                                                                if (
                                                                                                                    SessionUser::ifUserIsPro($sessionUserRole) ||
                                                                                                                    UserPermissions::hasPermission('payments', 11)
                                                                                                                ) {
                                                                                                                ?>
                                                                                                                    <li>
                                                                                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                            onclick="removeRequestStudentPayment(<?= $data['course_payments_id'] ?>)">
                                                                                                                            <iconify-icon icon="material-symbols:delete-outline-rounded" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                            Remove
                                                                                                                        </a>
                                                                                                                    </li>
                                                                                                                <?php
                                                                                                                }
                                                                                                                ?>
                                                                                                            </ul>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
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
                                                <?php
                                                }
                                                ?>

                                                <div class="col-12 px-4 py-3 pe-4 g-0 component-theme mb-10">
                                                    <div class="row p-0 m-0 g-0 align-content-center" id="accordionParent">
                                                        <span class="text-16 main-text fw-medium">Student Payment History</span>
                                                        <?php

                                                        $query = "
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

                                                -- Main plan amount only
                                                COALESCE(`pshi`.`amount`, 0) AS `total_amount`,

                                                -- PPSHI discount
                                                COALESCE(
                                                    CASE
                                                        WHEN `pshi`.`discount_type_discount_type_id` = 2 THEN `pshi`.`discount`
                                                        WHEN `pshi`.`discount_type_discount_type_id` = 3 THEN (`pshi`.`amount` * `pshi`.`discount` / 100)
                                                        ELSE 0
                                                    END
                                                , 0) AS `ppshi_total_discount`,

                                                -- CP discounts
                                                IFNULL(SUM(
                                                    CASE
                                                        WHEN `cp`.`discount_type_discount_type_id` = 2 THEN COALESCE(`cp`.`discount`, 0)
                                                        WHEN `cp`.`discount_type_discount_type_id` = 3 THEN (COALESCE(`cp`.`amount`, 0) * COALESCE(`cp`.`discount`, 0) / 100)
                                                        ELSE 0
                                                    END
                                                ), 0) AS `cp_total_discount`,

                                                -- CPP discounts
                                                IFNULL(SUM(COALESCE(`cpp_all`.`total_discount`, 0)), 0) AS `cpp_total_discount`,

                                                -- Total discounts = PPSHI + CP + CPP
                                                (
                                                    COALESCE(
                                                        CASE
                                                            WHEN `pshi`.`discount_type_discount_type_id` = 2 THEN `pshi`.`discount`
                                                            WHEN `pshi`.`discount_type_discount_type_id` = 3 THEN (`pshi`.`amount` * `pshi`.`discount` / 100)
                                                            ELSE 0
                                                        END
                                                    , 0)
                                                    +
                                                    IFNULL(SUM(
                                                        CASE
                                                            WHEN `cp`.`discount_type_discount_type_id` = 2 THEN COALESCE(`cp`.`discount`, 0)
                                                            WHEN `cp`.`discount_type_discount_type_id` = 3 THEN (COALESCE(`cp`.`amount`, 0) * COALESCE(`cp`.`discount`, 0) / 100)
                                                            ELSE 0
                                                        END
                                                    ), 0)
                                                    +
                                                    IFNULL(SUM(COALESCE(`cpp_all`.`total_discount`, 0)), 0)
                                                ) AS `total_discounts`,

                                                -- Main penalties
                                                IFNULL(SUM(COALESCE(`ppn`.`panelty_amount`, 0)), 0) AS `main_total_panelty`,

                                                -- CPP penalties
                                                IFNULL(SUM(COALESCE(`cpp_all`.`total_sub_panelty_amount`, 0)), 0) AS `cpp_total_panelty`,

                                                -- Total penalties = panelty_payments + cpp penalties
                                                (
                                                    IFNULL(SUM(COALESCE(`ppn`.`panelty_amount`, 0)), 0)
                                                    +
                                                    IFNULL(SUM(COALESCE(`cpp_all`.`total_sub_panelty_amount`, 0)), 0)
                                                ) AS `total_panelties`,

                                                -- Payable amount = PPSHI amount - total discounts + total penalties
                                                (
                                                    COALESCE(`pshi`.`amount`, 0)
                                                    -
                                                    (
                                                        COALESCE(
                                                            CASE
                                                                WHEN `pshi`.`discount_type_discount_type_id` = 2 THEN `pshi`.`discount`
                                                                WHEN `pshi`.`discount_type_discount_type_id` = 3 THEN (`pshi`.`amount` * `pshi`.`discount` / 100)
                                                                ELSE 0
                                                            END
                                                        , 0)
                                                        +
                                                        IFNULL(SUM(
                                                            CASE
                                                                WHEN `cp`.`discount_type_discount_type_id` = 2 THEN COALESCE(`cp`.`discount`, 0)
                                                                WHEN `cp`.`discount_type_discount_type_id` = 3 THEN (COALESCE(`cp`.`amount`, 0) * COALESCE(`cp`.`discount`, 0) / 100)
                                                                ELSE 0
                                                            END
                                                        ), 0)
                                                        +
                                                        IFNULL(SUM(COALESCE(`cpp_all`.`total_discount`, 0)), 0)
                                                    )
                                                    +
                                                    (
                                                        IFNULL(SUM(COALESCE(`ppn`.`panelty_amount`, 0)), 0)
                                                        +
                                                        IFNULL(SUM(COALESCE(`cpp_all`.`total_sub_panelty_amount`, 0)), 0)
                                                    )
                                                ) AS `payable_amount`,

                                                -- Completed payments = cpp paid_amount only
                                                IFNULL(SUM(COALESCE(`cpp_completed`.`paid_amount`, 0)), 0) AS `completed_payments`,

                                                -- Pending approval = cpp paid_amount only
                                                IFNULL(SUM(COALESCE(`cpp_pending`.`paid_amount`, 0)), 0) AS `awaiting_approval_payments`,

                                                -- Rejected = cpp paid_amount only
                                                IFNULL(SUM(COALESCE(`cpp_rejected`.`paid_amount`, 0)), 0) AS `rejected_payments`,

                                                -- Awaiting = payable - completed
                                                (
                                                    (
                                                        COALESCE(`pshi`.`amount`, 0)
                                                        -
                                                        (
                                                            COALESCE(
                                                                CASE
                                                                    WHEN `pshi`.`discount_type_discount_type_id` = 2 THEN `pshi`.`discount`
                                                                    WHEN `pshi`.`discount_type_discount_type_id` = 3 THEN (`pshi`.`amount` * `pshi`.`discount` / 100)
                                                                    ELSE 0
                                                                END
                                                            , 0)
                                                            +
                                                            IFNULL(SUM(
                                                                CASE
                                                                    WHEN `cp`.`discount_type_discount_type_id` = 2 THEN COALESCE(`cp`.`discount`, 0)
                                                                    WHEN `cp`.`discount_type_discount_type_id` = 3 THEN (COALESCE(`cp`.`amount`, 0) * COALESCE(`cp`.`discount`, 0) / 100)
                                                                    ELSE 0
                                                                END
                                                            ), 0)
                                                            +
                                                            IFNULL(SUM(COALESCE(`cpp_all`.`total_discount`, 0)), 0)
                                                        )
                                                        +
                                                        (
                                                            IFNULL(SUM(COALESCE(`ppn`.`panelty_amount`, 0)), 0)
                                                            +
                                                            IFNULL(SUM(COALESCE(`cpp_all`.`total_sub_panelty_amount`, 0)), 0)
                                                        )
                                                    )
                                                    -
                                                    IFNULL(SUM(COALESCE(`cpp_completed`.`paid_amount`, 0)), 0)
                                                ) AS `awaiting_payments`

                                            FROM `payment_plan_has_students_has_intakes` AS `pshi`
                                            INNER JOIN `discount_type` AS `dt`
                                                ON `pshi`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                                            INNER JOIN `students_has_intakes` AS `shi`
                                                ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                            INNER JOIN `payment_plan` AS `pp`
                                                ON `pshi`.`payment_plan_payment_plan_id` = `pp`.`payment_plan_id`
                                            INNER JOIN `payment_category` AS `pc`
                                                ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                            INNER JOIN `currencies` AS `c`
                                                ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                            LEFT JOIN `course_payments` AS `cp`
                                                ON `pshi`.`payment_plan_has_students_has_intakes_id` = `cp`.`payment_plan_has_students_has_intakes_id`

                                            LEFT JOIN (
                                                SELECT
                                                    `course_payments_course_payments_id`,
                                                    SUM(`amount`) AS `panelty_amount`
                                                FROM `panelty_payments`
                                                GROUP BY `course_payments_course_payments_id`
                                            ) AS `ppn`
                                                ON `cp`.`course_payments_id` = `ppn`.`course_payments_course_payments_id`

                                            LEFT JOIN (
                                                SELECT
                                                    `course_payments_course_payments_id`,
                                                    SUM(`discount_amount`) AS `total_discount`,
                                                    SUM(`panelty_amount`) AS `total_sub_panelty_amount`
                                                FROM `course_payment_has_payments`
                                                GROUP BY `course_payments_course_payments_id`
                                            ) AS `cpp_all`
                                                ON `cp`.`course_payments_id` = `cpp_all`.`course_payments_course_payments_id`

                                            LEFT JOIN (
                                                SELECT
                                                    `course_payments_course_payments_id`,
                                                    SUM(`paid_amount`) AS `paid_amount`
                                                FROM `course_payment_has_payments`
                                                WHERE `payment_status_payment_status_id` = 8
                                                GROUP BY `course_payments_course_payments_id`
                                            ) AS `cpp_completed`
                                                ON `cp`.`course_payments_id` = `cpp_completed`.`course_payments_course_payments_id`

                                            LEFT JOIN (
                                                SELECT
                                                    `course_payments_course_payments_id`,
                                                    SUM(`paid_amount`) AS `paid_amount`
                                                FROM `course_payment_has_payments`
                                                WHERE `payment_status_payment_status_id` IN (2,5)
                                                GROUP BY `course_payments_course_payments_id`
                                            ) AS `cpp_pending`
                                                ON `cp`.`course_payments_id` = `cpp_pending`.`course_payments_course_payments_id`

                                            LEFT JOIN (
                                                SELECT
                                                    `course_payments_course_payments_id`,
                                                    SUM(`paid_amount`) AS `paid_amount`
                                                FROM `course_payment_has_payments`
                                                WHERE `payment_status_payment_status_id` IN (3,7)
                                                GROUP BY `course_payments_course_payments_id`
                                            ) AS `cpp_rejected`
                                                ON `cp`.`course_payments_id` = `cpp_rejected`.`course_payments_course_payments_id`

                                            WHERE `shi`.`students_student_id` = '" . $id . "'

                                            GROUP BY `pshi`.`payment_plan_has_students_has_intakes_id`

                                            ORDER BY `pp`.`payment_plan_id` ASC
                                            ";
                                                        $paymentsCategory = Database::search($query);

                                                        if ($paymentsCategory->num_rows > 0) {
                                                            while ($data = $paymentsCategory->fetch_assoc()) {

                                                                if ($data['discount_type_discount_type_id'] == 3) {
                                                                    $data['discount'] = $data['amount'] * $data['discount'] / 100;
                                                                }

                                                                $totalDue = $data['amount'] - $data['discount'] - $data['completed_payments'] - $data['awaiting_approval_payments'] - $data['total_discounts'];
                                                        ?>
                                                                <div class="col-12 bl-solid p-4 m-0 g-0 mt-3">
                                                                    <div class="row p-0 g-0 m-0">
                                                                        <div class="col-12">
                                                                            <div class="row">
                                                                                <div class="col-8 col-xl-8" data-bs-toggle="collapse" role="button" data-bs-target="#collapse-<?= $data['pphsiId'] ?>" aria-expanded="false" aria-controls="collapse-<?= $data['pphsiId'] ?>">
                                                                                    <p class="text-16 dark-color fw-bold p-0 m-0 g-0"><?php echo $data['payment_category'] ?> - <?= $data['code'] ?> <?php echo number_format($data['amount'], $data["decimal_places"]) ?></p>
                                                                                    <span class="text-14 sub-text p-0 mt-1 g-0">Payment Plan : </span><span class="text-14 sub-text mt-1 fw-medium"><?php echo $data['payment_plan'] ?></span>
                                                                                </div>
                                                                                <?php
                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 13, $studentHasIntakeData['intake_id'])) {
                                                                                ?>
                                                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                        <div class="d-flex flex-row align-items-center justify-content-end m-0 p-0 ">
                                                                                            <button class="custom-danger-btn w-100" onclick="window.location.href='update-student-payment-plan?payment-paln-id=<?= $data['pphsiId'] ?>'">Update Payment Plan</button>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                        <div class="d-flex flex-row align-items-center justify-content-end m-0 p-0 ">
                                                                                            <button class="custom-btn w-100" onclick="window.location.href='add-payment-intake-student?student-id=<?= $id ?>&payment-plan-id=<?= $data['pphsiId'] ?>'">Add Payment</button>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </div>
                                                                        </div>
                                                                        <div class="overflow-x-auto p-0 mt-3 g-0 collapse" id="collapse-<?= $data['pphsiId'] ?>" data-bs-target="#accordionParent" aria-expanded="false" aria-controls="accordionParent">
                                                                            <div class="p-0 mb-4 g-0">
                                                                                <div class="row g-4 d-flex">
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Payable Amount</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['payable_amount'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Settled Amount</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['completed_payments'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Total Discount</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['total_discounts'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Total Penalty Amount</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['total_panelties'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Accepted Payment</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['completed_payments'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Awaiting Payments</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['awaiting_payments'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Awaiting Approval</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['awaiting_approval_payments'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                                        <div class="bl-solid p-3 w-100">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <label class="flex-grow-1 text-16 sub-text">Rejected Payment</label>
                                                                                                <iconify-icon icon="mdi:payment-settings" width="25" height="25" class="dark-theme"></iconify-icon>
                                                                                            </div>
                                                                                            <div class="mt-2">
                                                                                                <span class="text-24 dark-color fw-semibold lh-lg"><?= $data["code"] ?> <?= number_format($data['rejected_payments'], $data["decimal_places"]) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="table-responsive-wrapper">
                                                                                <div class="table-responsive overflow-visible">
                                                                                    <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                                                        <thead>
                                                                                            <tr class="br-t-5">
                                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                        <span class="text-14 main-text fw-medium mx-2">Payment</span>
                                                                                                    </div>
                                                                                                </th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Due Date</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Discount</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Penalty Payment</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payable Payment</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid Amount</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Paid</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Balance</th>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment Method</th>
                                                                                                <th scope="col" class="py-3 bg-table-header">
                                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                                        <span class="text-14 main-text fw-medium">Status</span>
                                                                                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                                    </div>
                                                                                                </th>
                                                                                                <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody id="user-table-data">
                                                                                            <?php
                                                                                            $query = "
                                                                        SELECT 
                                                                            `cp`.`course_payments_id`,
                                                                            `ps`.`payment_status`,
                                                                            `ps`.`payment_status_id`,
                                                                           
                                                                            `cp`.`amount`,
                                                                            `cp`.`discount`,
                                                                            `cp`.`due_date`,
                                                                            `pt`.`payment_type`,
                                                                            `dt`.`discount_type`,
                                                                            `dt`.`discount_type_id`,
                                                                            `c`.`code`,
                                                                            `c`.`decimal_places`,
                                                                            IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                            IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                                                            IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                            IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                                        FROM `course_payments` AS `cp`
                                                                        INNER JOIN `payment_type` AS `pt`
                                                                            ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
                                                                        INNER JOIN `discount_type` AS `dt`
                                                                            ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                                                                        INNER JOIN `payment_status` AS `ps` 
                                                                            ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                       
                                                                        INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                                                                            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                        INNER JOIN `payment_category` AS `pc`
                                                                            ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                                        INNER JOIN `currencies` AS `c`
                                                                            ON `pc`.`currencies_currency_id` = `c`.`currency_id`    
                                                                        INNER JOIN `students_has_intakes` AS `shi` 
                                                                            ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                         LEFT JOIN (
                                                                            SELECT
                                                                                course_payments_course_payments_id,
                                                                                SUM(amount) AS panelty_amount
                                                                            FROM panelty_payments
                                                                            GROUP BY course_payments_course_payments_id
                                                                        ) pp
                                                                            ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                                    
                                                                        LEFT JOIN (
                                                                            SELECT
                                                                                course_payments_course_payments_id,
                                                                                SUM(paid_amount) AS paid_amount,
                                                                                SUM(discount_amount) AS total_discount,
                                                                                SUM(panelty_amount) AS total_sub_panelty_amount
                                                                            FROM course_payment_has_payments
                                                                            WHERE payment_status_payment_status_id IN (2,3,5,6,8)
                                                                            GROUP BY course_payments_course_payments_id
                                                                        ) cpp
                                                                            ON cp.course_payments_id = cpp.course_payments_course_payments_id 
                                                                        WHERE 
                                                                            `shi`.`students_student_id` = '" . $id . "'
                                                                        AND `pshi`.`payment_category_payment_category_id` = '" . $data["payment_category_id"] . "'
                                                                        ORDER BY `cp`.`course_payments_id` DESC
                                                                    ";

                                                                                            $results = Database::search($query);
                                                                                            $pageno = 1;
                                                                                            $results_count = $results->num_rows;
                                                                                            $result_per_page = 10;

                                                                                            $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                                                                                            $page_results = ($pageno - 1) * $result_per_page;

                                                                                            $query_paginated = $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results;
                                                                                            $payments = Database::search($query_paginated);

                                                                                            if ($payments->num_rows > 0) {
                                                                                                while ($data = $payments->fetch_assoc()) {
                                                                                                    $discountAmount = $data["discount"];
                                                                                                    if ($data["discount_type_id"] == 3) {
                                                                                                        $discountAmount = $data["amount"] / 100 * $data["discount"];
                                                                                                    }
                                                                                                    $payableAmount = $data["amount"] - $discountAmount - $data["total_discount"] + $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                                                                                    $balance = $payableAmount - $data["paid_amount"];

                                                                                                    $total_amount = $data["amount"];
                                                                                                    $total_discount = $discountAmount + $data["total_discount"];
                                                                                                    $total_panelty_amount = $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                                                                                    $total_payable_amount = $payableAmount;
                                                                                                    $total_paid_amount = $data["paid_amount"];
                                                                                                    $total_balance = $balance;

                                                                                                    $paidPayments = Database::search("SELECT * FROM `course_payment_has_payments` 
                                                                                                    INNER JOIN `payment_method` ON `payment_method_payment_method_id` = `payment_method_id`
                                                                                                    INNER JOIN `payment_status` ON `payment_status_payment_status_id` = `payment_status_id`
                                                                                                    WHERE `course_payments_course_payments_id` = '" . $data["course_payments_id"] . "'");
                                                                                            ?>
                                                                                                    <tr>
                                                                                                        <td class="px-4 py-3 pe-0 align-middle">
                                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                <span class="text-14 main-text mx-2"><?php echo $data["payment_type"]; ?></span>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data['due_date'] ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] . " " . number_format($data["amount"], $data["decimal_places"]); ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] . " " . number_format($total_discount, $data["decimal_places"]); ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] . " " . number_format($total_panelty_amount, $data["decimal_places"]); ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] . " " . number_format($total_payable_amount, $data["decimal_places"]); ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] . " " . number_format($total_paid_amount, $data["decimal_places"]); ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $data["code"] . " " . number_format($total_balance, $data["decimal_places"]); ?></span></td>
                                                                                                        <td class="py-3 align-middle"><span class="text-14 main-text"></span></td>

                                                                                                        <td class="py-3 align-middle">
                                                                                                            <?php
                                                                                                            $status_classes = [
                                                                                                                "0"     => "table-btn-purple",
                                                                                                                "1"  => "table-btn-yellow",
                                                                                                                "2"  => "table-btn-orange",
                                                                                                                "3"  => "table-btn-success",
                                                                                                                "4"  => "table-btn-danger",
                                                                                                          
                                                                                                                "5"  => "table-btn-success",
                                                                                                            ];
                                                                                                            $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                                                                            ?>
                                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["payment_status"]; ?></button>
                                                                                                        </td>

                                                                                                        <td class="px-4 py-3 align-middle">
                                                                                                            <div class="dropdown dropup position-relative">
                                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20"
                                                                                                                    class="justify-content-center sub-text cursor-pointer"
                                                                                                                    id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                                                </iconify-icon>

                                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5 z-3 position-absolute"
                                                                                                                    aria-labelledby="dropdownMenuButton2">
                                                                                                                    <li>
                                                                                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                            href="intake-student-payment?payment-id=<?= $data['course_payments_id'] ?>">
                                                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                            View
                                                                                                                        </a>
                                                                                                                    </li>
                                                                                                                    <?php
                                                                                                                    if (
                                                                                                                        SessionUser::ifUserIsPro($sessionUserRole) ||
                                                                                                                        UserPermissions::validPermisisonType('payments', 0) || UserPermissions::validPermisisonType('payments', 24)
                                                                                                                    ) {
                                                                                                                        if ($balance > 0) {
                                                                                                                    ?>
                                                                                                                            <li>
                                                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                                    href="add-receipt-to-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>&inquiry-id=<?php echo $id; ?>">
                                                                                                                                    <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Add Reciept
                                                                                                                                </a>
                                                                                                                            </li>
                                                                                                                        <?php
                                                                                                                        }
                                                                                                                        ?>
                                                                                                                        <li>
                                                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                                href="update-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>">
                                                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Edit
                                                                                                                            </a>
                                                                                                                        </li>
                                                                                                                        <?php
                                                                                                                        if (
                                                                                                                            $paidPayments->num_rows == 0
                                                                                                                        ) {
                                                                                                                        ?>
                                                                                                                            <li>
                                                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                                    onclick="removeRequestStudentPayment(<?= $data['course_payments_id'] ?>)">
                                                                                                                                    <iconify-icon icon="material-symbols:delete-outline-rounded" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Remove
                                                                                                                                </a>
                                                                                                                            </li>
                                                                                                                    <?php
                                                                                                                        }
                                                                                                                    }
                                                                                                                    if (
                                                                                                                        
                                                                                                                        UserPermissions::validPermisisonType('leads', 0)
                                                                                                                    ) {
                                                                                                                        if ($balance > 0) {
                                                                                                                    ?>
                                                                                                                            <li>
                                                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                                    href="add-receipt-to-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>&inquiry-id=<?php echo $id; ?>">
                                                                                                                                    <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Add Reciept
                                                                                                                                </a>
                                                                                                                            </li>
                                                                                                                        <?php
                                                                                                                        }}
                                                                                                                    ?>
                                                                                                                </ul>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    </tr>

                                                                                                    <?php
                                                                                                    if ($paidPayments->num_rows > 0) {
                                                                                                        while ($paidPaymentsData = $paidPayments->fetch_assoc()) {
                                                                                                    ?>
                                                                                                            <tr>
                                                                                                                <td colspan="3" class="px-4 py-3 pe-0 align-middle">
                                                                                                                    <div class="d-flex flex-row align-items-center justify-content-start w-100 p-0 g-0 m-0">
                                                                                                                        <iconify-icon icon="ci:arrow-sub-down-right" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                        <span class="text-14 mx-2 table-btn-success active text-white fw-semibold px-3 py-2">Receipt : #R<?= $paidPaymentsData["course_payment_has_payment_id"] ?></span>
                                                                                                                    </div>
                                                                                                                </td>


                                                                                                                <td class="py-2 align-middle text-end"><span class="text-14 main-text"><?= $paidPaymentsData["discount_amount"] == 0 ? "" : number_format($paidPaymentsData["discount_amount"]) ?></span></td>
                                                                                                                <td class="py-3 align-middle text-end"><span class="text-14 main-text"><?= $paidPaymentsData["panelty_amount"] == 0 ? "" : number_format($paidPaymentsData["panelty_amount"]) ?></span></td>
                                                                                                                <td class="py-3 align-middle text-end"></td>
                                                                                                                <td class="py-3 align-middle text-end"><span class="text-14 main-text"><?= $paidPaymentsData["paid_amount"] == 0 ? "" : number_format($paidPaymentsData["paid_amount"]) ?></span></td>
                                                                                                                <td class="py-3 align-middle"><span class="text-14 main-text"><?= $paidPaymentsData['date_paid'] ?></span></td>
                                                                                                                <td class="py-3 align-middle"><span class="text-14 main-text"></span></td>
                                                                                                                <td class="pe-4 py-2 align-middle">
                                                                                                                    <?php
                                                                                                                    $status_classes = [
                                                                                                                        "1"  => "table-btn-orange",
                                                                                                                        "2"  => "table-btn-purple",
                                                                                                                        "3"  => "table-btn-pink",
                                                                                                                    ];
                                                                                                                    $sbutton_class = $status_classes[$paidPaymentsData["payment_method_id"]];
                                                                                                                    ?>
                                                                                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $paidPaymentsData["payment_method"]; ?></button>
                                                                                                                </td>
                                                                                                                <td class="py-3 align-middle">
                                                                                                                    <?php
                                                                                                                    $status_classes = [
                                                                                                                        "0"     => "table-btn-purple",
                                                                                                                        "1"  => "table-btn-yellow",
                                                                                                                        "2"  => "table-btn-orange",
                                                                                                                        "3"  => "table-btn-success",
                                                                                                                        "4"  => "table-btn-danger",
                                                                                                                        "5"  => "table-btn-orange",
                                                                                                                        "6"  => "table-btn-success",
                                                                                                                        "7"  => "table-btn-danger",
                                                                                                                        "8"  => "table-btn-success",
                                                                                                                    ];
                                                                                                                    $sbutton_class = $status_classes[$paidPaymentsData["payment_status_id"]];
                                                                                                                    ?>
                                                                                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $paidPaymentsData["payment_status"]; ?></button>
                                                                                                                </td>


                                                                                                                <td class="px-4 py-3 align-middle">
                                                                                                                    <div class="dropdown dropup position-relative">
                                                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20"
                                                                                                                            class="justify-content-center sub-text cursor-pointer"
                                                                                                                            id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                                                        </iconify-icon>

                                                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5 z-3 position-absolute"
                                                                                                                            aria-labelledby="dropdownMenuButton2">
                                                                                                                            <li>
                                                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                                                    href="intake-student-payment?payment-id=<?= $data['course_payments_id'] ?>">
                                                                                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    View
                                                                                                                                </a>


                                                                                                                        </ul>
                                                                                                                    </div>
                                                                                                                </td>

                                                                                                            </tr>
                                                                                                <?php
                                                                                                        }
                                                                                                    }
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
                                                            <?php
                                                            }
                                                        } else {
                                                            ?>
                                                            <div class="col-12 px-4 py-3 component-theme h-100">
                                                                <div class="row align-items-center justify-content-center h-100">
                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
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
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>