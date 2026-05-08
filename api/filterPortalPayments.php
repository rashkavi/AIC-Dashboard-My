<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
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
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;

                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

                    $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                    $query = "SELECT 
                                                                        `students`.`student_id` AS `sId`,
                                                                        `login_details`.`first_name`,
                                                                        `login_details`.`last_name`,
                                                                        `login_details`.`email`,
                                                                        `students`.`mobile`,
                                                                        `students`.`profile_img`,
                                                                        `title`.`title`,
                                                                        `status`.`status`,
                                                                        `portal_payment`.*,
                                                                        `payment_method`.`payment_method`,
                                                                        `portal_payment_status`.*
                                                                    FROM 
                                                                        `portal_payment`
                                                                    INNER JOIN 
                                                                        `students` 
                                                                        ON `portal_payment`.`students_student_id` = `students`.`student_id`
                                                                    INNER JOIN 
                                                                        `login_details` 
                                                                        ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                    INNER JOIN 
                                                                        `title` 
                                                                        ON `students`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN 
                                                                        `status` 
                                                                        ON `login_details`.`status_status_id` = `status`.`status_id`
                                                                    INNER JOIN `payment_method` 
                                                                        ON `portal_payment`.`payment_method_payment_method_id` = `payment_method`.`payment_method_id`   
                                                                    INNER JOIN
                                                                        `portal_payment_status` 
                                                                        ON `portal_payment`.`portal_payment_status_portal_payment_status_id` = `portal_payment_status`.`portal_payment_status_id`";

                    if ($search_text != "") {
                        $query .= " WHERE (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($sort != 0) {
                        if ($sort == 1) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `portal_payment_status_portal_payment_status_id`='1'";
                            } else {
                                $query .= " AND `portal_payment_status_portal_payment_status_id`='1'";
                            }
                        } else if ($sort == 2) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `portal_payment_status_portal_payment_status_id`='2'";
                            } else {
                                $query .= " AND `portal_payment_status_portal_payment_status_id`='2'";
                            }
                        } else if ($sort == 3) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `portal_payment_status_portal_payment_status_id`='3'";
                            } else {
                                $query .= " AND `portal_payment_status_portal_payment_status_id`='3'";
                            }
                        }
                    }

                    if ($sort == 0 || $sort == 3 || $sort == 4) {
                        $query .= " ORDER BY `portal_payment`.`portal_payment_id` DESC";
                    }

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
                    <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                    </div>
                                </th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid Amount</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Paid</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid Method</th>

                                <th scope="col" class="py-3 bg-table-header">
                                    <div class="d-flex flex-row align-items-center">
                                        <span class="text-14 main-text fw-medium">Status</span>
                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php

                            if ($payments->num_rows > 0) {
                                $payment_method = [
                                    "1"     => "table-btn-orange",
                                    "2"  => "table-btn-pink",
                                ];
                                $payment_status = [
                                    "1"  => "table-btn-primary",
                                    "2"  => "table-btn-yellow",
                                    "3"  => "table-btn-success",
                                    "4"  => "table-btn-danger",
                                ];
                                while ($data = $payments->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["sId"]; ?>" onchange="checkRowEvents(<?= $data['sId']; ?>);">
                                                <div class="d-flex align-items-center cursor-pointer mx-2" onclick="toggleCheckbox(<?= $data['sId']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="py-2 align-middle"> <span class="text-14 main-text">LKR <?= $data["amount"]; ?> .00</span></td>
                                        <td class="py-2 align-middle"> <span class="text-14 main-text"><?= $data["date_paid"]; ?></span></td>
                                        <td class="py-2 align-middle">
                                            <button class="table-btn <?php echo  $payment_method[$data["payment_method_payment_method_id"]]; ?> w-100"> <?php echo $data["payment_method"]; ?></button>
                                        </td>

                                        <td class="py-2 align-middle">
                                            <button class="table-btn <?php echo  $payment_status[$data["portal_payment_status_id"]]; ?> w-100"><?php echo $data["portal_payment_status"]; ?></button>
                                        </td>

                                        <td class="pe-4 pt-3 pb-3 align-middle">
                                            <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='./portal-payment?payment-id=<?= $data['portal_payment_id']; ?>'">View</button>
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
                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <!-- Previous Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handlePortalPaymnetAddStudentsPagination('previousPage');">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
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
                                            $action = $isActive ? '' : "onclick=\"handlePortalPaymnetAddStudentsPagination('goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                renderPageButton($i, $pageno == $i);
                                            } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                $shudderPages1 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                            } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                $shudderPages2 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                            }
                                        }
                                        ?>

                                    </ul>
                                </div>
                                <!-- Next Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handlePortalPaymnetAddStudentsPagination('nextPage',<?= $pageno ?>,<?= $number_of_pages ?>);">
                                    <span class="text-14 main-text fw-semibold">Next</span>
                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="darkColor px-1"></iconify-icon>
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
