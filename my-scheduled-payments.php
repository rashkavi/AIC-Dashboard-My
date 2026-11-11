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
        if (!SessionUser::ifUserIsLecturer($sessionUserRole)) {
            header("Location: ./dashboard");
        } else {
            $lecturerId = $sessionUserObject['user_id'];

            $query = "SELECT 
            u.*,
            t.title,
            lp.total_lecture_payments,
            ap.total_assignments_payments
        FROM users u
        INNER JOIN title t 
            ON u.title_title_id = t.title_id
        LEFT JOIN (
            SELECT users_user_id, SUM(amount) AS total_lecture_payments
            FROM lecturer_payments
            WHERE earning_status_earning_status_id = '1'
            AND users_user_id = '" . $lecturerId . "'
            GROUP BY users_user_id
        ) lp ON lp.users_user_id = u.user_id

        LEFT JOIN (
            SELECT users_user_id, SUM(amount) AS total_assignments_payments
            FROM assignment_payments
            WHERE earning_status_earning_status_id = '1'
            AND users_user_id = '" . $lecturerId . "'
            GROUP BY users_user_id
        ) ap ON ap.users_user_id = u.user_id

        WHERE u.user_id = '" . $lecturerId . "'";

            $result = Database::search($query);
            if ($result->num_rows == 0) {
                header("Location: ./my-payments");
            } else {
                $lecturerData = $result->fetch_assoc();

                $totalAmount = $lecturerData['total_lecture_payments'] + $lecturerData['total_assignments_payments'];

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                {

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $date = $dateTime->format('Y-m-d');
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>My Scheduled Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    include "./includes/side-menu.php";
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
                                            include "./includes/components/skeleton-table.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">My Scheduled Payments</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./my-payments">My Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./my-scheduled-payments">Scheduled Payments</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="payment-card-container mb-4">
                                                    <div class="payment-card">
                                                        <div class="card-background"></div>
                                                        <div class="card-content">
                                                            <div class="payment-header">
                                                                <div class="reference-section">
                                                                    <span class="reference-label">LECTURER</span>
                                                                    <h2 class="reference-number"><?= $lecturerData["title"] . "." . $lecturerData["user_fname"] . " " . $lecturerData["user_lname"]; ?></h2>
                                                                    <hr>
                                                                </div>
                                                                <div class="amount-section">
                                                                    <div class="amount-display">
                                                                        <span class="amount-label">Total Scheduled Amount</span>
                                                                        <span class="amount-value">LKR <?= number_format($lecturerData["total_lecture_payments"] + $lecturerData["total_assignments_payments"], 2); ?></span>
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
                                                                        <span class="detail-label">Lecture Payments</span>
                                                                        <span class="detail-value">LKR <?= number_format($lecturerData["total_lecture_payments"], 2) ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="detail-item">
                                                                    <div class="detail-icon">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                        </svg>
                                                                    </div>
                                                                    <div class="detail-content">
                                                                        <span class="detail-label">Assignments Payments</span>
                                                                        <span class="detail-value">LKR <?= number_format($lecturerData["total_assignments_payments"], 2) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="reference-label mt-4">Bank Account Details</span>
                                                            <?php
                                                            $bank_account = Database::search("SELECT * FROM `bank_account` WHERE `users_user_id`='" . $lecturerData["user_id"] . "'");
                                                            if ($bank_account->num_rows > 0) {
                                                                $bank_account_data = $bank_account->fetch_assoc();
                                                            ?>
                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="wordpress:term-name" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Account Name</span>
                                                                            <span class="detail-value"><?= $bank_account_data["account_name"] ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="tabler:number" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Account Number</span>
                                                                            <span class="detail-value"><?= $bank_account_data["account_number"] ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="circum:bank" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Bank</span>
                                                                            <span class="detail-value"><?= $bank_account_data["bank"] ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="eos-icons:branch-outlined" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Branch</span>
                                                                            <span class="detail-value"><?= $bank_account_data["branch"] ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="material-symbols:error-outline-rounded" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Lecture Bank Account Details Not Found</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="container p-0 m-0 g-0 mb-4">
                                                    <div class="row g-4 d-flex">
                                                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-green-blue d-flex align-items-center justify-content-center"> <iconify-icon icon="bx:money-withdraw" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <div class="row no-gutters align-items-center">
                                                                            <div class="col mr-2">
                                                                                <div class="text-xs font-weight-bold text-dark-green text-uppercase mb-1">
                                                                                    TOTAL PAYABLE AMOUNT</div>
                                                                                <div class="stat-value mb-0 text-gray-800">LKR <?= number_format($totalAmount, 2) ?></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php
                                                $query = "SELECT 
                                                        tt.time_table_id,
                                                        tt.date,
                                                        tt.start_time,
                                                        tt.end_time,
                                                        tt.lecturers_lecturers_id,
                                                        title.title,
                                                        users.user_fname,
                                                        users.user_lname,
                                                        users.profile_img,
                                                        lecture_type.lecture_type,
                                                        lecture_type.lecture_type_id,
                                                        tt.lecture_title,
                                                        tt.duration,
                                                        COUNT(tt.subjects_subject_id) AS batch_count,
                                                        `lecturer_payments`.`earning_status_earning_status_id`,
                                                        `earning_status`.`earning_status`,
                                                        `lecturer_payments`.`amount`,
                                                        `lecturer_payments`.`lecturer_payments_id`,
                                                        `lecturer_payments`.`duration` AS payment_duration,
                                                        `time_table_status_time_table_status_id`,
                                                        `time_table_status`.`time_table_status`,
                                                        `time_table_status`.`time_table_status_id`,
                                                        `programmes`.`programme`
                                                        FROM time_table AS tt
                                                        INNER JOIN `time_table_status` ON `time_table_status`.`time_table_status_id` = `tt`.`time_table_status_time_table_status_id`
                                                        INNER JOIN `lecturers`  ON `lecturers`.`lecturers_id` = `tt`.`lecturers_lecturers_id`
                                                        INNER JOIN `subjects` ON `subjects`.`subject_id` = `tt`.`subjects_subject_id`
                                                        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                        INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
                                                        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
                                                        INNER JOIN `lecture_type` ON `lecture_type`.`lecture_type_id` = `tt`.`lecture_type_lecture_type_id`
                                                        INNER JOIN `lecturer_payments_has_lectures` ON `lecturer_payments_has_lectures`.`time_table_time_table_id` = `tt`.`time_table_id`
                                                        INNER JOIN `lecturer_payments` ON `lecturer_payments`.`lecturer_payments_id` = `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`
                                                        INNER JOIN `earning_status` ON `earning_status`.`earning_status_id` = `lecturer_payments`.`earning_status_earning_status_id`
                                                        WHERE  tt.lecture_type_lecture_type_id != 3
                                                        AND CONCAT(tt.date , ' ', tt.start_time) <= '" . date('Y-m-d H:i:s') . "'
                                                        AND users.user_id = '" . $lecturerId . "'
                                                        AND `earning_status`.`earning_status_id`='1'
                                                        GROUP BY 
                                                        tt.date,
                                                        tt.start_time,
                                                        tt.end_time,
                                                        users.user_id
                                                        ORDER BY tt.date DESC";

                                                $lectures = Database::search($query);
                                                if ($lectures->num_rows > 0) {
                                                ?>
                                                    <div class="col-12 p-4 component-theme">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <span class="sub-topic mb-4">Scheduled Lectures Payments</span>
                                                                    <div class="table-responsive-wrapper">
                                                                        <div class="table-responsive" id="users-table-container">
                                                                            <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <span class="text-14 main-text fw-medium">Date Time</span>
                                                                                            </div>
                                                                                        </th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture Type</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture Title</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Concurrent Batches</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Scheduled Duration</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Scheduled Payment</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="user-table-data">
                                                                                    <?php
                                                                                    if ($lectures->num_rows > 0) {
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
                                                                                        while ($data = $lectures->fetch_assoc()) {
                                                                                            $lecture_status = [
                                                                                                "1" => "table-btn-primary",
                                                                                                "2"  => "table-btn-danger",
                                                                                                "3" => "table-btn-purple",
                                                                                                "4" => "table-btn-pink",
                                                                                                "5" => "table-btn-yellow",
                                                                                                "6" => "table-btn-success",
                                                                                            ];
                                                                                            $status = "Awaiting Payment";
                                                                                            $statusId = '5';

                                                                                            $status_button_class = $lecture_status[$statusId];

                                                                                    ?>
                                                                                            <tr>
                                                                                                <td class="px-4 pe-0 py-3 pe-0 align-middle">
                                                                                                    <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                                                                        <p class="text-14 main-text"><?= $data['date'] ?></br><?= date("h:i A", strtotime($data['start_time'])) ?> - <?= date("h:i A", strtotime($data['end_time'])) ?></p>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["programme"]; ?></span></td>

                                                                                                <td class="py-2 align-middle">
                                                                                                    <?php
                                                                                                    $status_classes = [
                                                                                                        "1"     => "table-btn-primary",
                                                                                                        "2"  => "table-btn-yellow",
                                                                                                    ];
                                                                                                    $sbutton_class = $status_classes[$data["lecture_type_id"]];
                                                                                                    ?>
                                                                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["lecture_type"]; ?></button>
                                                                                                </td>
                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["lecture_title"]; ?></span></td>
                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["batch_count"]; ?></span></td>
                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= formatDuration($data["payment_duration"]); ?></span></td>
                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($data["amount"], 2) ?></span></td>
                                                                                                <td class="py-3 align-middle">
                                                                                                    <label class="table-btn <?php echo $status_button_class; ?> text-center w-100"><?php echo $status; ?></label>
                                                                                                </td>
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

                                                $assignmentPaymentsQuery = "SELECT 
                                                        `assignment_payments`.`assignment_payments_id`,
                                                        `assignment_payments`.`amount`,
                                                        `assignment_payments`.`date_added`,
                                                        `assignments`.`assignment_code`,
                                                        `assignments`.`assignment`,
                                                        `subjects`.`subject`,
                                                        `programmes`.`programme`,
                                                        `lecturers`.`per_assignment_fee`,
                                                        COUNT(`assignment_payments_has_marked_assignments`.`assignment_payments_has_marked_assignment_id`) AS marked_count
                                                        FROM `assignment_payments`
                                                        INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `assignment_payments`.`lecturers_lecturers_id`
                                                        INNER JOIN `assignments` ON `assignments`.`assignment_id` = `assignment_payments`.`assignments_assignment_id`
                                                        INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                        LEFT JOIN `assignment_payments_has_marked_assignments` ON `assignment_payments_has_marked_assignments`.`assignment_payments_id` = `assignment_payments`.`assignment_payments_id`
                                                        WHERE `assignment_payments`.`users_user_id` = '" . $lecturerId . "'
                                                        AND `assignment_payments`.`earning_status_earning_status_id` = '1'
                                                        GROUP BY `assignment_payments`.`assignment_payments_id`, `assignment_payments`.`amount`, `assignment_payments`.`date_added`, `assignments`.`assignment_code`, `assignments`.`assignment`, `subjects`.`subject`, `programmes`.`programme`, `lecturers`.`per_assignment_fee`
                                                        ORDER BY `assignment_payments`.`date_added` DESC";

                                                $assignmentPayments = Database::search($assignmentPaymentsQuery);
                                                if ($assignmentPayments->num_rows > 0) {
                                                ?>
                                                    <div class="col-12 p-4 component-theme mt-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <span class="sub-topic mb-4">Scheduled Assignments Payments</span>
                                                                    <div class="table-responsive-wrapper">
                                                                        <div class="table-responsive" id="assignments-table-container">
                                                                            <table class="table bl-solid m-0 p-0 g-0" id="assignments-table">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                            <div class="d-flex flex-row align-items-center">
                                                                                                <span class="text-14 main-text fw-medium">Scheduled Date</span>
                                                                                            </div>
                                                                                        </th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Subject</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Marked Assignments</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Fee Per Assignment</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Scheduled Payment</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="assignment-table-data">
                                                                                    <?php
                                                                                    while ($data = $assignmentPayments->fetch_assoc()) {
                                                                                    ?>
                                                                                        <tr>
                                                                                            <td class="px-4 pe-0 py-3 pe-0 align-middle">
                                                                                                <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                                                                    <p class="text-14 main-text"><?= date("Y-m-d", strtotime($data['date_added'])) ?></br><?= date("h:i A", strtotime($data['date_added'])) ?></p>
                                                                                                </div>
                                                                                            </td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["programme"]; ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["subject"]; ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["assignment_code"] . " - " . $data["assignment"]; ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["marked_count"]; ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($data["per_assignment_fee"], 2) ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($data["amount"], 2) ?></span></td>
                                                                                            <td class="py-3 align-middle">
                                                                                                <label class="table-btn table-btn-yellow text-center w-100">Awaiting Payment</label>
                                                                                            </td>
                                                                                            <td class="py-3 align-middle">
                                                                                                <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='lecturer-assignment-payment?payment-id=<?= $data['assignment_payments_id']; ?>'">View</button>
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