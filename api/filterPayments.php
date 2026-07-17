<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) ||  UserPermissions::hasPermission('payments', 15) || UserPermissions::hasPermission('payments', 1))) {
                        $status->error = 'You do not have permission to perform this action!';
                    } else {
                        $category_id = isset($decoded['categoryId']) ? $decoded['categoryId'] : 0;
                        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                        $status_selector = isset($decoded['status']) ? $decoded['status'] : 0;
                        $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                        $year = isset($decoded['year']) ? $decoded['year'] : 0;
                        $month = isset($decoded['month']) ? $decoded['month'] : 0;
                        $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                        $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                        $sortById = isset($decoded['sortById']) ? $decoded['sortById'] : 0;
                        $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                        $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                        $query = "
                        SELECT 
                            `cp`.`course_payments_id`,
                             `cp`.`discount`,
                            `cp`.`discount_type_discount_type_id`,
                            `ps`.`payment_status`,
                            `ps`.`payment_status_id`,
                            `pm`.`payment_method`,
                            `cp`.`amount`,
                            `cp`.`due_date`,
                            `cpp`.`paid_amount`,
                            `shi`.`student_id`,
                            `ld`.`first_name`,
                            `ld`.`last_name`,
                            `t`.`title`,
                            `s`.`profile_img`,
                            MIN(`pl`.`date_updated`) AS `date_added`,
                            SUM(`pp`.`amount`) AS `panelty_charge`,
                            SUM(`cpp`.`panelty_amount`) AS `total_sub_penalty_amount`,
                            `cpp`.`discount_amount` AS `sub_discount`,
                            `cpp`.`course_payment_has_payment_id`,
                            `cpp`.`date_paid`,
                            `c`.`code`,
                            `c`.`decimal_places`,
                            `pgp`.`payment_type`
                        
                        FROM `course_payment_has_payments` `cpp`
                        INNER JOIN `course_payments` `cp`
                            ON `cp`.`course_payments_id` = `cpp`.`course_payments_course_payments_id`
                        INNER JOIN `payment_status` `ps` 
                            ON `cpp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                        INNER JOIN `payment_type` `pgp` 
                            ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
                        INNER JOIN `payment_method` `pm` 
                            ON `cpp`.`payment_method_payment_method_id` = `pm`.`payment_method_id`
                        INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                        INNER JOIN `students_has_intakes` AS `shi` 
                            ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                        INNER JOIN `students` AS `s` 
                            ON `shi`.`students_student_id` = `s`.`student_id`
                        INNER JOIN `login_details` AS `ld`
                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                        INNER JOIN `title` AS `t` 
                            ON `s`.`title_title_id` = `t`.`title_id`
                        INNER JOIN `intakes` AS `i` 
                            ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                        INNER JOIN `programmes` AS `p` 
                            ON `i`.`programmes_programme_id` = `p`.`programme_id`
                        INNER JOIN `faculties` AS `f` 
                            ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
                         INNER JOIN `payment_category` AS `pc` 
                            ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                        INNER JOIN `currencies` AS `c`
                            ON `pc`.`currencies_currency_id` = `c`.`currency_id`  
                        LEFT JOIN `payment_logs` AS `pl`
                            ON `cp`.`course_payments_id` = `pl`.`course_payments_course_payments_id`
                            AND `cpp`.`course_payment_has_payment_id` = `pl`.`course_payment_has_payment_id`
                        LEFT JOIN `panelty_payments` AS `pp`
                            ON `cp`.`course_payments_id` = `pp`.`course_payments_course_payments_id`      
                        WHERE `pshi`.`payment_category_payment_category_id` = '" . $category_id . "'
                       
                        ";

                        if ($search_text != "") {
                            $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `shi`.`student_id` LIKE '%" . $search_text . "%')";
                        }

                        if ($status_selector != 0) {
                            $query .= " AND `ps`.`payment_status_id` = '" . $status_selector . "'";
                        }

                        if ($sort != 0) {
                            if ($sort == 1) {
                                $query .= " AND `payment_method_id` = '1'";
                            } else if ($sort == 2) {
                                $query .= " AND `payment_method_id` = '2'";
                            }
                        }

                        if ($year != 0) {
                            $query .= " AND YEAR(`pl`.`date_updated`) = '" . $year . "'";
                        }

                        if ($month != 0) {
                            $query .= " AND MONTH(`pl`.`date_updated`) = '" . $month . "'";
                        }

                        $query .= " GROUP BY `cp`.`course_payments_id`";

                        if ($sortByDate != 0) {
                            $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                            if (strpos($query, 'ORDER BY') === false) {
                                $query .= " ORDER BY `cpp`.`date_paid` " . $sort;
                            } else {
                                $query .= ", `cpp`.`date_paid` " . $sort;
                            }
                        }

                        if ($sortByName != 0) {
                            $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                            if (strpos($query, 'ORDER BY') === false) {
                                $query .= " ORDER BY `ld`.`first_name` " . $sort;
                            } else {
                                $query .= ", `ld`.`first_name` " . $sort;
                            }
                        }

                        if ($sortById != 0) {
                            $sort = $sortById == 1 ? 'ASC' : 'DESC';
                            if (strpos($query, 'ORDER BY') === false) {
                                $query .= " ORDER BY `shi`.`student_id` " . $sort;
                            } else {
                                $query .= ", `shi`.`student_id` " . $sort;
                            }
                        }


                        if ($sortByDate == 0  && $sortByName == 0 && $sortById == 0) {
                            $query .= " ORDER BY `cp`.`course_payments_id` DESC";
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
                        $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                        <div class="d-flex flex-row align-items-center">
                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleSelectedPaymentCategoryPagination(<?= $category_id ?>,'sortByName')">
                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                <?php
                                                if ($sortByName == 0) {
                                                ?>
                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                <?php
                                                } else {
                                                ?>
                                                    <iconify-icon icon="<?= $sortByName == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleSelectedPaymentCategoryPagination(<?= $category_id ?>,'sortById')">
                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                            <span class="text-14 main-text fw-medium me-2">Student ID</span>
                                            <?php
                                            if ($sortById == 0) {
                                            ?>
                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php
                                            } else {
                                            ?>
                                                <iconify-icon icon="<?= $sortById == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment Type</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Due Date</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Discount</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty Amount</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                            <span class="text-14 main-text fw-medium me-2">Payable Amount</span>

                                        </div>
                                    </th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleSelectedPaymentCategoryPagination(<?= $category_id ?>,'sortByDate')">
                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                            <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                            <?php
                                            if ($sortByDate == 0) {
                                            ?>
                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php
                                            } else {
                                            ?>
                                                <iconify-icon icon="<?= $sortByDate == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment method</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                    <?php
                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 15)) {
                                    ?>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <?php
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody id="user-table-data">
                                <?php
                                if ($users->num_rows > 0) {
                                    while ($data = $users->fetch_assoc()) {
                                        $discount = $data['discount'];
                                        if ($data['discount_type_discount_type_id'] == 3) {
                                            $discount = $data['amount'] * $data['discount'] / 100;
                                        }
                                        $totalDiscount = $data['sub_discount'] + $discount;
                                        $totalPenalty = $data['panelty_charge'] + $data['total_sub_penalty_amount'];
                                        $payableAmount = $data['amount'] - $totalDiscount + $totalPenalty;
                                        $balance = $payableAmount - $data['paid_amount'];
                                ?>
                                        <tr>
                                            <td class="px-4 py-3 pe-0 align-middle">
                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
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
                                            </td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["payment_type"]; ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['due_date'] ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data['amount'], $data["decimal_places"]) ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($totalDiscount, $data["decimal_places"]) ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($totalPenalty, $data["decimal_places"]) ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($payableAmount, $data["decimal_places"]) ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data['paid_amount'], $data["decimal_places"]) ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date_paid"] == '' ? "N/A" : date("Y-m-d", strtotime($data["date_paid"])); ?>
                                                </span></td>
                                            <td class="py-2 align-middle">
                                                <?php
                                                $status_classes = [
                                                    "Bank Transfer"     => "table-btn-orange",
                                                    "Card Payment"  => "table-btn-pink",
                                                ];
                                                $sbutton_class = $status_classes[$data["payment_method"]];
                                                ?>
                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_method"]; ?></button>
                                            </td>
                                            <td class="py-2 align-middle">
                                                <?php
                                                $status_classes = [
                                                    "0" => "table-btn-purple",
                                                    "1" => "table-btn-yellow",
                                                    "2" => "table-btn-orange",
                                                    "3" => "table-btn-success",
                                                    "4" => "table-btn-danger",
                                                    "5" => "table-btn-orange",
                                                    "6" => "table-btn-success",
                                                    "7" => "table-btn-danger",
                                                    "8" => "table-btn-success",
                                                ];
                                                $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                ?>
                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                            </td>
                                            <?php
                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 15)) {
                                            ?>
                                                <td class="py-3 align-middle">
                                                    <div class="dropdown">
                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="payment-category-payment?payment-id=<?php echo $data['course_payment_has_payment_id']; ?>">
                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    View</a></li>
                                                        </ul>
                                                    </div>
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSelectedPaymentCategoryPagination(<?= $category_id ?>,'previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton($catId, $i, $isActive = false)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handleSelectedPaymentCategoryPagination('{$catId}','goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                            }

                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                    renderPageButton($category_id, $i, $pageno == $i);
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSelectedPaymentCategoryPagination(<?= $category_id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                        <span class="text-14 main-text fw-semibold">Next</span>
                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    </button>
                                </div>
                            </nav>
                        </div>
<?php
                    }
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