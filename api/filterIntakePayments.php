<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $intake_id = isset($decoded['intakeId']) ? $decoded['intakeId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $category = isset($decoded['category']) ? $decoded['category'] : '';
                    $status_selector = isset($decoded['status']) ? $decoded['status'] : 0;
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $sortByAmount = isset($decoded['sortByAmount']) ? $decoded['sortByAmount'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortById = isset($decoded['sortById']) ? $decoded['sortById'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    if ($intake_id == 0) {
                        $status->error = 'Invalid Intake ID';
                    } else {
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                        if (!(SessionUser::ifUserIsPro($sessionUserRole) ||  SessionUser::ifUserIsAccounts($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $intake_id))) {
                            $status->error = 'You do not have permission to view this page';
                        } else {

                            $query = "SELECT
                            `cp`.`course_payments_id`,
                            `ps`.`payment_status`,
                            `ps`.`payment_status_id`,
                           `cp`.`due_date`,
                            `cp`.`amount`,
                           
                            `cp`.`discount`,
                            `cp`.`discount_type_discount_type_id`,
                            `shi`.`student_id`,
                            `ld`.`first_name`,
                            `ld`.`last_name`,
                            `t`.`title`,
                            `s`.`profile_img`,
                            `c`.`code`,
                            `c`.`decimal_places`,
                            `pt`.`payment_type`,
                            IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                            IFNULL(cpp.paid_amount, 0) AS paid_amount,
                            IFNULL(cpp.total_discount, 0) AS total_discount,
                            IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                            FROM `course_payments` AS `cp`
                        INNER JOIN `payment_status` AS `ps` 
                            ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                       
                        INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                            INNER JOIN `payment_category` AS `pc`
                            ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                            INNER JOIN `currencies` AS `c`
                            ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                        INNER JOIN `payment_type` AS `pt`
                            ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`    
                        INNER JOIN `students_has_intakes` AS `shi` 
                            ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                        INNER JOIN `students` AS `s` 
                            ON `shi`.`students_student_id` = `s`.`student_id`
                        INNER JOIN `login_details` AS `ld`
                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                        INNER JOIN `title` AS `t` 
                            ON `s`.`title_title_id` = `t`.`title_id`
                       LEFT JOIN (
                            SELECT
                                course_payments_course_payments_id,
                                SUM(amount) AS panelty_amount
                            FROM panelty_payments
                            GROUP BY course_payments_course_payments_id
                        ) pp
                            ON cp.course_payments_id = pp.course_payments_course_payments_id
                    
                        LEFT JOIN (
                            SELECT
                                course_payments_course_payments_id,
                                SUM(paid_amount) AS paid_amount,
                                SUM(discount_amount) AS total_discount,
                                SUM(panelty_amount) AS total_sub_panelty_amount
                            FROM course_payment_has_payments
                            WHERE payment_status_payment_status_id IN (2,3,5,6,8)
                            GROUP BY course_payments_course_payments_id
                        ) cpp
                            ON cp.course_payments_id = cpp.course_payments_course_payments_id 
                        WHERE `shi`.`intakes_intake_id` = '" . $intake_id . "'
                      AND `ld`.`status_status_id`= 1
                      
                        ";


                            if ($search_text != "") {
                                $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `shi`.`student_id` LIKE '%" . $search_text . "%')";
                            }

                            if ($category != 0) {
                                $query .= " AND `pshi`.`payment_category_payment_category_id`='" . $category . "'";
                            }

                            if ($status_selector != 0) {
                                $query .= " AND `ps`.`payment_status_id` = '" . $status_selector . "'";
                            }


                            $query .= "   GROUP BY `cp`.`course_payments_id`";

                            if ($sortByName != 0) {
                                $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                                if (strpos($query, 'ORDER BY') === false) {
                                    $query .= " ORDER BY `first_name` " . $sort;
                                } else {
                                    $query .= ", `first_name` " . $sort;
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

                            if ($sortByAmount != 0) {
                                $sort = $sortByAmount == 1 ? 'ASC' : 'DESC';
                                if (strpos($query, 'ORDER BY') === false) {
                                    $query .= " ORDER BY `cp`.`amount` " . $sort;
                                } else {
                                    $query .= ", `cp`.`amount` " . $sort;
                                }
                            }

                            if ($sortByDate != 0) {
                                $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                                if (strpos($query, 'ORDER BY') === false) {
                                    $query .= " ORDER BY `cp`.`due_date` " . $sort;
                                } else {
                                    $query .= ", `cp`.`due_date` " . $sort;
                                }
                            }

                            if ($sortByAmount == 0 && $sortByDate == 0 && $sortByName == 0 && $sortById == 0) {
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
                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                <div class="d-flex justify-content-between align-items-center w-100" onclick="handleIntakeHasPaymentsPagination(<?= $intake_id ?>,'sortByName')">
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

                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $intake_id ?>,'sortById')">
                                            <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                <span class="text-14 main-text fw-medium me-2">Student Id</span>
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
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                            <span class="text-14 main-text fw-medium me-2">Payment Type</span>
                                        </th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $intake_id ?>,'sortByDate')">
                                            <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                <span class="text-14 main-text fw-medium me-2">Due Date</span>
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
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $intake_id ?>,'sortByAmount')">
                                            <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                <span class="text-14 main-text fw-medium me-2">Amount</span>
                                                <?php
                                                if ($sortByAmount == 0) {
                                                ?>
                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                <?php
                                                } else {
                                                ?>
                                                    <iconify-icon icon="<?= $sortByAmount == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </th>


                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Balance Amount</th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>

                                        <?php

                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $intake_id)) {
                                        ?>
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php
                                    if ($users->num_rows > 0) {
                                        while ($data = $users->fetch_assoc()) {
                                            $discountAmount = $data["discount"];
                                            if ($data["discount_type_discount_type_id"] == 3) {
                                                $discountAmount = $data["amount"] / 100 * $data["discount"];
                                            }
                                            $payableAmount = $data["amount"] - $discountAmount - $data["total_discount"] + $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                            $balance = $payableAmount - $data["paid_amount"];
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
                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["due_date"] != '' ? date("Y-m-d", strtotime($data["due_date"])) : 'N/A'; ?></span></td>
                                                <td class="py-2 align-middle"><span class="text-14 main-text"> <?= $data['code'] . " " . number_format($payableAmount, 00); ?></span></td>
                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data["paid_amount"], 00) ?></span></td>
                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($balance, 00); ?></span></td>
                                                <td class="py-2 align-middle">
                                                    <?php
                                                    $status_classes = [
                                                        "0"     => "table-btn-purple",
                                                        "1"  => "table-btn-yellow",
                                                        "2"  => "table-btn-orange",
                                                        "3"  => "table-btn-success",
                                                        "4"  => "table-btn-danger",
                                                        "5"  => "table-btn-orange",
                                                        "6"  => "table-btn-success",
                                                        "7"  => "table-btn-danger",
                                                        "9"  => "table-btn-success",
                                                    ];
                                                    $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                    ?>
                                                    <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                                </td>
                                                <?php

                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $intake_id)) {
                                                ?>
                                                    <td class="py-3 align-middle">
                                                        <div class="dropdown">
                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payment?payment-id=<?= $data['course_payments_id'] ?>">
                                                                        <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                        Edit</a></li>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleIntakeHasPaymentsPagination(<?= $intake_id ?>,'previousPage')">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;
                                                function renderPaymentPageButton($catId, $i, $isActive = false)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleIntakeHasPaymentsPagination('{$catId}','goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                        renderPaymentPageButton($intake_id, $i, $pageno == $i);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleIntakeHasPaymentsPagination(<?= $intake_id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                            <span class="text-14 main-text fw-semibold">Next</span>
                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        </button>
                                    </div>
                                </nav>
                            </div>
<?php
                        }
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