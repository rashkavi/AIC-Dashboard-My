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

            $id = $_GET['lecture-id'];

            $time = Database::search("SELECT *,`lecturers`.`users_user_id` AS lecturer_user_id FROM `time_table` 
            INNER JOIN `lecturers`  ON `lecturers`.`lecturers_id` = `time_table`.`lecturers_lecturers_id`
            WHERE `time_table_id` = '" . $id . "'");
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
            `lecturers`.`hourly_rate`,
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
        AND users.user_id = '" . $timedata['lecturer_user_id'] . "' 
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

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
                        header("Location: ./dashboard");
                    } else {

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
                                                                        <style>
                                                                            .recordings-container {
                                                                                display: flex;
                                                                                gap: 8px;
                                                                                flex-wrap: wrap;
                                                                            }
                                                                        </style>
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
                                                                                                    WHERE `users`.`user_id` = '" . $timedata['lecturer_user_id'] . "'
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

                                                                        // Display grouped batches
                                                                        foreach ($groupedBatches as $batchKey => $batchData) {
                                                                        ?>
                                                                            <div class="details-row d-flex flex-column flex-lg-row">
                                                                                <div class="detail-item">
                                                                                    <div class="detail-icon">
                                                                                        <iconify-icon icon="tdesign:menu" width="20" height="20"></iconify-icon>
                                                                                    </div>

                                                                                    <div class="detail-content">

                                                                                        <span class="detail-label"><?= $batchData["programme"] . " / " . $batchData["intake"] ?></span>
                                                                                        <span class="detail-value">1 <?= $batchData["branch"] . " / " . $batchData["faculty"] ?></span>
                                                                                    </div>

                                                                                    <!-- <?php
                                                                                            if (!empty($batchData['recordings'])) {
                                                                                            ?>
                                                                                        <div class="flex-grow-1"></div>

                                                                                        <div class="recordings-container">
                                                                                            <?php foreach ($batchData['recordings'] as $index => $recording) { ?>
                                                                                                <button class="recording-btn" onclick="window.open('<?= $recording ?>', '_blank');">
                                                                                                    <iconify-icon icon="hugeicons:video-01" width="18"></iconify-icon>
                                                                                                    Recording <?= $index + 1 ?>
                                                                                                </button>
                                                                                            <?php } ?>
                                                                                        </div>
                                                                                    <?php
                                                                                            }
                                                                                    ?> -->
                                                                                </div>
                                                                            </div>
                                                                        <?php
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

                                                    <?php
                                                    if (($lectureData['earning_status_id'] == 1) || $lectureData['earning_status_id'] == '') {
                                                    ?>
                                                        <div class="col-12 mt-4">
                                                            <div class="row p-4 m-0 g-0 component-theme">
                                                                <span class="sub-topic"><?= $lectureData['earning_status_id'] == '' ? 'Schedule Lecture Payment' : 'Update Scheduled Lecture Payment' ?></span>
                                                                <div class="col-12 p-0 g-0 mt-4">
                                                                    <div class="row gx-4 gy-3 align-items-end">
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Start Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" disabled value="<?php echo $lectureData["start_time"]; ?>">
                                                                        </div>
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">End Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" disabled value="<?php echo $lectureData["end_time"]; ?>">
                                                                        </div>
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Scheduled Duration</label>
                                                                            <input type="number" class="form-control custom-input mt-2" disabled value="<?php echo $lectureData["duration"]; ?>">
                                                                        </div>
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Amount (Rate per hour)</label>
                                                                            <input type="text" class="form-control custom-input mt-2" disabled value="<?php echo number_format($hourly_rate, 2) ?>">
                                                                        </div>
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Conducted Duration</label>
                                                                            <input type="number" class="form-control custom-input mt-2" onkeyup="calculateDurationLecturerAmount(this.value,'<?= $hourly_rate ?>')" value="<?php echo $lectureData["conducted_duration"] == '' ? $lectureData["duration"] : $lectureData["conducted_duration"]; ?>" id="duration">
                                                                            <span id="duration-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Amount (Schedule to pay)</label>
                                                                            <input type="text" class="form-control custom-input mt-2" value="<?php echo $lectureData['amount'] == '' ?  $amount : $lectureData['amount']; ?>" id="amount">
                                                                            <span id="amount-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 d-flex flex-column">
                                                                            <label class="text-14 main-text" for="">Note</label>
                                                                            <textarea name="" class="form-control custom-input mt-2" rows="5" id="note"></textarea>
                                                                            <span id="note-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                    </div>
                                                                    <?php
                                                                    if ($lectureData['earning_status_id'] == '') {
                                                                    ?>
                                                                        <div class="row justify-content-end mt-4">
                                                                            <div class="col-12 col-lg-8 d-flex gap-2">
                                                                                <button class="custom-btn w-50" onclick="scheduleLecturerLecturePayment(this,<?= $id ?>);">Confirm Scheduled Payment</button>
                                                                                <button class="custom-danger-btn w-50" onclick="rejectLecturerLecturePayment(this,<?= $id ?>);">Reject Scheduled Payment</button>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    } else if ($lectureData['earning_status_id'] == 1) {
                                                                    ?>
                                                                        <div class="row justify-content-end mt-4">
                                                                            <div class="col-12 col-lg-4">
                                                                                <button class="custom-btn w-100" onclick="updateScheduledLecturerLecturePayment(this,<?= $lectureData['lecturer_payments_id'] ?>);">Save Changes</button>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>


                                                    <?php
                                                    if ($lectureData['earning_status_id'] != '') {
                                                    ?>
                                                        <div class="col-12 mt-4">
                                                            <div class="row p-4 m-0 g-0 component-theme">
                                                                <div class="col-12 mb-4">
                                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                <?php
                                                                                $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `lecturer_payments_logs` 
                                                                            WHERE `lecturer_payments_lecturer_payments_id`='" . $lectureData['lecturer_payments_id'] . "'");
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
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="user-table-data">
                                                                                    <?php
                                                                                    $query = "SELECT * FROM `lecturer_payments_logs`
                                                                    INNER JOIN `users` ON `lecturer_payments_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    WHERE `lecturer_payments_lecturer_payments_id` = '" . $lectureData['lecturer_payments_id'] . "' ORDER BY `date_updated` DESC";

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
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["date_updated"])) ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"]; ?></span></td>
                                                                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>
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
}
?>