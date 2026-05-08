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
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
            header("Location: ./faculties");
        } else {

            $id = $_GET['payment-id'];
            $paymentDataPlan = Database::search("SELECT
                    `arp`.`assignment_recorrection_payment_id`, 
                    `ps`.`payment_status`,
                    `ps`.`payment_status_id`,
                    `arp`.`amount`,
                    `pm`.`payment_method`,
                    `arp`.`date_paid`,
                    `arp`.`refference`,
                    `arp`.`bank_bank_id`,
                    `arp`.`payment_receipt`,
                    `t`.`title`,
                    `s`.`first_name`,
                    `s`.`last_name`,
                    `a`.`assignment`
                FROM `assignment_recorrection_payment` AS `arp`
                INNER JOIN `payment_status` AS `ps` 
                    ON `arp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                INNER JOIN `payment_method` AS `pm` 
                    ON `arp`.`payment_method_payment_method_id` = `pm`.`payment_method_id`
                INNER JOIN `students_has_assignments` AS `sha`
                    ON `arp`.`students_has_assignments_students_has_assignments_id`=`sha`.`students_has_assignments_id`
                INNER JOIN `students` AS `s` 
                    ON `sha`.`students_student_id` = `s`.`student_id`
                INNER JOIN `assignments` AS `a`
                    ON `sha`.`assignments_assignment_id` = `a`.`assignment_id`    
                INNER JOIN `title` AS `t` 
                    ON `s`.`title_title_id` = `t`.`title_id`
                WHERE `arp`.`assignment_recorrection_payment_id` = '" . $id . "';
                ");

            if ($paymentDataPlan->num_rows == 0) {
                header("Location: ./students");
            } else {
                $paymentData = $paymentDataPlan->fetch_assoc();

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Student Assignmnet Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    <!-- Font Awesome for icons -->
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
                            <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                                <div class="row p-0 m-0 g-0 h-100">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("Course Overview");
                                        ?>
                                    </div>
                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include './includes/components/skeleton-header.php';
                                        include './includes/components/skeleton-add-edit.php'; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg"><?= $paymentData['title'] . " " . $paymentData['first_name'] . " " . $paymentData['last_name'] ?> - <?= $paymentData['assignment'] ?> Re-correction Payment</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.history.back()">Assignmnet Students Performance</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Student Assignment Re-correction Payment</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                        <?php
                                                        if ($sessionUserRole != null) {
                                                        ?>
                                                            <button class="custom-btn w-100" type="button" onclick="openUpdatePaymentStatusModal();">
                                                                Update Payment Status
                                                            </button>

                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 d-flex">
                                                <div class="row p-4 m-0 g-0 component-theme w-100">
                                                    <div class="col-12">
                                                        <div class="payment-timeline">
                                                            <div class="timeline-progress">
                                                                <?php
                                                                $query = "SELECT * FROM `payment_status`";
                                                                $progress = 0;
                                                                if ($paymentData['payment_status_id'] == 4) {
                                                                    $query .= " WHERE `payment_status_id` IN (1,2,4,5,6,8)";
                                                                    $progress = 100 / 6 * 3;
                                                                } else if ($paymentData['payment_status_id'] == 7) {
                                                                    $query .= " WHERE `payment_status_id` IN (1,2,3,5,7,8)";
                                                                    $progress = 100 / 6 * 5;
                                                                } else {
                                                                    $query .= " WHERE `payment_status_id` IN (1,2,3,5,6,8)";
                                                                }

                                                                if ($paymentData['payment_status_id'] == 8) {
                                                                    $progress = 100;
                                                                } else if ($paymentData['payment_status_id'] != 4 && $paymentData['payment_status_id'] != 7) {
                                                                    $progress = 100 / 6 * $paymentData['payment_status_id'];
                                                                }

                                                                ?>
                                                                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                                            </div>
                                                            <div class="timeline-items">
                                                                <?php

                                                                $paymentDataStatus = Database::search($query);
                                                                while ($status = $paymentDataStatus->fetch_assoc()) {

                                                                    if ($status['payment_status_id'] == 8) {
                                                                ?>
                                                                        <div class="timeline-item <?php echo $paymentData['payment_status_id'] === $status['payment_status_id'] ? 'completed' : '' ?>">
                                                                            <div class="timeline-bubble <?php echo $paymentData['payment_status_id'] === $status['payment_status_id'] ? 'pulse' : '' ?>">
                                                                                <i class="fas fa-clock"></i>
                                                                            </div>
                                                                            <div class="timeline-label">
                                                                                <span><?= $status['payment_status'] ?></span>
                                                                                <div class="status-detail">Initial state</div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <div class="timeline-item <?php echo $paymentData['payment_status_id'] === $status['payment_status_id'] ? 'active' : '' ?>">
                                                                            <div class="timeline-bubble <?php echo $paymentData['payment_status_id'] === $status['payment_status_id'] ? 'pulse' : '' ?>">
                                                                                <i class="fas fa-clock"></i>
                                                                            </div>
                                                                            <div class="timeline-label">
                                                                                <span><?= $status['payment_status'] ?></span>
                                                                                <div class="status-detail">Initial state</div>
                                                                            </div>
                                                                        </div>
                                                                <?php
                                                                    }
                                                                }
                                                                ?>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 px-4 pe-4 component-theme">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-12">
                                                        <div class="row g-4">
                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                <label for="">Payment Date</label>
                                                                <input type="text" class="form-control custom-input mt-2" readonly disabled value="<?php echo $paymentData["date_paid"]; ?>">
                                                            </div>

                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                <label for="">Amount</label>
                                                                <input type="text" class="form-control custom-input mt-2" readonly disabled value="LKR <?php echo $paymentData["amount"]; ?> .00">
                                                                <span class="text-12 text-danger" id="ref-id-feedback"></span>
                                                            </div>


                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                <label for="">Payment Method</label>
                                                                <input type="text" class="form-control custom-input mt-2" readonly disabled value="<?php echo $paymentData["payment_method"]; ?>">
                                                            </div>

                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                <label for="">Bank</label>
                                                                <?php
                                                                if ($paymentData['bank_bank_id'] != null) {
                                                                    $bank = Database::search("SELECT * FROM `bank` WHERE `bank_id`='" . $paymentData['bank_bank_id'] . "'");
                                                                    if ($bank->num_rows > 0) {
                                                                        $bank = $bank->fetch_assoc();
                                                                ?>
                                                                        <input type="text" class="form-control custom-input mt-2" readonly disabled value="<?php echo $bank["bank_name"] . ' | ACC:' . $bank["acc_no"] ?>">
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <input type="text" class="form-control custom-input mt-2" readonly disabled value="-">
                                                                <?php
                                                                    }
                                                                }
                                                                ?>

                                                            </div>
                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                <label for="">Reference Id</label>
                                                                <input type="text" class="form-control custom-input mt-2" readonly disabled value="<?php echo $paymentData["refference"]; ?>">
                                                            </div>
                                                            <?php
                                                            if ($paymentData['payment_receipt'] != null) {
                                                            ?>
                                                                <div class="col-12 col-lg-8 d-flex flex-column">
                                                                    <label class="mb-3">Payment Slip</label>
                                                                    <img src="assets/imgs/payment-slip/<?= $paymentData['payment_receipt']; ?>" class="w-100 h-auto cursor-pointer" alt="Payment Receipt" onclick="openFullScreen(this)">
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div id="fullScreenOverlay" class="full-screen-overlay" onclick="closeFullScreen()">
                                                <div class="image-container">
                                                    <img id="fullScreenImage" src="" alt="Full Screen Image" class="full-screen-image">
                                                </div>
                                            </div>

                                            <div class="col-12 p-4 component-theme">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 mb-4">
                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                                    <?php
                                                                    $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `assignment_recorrection_payment_log` WHERE `assignment_recorrection_payment_id`='" . $paymentData['assignment_recorrection_payment_id'] . "'");
                                                                    $data = $logs_count->fetch_assoc();
                                                                    ?>
                                                                    <span class="text-14 dark-theme fw-medium"><?php echo $data['count']; ?> Logs</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

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
                                                                            $query = "SELECT * FROM `assignment_recorrection_payment_log`
                                                                    INNER JOIN `users` ON `assignment_recorrection_payment_log`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    WHERE `assignment_recorrection_payment_id` = '" . $paymentData['assignment_recorrection_payment_id'] . "' ORDER BY `date_updated` DESC";

                                                                            $results = Database::search($query);
                                                                            $pageno = 1;
                                                                            $results_count = $results->num_rows;
                                                                            $result_per_page = $results_count;  // Consistent variable name

                                                                            if ($results_count > $result_per_page) {
                                                                                $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                            } else {
                                                                                $number_of_pages = 1;
                                                                            }

                                                                            $page_results = ($pageno - 1) * $result_per_page;
                                                                            $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                            if ($users->num_rows > 0) {
                                                                                while ($data = $users->fetch_assoc()) {
                                                                            ?>
                                                                                    <tr>
                                                                                        <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                                                                <?php
                                                                                                if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                <?php
                                                                                                } else {
                                                                                                    $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                    $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                ?>
                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["date_updated"]; ?></span></td>
                                                                                        <td colspan="4" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["note"]; ?></span></td>
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
                                                                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                            <div class="row p-4 align-items-center">
                                                                                <!-- Previous Button (col-1) -->
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleUsersPagination('previousPage')">
                                                                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                </button>
                                                                                <!-- Page Numbers (col-10, centered) -->
                                                                                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                    <ul class="pagination-list d-flex mb-0">
                                                                                        <?php
                                                                                        $shudderPages1 = false;
                                                                                        $shudderPages2 = false;

                                                                                        function renderPageButton($i, $isActive = false)
                                                                                        {
                                                                                            $activeClass = $isActive ? 'active' : '';
                                                                                            $action = $isActive ? '' : "onclick=\"handleUsersPagination('goToPage', '{$i}')\"";
                                                                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                        }

                                                                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                                renderPageButton($i, $pageno == $i);
                                                                                            } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                                $shudderPages1 = true;
                                                                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                            } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                                $shudderPages2 = true;
                                                                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                    </ul>
                                                                                </div>
                                                                                <!-- Next Button (col-1) -->
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleUsersPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                                    <span class="text-14 main-text fw-semibold">Next</span>
                                                                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </button>
                                                                            </div>
                                                                        </nav>
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

                    <div class="modal fade" id="updatePaymentStatus" tabindex="-1" role="dialog" aria-labelledby="updatePaymentStatusTitle" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLongTitle">Update Payment Status</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="col-12 p-0 g-0">
                                        <div class="row gx-4 gy-3">
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text required" for="">Payment Status</label>
                                                <select class="form-select custom-select mt-2" id="status-selector">
                                                    <?php
                                                    $status = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` != '1'");
                                                    while ($data = $status->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?php echo $data["payment_status_id"]; ?>" <?php echo $data["payment_status_id"] == $paymentData["payment_status_id"] ? "selected" : ""; ?>><?php echo $data["payment_status"]; ?></option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                                <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                            </div>
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text required" for="">Note</label>
                                                <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                                <span id="note-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="col-12 col-md-4 custom-btn" onclick="updateAssignmentRecorrectionPaymentStatus(<?= $paymentData['assignment_recorrection_payment_id'] ?>);">Update</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Animate progress bar
                            const progressBar = document.querySelector('.progress-bar');
                            setTimeout(() => {
                                progressBar.style.width = <?= $progress ?>;
                            }, 300);

                            // Add hover effects
                            const bubbles = document.querySelectorAll('.timeline-bubble');
                            bubbles.forEach(bubble => {
                                bubble.addEventListener('mouseenter', function() {
                                    this.style.transform = 'scale(1.15)';
                                });
                                bubble.addEventListener('mouseleave', function() {
                                    if (!this.closest('.timeline-item').classList.contains('active')) {
                                        this.style.transform = 'scale(1)';
                                    }
                                });
                            });
                        });
                    </script>
                </body>

                </html>
<?php

            }
        }
    }
}
?>