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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecturer-id']) && !empty($_GET['lecturer-id']))) {
            header("Location: ./lecturers-scheduled-payments");
        } else {
            $lecturerId = $_GET['lecturer-id'];

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

        WHERE u.user_id = '" . $lecturerId . "' AND lp.users_user_id IS NOT NULL 
        OR ap.users_user_id IS NOT NULL";

            $result = Database::search($query);
            if ($result->num_rows == 0) {
                header("Location: ./lecturers-scheduled-payments");
            } else {
                $lecturerData = $result->fetch_assoc();

                $totalAmount = $lecturerData['total_lecture_payments'] + $lecturerData['total_assignments_payments'];

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
                    header("Location: ./dashboard");
                } else {

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
                        <title>Lecturer Scheduled Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Lecturers Scheduled Payments</span>
                                                                <span class="text-14 sub-text lh-base mt-1">Effortlessly manage users, access profiles, and maintain oversight</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-2 mt-4 mt-lg-0">
                                                            <div class="row m-0 p-0 align-items-center">
                                                                <button class="custom-btn w-100" onclick="openMarkAsPaidModal()">Mark as Paid</button>
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
                                                                    <span class="reference-label">PAYMENT LECTURER</span>
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
                                                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-purple d-flex align-items-center justify-content-center"> <iconify-icon icon="ic:outline-plus" width="32" height="32" class="text-dark-purple"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <div class="row no-gutters align-items-center">
                                                                            <div class="col mr-2">
                                                                                <div class="text-xs font-weight-bold text-dark-purple text-uppercase mb-1">
                                                                                    TOTAL COLLECTING AMOUNT</div>
                                                                                <div class="stat-value mb-0 text-gray-800" id="totalCollected">LKR 0</div>
                                                                            </div>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                                                            <div class="component-theme p-3 w-100">
                                                                <div class="d-flex flex-row align-items-center">
                                                                    <div class="hwr-70 table-btn-orange d-flex align-items-center justify-content-center"> <iconify-icon icon="mdi:white-balance-sunny" width="32" height="32" class="text-dark-orange"></iconify-icon></div>
                                                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                                                        <div class="row no-gutters align-items-center">
                                                                            <div class="col mr-2">
                                                                                <div class="text-xs font-weight-bold text-dark-orange text-uppercase mb-1">
                                                                                    TOTAL BALANCE AMOUNT</div>
                                                                                <div class="stat-value mb-0 text-gray-800" data-total="<?= $totalAmount ?>" id="totalBalance">LKR <?= number_format($totalAmount, 2) ?></div>
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
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="lp-check-all" onchange="lecturerPaymentsCheckEvents()">
                                                                                                <span class="text-14 main-text fw-medium mx-2">Date Time</span>
                                                                                            </div>
                                                                                        </th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture Type</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture Title</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Concurrent Batches</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Scheduled Duration</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Scheduled Payment</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
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
                                                                                                        <input type="checkbox"
                                                                                                            class="form-check-input text-20 mt-0 mb-0 me-2 lp-row-check"
                                                                                                            data-amount="<?= $data['amount'] ?>"
                                                                                                            value="<?= $data['lecturer_payments_id'] ?>"
                                                                                                            id="lp-<?= $data['lecturer_payments_id']; ?>"
                                                                                                            <?= $data['time_table_status_time_table_status_id'] != 1 ? "disabled" : "" ?>
                                                                                                            onchange="lecuturePaymentsCheckRowEvents(<?= $data['lecturer_payments_id']; ?>)">
                                                                                                        <p class="text-14 main-text mx-2"><?= $data['date'] ?></br><?= date("h:i A", strtotime($data['start_time'])) ?> - <?= date("h:i A", strtotime($data['end_time'])) ?></p>
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
                                                                                                <td class="py-3 align-middle">
                                                                                                    <div class="dropdown">
                                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                        <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="rejectScheduledLecturePayment('<?php echo $data['lecturer_payments_id']; ?>')">
                                                                                                                    <iconify-icon icon="icon-park-outline:reject" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                                    Reject Payment</a></li>

                                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                    onclick="openCancelScheduleModal('<?= $data['time_table_id']; ?>', '<?= htmlspecialchars($data['title'] . '.' . $data['user_fname'] . ' ' . $data['user_lname'], ENT_QUOTES); ?>', '<?= $data['date']; ?>')">
                                                                                                                    <iconify-icon icon="ic:twotone-cancel" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                    Cancel Schedule</a></li>
                                                                                                        </ul>
                                                                                                    </div>
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

                        <div class="modal fade" id="markAsPaidModal" tabindex="-1" role="dialog" aria-labelledby="markAsPaidModalTitle" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLongTitle">Mark as Paid</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="col-12 p-0 g-0">
                                            <div class="row gx-4 gy-3">
                                                <div class="col-12 col-lg-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Total Payable Amount</label>
                                                    <input type="text" class="form-control custom-input mt-2" value="<?= "LKR " . $totalAmount ?>" disabled>
                                                </div>

                                                <div class="col-12 col-lg-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Total Collecting Amount</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="modalTotalCollected" value="LKR 0" disabled>
                                                </div>

                                                <div class="col-12 col-lg-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Date Paid</label>
                                                    <input type="date" class="form-control custom-input mt-2" id="date_paid" value="<?= $date ?>">
                                                </div>

                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text" for="">Note</label>
                                                    <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                                    <span id="note-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="col-12 col-md-4 custom-btn" onclick="markAsPaidLecturerPayments(this, <?= $lecturerId ?>);">Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="modal fade" id="cancelScheduleModal" tabindex="-1"
                            aria-labelledby="cancelScheduleModalTitle" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">

                                    <div class="d-flex justify-content-end pe-3 pt-3">
                                        <button type="button"
                                            class="btn-close"
                                            data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body text-center p-4">

                                        <div class="mb-3">
                                            <iconify-icon
                                                icon="mdi:alert-circle-outline"
                                                width="48"
                                                height="48"
                                                class="text-danger">
                                            </iconify-icon>
                                        </div>

                                        <h5 class="fw-semibold mb-2" id="cancelScheduleModalTitle">
                                            Cancel Schedule
                                        </h5>

                                        <p class="text-muted mb-3" id="cancelScheduleModalMessage">
                                            Are you sure you want to cancel this scheduled lecture payment? This action cannot be undone.
                                        </p>

                                        <div class="alert alert-warning text-start small mb-4" id="cancelScheduleDetails">
                                        </div>

                                        <div class="d-flex justify-content-center gap-3">
                                            <button type="button"
                                                class="btn btn-outline-secondary px-4 br-0"
                                                data-bs-dismiss="modal">
                                                No, Keep It
                                            </button>

                                            <button type="button"
                                                id="confirmCancelSchedule"
                                                class="btn btn-danger px-4 br-0">
                                                <iconify-icon icon="mdi:cancel" class="me-1"></iconify-icon>
                                                Yes, Cancel Schedule
                                            </button>
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