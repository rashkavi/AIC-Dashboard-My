<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if (!SessionUser::ifUserIsLecturer($sessionUserRole)) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecture-id']) && !empty($_GET['lecture-id']))) {
            header("Location: ./lecture-schedule");
        } else {

            $id = $_GET['lecture-id'];

            $time = Database::search("SELECT * FROM `time_table` WHERE `time_table_id` = '" . $id . "'");
            if ($time->num_rows == 0) {
                header("Location: ./lecture-schedule");
            } else {
                $timedata = $time->fetch_assoc();

                $query = "SELECT 
            tt.time_table_id,
            tt.date,
            tt.start_time,
            tt.end_time,
            lecturers.lecturers_id,
            title.title,
            users.user_fname,
            users.user_lname,
            users.profile_img,
            users.user_id,
            lecture_type.lecture_type,
            lecture_type.lecture_type_id,
            tt.lecture_title,
            tt.duration,
            COUNT(tt.subjects_subject_id) AS batch_count,
            `lecturer_payments`.`earning_status_earning_status_id`,
            `lecturer_payments`.`lecturer_payments_id`,
            `lecturer_payments`.`amount`,
            `lecturer_payments`.`duration` AS conducted_duration,
            `lecturer_payments`.`date_added`,
            
             `lecturer_payments`.`note`,
            `earning_status`.`earning_status`,
            `earning_status`.`earning_status_id`,
            `lecturers`.`hourly_rate`
           
        FROM time_table AS tt
        INNER JOIN `lecturers`  ON `lecturers`.`lecturers_id` = `tt`.`lecturers_lecturers_id`
        INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
        INNER JOIN `lecture_type` ON `lecture_type`.`lecture_type_id` = `tt`.`lecture_type_lecture_type_id`
        LEFT JOIN `lecturer_payments_has_lectures` ON `lecturer_payments_has_lectures`.`time_table_time_table_id` = `tt`.`time_table_id`
        LEFT JOIN `lecturer_payments` ON `lecturer_payments`.`lecturer_payments_id` = `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`
        LEFT JOIN `earning_status` ON `earning_status`.`earning_status_id` = `lecturer_payments`.`earning_status_earning_status_id`
        
        WHERE tt.time_table_status_time_table_status_id = '1'
        AND tt.lecture_type_lecture_type_id != 3
        AND tt.date='" . $timedata['date'] . "' AND tt.start_time='" . $timedata['start_time'] . "'
        AND users.user_id = '" . $sessionUserObject['user_id'] . "'
        GROUP BY 
            tt.date,
            tt.start_time,
            tt.end_time,
            users.user_id
       ";

                $lecture = Database::search($query);

                if ($lecture->num_rows == 0) {
                    header("Location: ./lecture-schedule");
                } else {
                    $lectureData = $lecture->fetch_assoc();

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);


                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d');
                    $nowTime = $today->format('H:i:s');

                    $hourly_rate = $lectureData['lecture_type_id'] == 1 ? $lectureData['hourly_rate'] : $lectureData['hourly_rate'];
                    $minutes = $lectureData['conducted_duration'] == '' ? $lectureData['duration'] : $lectureData['conducted_duration'];

                    $hours = $minutes / 60;
                    $amount = $hours * $hourly_rate;

                    function formatDuration($minutes)
                    {
                        $hours = floor($minutes / 60);
                        $mins = $minutes % 60;

                        $result = "";

                        if ($hours > 0) {
                            $result .= $hours . " h ";
                        }

                        if ($mins > 0) {
                            $result .= $mins . " min";
                        }

                        return trim($result);
                    }
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>My Lecture Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./payments'>Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./lecturers-payemnts'>Lecturers Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./lecturers-lecture-payments'>Lecturers Lecture Payments</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
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
                                                                    switch ($lectureData['earning_status_earning_status_id']) {
                                                                        case '':
                                                                            $progressColor = "Pending";
                                                                            $progress = 100 / 2 * 0;
                                                                            $query .= " WHERE `earning_status_id` IN (1,2)";
                                                                            break;
                                                                        case 1:
                                                                            $progressColor = "completed";
                                                                            $progress = 100 / 2 * 1;
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

                                                                        if ($lectureData['earning_status_earning_status_id'] >= $status['earning_status_id']) {
                                                                            if ($lectureData['earning_status_earning_status_id'] == 3) {
                                                                                $timelineItemColor = "reject";
                                                                            } else if ($lectureData['earning_status_earning_status_id'] == 2) {
                                                                                $timelineItemColor = "accepted";
                                                                            } else {
                                                                                if ($lectureData['earning_status_earning_status_id']  ==  $status['earning_status_id']) {
                                                                                    $bubbleAction = "pulse";
                                                                                } else {
                                                                                    $timelineItemColor = "completed";
                                                                                }
                                                                            }
                                                                        }

                                                                        $statusDetails = "Initial state";
                                                                        if ($status['earning_status_id'] == 1) {
                                                                            $statusDetails = "Pending scheduling";
                                                                        } else if ($status['earning_status_id'] == 2) {
                                                                            $statusDetails = "Confirmed lecturer lecture payment";
                                                                        } else if ($status['earning_status_id'] == 3) {
                                                                            $statusDetails = "Rejected lecturer lecture payment";
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
                                                                <div class="card-background"></div>
                                                                <div class="card-content">
                                                                    <div class="payment-header">
                                                                        <div class="reference-section">
                                                                            <span class="reference-label">PAYMENT LECTURER</span>
                                                                            <h2 class="reference-number"><?= $lectureData["title"] . "." . $lectureData["user_fname"] . " " . $lectureData["user_lname"]; ?></h2>
                                                                            <hr>
                                                                        </div>

                                                                    </div>
                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Lecture Date</span>
                                                                                <span class="detail-value"><?= $lectureData["date"] ?? '--/--/----'; ?></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Time</span>
                                                                                <span class="detail-value"><?= date('g:i A', strtotime($lectureData["start_time"])) ?> - <?= date('g:i A', strtotime($lectureData["end_time"])) ?></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Lecture Type</span>
                                                                                <span class="detail-value"><?= $lectureData["lecture_type"] ?? '--/--/----'; ?></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Duration</span>
                                                                                <span class="detail-value"><?= formatDuration($lectureData["duration"]) ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Lecture Titele</span>
                                                                                <span class="detail-value"><?= $lectureData["lecture_title"] ?? '--/--/----'; ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <span class="reference-label mt-4">Concurrent Batches</span>
                                                                    <?php
                                                                    $query = "SELECT `intake`,`faculty`,`programme`,`branch`,`lecture_recording` FROM `time_table` 
                                                                                                    INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                                                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                                                    INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                                                                    INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                                                                                    INNER JOIN `branches` ON `faculties`.`branches_branch_id` = `branches`.`branch_id`
                                                                                                    INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id`=`lecturers`.`lecturers_id`
                                                                                                    INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                                                                                    LEFT JOIN `lecture_recording_has_lectures` ON `time_table`.`time_table_id` = `lecture_recording_has_lectures`.`time_table_time_table_id`
                                                                                                    LEFT JOIN `lecture_recording` ON `lecture_recording_has_lectures`.`lecture_recording_lecture_recording_id` = `lecture_recording`.`lecture_recording_id`
                                                                                                    WHERE `users`.`user_id` = '" . $sessionUserObject['user_id'] . "'
                                                                                                    AND `time_table`.`date` = '" . $timedata['date'] . "'
                                                                                                    AND `time_table`.`start_time` = '" . $timedata['start_time'] . "'
                                                                                                    AND `time_table`.`end_time` = '" . $timedata['end_time'] . "'";

                                                                    $concurrentBatches = Database::search($query);
                                                                    // Group recordings by batch
                                                                    $groupedBatches = [];
                                                                    while ($concurrentBatch = $concurrentBatches->fetch_assoc()) {
                                                                        $batchKey = $concurrentBatch["branch"] . " / " . $concurrentBatch["faculty"] . " / " .  $concurrentBatch["programme"] . " / " . $concurrentBatch["intake"];

                                                                        if (!isset($groupedBatches[$batchKey])) {
                                                                            $groupedBatches[$batchKey] = [
                                                                                'branch' => $concurrentBatch["branch"],
                                                                                'faculty' => $concurrentBatch["faculty"],
                                                                                'intake' => $concurrentBatch["intake"],
                                                                                'programme' => $concurrentBatch["programme"],
                                                                                'recordings' => []
                                                                            ];
                                                                        }

                                                                        if ($concurrentBatch['lecture_recording'] != null) {
                                                                            $groupedBatches[$batchKey]['recordings'][] = $concurrentBatch['lecture_recording'];
                                                                        }
                                                                    }

                                                                    if ($lectureData['earning_status_id'] != '') {

                                                                    ?>
                                                                        <span class="reference-label mt-4"><?= $lectureData['earning_status_id'] == 1 ? 'Scheduled Payment Details' : 'Completed Payment Details' ?></span>
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="hugeicons:time-04" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Start Time</span>
                                                                                    <span class="detail-value"><?= date('g:i A', strtotime($lectureData["start_time"])) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="hugeicons:time-04" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">End Time</span>
                                                                                    <span class="detail-value"><?= date('g:i A', strtotime($lectureData["end_time"])) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="mingcute:time-duration-line" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Scheduled Duration</span>
                                                                                    <span class="detail-value"><?= formatDuration($lectureData["duration"]) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="lsicon:amount-dollar-outline" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Amount (Rate per hour)</span>
                                                                                    <span class="detail-value">LKR <?= number_format($hourly_rate, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="mingcute:time-duration-line" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Conducted Duration</span>
                                                                                    <span class="detail-value"><?= formatDuration($lectureData["conducted_duration"]) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="lsicon:amount-dollar-outline" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Scheduled Amount</span>
                                                                                    <span class="detail-value">LKR <?= number_format($lectureData["amount"], 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="solar:calendar-date-broken" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Scheduled Date Time</span>
                                                                                    <span class="detail-value"><?= date('Y-m-d h:i A', strtotime($lectureData["date_added"])) ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <iconify-icon icon="hugeicons:note" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Note</span>
                                                                                    <span class="detail-value"><?= $lectureData["note"] == '' ? 'N/A' : $lectureData["note"] ?></span>
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