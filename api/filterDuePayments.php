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

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : '';
                    $programme = isset($decoded['programme']) ? $decoded['programme'] : '';
                    $intake = isset($decoded['intake']) ? $decoded['intake'] : '';
                    $category = isset($decoded['category']) ? $decoded['category'] : '';
                    $plan = isset($decoded['plan']) ? $decoded['plan'] : 0;
                    $type = isset($decoded['type']) ? $decoded['type'] : 0;
                    $sortByAmount = isset($decoded['sortByAmount']) ? $decoded['sortByAmount'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortById = isset($decoded['sortById']) ? $decoded['sortById'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $query = "     SELECT
                                                                                                `login_details`.`first_name`,
                                                                                                 `login_details`.`last_name`,
                                                                                                `students`.`profile_img`,
                                                                                                `title`.`title`,
                                                                                                `cp`.`course_payments_id`,
                                                                                                `ps`.`payment_status`, `ps`.`payment_status_id`,
                                                                                                `cp`.`amount`, `cp`.`discount`, `cp`.`due_date`,
                                                                                                `pt`.`payment_type`,
                                                                                                `dt`.`discount_type`, `dt`.`discount_type_id`,
                                                                                                `c`.`code`, `c`.`decimal_places`,
                                                                                                `c`.`currency_id` AS `course_currency_id`,
                                                                                                IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                                                IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                                                IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                                                            FROM `course_payments` AS `cp`
                                                                                            INNER JOIN `payment_type` AS `pt`     ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
                                                                                            INNER JOIN `discount_type` AS `dt`    ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                                                                                            INNER JOIN `payment_status` AS `ps`   ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                                            INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                                                                ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                                            INNER JOIN `payment_category` AS `pc` ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                                                            INNER JOIN `currencies` AS `c`        ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                                                            INNER JOIN `students_has_intakes` AS `shi`
                                                                                                ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                                            INNER JOIN `students` 
                                                                                                ON `shi`.`students_student_id` = `students`.`student_id`
                                                                                            INNER JOIN `login_details` 
                                                                                                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                                            INNER JOIN `title` 
                                                                                                ON `students`.`title_title_id` = `title`.`title_id`
                                                                                            INNER JOIN `intakes` 
                                                                                                ON `shi`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                                            INNER JOIN `programmes` 
                                                                                                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                                                            LEFT JOIN (
                                                                                                SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
                                                                                                FROM panelty_payments GROUP BY course_payments_course_payments_id
                                                                                            ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                                                            LEFT JOIN (
                                                                                                SELECT course_payments_course_payments_id,
                                                                                                    SUM(discount_amount) AS total_discount,
                                                                                                    SUM(panelty_amount)  AS total_sub_panelty_amount
                                                                                                FROM course_payment_has_payments
                                                                                                WHERE payment_status_payment_status_id IN (2,3)
                                                                                                GROUP BY course_payments_course_payments_id
                                                                                            ) cpp ON cp.course_payments_id = cpp.course_payments_course_payments_id
                                                                                            WHERE cp.payment_status_payment_status_id = 1
                                                                                            AND `cp`.`due_date` <= CURDATE()
                                                                                            
";


                    if ($search_text != "") {
                        $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `shi`.`student_id` LIKE '%" . $search_text . "%')";
                    }

                    if ($faculty  != 0) {
                        $query .= " AND `programmes`.`faculties_faculty_id` = '" . $faculty . "'";
                    }

                    if ($programme != 0) {
                        $query .= " AND `programmes`.`programme_id` = '" . $programme . "'";
                    }

                    if ($intake != 0) {
                        $query .= " AND `intakes`.`intake_id` = '" . $intake . "'";
                    }

                    if ($category != 0) {
                        $query .= " AND `pshi`.`payment_category_payment_category_id`='" . $category . "'";
                    }

                    if ($plan != 0) {
                        $query .= " AND `pshi`.`payment_plan_has_students_has_intakes_id`='" . $plan . "'";
                    }

                    if ($type != 0) {
                        $query .= " AND `cp`.`payment_type_payment_type_id`='" . $type . "'";
                    }



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
                        $query .= " ORDER BY `cp`.`due_date` ASC";
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
                    <table class="table bl-solid m-0 p-0 g-0">
                        <thead>
                            <tr class="br-t-5">
                                <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Student</span>
                                    </div>
                                </th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Payment Type</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Due Date</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Discount</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Tax</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Payable</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Paid Amount</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Course Balance</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Penalty (LKR)</th>
                                <th class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php

                            if ($users && $users->num_rows > 0):
                                while ($row = $users->fetch_assoc()):

                                    // CP-level discount
                                    $discountAmount = ($row['discount_type_id'] == 3)
                                        ? $row['amount'] / 100 * $row['discount']
                                        : $row['discount'];

                                    $totalDiscount  = $discountAmount;
                                    // Keep raw LKR penalty amounts separate for display
                                    $totalPenaltyLKR = $row['panelty_amount']; // Store raw LKR amount
                                    $rowTaxAmounts  = 0; // accumulate tax per receipt below

                                    // Fetch receipts for this payment
                                    $convertedPaidTotal = 0;
                                    $receiptRows = [];
                                    $totalPaidAmount = 0;
                                    $totalReceiptPenaltiesLKR = 0; // Track receipt penalties in LKR
                                    $paidBeforePenalty = 0; // Amount paid BEFORE penalty was added
                                    $paidAfterPenalty = 0;  // Amount paid AFTER penalty was added

                                    // Get earliest penalty date for this course payment
                                    $penaltyDateQuery = Database::search("
                                                                                                        SELECT MIN(pp.date_added) as earliest_penalty_date
                                                                                                        FROM panelty_payments pp
                                                                                                        WHERE pp.course_payments_course_payments_id = '" . (int)$row['course_payments_id'] . "'
                                                                                                    ");
                                    $penaltyDate = null;
                                    if ($penaltyDateQuery && $penaltyDateQuery->num_rows > 0) {
                                        $penaltyData = $penaltyDateQuery->fetch_assoc();
                                        $penaltyDate = $penaltyData['earliest_penalty_date'];
                                    }

                                    $receipts = Database::search("
                                                                                                    SELECT
                                                                                                        cpp.*,
                                                                                                        pm.payment_method,
                                                                                                        pm.payment_method_id,
                                                                                                        ps2.payment_status,
                                                                                                        ps2.payment_status_id,
                                                                                                        cur.code AS paid_currency_code,
                                                                                                        cur.decimal_places AS paid_decimal_places,
                                                                                                        cpp.currency_exchange_rate,
                                                                                                        cpp.tax_rate,
                                                                                                        cpp.tax_amount,
                                                                                                        cpr.receipt_number
                                                                                                    FROM course_payment_has_payments AS cpp
                                                                                                    INNER JOIN payment_method AS pm  ON cpp.payment_method_payment_method_id = pm.payment_method_id
                                                                                                    INNER JOIN payment_status AS ps2 ON cpp.payment_status_payment_status_id = ps2.payment_status_id
                                                                                                    LEFT JOIN currencies AS cur      ON cpp.currencies_currency_id = cur.currency_id
                                                                                                    LEFT JOIN course_payment_receipts AS cpr
                                                                                                        ON cpp.course_payment_receipts_course_payment_receipt_id = cpr.course_payment_receipt_id
                                                                                                    WHERE cpp.course_payments_course_payments_id = '" . (int)$row['course_payments_id'] . "'
                                                                                                    
                                                                                                    GROUP BY cpp.course_payment_has_payment_id
                                                                                                    ORDER BY cpp.date_paid ASC
                                                                                                ");
                                    if ($receipts && $receipts->num_rows > 0) {
                                        while ($rec = $receipts->fetch_assoc()) {
                                            $rate = resolveExchangeRate(null, $rec, $row['course_currency_id']);
                                            $rec['converted_paid']        = convertToCourseAmount($rec['paid_amount'], $rate);
                                            $rec['is_foreign_currency']   = ($rate !== null);
                                            $rec['currency_exchange_rate'] = $rate;
                                            $receiptRows[] = $rec;

                                            if (in_array($rec['payment_status_id'], [2, 3])) {
                                                $convertedPaidTotal += $rec['converted_paid'];
                                                $totalPaidAmount += $rec['converted_paid'];
                                                $totalDiscount  += convertToCourseAmount($rec['discount_amount'], $rate);
                                                // Track receipt penalties in LKR
                                                $totalReceiptPenaltiesLKR += $rec['panelty_amount'];
                                                $rowTaxAmounts  += convertToCourseAmount($rec['tax_amount'], $rate);

                                                // Allocate payment to course balance based on penalty date
                                                if ($penaltyDate && $rec['date_paid'] < $penaltyDate) {
                                                    // Payment made BEFORE penalty: all goes to course
                                                    $paidBeforePenalty += $rec['converted_paid'];
                                                } else if ($penaltyDate) {
                                                    // Payment made AFTER penalty: deduct penalty from this payment
                                                    $paidAfterPenalty += $rec['converted_paid'];
                                                } else {
                                                    // No penalty at all, all payments count for course
                                                    $paidBeforePenalty += $rec['converted_paid'];
                                                }
                                            }
                                        }
                                    }

                                    // Calculate total penalty in LKR (course_payments + receipt penalties)
                                    $totalPenaltyLKRDisplay = $totalPenaltyLKR + $totalReceiptPenaltiesLKR;

                                    // For balance calculation: payable should EXCLUDE penalties
                                    // payable = amount - discount + tax (NO penalties)
                                    $payableAmount = $row['amount'] - $totalDiscount + $rowTaxAmounts;

                                    // Calculate course balance considering penalty dates
                                    $totalPenaltyConverted = convertPaneltyPaymentsAmount($totalPenaltyLKR, $row['course_currency_id']);

                                    // Amount paid towards course fee (excluding penalty portion)
                                    $coursePaymentReceived = $paidBeforePenalty; // Payments before penalty

                                    // For payments after penalty: deduct penalty amount, rest goes to course
                                    $penaltyInCourse = convertPaneltyPaymentsAmount($totalPenaltyLKR, $row['course_currency_id']);
                                    $coursePaymentReceived += max(0, $paidAfterPenalty - $penaltyInCourse);

                                    // Course balance = payable - course payment received (NO penalties included)
                                    $balance = $payableAmount - $coursePaymentReceived;
                                    $paidAmount = $convertedPaidTotal;

                                    $status_classes = ["0" => "table-btn-purple", "1" => "table-btn-yellow", "2" => "table-btn-orange", "3" => "table-btn-success", "4" => "table-btn-danger", "5" => "table-btn-success", "6" => "table-btn-success", "7" => "table-btn-danger", "9" => "table-btn-success"];
                                    $sbutton_class  = $status_classes[$row['payment_status_id']] ?? 'table-btn-yellow';
                            ?>
                                    <!-- CP Main Row -->
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                    <?php
                                                    if (!empty($row["profile_img"]) && file_exists('./assets/imgs/students/' . $row["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?php echo $row["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($row["first_name"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($row["last_name"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?php echo $row["title"] . "." . $row["first_name"] . " " . $row["last_name"]; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['payment_type'] ?></span></td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['due_date'] ?></span></td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($row['amount'], $row['decimal_places']) ?></span></td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($totalDiscount, $row['decimal_places']) ?></span></td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($rowTaxAmounts, $row['decimal_places']) ?></span></td>
                                        <!-- Payable now shows COURSE FEE ONLY (no penalties) -->
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($payableAmount, $row['decimal_places']) ?></span></td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($coursePaymentReceived, $row['decimal_places']) ?></span></td>
                                        <!-- Course Balance now shows COURSE BALANCE ONLY (no penalties) -->
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?= $row['code'] . " " . number_format($balance < 0 ? 0 : $balance, $row['decimal_places']) ?></span></td>
                                        <!-- Penalty column displays in LKR -->
                                        <td class="py-3 align-middle"><span class="text-14 main-text">LKR <?= number_format($totalPenaltyLKRDisplay, 2) ?></span></td>

                                        <td class="px-4 py-3 align-middle">
                                            <div class="dropdown dropup position-relative">
                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                <ul class="dropdown-menu border-0 shadow w-100 br-5 z-3 position-absolute">
                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="intake-student-payment?payment-id=<?= $row['course_payments_id'] ?>&department=payments">
                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon> View</a></li>
                                                    <?php
                                                    if (
                                                        SessionUser::ifUserIsPro($sessionUserRole)
                                                        || SessionUser::ifUserIsAccounts($sessionUserRole)
                                                        || UserPermissions::validPermisisonType('payments', 0)
                                                        || UserPermissions::validPermisisonType('intake_payments', 24, $studentHasIntakeData['intake_id'] ?? null)
                                                    ):
                                                        if ($balance > 0): ?>
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="add-receipt-to-student-payment?payment-id=<?= $row['course_payments_id'] ?>">
                                                                    <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon> Add Reciept</a></li>
                                                        <?php endif; ?>

                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php

                                endwhile; // detail rows
                            else:
                                ?>
                                <tr class="justify-content-center align-middle">
                                    <td colspan="13" class="align-middle justify-content-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; // detail rows
                            ?>
                        </tbody>
                    </table>
                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <!-- Previous Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleDuePaymentsPagination('previousPage');">
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
                                            $action = $isActive ? '' : "onclick=\"handleDuePaymentsPagination('goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                renderPageButton($i, $pageno == $i);
                                            } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                $shudderPages1 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                            } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                $shudderPages2 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                            }
                                        }
                                        ?>

                                    </ul>
                                </div>
                                <!-- Next Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleDuePaymentsPagination('nextPage',<?= $number_of_pages ?>);">
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


// ============================================================
// HELPER: resolve exchange rate for course_payment_has_payments
// Returns the rate to divide by to convert paid_currency → course_currency
// Returns null if no conversion needed (same currency or unknown)
// ============================================================
function resolveExchangeRate($db, $row, $courseCurrencyId)
{
    if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrencyId) {
        return null;
    }
    if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) {
        return (float)$row['currency_exchange_rate'];
    }
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

// ============================================================
// HELPER: convert a paid_amount to course currency
// paid_currency / exchange_rate = course_currency amount
// ============================================================
function convertToCourseAmount($paidAmount, $exchangeRate)
{
    if ($exchangeRate === null || $exchangeRate <= 0) return (float)$paidAmount;
    return (float)$paidAmount / $exchangeRate;
}

// ============================================================
// HELPER: convert panelty_payments.amount (always LKR) to course currency
// We need 1 LKR = X course_currency
// i.e. lkr_amount / lkr_per_course_unit = course_currency_amount
// We look up the LKR→course-currency rate from currency_exchange_rates
// The exchange_rate stored is: 1 course_currency = N LKR
// So: course_amount = lkr_amount / rate
// If course currency IS LKR (currency_id matches LKR), no conversion needed.
// ============================================================
function convertPaneltyPaymentsAmount($lkrAmount, $courseCurrencyId)
{
    // Look up LKR currency_id (code = 'LKR')
    static $lkrCurrencyId = null;
    if ($lkrCurrencyId === null) {
        $res = Database::search("SELECT `currency_id` FROM `currencies` WHERE `code` = 'LKR' LIMIT 1");
        $lkrCurrencyId = ($res && $res->num_rows > 0) ? (int)$res->fetch_assoc()['currency_id'] : 0;
    }

    // If course currency is already LKR, no conversion
    if ($lkrCurrencyId === 0 || $courseCurrencyId == $lkrCurrencyId) {
        return (float)$lkrAmount;
    }

    // Find the exchange rate: how many LKR per 1 unit of course currency
    // Stored as: currencies_currency_id = course_currency, rate = LKR equivalent
    $rateRow = Database::search(
        "SELECT `rate` FROM `currency_exchange_rates`
                                                         WHERE `base_currency_id` = '" . (int)$courseCurrencyId . "'
                                                         ORDER BY `currency_exchange_rate_id` DESC LIMIT 1"
    );
    if ($rateRow && $rateRow->num_rows > 0) {
        $rate = (float)$rateRow->fetch_assoc()['rate'];
        if ($rate > 0) {
            return (float)$lkrAmount / $rate;
        }
    }

    // Fallback: cannot convert, return as-is
    return (float)$lkrAmount;
}

function calculateTaxInclusiveAmount($base, $taxRate)
{
    $base = (float)$base;
    $taxRate = (float)$taxRate;
    if ($taxRate <= 0 || $base <= 0) {
        return $base;
    }
    $basePortion = 1 - ($taxRate / 100);
    if ($basePortion <= 0) {
        return $base;
    }
    return round($base / $basePortion, 2);
}

?>

<?php

// ============================================================
// HELPER: get currency-converted summary amounts for a payment plan
// FIX: panelty_payments.amount is always LKR → convert to course currency
// FIX: tax amounts included in awaiting balance calculation
// ============================================================
function getSummaryAmounts($db, $pphsiId, $courseCurrencyId)
{
    $discounts  = 0;
    $penalties  = 0; // receipt-level penalties (in paid currency)
    $completed  = 0;
    $taxAmounts = 0;

    // Completed receipts (status 3)
    $res = Database::search("
                                                                SELECT paid_amount, discount_amount, panelty_amount,
                                                                       currencies_currency_id, currency_exchange_rate,
                                                                       currency_exchange_rate_id, tax_rate, tax_amount
                                                                FROM course_payment_has_payments cpp
                                                                INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                                                WHERE cp.payment_plan_has_students_has_intakes_id = '" . (int)$pphsiId . "'
                                                                AND cpp.payment_status_payment_status_id = 3
                                                            ");
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $rate = resolveExchangeRate(null, $r, $courseCurrencyId);
            $completed  += convertToCourseAmount($r['paid_amount'], $rate);
            $discounts  += convertToCourseAmount($r['discount_amount'], $rate);
            // receipt penalty is in the payment's paid currency
            $penalties  += convertToCourseAmount($r['panelty_amount'], $rate);
            $taxAmounts += convertToCourseAmount($r['tax_amount'], $rate);
        }
    }

    // Pending receipts (status 2)
    $pending = 0;
    $res2 = Database::search("
                                                                SELECT paid_amount, discount_amount, panelty_amount,
                                                                       currencies_currency_id, currency_exchange_rate, currency_exchange_rate_id
                                                                FROM course_payment_has_payments cpp
                                                                INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                                                WHERE cp.payment_plan_has_students_has_intakes_id = '" . (int)$pphsiId . "'
                                                                AND cpp.payment_status_payment_status_id = 2
                                                            ");
    if ($res2 && $res2->num_rows > 0) {
        while ($r = $res2->fetch_assoc()) {
            $rate = resolveExchangeRate(null, $r, $courseCurrencyId);
            $pending   += convertToCourseAmount($r['paid_amount'], $rate);
            $discounts += convertToCourseAmount($r['discount_amount'], $rate);
            $penalties += convertToCourseAmount($r['panelty_amount'], $rate);
        }
    }

    // Rejected receipts (status 4)
    $rejected = 0;
    $res3 = Database::search("
                                                                SELECT paid_amount, currencies_currency_id,
                                                                       currency_exchange_rate, currency_exchange_rate_id
                                                                FROM course_payment_has_payments cpp
                                                                INNER JOIN course_payments cp ON cpp.course_payments_course_payments_id = cp.course_payments_id
                                                                WHERE cp.payment_plan_has_students_has_intakes_id = '" . (int)$pphsiId . "'
                                                                AND cpp.payment_status_payment_status_id = 4
                                                            ");
    if ($res3 && $res3->num_rows > 0) {
        while ($r = $res3->fetch_assoc()) {
            $rate = resolveExchangeRate(null, $r, $courseCurrencyId);
            $rejected += convertToCourseAmount($r['paid_amount'], $rate);
        }
    }

    // panelty_payments penalty for this pphsi — always LKR, convert to course currency
    $paneltyPaymentsConverted = 0;
    $res4 = Database::search("
                                                                SELECT SUM(pp.amount) AS total_lkr_penalty
                                                                FROM panelty_payments pp
                                                                INNER JOIN course_payments cp ON pp.course_payments_course_payments_id = cp.course_payments_id
                                                                WHERE cp.payment_plan_has_students_has_intakes_id = '" . (int)$pphsiId . "'
                                                            ");
    if ($res4 && $res4->num_rows > 0) {
        $r4 = $res4->fetch_assoc();
        if (!empty($r4['total_lkr_penalty'])) {
            $paneltyPaymentsConverted = convertPaneltyPaymentsAmount($r4['total_lkr_penalty'], $courseCurrencyId);
        }
    }

    return [
        'completed'                => $completed,
        'pending'                  => $pending,
        'rejected'                 => $rejected,
        'discounts'                => $discounts,
        'penalties'                => $penalties,               // receipt-level (paid currency → course)
        'paneltyPaymentsConverted' => $paneltyPaymentsConverted, // panelty_payments (LKR → course)
        'taxAmounts'               => $taxAmounts,
    ];
}
