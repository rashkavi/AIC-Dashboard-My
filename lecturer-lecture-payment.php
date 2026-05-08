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
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecturer-payment-id']) && !empty($_GET['lecturer-payment-id']))) {
            header("Location: ./lecture-schedule");
        } else {

            $id = $_GET['lecturer-payment-id'];

            $lecturerLecturePayment = Database::search("SELECT * FROM `lecturer_payments`
            INNER JOIN `lecturers` ON `lecturer_payments`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
            INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
            WHERE `lecturer_payments_id`='" . $id . "'");

            if ($lecturerLecturePayment->num_rows == 0) {
                header("Location: ./lecture-schedule");
            } else {
                $lecturerLecturePaymentData = $lecturerLecturePayment->fetch_assoc();

                $paymentLog = Database::search("SELECT * FROM `lecturer_payments_logs` 
                INNER JOIN `users` ON `lecturer_payments_logs`.`users_user_id` = `users`.`user_id`
                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
                WHERE `lecturer_payments_lecturer_payments_id`='" . $lecturerLecturePaymentData['lecturer_payments_id'] . "' ORDER BY `lecturer_payments_logs_id` DESC LIMIT 1");
                $paymentLogData = $paymentLog->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole))) {
                    header("Location: ./subject-lecture-schedule?subejct-id=" . $lectureData['subejcts_subject_id']);
                } else {

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d');
                    $nowTime = $today->format('H:i:s');
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Lecturer Lecture Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

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
                                    load_side_menu::load("Course Overview");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Course Overview");
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
                                                        <div class="col-12 col-lg-8">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Lecture Payment Details</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./payments';">Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-payemnts';">Lecturers Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-lecture-payments';">Lecturers Lecture Payments</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if ($lecturerLecturePaymentData['earning_status_earning_status_id'] == 1) {
                                                        ?>
                                                            <div class="col-12 col-lg-4 mt-3 mt-lg-0">
                                                                <div class="row gx-3 align-content-center h-100">
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn col-12" onclick="updateLecturerLecturePayment(<?= $id ?>,2)">Approve</button>
                                                                    </div>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-danger-btn col-12" onclick="updateLecturerLecturePayment(<?= $id ?>,3)">Reject</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12">
                                                            <div class="payment-timeline">
                                                                <div class="timeline-progress">
                                                                    <?php
                                                                    $query = "SELECT * FROM `earning_status`";
                                                                    $progress = 0;
                                                                    $progressColor = "";
                                                                    switch ($lecturerLecturePaymentData['earning_status_earning_status_id']) {
                                                                        case 1:
                                                                            $progressColor = "completed";
                                                                            $progress = 100 / 2 * 0;
                                                                            $query .= " WHERE `earning_status_id` IN (1,2)";
                                                                            break;
                                                                        case 2:
                                                                            $progressColor = "accepted";
                                                                            $progress = 100 / 2 * 2;
                                                                            $query .= " WHERE `earning_status_id` IN (1,2)";
                                                                            break;
                                                                        case 3:
                                                                            $progressColor = "reject";
                                                                            $progress = 100 / 2 * 2;
                                                                            $query .= " WHERE `earning_status_id` IN (1,3)";
                                                                            break;
                                                                    }

                                                                    ?>
                                                                    <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                                </div>
                                                                <div class="timeline-items">
                                                                    <?php

                                                                    $icons = ["material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close",  "mdi:approve"];

                                                                    $paymentStatus = Database::search($query);
                                                                    while ($status = $paymentStatus->fetch_assoc()) {
                                                                        $timelineItemColor = "";
                                                                        $bubbleAction = "";

                                                                        if ($lecturerLecturePaymentData['earning_status_earning_status_id'] >= $status['earning_status_id']) {
                                                                            if ($lecturerLecturePaymentData['earning_status_earning_status_id'] == 3) {
                                                                                $timelineItemColor = "reject";
                                                                            } else if ($lecturerLecturePaymentData['earning_status_earning_status_id'] == 2) {
                                                                                $timelineItemColor = "accepted";
                                                                            } else {
                                                                                if ($lecturerLecturePaymentData['earning_status_earning_status_id']  ==  $status['earning_status_id']) {
                                                                                    $bubbleAction = "pulse";
                                                                                } else {
                                                                                    $timelineItemColor = "completed";
                                                                                }
                                                                            }
                                                                        }

                                                                        $statusDetails = "Initial state";
                                                                        if ($status['earning_status_id'] == 1) {
                                                                            $statusDetails = "Pending approval by manager";
                                                                        } else if ($status['earning_status_id'] == 2) {
                                                                            $statusDetails = "Confirmed lecturer lecture payment";
                                                                        } else if ($status['earning_status_id'] == 3) {
                                                                            $statusDetails = "Rejected by manager";
                                                                        }

                                                                    ?>
                                                                        <div class="timeline-item <?= $timelineItemColor ?>">
                                                                            <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                                <iconify-icon icon="<?= $icons[$status['earning_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                            </div>
                                                                            <div class="timeline-label">
                                                                                <span><?= $status['earning_status'] ?></span>

                                                                                <div class="status-detail"><?= $statusDetails ?></div>
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
                                                                    <div class="payment-header">
                                                                        <!-- Reference on left -->
                                                                        <div class="reference-section">
                                                                            <span class="reference-label">PAYMENT LECTURER</span>
                                                                            <h2 class="reference-number"><?= $lecturerLecturePaymentData["title"] . "." . $lecturerLecturePaymentData["user_fname"] . " " . $lecturerLecturePaymentData["user_lname"]; ?></h2>
                                                                            <hr>
                                                                        </div>

                                                                        <!-- Amount on right -->
                                                                        <div class="amount-section">
                                                                            <div class="amount-display">
                                                                                <span class="amount-label">Total Amount</span>
                                                                                <span class="amount-value">LKR <?= number_format($lecturerLecturePaymentData["amount"], 2); ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <?php
                                                                    if ($paymentLogData != null) {
                                                                        $lectureHourse = $lecturerLecturePaymentData['amount'] / $lecturerLecturePaymentData['hourly_rate'];

                                                                        $hours = floor($lectureHourse);
                                                                        $minutes = round(($lectureHourse - $hours) * 60);

                                                                        if ($minutes > 0) {
                                                                            $formattedTime = "{$hours}h {$minutes}m";
                                                                        } else {
                                                                            $formattedTime = "{$hours}h";
                                                                        }

                                                                    ?>
                                                                        <!-- Details in a row below -->
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <!-- Date -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Last Updated Date</span>
                                                                                    <span class="detail-value"><?= $paymentLogData["date_updated"] ?? '--/--/----'; ?></span>
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
                                                                                    <span class="detail-label">Updated By</span>
                                                                                    <span class="detail-value"><?= $paymentLogData["title"] . "." . $paymentLogData["user_fname"] . " " . $paymentLogData["user_lname"]; ?></span>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Method -->
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Lecture Hours</span>
                                                                                    <span class="detail-value"><?= $formattedTime; ?></span>
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
                                                                                    <span class="detail-label">Lecturer Hourly Rate </span>
                                                                                    <span class="detail-value">LKR
                                                                                        <?= $lecturerLecturePaymentData['hourly_rate']; ?>
                                                                                    </span>
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

                                                <div class="col-12 mt-4">
                                                    <div class="row p-4 m-0 g-0 component-theme">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `lecturer_payments_logs` WHERE `lecturer_payments_lecturer_payments_id`='" . $id . "'");
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
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php
                                                                            $query = "SELECT * FROM `lecturer_payments_logs`
                                                                    INNER JOIN `users` ON `lecturer_payments_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    WHERE `lecturer_payments_lecturer_payments_id` = '" . $id . "' ORDER BY `date_updated` DESC";

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
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_updated"]; ?></span></td>
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"]; ?></span></td>
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
                        <script src="./assets/js/fileChooser.js"></script>
                    </body>

                    </html>

            <?php
                }
            }
            ?>
<?php
        }
    }
}
?>