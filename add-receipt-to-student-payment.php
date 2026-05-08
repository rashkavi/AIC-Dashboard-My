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
            $paymentPlan = Database::search("SELECT
                    `cp`.`course_payments_id`, 
                    `ps`.`payment_status`,
                    `ps`.`payment_status_id`,
                    `pgp`.`payment_type`,
                    `cp`.`amount`,
                    `cp`.`discount`,
                    `cp`.`panelty_payment_option_panelty_payment_option_id`,
                    `cp`.`discount_type_discount_type_id`,
                    `dt`.`discount_type`,
                    `dt`.`discount_type_id`, 
                    `cp`.`due_date`,
                    `cp`.`refference`,
                    `i`.`intake_id`,
                    `p`.`programme_id`,
                    `f`.`faculty_id`,
                    `i`.`intake`,
                    `p`.`programme`,
                    `f`.`faculty`,
                    `t`.`title`,
                    `ld`.`first_name`,
                    `ld`.`last_name`,
                    `ppo`.`panelty_payment_option`,
                    `shi`.`students_has_intakes_id`,
                IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                IFNULL(cpp.paid_amount, 0) AS paid_amount,
                IFNULL(cpp.total_discount, 0) AS total_discount
                FROM `course_payments` AS `cp`
                INNER JOIN `payment_status` AS `ps` 
                    ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                INNER JOIN `panelty_payment_option` AS `ppo` 
                    ON `cp`.`panelty_payment_option_panelty_payment_option_id` = `ppo`.`panelty_payment_option_id`
               
                INNER JOIN `discount_type` AS `dt` 
                    ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
               INNER JOIN `payment_type` AS `pgp`
                ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
                 INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi` 
                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                INNER JOIN `payment_category` AS `pc` 
                    ON `pphsi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                INNER JOIN `currencies` AS `c`
                    ON `pc`.`currencies_currency_id` = `c`.`currency_id`    
                
                INNER JOIN `students_has_intakes` AS `shi` 
                    ON `pphsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                INNER JOIN `students` AS `s` 
                    ON `shi`.`students_student_id` = `s`.`student_id`
                INNER JOIN `login_details` AS `ld`
                    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                INNER JOIN `title` AS `t` 
                    ON `s`.`title_title_id` = `t`.`title_id`
                INNER JOIN `intakes` AS `i` 
                    ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                INNER JOIN `programmes` AS `p` 
                    ON `i`.`programmes_programme_id` = `p`.`programme_id`
                INNER JOIN `faculties` AS `f` 
                    ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
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
                    SUM(discount_amount) AS total_discount
                FROM course_payment_has_payments
                WHERE payment_status_payment_status_id IN (2,3,5,6,8)
                GROUP BY course_payments_course_payments_id
            ) cpp
                ON cp.course_payments_id = cpp.course_payments_course_payments_id
                WHERE `cp`.`course_payments_id` = '" . $id . "'
                
                GROUP BY `cp`.`course_payments_id`
                ");

            if ($paymentPlan->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $paymentData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 24, $paymentData['intake_id']))) {
                    header("Location: ./faculties");
                } else {

                    if ($paymentData['payment_status_id'] != 1) {
                        header("Location: ./batch-student-payments?student-id=" . $paymentData['students_has_intakes_id']);
                    } else {

                        $discountAmount = $paymentData["discount"];
                        if ($paymentData["discount_type_discount_type_id"] == 3) {
                            $discountAmount = $paymentData["amount"] / 100 * $paymentData["discount"];
                        }
                        $payableAmount = $paymentData["amount"] - $discountAmount + $paymentData["panelty_amount"];
                        $balance = $payableAmount - $paymentData["paid_amount"] - $paymentData["total_discount"];
                        $paidAmount = $payableAmount - $balance;
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
                                                                    <span class="main-topic lh-lg">Add Receipt to Student Payment</span>
                                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                        <ol class="breadcrumb m-0">
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $paymentData['faculty_id'] ?>'><?= $paymentData['faculty']; ?></a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $paymentData['programme_id'] ?>'>...</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='batch-details?batch-id=<?= $paymentData['intake_id'] ?>'><?= $paymentData['intake']; ?></a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo ucwords(strtolower($paymentData['title'] . " " . $paymentData['first_name'] . " " . $paymentData['last_name'])); ?></a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payments</a></li>
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
                                                                                    <span class="amount-value">LKR <?= number_format($balance, 2); ?></span>
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
                                                                                    <span class="detail-value"><?= number_format($discountAmount, 2) ?></span>
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
                                                                                    <span class="detail-value"><?= number_format($paymentData["panelty_amount"], 2) ?></span>
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
                                                            <div class="col-12 p-0 mt-4">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <div class="col-12">
                                                                        <div class="row g-4">
                                                                            <h4 class="sub-topic">Add Receipt</h4>
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Discount Given Amount</label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="discount-amount" value="0">
                                                                                <div class="text-12 text-danger" id="discount-amount-feedback"></div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Penalty Added Amount</label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="penalty-amount" value="0">
                                                                                <div class="text-12 text-danger" id="penalty-amount-feedback"></div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Balance Payable</label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="balance-amount" disabled value="<?= $balance ?>">
                                                                                <div class="text-12 text-danger" id="balance-amount-feedback"></div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Payment Method</label>
                                                                                <select name="payment_method" class="form-select custom-select mt-2" id="payment-method">
                                                                                    <option value="0">- Select -</option>
                                                                                    <?php
                                                                                    $methods = Database::search("SELECT * FROM `payment_method`");
                                                                                    while ($method = $methods->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?= $method["payment_method_id"] ?>"><?= $method["payment_method"] ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <div class="text-12 text-danger" id="payment-method-feedback"></div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Paid Amount</label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="paid-amount">
                                                                                <div class="text-12 text-danger" id="paid-amount-feedback"></div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-3 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Paid Date</label>
                                                                                <input type="date" class="form-control custom-input mt-2" id="paid-date" value="<?= date("Y-m-d") ?>">
                                                                                <div class="text-12 text-danger" id="paid-date-feedback"></div>
                                                                            </div>
                                                                            <div class="col-lg-6"></div>
                                                                            <div class="col-12 col-lg-6 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Note</label>
                                                                                <textarea name="note" class="form-control custom-input mt-2" id="note" rows="8"></textarea>
                                                                                <div class="text-12 text-danger" id="note-feedback"></div>
                                                                            </div>

                                                                            <div class="col-12 col-lg-6 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text">Payment Slip</label>
                                                                                <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                                    <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                                    <span>Drag & Drop your files here</span>
                                                                                    <span>OR</span>
                                                                                    <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                                    <input type="file" class="d-none" id="file-selector-input" accept="image/*,application/pdf" multiple>
                                                                                    <div class="col-12 px-1 mt-4">
                                                                                        <div class="row" id="list-section"></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="mt-2" id="file-feedback"></div>
                                                                            </div>
                                                                            <div class="col-12 d-flex flex-column justify-content-end align-items-end">
                                                                                <div class="col-12 d-flex justify-content-end">
                                                                                    <button class="custom-btn col-12 col-lg-4" onclick="uploadStudentPaymentReceipt(this,'<?php echo $paymentData['course_payments_id'] ?>')">Save Payment</button>
                                                                                </div>
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

                            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
                            <script>
                                const baseBalance = parseFloat("<?= $balance ?>") || 0;

                                const discountInput = document.getElementById("discount-amount");
                                const penaltyInput = document.getElementById("penalty-amount");
                                const balanceInput = document.getElementById("balance-amount");

                                function updateBalance() {
                                    const discount = parseFloat(discountInput.value) || 0;
                                    const penalty = parseFloat(penaltyInput.value) || 0;

                                    let newBalance = baseBalance - discount + penalty;

                                    if (newBalance < 0) {
                                        newBalance = 0;
                                    }

                                    balanceInput.value = newBalance.toFixed(2);
                                }

                                discountInput.addEventListener("input", updateBalance);
                                penaltyInput.addEventListener("input", updateBalance);
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