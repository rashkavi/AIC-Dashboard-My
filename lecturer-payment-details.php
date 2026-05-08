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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
            header("Location: ./lecturer-payments-history");
        } else {
            $paymentId = $_GET['payment-id'];
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            $payment = Database::search("SELECT * FROM `lecturer_payouts` 
            INNER JOIN `users` ON `lecturer_payouts`.`users_user_id`=`users`.`user_id`
            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
            INNER JOIN `payout_status` ON `lecturer_payouts`.`payout_status_payout_status_id` = `payout_status`.`payout_status_id`
            WHERE `lecturer_payouts`.`lecturer_payouts_id`='" . $paymentId . "'
            AND `lecturer_payouts`.`payout_status_payout_status_id` = '7'");

            if ($payment->num_rows == 0) {
                header("Location: ./lecturer-payments-history");
            } else {
                $paymentData = $payment->fetch_assoc();
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Lecturer Payment Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                        include "./includes/components/skeleton-add-edit.php"; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg">Lecturer Payouts request Details</span>
                                                            <span class="text-14 sub-text lh-base mt-1">Effortlessly manage users, access profiles, and maintain oversight</span>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="row p-4 m-0 g-0 component-theme w-100">
                                                    <div class="col-12">
                                                        <div class="payment-timeline">
                                                            <div class="timeline-progress">
                                                                <?php
                                                                $query = "SELECT * FROM `payout_status`";
                                                                $progress = 0;
                                                                $progressColor = "";
                                                                switch ($paymentData['payout_status_id']) {
                                                                    case 1:
                                                                        $progressColor = "completed";
                                                                        $progress = 100 / 3 * 0;
                                                                        $query .= " WHERE `payout_status_id` IN (1,4,7)";
                                                                        break;
                                                                    case 2:
                                                                        $progressColor = "completed";
                                                                        $progress = 100 / 3 * 0;
                                                                        $query .= " WHERE `payout_status_id` IN (2,4,7)";
                                                                        break;
                                                                    case 3:
                                                                        $progressColor = "reject";
                                                                        $progress = 100 / 3 * 0;
                                                                        $query .= " WHERE `payout_status_id` IN (3,4,7)";
                                                                        break;
                                                                    case 4:
                                                                        $progressColor = "completed";
                                                                        $progress = 100 / 3 * 1;
                                                                        $query .= " WHERE `payout_status_id` IN (2,4,7)";
                                                                        break;
                                                                    case 5:
                                                                        $progressColor = "completed";
                                                                        $progress = 100 / 3 * 2;
                                                                        $query .= " WHERE `payout_status_id` IN (2,5,7)";
                                                                        break;
                                                                    case 6:
                                                                        $progressColor = "reject";
                                                                        $progress = 100 / 3 * 2;
                                                                        $query .= " WHERE `payout_status_id` IN (2,6,7)";
                                                                        break;
                                                                    case 7:
                                                                        $progressColor = "accepted";
                                                                        $progress = 100 / 3 * 3;
                                                                        $query .= " WHERE `payout_status_id` IN (2,5,7)";
                                                                        break;
                                                                }

                                                                ?>
                                                                <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                            </div>
                                                            <div class="timeline-items">
                                                                <?php

                                                                $icons = ["material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve"];

                                                                $paymentStatus = Database::search($query);
                                                                while ($status = $paymentStatus->fetch_assoc()) {
                                                                    $timelineItemColor = "";
                                                                    $bubbleAction = "";

                                                                    if ($paymentData['payout_status_id'] >= $status['payout_status_id']) {
                                                                        if ($paymentData['payout_status_id'] == 3 || $paymentData['payout_status_id'] == 6) {
                                                                            $timelineItemColor = "reject";
                                                                        } else if ($paymentData['payout_status_id'] == 7) {
                                                                            $timelineItemColor = "accepted";
                                                                        } else {
                                                                            if ($paymentData['payout_status_id']  ==  $status['payout_status_id'] && $paymentData['payout_status_id'] != 5) {
                                                                                $bubbleAction = "pulse";
                                                                            } else {
                                                                                $timelineItemColor = "completed";
                                                                            }
                                                                        }
                                                                    } else if ($paymentData['payout_status_id'] == 5 && $status['payout_status_id'] == 7) {
                                                                        $bubbleAction = "pulse";
                                                                    }

                                                                ?>
                                                                    <div class="timeline-item <?= $timelineItemColor ?>">
                                                                        <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                            <iconify-icon icon="<?= $icons[$status['payout_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                        </div>
                                                                        <div class="timeline-label">
                                                                            <span><?= $status['payout_status'] ?></span>
                                                                            <div class="status-detail">Initial state</div>
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
                                                                        <span class="reference-label">PAYOUT REQUEST NO</span>
                                                                        <h2 class="reference-number">#<?= $paymentData["lecturer_payouts_id"]; ?></h2>
                                                                        <hr>
                                                                    </div>

                                                                    <!-- Amount on right -->
                                                                    <div class="amount-section">
                                                                        <div class="amount-display">
                                                                            <span class="amount-label">Total Amount</span>
                                                                            <span class="amount-value">LKR <?= number_format($paymentData["amount"], 2); ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 mt-4">
                                                <div class="row p-4 m-0 g-0 component-theme w-100">

                                                    <div class="col-12 mb-4">
                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                                    <?php
                                                                    $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `payout_logs` WHERE `lecturer_payouts_lecturer_payouts_id`='" . $paymentData['lecturer_payouts_id'] . "'");
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
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        $query = "SELECT * FROM `payout_logs`
                                                                    INNER JOIN `users` ON `payout_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    WHERE `lecturer_payouts_lecturer_payouts_id` = '" . $paymentData['lecturer_payouts_id'] . "' ORDER BY `date_updated` DESC";

                                                                        $users = Database::search($query);
                                                                        if ($users->num_rows > 0) {
                                                                            while ($data = $users->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td colspan="4" class="px-4 py-3 pe-0 align-middle">
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
                                                                                    <td colspan="4" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"]; ?></span></td>
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
?>