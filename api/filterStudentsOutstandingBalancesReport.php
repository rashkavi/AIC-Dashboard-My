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
                        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                        $status_selector = isset($decoded['status']) ? $decoded['status'] : 0;
                        $branch = isset($decoded['branch']) ? $decoded['branch'] : 0;
                        $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : 0;
                        $program = isset($decoded['program']) ? $decoded['program'] : 0;
                        $intake = isset($decoded['intake']) ? $decoded['intake'] : 0;
                        $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                        $from = isset($decoded['from']) ? $decoded['from'] : 0;
                        $to = isset($decoded['to']) ? $decoded['to'] : 0;
                        $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                        $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                        $sortById = isset($decoded['sortById']) ? $decoded['sortById'] : 0;
                        $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                        $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                        $query = "
                                                                            SELECT
                                                                                pshi.payment_plan_has_students_has_intakes_id AS pphsiId,
                                                                                pshi.amount,
                                                                                pshi.discount,
                                                                                pshi.discount_type_discount_type_id,

                                                                                pc.payment_category,
                                                                                pc.payment_category_id,

                                                                                c.code,
                                                                                c.decimal_places,
                                                                                c.currency_id AS course_currency_id,

                                                                                shi.students_has_intakes_id,
                                                                                shi.student_id AS shiStudentId,
                                                                                shi.status_status_id,

                                                                                s.student_id AS aId,
                                                                                s.profile_img,

                                                                                ld.first_name,
                                                                                ld.last_name,
                                                                                t.title,

                                                                                i.intake,
                                                                                i.intake_id,
                                                                                prog.programme,
                                                                                prog.programme_id,
                                                                                fac.faculty,
                                                                                fac.faculty_id,

                                                                                st.status,

                                                                                IFNULL(SUM(CASE
                                                                                    WHEN cp.discount_type_discount_type_id = 2 THEN COALESCE(cp.discount, 0)
                                                                                    WHEN cp.discount_type_discount_type_id = 3 THEN (COALESCE(cp.amount, 0) * COALESCE(cp.discount, 0) / 100)
                                                                                    ELSE 0
                                                                                END), 0) AS cp_total_discount,

                                                                                IFNULL(SUM(COALESCE(ppn.panelty_amount, 0)), 0) AS main_total_panelty

                                                                            FROM payment_plan_has_students_has_intakes AS pshi

                                                                            INNER JOIN payment_category AS pc
                                                                                ON pshi.payment_category_payment_category_id = pc.payment_category_id

                                                                            INNER JOIN currencies AS c
                                                                                ON pc.currencies_currency_id = c.currency_id

                                                                            INNER JOIN students_has_intakes AS shi
                                                                                ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                                                                            INNER JOIN students AS s
                                                                                ON shi.students_student_id = s.student_id

                                                                            INNER JOIN login_details AS ld
                                                                                ON s.login_details_login_details_id = ld.login_details_id

                                                                            INNER JOIN title AS t
                                                                                ON s.title_title_id = t.title_id

                                                                            INNER JOIN intakes AS i
                                                                                ON shi.intakes_intake_id = i.intake_id

                                                                            INNER JOIN programmes AS prog
                                                                                ON i.programmes_programme_id = prog.programme_id

                                                                            INNER JOIN faculties AS fac
                                                                                ON prog.faculties_faculty_id = fac.faculty_id

                                                                            INNER JOIN status AS st
                                                                                ON shi.status_status_id = st.status_id

                                                                            LEFT JOIN course_payments AS cp
                                                                                ON pshi.payment_plan_has_students_has_intakes_id = cp.payment_plan_has_students_has_intakes_id

                                                                            LEFT JOIN (
                                                                                SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                                                                                FROM panelty_payments GROUP BY course_payments_course_payments_id
                                                                            ) ppn ON cp.course_payments_id = ppn.course_payments_course_payments_id

                                                                            WHERE cp.due_date < CURDATE()
                                                                        ";


                        if ($search_text != "") {
                            $query .= " WHERE (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `shi`.`student_id` LIKE '%" . $search_text . "%')";
                        }

                        if ($status_selector != 0) {
                            $query .= " AND `ps`.`payment_status_id` = '" . $status_selector . "'";
                        }

                        if (!empty($branch) && $branch != '0') {
                            $query .= " AND f.branches_branch_id = '$branch' ";
                        }
                        if (!empty($faculty) && $faculty != '0') {
                            $query .= " AND p.faculties_faculty_id = '$faculty' ";
                        }
                        if (!empty($programme) && $programme != '0') {
                            $query .= " AND i.programmes_programme_id = '$programme' ";
                        }
                        if (!empty($intake) && $intake != '0') {
                            $query .= " AND i.intake_id = '$intake' ";
                        }

                        if (!empty($filter) && $filter == '1') {
                            // This Month
                            $query .= " AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                AND YEAR(cpp.date_paid) = YEAR(CURDATE())";
                            $periodLabel = "This Month";
                        } elseif (!empty($filter) && $filter == '2') {
                            // Last Month
                            $query .= " AND MONTH(cpp.date_paid) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND YEAR(cpp.date_paid) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                        } elseif (!empty($filter) && $filter == '3') {
                            // Custom Date Range
                            if (!empty($from)) {
                                $query .= " AND DATE(cpp.date_paid) >= '$from' ";
                            }
                            if (!empty($to)) {
                                $query .= " AND DATE(cpp.date_paid) <= '$to' ";
                            }
                        }

                        $query .= "  GROUP BY pshi.payment_plan_has_students_has_intakes_id
                                                                            ORDER BY pshi.payment_plan_has_students_has_intakes_id DESC
                                                                        ";
                                                                          

                        $query .= " GROUP BY `cpp`.`course_payment_has_payment_id`";

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
                            $query .= " ORDER BY `cpp`.`course_payment_has_payment_id` DESC";
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
                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                        <div class="d-flex flex-row align-items-center">
                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPaymentsSumamryReportPagination('sortByName')">
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
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsPaymentsSumamryReportPagination('sortById')">
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
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsPaymentsSumamryReportPagination('sortByDate')">
                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                            <span class="text-14 main-text fw-medium me-2">Date Paid</span>
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

                                </tr>
                            </thead>
                            <tbody id="user-table-data">
                                <?php
                                if ($payments && $payments->num_rows > 0):
                                    while ($data = $payments->fetch_assoc()):

                                        // ------------------------------------------------
                                        // Resolve exchange rate using course currency as base
                                        // paid_currency_id = what the student paid in
                                        // course_currency_id = what the plan is in
                                        // ------------------------------------------------
                                        $rate = resolveExchangeRate($data, $data['course_currency_id']);

                                        // paid_amount is in paid currency → convert to course currency
                                        $convertedPaid = convertToCourseAmount($data['paid_amount'], $rate);
                                        $convertedDiscount = convertToCourseAmount($data['discount_amount'], $rate);
                                        $convertedPenalty = convertToCourseAmount($data['panelty_charge'], $rate);
                                        $convertedTax = convertToCourseAmount($data['tax_charge'], $rate);

                                        // CP-level discount (stored in course currency — no conversion)
                                        $cpDiscount = $data['discount'];
                                        if ($data['discount_type_discount_type_id'] == 3) {
                                            $cpDiscount = $data['amount'] / 100 * $data['discount'];
                                        }

                                        // receipt-level discount_amount stored in course currency — no conversion
                                        $totalDiscount = $cpDiscount + $convertedDiscount;

                                        // penalty from panelty_payments table (course currency)
                                        // + receipt-level panelty_charge (stored in course currency)
                                        $totalPenalty = $data['main_panelty_amount'] + $convertedPenalty;

                                        // Payable = amount - discounts + penalties  (all course currency)
                                        $payableAmount = $data['amount'] - $totalDiscount + $totalPenalty;

                                        $code = $data['course_currency_code'];
                                        $dp   = $data['course_decimal_places'];

                                        $isForeign = ($rate !== null);

                                        $status_classes_method = [
                                            "1" => "table-btn-orange",
                                            "2" => "table-btn-pink",
                                            "3" => "table-btn-purple",
                                            "4" => "table-btn-yellow-green",
                                            "5" => "table-btn-purle-pink",
                                        ];
                                        $status_classes_payment = [
                                            "0" => "table-btn-purple",
                                            "1" => "table-btn-yellow",
                                            "2" => "table-btn-orange",
                                            "3" => "table-btn-success",
                                            "4" => "table-btn-danger",
                                            "8" => "table-btn-green-blue",
                                        ];
                                        $mclass = $status_classes_method[$data['payment_method_id']] ?? 'table-btn-yellow';
                                        $sclass = $status_classes_payment[$data['payment_status_id']] ?? 'table-btn-yellow';
                                ?>
                                        <tr>
                                            <td class="px-4 py-3 pe-0 align-middle">
                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                    <?php
                                                    if (!empty($data['profile_img']) && file_exists('../assets/imgs/students/' . $data['profile_img'])): ?>
                                                        <img src="./assets/imgs/students/<?= $data['profile_img'] ?>" class="hwr-40" alt="User image">
                                                    <?php else:
                                                        $fi = strtoupper(substr($data['first_name'], 0, 1));
                                                        $li = strtoupper(substr($data['last_name'], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $fi . $li ?></div>
                                                    <?php endif; ?>
                                                    <span class="text-14 main-text mx-2"><?= $data['title'] . '.' . $data['first_name'] . ' ' . $data['last_name'] ?></span>
                                                </div>
                                            </td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['aId'] ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['payment_type'] ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['due_date'] ?: 'N/A' ?></span></td>

                                            <!-- Amount (course currency) -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= $code . ' ' . number_format($data['amount'], $dp) ?></span>
                                            </td>

                                            <!-- Total Discount (course currency) -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= $code . ' ' . number_format($totalDiscount, $dp) ?></span>
                                            </td>

                                            <!-- Total Penalty (course currency) -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= $code . ' ' . number_format($totalPenalty, $dp) ?></span>
                                            </td>

                                            <!-- Payable Amount (course currency) -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= $code . ' ' . number_format($payableAmount, $dp) ?></span>
                                            </td>

                                            <!-- Paid Amount: converted to course currency, original shown as tooltip if foreign -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text">
                                                    <?= $data['paid_currency_code'] . ' ' . number_format($data['paid_amount'], 2) ?>
                                                </span>
                                            </td>

                                            <!-- Paid Date -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= $data['date_paid'] ? date('Y-m-d', strtotime($data['date_paid'])) : 'N/A' ?></span>
                                            </td>

                                            <!-- Payment Method -->
                                            <td class="py-2 align-middle">
                                                <button class="table-btn <?= $mclass ?> w-100"><?= $data['payment_method'] ?></button>
                                            </td>

                                            <!-- Status -->
                                            <td class="py-2 align-middle">
                                                <button class="table-btn <?= $sclass ?> w-100"><?= $data['payment_status'] ?></button>
                                            </td>

                                        </tr>
                                    <?php
                                    endwhile;
                                else:
                                    ?>
                                    <tr class="justify-content-center align-middle">
                                        <td colspan="14" class="align-middle justify-content-center">
                                            <div class="d-flex flex-column align-items-center">
                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                <div class="row p-4 align-items-center">
                                    <!-- Previous Button (col-1) -->
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPaymentsSumamryReportPagination('previousPage')">
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
                                                $action = $isActive ? '' : "onclick=\"handleStudentsPaymentsSumamryReportPagination('goToPage', '{$i}')\"";
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPaymentsSumamryReportPagination('nextPage',<?= $pageno ?>,'<?= $number_of_pages ?>')">
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

function resolveExchangeRate($row, $courseCurrencyId)
{
    if (empty($row['paid_currency_id']) || $row['paid_currency_id'] == $courseCurrencyId) {
        return null;
    }
    // 1) Inline custom rate
    if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) {
        return (float)$row['currency_exchange_rate'];
    }
    // 2) Lookup from currency_exchange_rates table
    if (!empty($row['currency_exchange_rate_id'])) {
        $rateRow = Database::search(
            "SELECT `rate` AS `currency_exchange_rate` FROM `currency_exchange_rates`
                             WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1"
        );
        if ($rateRow && $rateRow->num_rows > 0) {
            $r = $rateRow->fetch_assoc();
            if (!empty($r['currency_exchange_rate']) && $r['currency_exchange_rate'] > 0) {
                return (float)$r['currency_exchange_rate'];
            }
        }
    }
    return null;
}

function convertToCourseAmount($amount, $rate)
{
    if ($rate === null || $rate <= 0) return (float)$amount;
    return (float)$amount / $rate;
}

?>