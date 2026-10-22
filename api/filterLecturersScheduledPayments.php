<?php
$status = new stdClass();
require_once "../includes/connection.php";
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

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $lecturer = isset($decoded['lecturer']) ? $decoded['lecturer'] : 0;
                    $from = isset($decoded['from']) ? $decoded['from'] : 0;
                    $to = isset($decoded['to']) ? $decoded['to'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $lecturerPaymentsDateFilter = "";
                    $assignmentPaymentsDateFilter = "";

                    if (!empty($from)) {
                        $fromEscaped = Database::escape($from);
                        $lecturerPaymentsDateFilter .= " AND DATE(date_added) >= '" . $fromEscaped . "'";
                        $assignmentPaymentsDateFilter .= " AND DATE(date_added) >= '" . $fromEscaped . "'";
                    }

                    if (!empty($to)) {
                        $toEscaped = Database::escape($to);
                        $lecturerPaymentsDateFilter .= " AND DATE(date_added) <= '" . $toEscaped . "'";
                        $assignmentPaymentsDateFilter .= " AND DATE(date_added) <= '" . $toEscaped . "'";
                    }

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
                            WHERE earning_status_earning_status_id = '1'" . $lecturerPaymentsDateFilter . "
                            GROUP BY users_user_id
                        ) lp ON lp.users_user_id = u.user_id

                        LEFT JOIN (
                            SELECT users_user_id, SUM(amount) AS total_assignments_payments
                            FROM assignment_payments
                            WHERE earning_status_earning_status_id = '1'" . $assignmentPaymentsDateFilter . "
                            GROUP BY users_user_id
                        ) ap ON ap.users_user_id = u.user_id

                        WHERE (lp.users_user_id IS NOT NULL 
                        OR ap.users_user_id IS NOT NULL)";

                    if (!empty($search_text)) {
                        $query .= " AND (`u`.`user_fname` LIKE '%" . $search_text . "%')";
                    }

                    if (!empty($lecturer)) {
                        $query .= " AND `u`.`user_id`='" . $lecturer . "'";
                    }

                    $results = Database::search($query);
                    $pageno = 1;
                    $results_count = $results->num_rows;
                    $result_per_page = 100;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);
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
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lectures Payments</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assignments Payments</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Total Schedule Payments</th>
                                <?php
                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 2)) {
                                ?>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                <?php
                                }
                                ?>

                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php

                            if ($payments->num_rows > 0) {
                                while ($data = $payments->fetch_assoc()) {
                                    $total = $data["total_lecture_payments"] + $data["total_assignments_payments"];
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
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
                                        <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($data["total_lecture_payments"], 2); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($data["total_assignments_payments"], 2); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($total, 2); ?></span></td>
                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 2)) {
                                        ?>
                                            <td class="pe-4 py-3 align-middle">
                                                <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='lecturer-scheduled-payments-details?lecturer-id=<?= $data['user_id']; ?>'">Pay Now</button>
                                            </td>
                                        <?php
                                        }
                                        ?>


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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersScheduledPagination('previousPage')">
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
                                            $action = $isActive ? '' : "onclick=\"handleLecturersScheduledPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersScheduledPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
