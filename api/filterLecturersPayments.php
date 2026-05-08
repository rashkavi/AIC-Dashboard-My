<?php
$status = new stdClass();
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $sessionUserObject = SessionUser::getCurrentUserObject();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    require_once "../includes/connection.php";

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                    $query = "
                    SELECT 
                        `lp`.*, 
                        `u`.*, 
                        `t`.*, 
                        `ps`.*, 
                        `plogs`.`date_updated` AS `latest_log_time`,
                        `u2`.`user_fname` AS `latest_log_ufname`,
                        `u2`.`user_lname` AS `latest_log_ulname`,
                        `t2`.`title` AS `latest_log_utitle`,
                        `u2`.`profile_img` AS `latest_log_uprofile_img`
                    FROM `lecturer_payouts` `lp`
                    INNER JOIN `users` `u` 
                        ON `lp`.`users_user_id` = `u`.`user_id`
                    INNER JOIN `title` `t` 
                        ON `u`.`title_title_id` = `t`.`title_id`
                    INNER JOIN `payout_status` `ps` 
                        ON `lp`.`payout_status_payout_status_id` = `ps`.`payout_status_id`
                    LEFT JOIN (
                        SELECT 
                            `lecturer_payouts_lecturer_payouts_id`,
                            MAX(`date_updated`) AS `latest_log_time`
                        FROM `payout_logs`
                        GROUP BY `lecturer_payouts_lecturer_payouts_id`
                    ) `pl` ON `pl`.`lecturer_payouts_lecturer_payouts_id` = `lp`.`lecturer_payouts_id`
                    LEFT JOIN `payout_logs` `plogs` 
                        ON `plogs`.`lecturer_payouts_lecturer_payouts_id` = `lp`.`lecturer_payouts_id`
                       AND `plogs`.`date_updated` = `pl`.`latest_log_time`
                    LEFT JOIN `users` `u2` 
                        ON `plogs`.`users_user_id` = `u2`.`user_id`
                    LEFT JOIN `title` `t2` 
                        ON `u2`.`title_title_id` = `t2`.`title_id`
                    WHERE `lp`.`payout_status_payout_status_id` = '7'
                ";
               


                    if ($search_text != "") {
                        $query .= " AND (`u`.`user_fname` LIKE '%" . $search_text . "%' OR `u`.`user_lname` LIKE '%" . $search_text . "%' OR `u`.`user_email` LIKE '%" . $search_text . "%' OR `u`.`user_mobile` LIKE '%" . $search_text . "%')";
                    }

                    $query .= "  ORDER BY `lp`.`lecturer_payouts_id` DESC";

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $payments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Lecturer Name</span>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Request Date</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid By</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid Date</th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php
                            if ($payments->num_rows > 0) {
                                while ($data = $payments->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
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
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($data["amount"]); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['date']; ?></span></td>
                                        <td colspan="4" class="py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <?php
                                                if (!empty($data["latest_log_uprofile_img"]) && file_exists('./assets/imgs/users/' . $data["latest_log_uprofile_img"])) {
                                                ?>
                                                    <img src="./assets/imgs/users/<?= $data["latest_log_uprofile_img"]; ?>" class="hwr-40" alt="User image">
                                                <?php
                                                } else {
                                                    $firstNameInitial = strtoupper(substr($data["latest_log_ufname"], 0, 1));
                                                    $lastNameInitial = strtoupper(substr($data["latest_log_ulname"], 0, 1));
                                                ?>
                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                <?php
                                                }
                                                ?>
                                                <span class="text-14 main-text mx-2"><?= $data["latest_log_utitle"] . "." . $data["latest_log_ufname"] . " " . $data["latest_log_ulname"]; ?></span>
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['latest_log_time']; ?></span></td>
                                        <td class="pe-4 py-3 align-middle">
                                            <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='lecturer-payment-details?payment-id=<?= $data['lecturer_payouts_id']; ?>'">View</button>
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
                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <!-- Previous Button (col-1) -->
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersPaymentsPagination('previousPage')">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                </button>
                                <!-- Page Numbers (col-10, centered) -->
                                <div class="col-10 custom-pagination d-flex justify-content-center">
                                    <ul class="pagination-list d-flex mb-0">
                                        <?php
                                        $shudderPages1 = false;
                                        $shudderPages2 = false;

                                        function renderPageButton($i, $isActive = false)
                                        {
                                            $activeClass = $isActive ? 'active' : '';
                                            $action = $isActive ? '' : "onclick=\"handleLecturersPaymentsPagination('goToPage', '{$i}')\"";
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
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                    <span class="text-14 main-text fw-semibold">Next</span>
                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                </button>
                            </div>
                        </nav>
                    </div>
<?php

                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}


?>