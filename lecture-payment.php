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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecture-id']) && !empty($_GET['lecture-id']))) {
            header("Location: ./lecture-schedule");
        } else {

            $lecture_id = $_GET['lecture-id'];
            $subject_id = 0;

            $query = "SELECT * FROM `time_table`
            INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
            INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
            INNER JOIN `subjects` ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            INNER JOIN `lecture_type` ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id`
            INNER JOIN `time_table_status` ON `time_table`.`time_table_status_time_table_status_id` = `time_table_status`.`time_table_status_id`
            WHERE `time_table`.`time_table_id`='" . $lecture_id . "'";

            if (isset($_GET['subject-id']) && !empty($_GET['subject-id'])) {
                $subject_id = $_GET['subject-id'];
                $query .= " AND `subjects`.`subject_id`='" . $subject_id . "'";
            }

            $lecture = Database::search($query);

            if ($lecture->num_rows == 0) {
                header("Location: ./lecture-schedule");
            } else {
                $lectureData = $lecture->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("super_lecture_schedule", 24))) {
                    header("Location: ./lecture-schedule");
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
                        <title>Lecture Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                            selectItem::setSelected("Lecture Schedule");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0 g-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-dashboards.php"; ?>
                                        </div>
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg" data-i18n="lecture-payment">Lecture Payment</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="lecture-payment">Lecture Payment</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                                <?php
                                                $lecturerLecturePayment = Database::search("SELECT * FROM `lecturer_payments`
                                                        INNER JOIN `lecturers` ON `lecturer_payments`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                        WHERE `lecturers_lecturers_id`='" . $lectureData['lecturers_lecturers_id'] . "' AND `time_table_time_table_id`='" . $lectureData['time_table_id'] . "'");
                                                if ($lecturerLecturePayment->num_rows > 0) {
                                                    $lecturerLecturePaymentData = $lecturerLecturePayment->fetch_assoc();

                                                    $paymentLog = Database::search("SELECT * FROM `lecturer_payments_logs` 
                                                            INNER JOIN `users` ON `lecturer_payments_logs`.`users_user_id` = `users`.`user_id`
                                                            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
                                                            WHERE `lecturer_payments_lecturer_payments_id`='" . $lecturerLecturePaymentData['lecturer_payments_id'] . "' ORDER BY `lecturer_payments_logs_id` DESC LIMIT 1");
                                                    $paymentLogData = $paymentLog->fetch_assoc();
                                                ?>
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
                                                                                <h2 class="reference-number"><?= $lectureData["title"] . "." . $lectureData["user_fname"] . " " . $lectureData["user_lname"]; ?></h2>
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
                                                    <?php
                                                    if ($lecturerLecturePaymentData['earning_status_earning_status_id'] != 2) {
                                                    ?>
                                                        <div class="col-12 p-4 component-theme">
                                                            <div class="row p-0 m-0 align-items-end">

                                                                <div class="col-12 p-0 g-0">
                                                                    <div class="row gx-4 gy-3">
                                                                        <span class="main-topic lh-lg">Update Payment</span>
                                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Start Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" value="<?php echo $lectureData["start_time"]; ?>" id="start-time">
                                                                            <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">End Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" value="<?php echo $lectureData["end_time"]; ?>" id="end-time">
                                                                            <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                                        </div>

                                                                        <div class="col-12 col-md-4 d-flex flex-column justify-content-end">
                                                                            <button class="custom-btn w-100" onclick="addLecturerLecturePayment(this,<?= $lecture_id ?>);">Confirm Lecturer Payment</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <div class="col-12 p-4 component-theme">
                                                        <div class="row p-0 m-0 align-content-center">
                                                            <div class="col-12 p-0 mb-4">
                                                                <div class="row p-0 m-0">
                                                                    <div class="payment-card-container p-0">
                                                                        <div class="payment-card shadow-none">
                                                                            <!-- Background elements -->
                                                                            <div class="card-background"></div>
                                                                            <!-- Card content -->
                                                                            <div class="card-content">
                                                                                <div class="payment-header">
                                                                                    <!-- Reference on left -->
                                                                                    <div class="reference-section">
                                                                                        <span class="reference-label">Title</span>
                                                                                        <h2 class="reference-number"><?= $lectureData["lecture_title"]; ?></h2>
                                                                                        <hr>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                                    <div class="detail-item">
                                                                                        <div class="detail-icon">
                                                                                            <?php
                                                                                            if (!empty($lectureData["profile_img"]) && file_exists('./assets/imgs/users/' . $lectureData["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?php echo $lectureData["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($lectureData["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($lectureData["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 text-white align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </div>
                                                                                        <div class="detail-content">
                                                                                            <span class="detail-label">Lecturer</span>
                                                                                            <span class="detail-value"><?= $lectureData["title"] . '.' . $lectureData["user_fname"] . ' ' . $lectureData["user_lname"] ?></span>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="detail-item">
                                                                                        <div class="detail-icon">
                                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                            </svg>
                                                                                        </div>
                                                                                        <div class="detail-content">
                                                                                            <span class="detail-label">Date Time</span>
                                                                                            <span class="detail-value"><?= $lectureData["date"] . ' ' . date("h:i A", strtotime($lectureData["start_time"])) . ' - ' . date("h:i A", strtotime($lectureData["end_time"])) ?? '--/--/----'; ?></span>
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
                                                                                            <span class="detail-label">Type</span>
                                                                                            <span class="detail-value"><?= $lectureData['lecture_type'] ?></span>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div class="detail-item">
                                                                                        <div class="detail-icon">
                                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                                                <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                                            </svg>
                                                                                        </div>
                                                                                        <div class="detail-content">
                                                                                            <span class="detail-label">Status</span>
                                                                                            <span class="detail-value">Completed</span>
                                                                                        </div>
                                                                                    </div>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 p-0 g-0">
                                                                <div class="row gx-4 gy-3 align-items-end">
                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Start Time</label>
                                                                        <input type="time" class="form-control custom-input mt-2" value="<?php echo $lectureData["start_time"]; ?>" id="start-time">
                                                                        <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">End Time</label>
                                                                        <input type="time" class="form-control custom-input mt-2" value="<?php echo $lectureData["end_time"]; ?>" id="end-time">
                                                                        <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-4 d-flex flex-column align-items-end">
                                                                        <button class="custom-btn w-100" data-i18n="confirm-payment" onclick="addLecturerLecturePayment(this,<?= $lecture_id ?>);">Confirm Lecturer Payment</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
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