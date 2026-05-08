<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        if ($sessionUserRole != 'Lecturer') {
            header("Location: ./dashboard");
        } else {
            $sessionUserObject = SessionUser::getCurrentUserObject();
            $id = $sessionUserObject['user_id'];
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            $timezone = new DateTimeZone("Asia/Colombo");
            $dateTime = new DateTime("now", $timezone);
            $todayDate = $dateTime->format('Y-m-d');
            $nowTime = $dateTime->format('H:i:s');

            $totalIncome = 0;
            $totalPayouts = 0;
            $totalPayableAmount = 0;
            $paymentpayablePending = 0;
            $pendingPayments = 0;
            $rejectedPayments = 0;

            $assignmentsPayments = Database::search("SELECT * FROM `assignment_payments`
              
                INNER JOIN `lecturers` ON `assignment_payments`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                WHERE `users`.`user_id`= '" . $id . "'");

            if ($assignmentsPayments->num_rows > 0) {
                while ($assignmentPayment = $assignmentsPayments->fetch_assoc()) {
                    if ($assignmentPayment['earning_status_earning_status_id'] == 1) {
                        $pendingPayments += $assignmentPayment['amount'];
                    } else if ($assignmentPayment['earning_status_earning_status_id'] == 2) {
                        $totalIncome += $assignmentPayment['amount'];
                    } else if ($assignmentPayment['earning_status_earning_status_id'] == 3) {
                        $rejectedPayments += $assignmentPayment['amount'];
                    }
                }
            }

            $examPayments = Database::search("SELECT * FROM `exam_payments`
               
                INNER JOIN `lecturers` ON `exam_payments`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                WHERE `users`.`user_id`= '" . $id . "'");

            if ($examPayments->num_rows > 0) {
                while ($examPayment = $examPayments->fetch_assoc()) {
                    if ($examPayment['earning_status_earning_status_id'] == 1) {
                        $pendingPayments += $examPayment['amount'];
                    } else if ($examPayment['earning_status_earning_status_id'] == 2) {
                        $totalIncome += $examPayment['amount'];
                    } else if ($examPayment['earning_status_earning_status_id'] == 3) {
                        $rejectedPayments += $examPayment['amount'];
                    }
                }
            }

            $lecturePayments = Database::search("SELECT * FROM `lecturer_payments`
               
                INNER JOIN `lecturers` ON `lecturer_payments`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                WHERE `users`.`user_id`= '" . $id . "'");

            if ($lecturePayments->num_rows > 0) {
                while ($lecturePayment = $lecturePayments->fetch_assoc()) {
                    if ($lecturePayment['earning_status_earning_status_id'] == 1) {
                        $pendingPayments += $lecturePayment['amount'];
                    } else if ($lecturePayment['earning_status_earning_status_id'] == 2) {
                        $totalIncome += $lecturePayment['amount'];
                    } else if ($lecturePayment['earning_status_earning_status_id'] == 3) {
                        $rejectedPayments += $lecturePayment['amount'];
                    }
                }
            }

            $subjectsPayments = Database::search("SELECT 
            s.subject_id,
            s.subject,
            s.max_course_hours,
            l.hourly_rate,
            SUM(lp.amount) AS total_paid,
            SUM(lp.amount / l.hourly_rate) AS completed_hours
        FROM 
            subjects s
        INNER JOIN 
            lecturers l ON s.subject_id = l.subjects_subject_id
        LEFT JOIN 
            lecturer_payments lp ON lp.lecturers_lecturers_id = l.lecturers_id
        WHERE 
            l.users_user_id = '$id'
        GROUP BY 
            s.subject_id
            ");

            if ($subjectsPayments->num_rows > 0) {
                while ($subjectPayment = $subjectsPayments->fetch_assoc()) {
                    if ($subjectPayment['completed_hours'] < $subjectPayment['max_course_hours']) {
                        $paymentpayablePending += $subjectPayment['total_paid'];
                    }
                }
            }

            $payouts = Database::search("SELECT SUM(`amount`) FROM `lecturer_payouts` 
            WHERE `lecturer_payouts`.`users_user_id` = '" . $id . "'");

            if ($payouts->num_rows > 0) {
                $totalPayouts = $payouts->fetch_assoc()['SUM(`amount`)'];
            }

            $totalPayableAmount = $totalIncome - $totalPayouts - $paymentpayablePending;

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>My Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/calender.css">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
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
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0" id="accordionParent">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-8 col-lg-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">My Payments</span>
                                                        <span class="text-14 sub-text lh-base mt-1">Manage payment records, fees, and transaction history efficiently.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="container p-0 m-0 g-0 mb-4">
                                            <div class="row g-4 d-flex">
                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                    <div class="component-theme p-3 w-100">
                                                        <div class="d-flex flex-row align-items-center">
                                                            <div class="hwr-70 table-btn-purple d-flex align-items-center justify-content-center"> <iconify-icon icon="ic:round-ssid-chart" width="32" height="32" class="text-dark-purple"></iconify-icon></div>
                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                <div class="row no-gutters align-items-center">
                                                                    <div class="col mr-2">
                                                                        <div class="text-xs font-weight-bold text-dark-purple text-uppercase mb-1">
                                                                            TOTAL EARNINGS</div>
                                                                        <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($totalIncome) ?></div>
                                                                        <div class="text-xs text-muted">Available for withdrawal</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <a href="my-earnings-history" class="text-xs text-dark-purple">Earnings History <i class="fas fa-arrow-right ms-1"></i></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                    <div class="component-theme p-3 w-100">
                                                        <div class="d-flex flex-row align-items-center">
                                                            <div class="hwr-70 table-btn-orange d-flex align-items-center justify-content-center"> <iconify-icon icon="token:pay" width="32" height="32" class="text-dark-orange"></iconify-icon></div>
                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                <div class="row no-gutters align-items-center">
                                                                    <div class="col mr-2">
                                                                        <div class="text-xs font-weight-bold text-dark-orange text-uppercase mb-1">
                                                                            TOTAL PAYOUTS</div>
                                                                        <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($totalPayouts) ?></div>
                                                                        <div class="text-xs text-muted">Complete payouts</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <a href="my-payout-history" class="text-xs text-dark-orange">Payout History <i class="fas fa-arrow-right ms-1"></i></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                    <div class="component-theme p-3 w-100">
                                                        <div class="d-flex flex-row align-items-center">
                                                            <div class="hwr-70 table-btn-green-blue d-flex align-items-center justify-content-center"> <iconify-icon icon="bx:money-withdraw" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                <div class="row no-gutters align-items-center">
                                                                    <div class="col mr-2">
                                                                        <div class="text-xs font-weight-bold text-dark-green text-uppercase mb-1">
                                                                            TOTAL PAYABLE AMOUNT</div>
                                                                        <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($totalPayableAmount) ?></div>
                                                                        <div class="text-xs text-muted">Available for withdrawal</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <button class="table-btn table-btn-success active" onclick="location.href='my-payable-payments'">Withdraw <i class="fas fa-arrow-right ms-1"></i></button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                    <div class="component-theme p-3 w-100">
                                                        <div class="d-flex flex-row align-items-center">
                                                            <div class="hwr-70 table-btn-primary d-flex align-items-center justify-content-center"> <iconify-icon icon="carbon:condition-wait-point" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                <div class="row no-gutters align-items-center">
                                                                    <div class="col mr-2">
                                                                        <div class="text-xs font-weight-bold dark-theme text-uppercase mb-1">
                                                                            PENDING PAYMENTS</div>
                                                                        <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($paymentpayablePending) ?></div>
                                                                        <div class="text-xs text-muted">Next payment cycle</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <a href="#" class="text-xs dark-theme">Payment Schedule <i class="fas fa-arrow-right ms-1"></i></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                    <div class="component-theme p-3 w-100">
                                                        <div class="d-flex flex-row align-items-center">
                                                            <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="carbon:condition-wait-point" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                <div class="row no-gutters align-items-center">
                                                                    <div class="col mr-2">
                                                                        <div class="text-xs font-weight-bold text-dark-yellow text-uppercase mb-1">
                                                                            PENDING APPROVAL</div>
                                                                        <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($pendingPayments) ?></div>
                                                                        <div class="text-xs text-muted">Next payment cycle</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <a href="#" class="text-xs text-dark-yellow">Payment Schedule <i class="fas fa-arrow-right ms-1"></i></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                    <div class="component-theme p-3 w-100">
                                                        <div class="d-flex flex-row align-items-center">
                                                            <div class="hwr-70 table-btn-danger d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:pending" width="32" height="32" style="color: var(--dark-red);"></iconify-icon></div>
                                                            <div class="d-flex flex-column flex-grow-1 px-4">
                                                                <div class="row no-gutters align-items-center">
                                                                    <div class="col mr-2">
                                                                        <div class="text-xs font-weight-bold text-dark-red text-uppercase mb-1">
                                                                            REJECTED Payments</div>
                                                                        <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($rejectedPayments) ?></div>
                                                                        <div class="text-xs text-muted">Rejected payments</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <a href="#" class="text-xs text-dark-red">Send Reminders <i class="fas fa-arrow-right ms-1"></i></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <span class="sub-topic mb-3">Recent Payments</span>
                                                <div class="col-12">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="users-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium mx-2">Subject</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Type</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Count</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Rate</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        $withdrawalData = [];

                                                                        $assignmentsPayments = Database::search("
                                                                    SELECT 
                                                                        `s`.`subject_id`,
                                                                        `s`.`subject`,
                                                                        `s`.`subject_cover`,
                                                                        `l`.`per_assignment_fee`,
                                                                        SUM(`ap`.`amount`) AS `total_assignment_amount`,
                                                                        SUM(`pd`.`amount`) AS `total_paid_amount`,
                                                                        `l`.`lecturers_id`
                                                                    FROM 
                                                                        `assignment_payments` `ap`
                                                                    INNER JOIN 
                                                                        `lecturers` `l` ON `ap`.`lecturers_lecturers_id` = `l`.`lecturers_id`
                                                                    INNER JOIN 
                                                                        `subjects` `s` ON `l`.`subjects_subject_id` = `s`.`subject_id`
                                                                    INNER JOIN 
                                                                        `users` `u` ON `l`.`users_user_id` = `u`.`user_id`
                                                                    LEFT JOIN 
                                                                        `lecturer_payouts` `lp` 
                                                                            ON `u`.`user_id` = `lp`.`users_user_id` 
                                                                           AND `lp`.`payout_status_payout_status_id` != 3
                                                                    LEFT JOIN 
                                                                        `payout_data` `pd` 
                                                                            ON `lp`.`lecturer_payouts_id` = `pd`.`lecturer_payouts_lecturer_payouts_id`
                                                                    WHERE 
                                                                        `u`.`user_id` = '" . $id . "'
                                                                        AND `pd`.`payout_type_payout_type_id` = 2
                                                                        AND `pd`.`lecturers_lecturers_id` = `ap`.`lecturers_lecturers_id`
                                                                        AND `lp`.`payout_status_payout_status_id` = 7
                                                                    GROUP BY 
                                                                        `s`.`subject_id`, `s`.`subject`
                                                                ");

                                                                        if ($assignmentsPayments->num_rows > 0) {
                                                                            while ($assignmentsPayment = $assignmentsPayments->fetch_assoc()) {
                                                                                if ($assignmentsPayment['total_assignment_amount'] > $assignmentsPayment['total_paid_amount']) {
                                                                                    $payableAssignmetAmount = $assignmentsPayment['total_assignment_amount'] - $assignmentsPayment['total_paid_amount'];
                                                                                    $assignmentCount = ceil($payableAssignmetAmount / $assignmentsPayment['per_assignment_fee']);
                                                                                    $withdrawalData[] = [
                                                                                        $assignmentsPayment['subject'],
                                                                                        $assignmentsPayment['subject_cover'],
                                                                                        'Assignment',
                                                                                        $assignmentCount,
                                                                                        $assignmentsPayment['per_assignment_fee'],
                                                                                        $payableAssignmetAmount,
                                                                                        $assignmentsPayment['lecturers_id'],
                                                                                        2
                                                                                    ];
                                                                                }
                                                                            }
                                                                        }

                                                                        $lecturesPayments = Database::search("
                                                                        SELECT 
                                                                            `s`.`subject_id`,
                                                                            `s`.`subject`,
                                                                            `s`.`subject_cover`,
                                                                            `s`.`max_course_hours`,
                                                                            `l`.`hourly_rate`,
                                                                            SUM(`lp`.`amount`) AS `total_lectures_amount`,
                                                                            SUM(`pd`.`amount`) AS `total_paid_amount`,
                                                                            `l`.`lecturers_id`
                                                                        FROM 
                                                                            `lecturer_payments` `lp`
                                                                        INNER JOIN 
                                                                            `lecturers` `l` ON `lp`.`lecturers_lecturers_id` = `l`.`lecturers_id`
                                                                        INNER JOIN 
                                                                            `subjects` `s` ON `l`.`subjects_subject_id` = `s`.`subject_id`
                                                                        INNER JOIN 
                                                                            `users` `u` ON `l`.`users_user_id` = `u`.`user_id`
                                                                        LEFT JOIN 
                                                                            `lecturer_payouts` `lop` 
                                                                                ON `u`.`user_id` = `lop`.`users_user_id` 
                                                                            AND `lop`.`payout_status_payout_status_id` != 3
                                                                        LEFT JOIN 
                                                                            `payout_data` `pd` 
                                                                                ON `lop`.`lecturer_payouts_id` = `pd`.`lecturer_payouts_lecturer_payouts_id`
                                                                        WHERE 
                                                                            `u`.`user_id` = '" . $id . "'
                                                                            AND `pd`.`payout_type_payout_type_id` = 1
                                                                            AND `pd`.`lecturers_lecturers_id` = `lp`.`lecturers_lecturers_id`
                                                                            AND `lop`.`payout_status_payout_status_id` = 7
                                                                        GROUP BY 
                                                                            `s`.`subject_id`, `s`.`subject`
                                                                    ");

                                                                        if ($lecturesPayments->num_rows > 0) {
                                                                            while ($lecturesPayment = $lecturesPayments->fetch_assoc()) {

                                                                                if ($lecturesPayment['total_lectures_amount'] > $lecturesPayment['total_paid_amount']) {
                                                                                    $completeHours = ceil($lecturesPayment['total_lectures_amount'] / $lecturesPayment['hourly_rate']);

                                                                                    if ($completeHours == $lecturesPayment['max_course_hours'] || $completeHours > $lecturesPayment['max_course_hours']) {
                                                                                        $payableLecturesAmount = $lecturesPayment['total_lectures_amount'] - $lecturesPayment['total_paid_amount'];
                                                                                        $hours = $payableLecturesAmount / $lecturesPayment['hourly_rate'];

                                                                                        $fullHours = floor($hours);
                                                                                        $minutes = round(($hours - $fullHours) * 60);

                                                                                        if ($minutes > 0) {
                                                                                            $formattedHours = "{$fullHours}h {$minutes}m";
                                                                                        } else {
                                                                                            $formattedHours = "{$fullHours}h";
                                                                                        }
                                                                                        $withdrawalData[] = [
                                                                                            $lecturesPayment['subject'],
                                                                                            $lecturesPayment['subject_cover'],
                                                                                            'Lectures',
                                                                                            $formattedHours,
                                                                                            $lecturesPayment['hourly_rate'],
                                                                                            $payableLecturesAmount,
                                                                                            $lecturesPayment['lecturers_id'],
                                                                                            1
                                                                                        ];
                                                                                    }
                                                                                }
                                                                            }
                                                                        }

                                                                        if (count($withdrawalData) > 0) {
                                                                            foreach ($withdrawalData as $key => $value) {
                                                                                $subject = $value[0];
                                                                                $subjectCover = $value[1];
                                                                                $subjectType = $value[2];
                                                                                $subjectHours = $value[3];
                                                                                $subjectRate = $value[4];
                                                                                $subjectAmount = $value[5];
                                                                                $lecturersId = $value[6];
                                                                                $type = $value[7];
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                                                                <?php
                                                                                                if (!empty($subjectCover) && file_exists('./assets/imgs/subjects_covers/' . $subjectCover)) {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/subjects_covers/<?= $subjectCover; ?>" class="w-80 h-auto" alt="Subject image">
                                                                                                <?php
                                                                                                } else {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-80 h-auto" alt="Subject image">
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <span class="text-14 main-text mx-2"><?= $subject; ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $subjectType; ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $subjectHours ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $subjectRate; ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $subjectAmount; ?></span></td>

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
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>
                <script src="./assets/js/eventCalender.js"></script>
            </body>

            </html>
<?php
        }
    }
} ?>