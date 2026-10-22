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
                                       
                WHERE `users_user_id`= '" . $id . "'");

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
                           
                WHERE `users_user_id`= '" . $id . "'");

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


            $payouts = Database::search("SELECT SUM(`amount`) FROM `lecturers_paid_payments` 
            WHERE `lecturers_paid_payments`.`users_user_id` = '" . $id . "'");

            if ($payouts->num_rows > 0) {
                $totalPayouts = $payouts->fetch_assoc()['SUM(`amount`)'];
            }

            $totalPayableAmount = $totalIncome - $totalPayouts - $paymentpayablePending;
            $totalPayableAmount = $totalPayableAmount < 0 ? 0 : $totalPayableAmount;

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

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="solar:bill-list-broken" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Payments History</span>
                                                        <span class="text-14 main-text">Manage lecturers payments with ease and flexibility</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="my-payments-history" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="mingcute:schedule-line" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Scheduled Lecturers Payments</span>
                                                        <span class="text-14 main-text">Schedule and manage lecturer payments with clarity and control.</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="my-scheduled-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="solar:calendar-date-broken" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Lecture Payments</span>
                                                        <?php
                                                        $pendingApprovallecturePayemnts = Database::search("SELECT COUNT(*) as 'count' FROM `lecturer_payments` 
                                                        WHERE `earning_status_earning_status_id`='1' AND `users_user_id`='" . $sessionUserObject['user_id'] . "'")->fetch_assoc();
                                                        ?>
                                                        <span class="text-14 main-text">Pending lecture payments - <?php echo $pendingApprovallecturePayemnts['count'] ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="my-lecture-payments.php" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="si:assignment-line" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Assignment Payments</span>
                                                        <?php
                                                        $pendingApprovalAssignemntPayemnts = Database::search("SELECT COUNT(*) as 'count' FROM `assignment_payments` 
                                                        WHERE `earning_status_earning_status_id`='1' AND `users_user_id`='" . $sessionUserObject['user_id'] . "'")->fetch_assoc();
                                                        ?>
                                                        <span class="text-14 main-text">Pending assignments payments - <?php echo $pendingApprovalAssignemntPayemnts['count'] ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="my-assignments-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100">View</button>
                                                            </a>
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