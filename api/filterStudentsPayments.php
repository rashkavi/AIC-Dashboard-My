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

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 15) || UserPermissions::hasPermission('payments', 1))) {
                        $status->error = 'You do not have permission to perform this action!';
                    } else {
                        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                        $status_selector = isset($decoded['status']) ? $decoded['status'] : 0;
                        $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                        $year = isset($decoded['year']) ? $decoded['year'] : 0;
                        $month = isset($decoded['month']) ? $decoded['month'] : 0;
                        $branch = isset($decoded['branch']) ? $decoded['branch'] : '';
                        $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : '';
                        $programme = isset($decoded['programme']) ? $decoded['programme'] : '';
                        $intake = isset($decoded['intake']) ? $decoded['intake'] : '';
                        $category = isset($decoded['category']) ? $decoded['category'] : '';
                        $plan = isset($decoded['plan']) ? $decoded['plan'] : 0;
                        $type = isset($decoded['type']) ? $decoded['type'] : 0;
                        $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                        $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                        $sortById = isset($decoded['sortById']) ? $decoded['sortById'] : 0;
                        $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                        $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                        $query = "
                                                                            SELECT
                                                                                cp.course_payments_id,
                                                                                cpp.course_payment_has_payment_id,

                                                                                cp.amount,
                                                                                cp.discount,
                                                                                cp.discount_type_discount_type_id,
                                                                                cp.due_date,

                                                                                cpp.paid_amount,
                                                                                cpp.discount_amount,
                                                                                cpp.panelty_amount          AS receipt_penalty_paid_cur,
                                                                                cpp.tax_rate,
                                                                                cpp.tax_amount              AS receipt_tax_paid_cur,
                                                                                cpp.currencies_currency_id  AS paid_currency_id,
                                                                                cpp.currency_exchange_rate,
                                                                                cpp.currency_exchange_rate_id,
                                                                                cpp.date_paid,

                                                                                ps.payment_status,
                                                                                ps.payment_status_id,
                                                                                pgp.payment_type,
                                                                                pm.payment_method,
                                                                                pm.payment_method_id,

                                                                                shi.student_id,
                                                                                ld.first_name,
                                                                                ld.last_name,
                                                                                t.title,
                                                                                s.profile_img,
                                                                                s.student_id AS aId,

                                                                                /* penalty_lkr: cumulative from panelty_payments — always LKR */
                                                                                IFNULL(pp.penalty_lkr, 0) AS penalty_lkr,

                                                                                /* course currency */
                                                                                course_cur.currency_id   AS course_currency_id,
                                                                                course_cur.code          AS course_currency_code,
                                                                                course_cur.decimal_places AS course_decimal_places,

                                                                                /* paid currency */
                                                                                COALESCE(paid_cur.code, course_cur.code)
                                                                                                         AS paid_currency_code,
                                                                                COALESCE(paid_cur.decimal_places, course_cur.decimal_places)
                                                                                                         AS paid_decimal_places,

                                                                                /* LKR currency id for penalty conversion */
                                                                                1 AS lkr_currency_id,
                                                                                cpr.receipt_number

                                                                            FROM course_payment_has_payments cpp

                                                                            INNER JOIN course_payments cp
                                                                                ON cp.course_payments_id = cpp.course_payments_course_payments_id

                                                                            INNER JOIN payment_status ps
                                                                                ON cpp.payment_status_payment_status_id = ps.payment_status_id

                                                                            INNER JOIN payment_method pm
                                                                                ON cpp.payment_method_payment_method_id = pm.payment_method_id

                                                                            INNER JOIN payment_type pgp
                                                                                ON cp.payment_type_payment_type_id = pgp.payment_type_id

                                                                            INNER JOIN payment_plan_has_students_has_intakes phsi
                                                                                ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

                                                                            INNER JOIN payment_category pc
                                                                                ON phsi.payment_category_payment_category_id = pc.payment_category_id

                                                                            /* Course currency: from payment_category */
                                                                            INNER JOIN currencies course_cur
                                                                                ON pc.currencies_currency_id = course_cur.currency_id

                                                                            /* Paid currency: nullable (student may pay in same currency) */
                                                                            LEFT JOIN currencies paid_cur
                                                                                ON cpp.currencies_currency_id = paid_cur.currency_id

                                                                            INNER JOIN students_has_intakes shi
                                                                                ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                                                                            INNER JOIN students s
                                                                                ON shi.students_student_id = s.student_id

                                                                            INNER JOIN intakes i
                                                                                ON shi.intakes_intake_id = i.intake_id

                                                                            INNER JOIN programmes p
                                                                                ON i.programmes_programme_id = p.programme_id
                                                                                
                                                                            INNER JOIN faculties f
                                                                                ON p.faculties_faculty_id = f.faculty_id

                                                                            INNER JOIN login_details ld
                                                                                ON s.login_details_login_details_id = ld.login_details_id

                                                                            INNER JOIN title t
                                                                                ON s.title_title_id = t.title_id

                                                                            /* Cumulative penalty log — amount always in LKR */
                                                                            LEFT JOIN (
                                                                                SELECT
                                                                                    course_payments_course_payments_id,
                                                                                    SUM(amount) AS penalty_lkr
                                                                                FROM panelty_payments
                                                                                GROUP BY course_payments_course_payments_id
                                                                            ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id

                                                                             LEFT JOIN `course_payment_receipts` cpr
                                                                                ON cpr.course_payment_receipt_id = cpp.course_payment_receipts_course_payment_receipt_id
WHERE 1=1
                                                                                ";


                        if ($search_text != "") {
                            $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `shi`.`student_id` LIKE '%" . $search_text . "%')";
                        }

                        if ($status_selector != 0) {

                            $query .= " AND `ps`.`payment_status_id` = '" . $status_selector . "'";
                        }

                        if ($sort != 0) {

                            $query .= " AND `payment_method_id` = '" . $sort . "'";
                        }

                        if ($year != 0) {

                            $query .= " AND YEAR(`cpp`.`date_paid`) = '" . $year . "'";
                        }

                        if ($month != 0) {

                            $query .= " AND MONTH(`cpp`.`date_paid`) = '" . $month . "'";
                        }

                        if ($branch != 0) {
                            $query .= " AND `f`.`branches_branch_id` = '" . $branch . "'";
                        }

                        if ($faculty  != 0) {
                            $query .= " AND `p`.`faculties_faculty_id` = '" . $faculty . "'";
                        }

                        if ($programme != 0) {
                            $query .= " AND `p`.`programme_id` = '" . $programme . "'";
                        }

                        if ($intake != 0) {
                            $query .= " AND `i`.`intake_id` = '" . $intake . "'";
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
                        function toCourse(float $amount, ?float $rate): float
                        {
                            if ($rate === null || $rate <= 0) return $amount;
                            return $amount / $rate;
                        }
?>
                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                        <div class="d-flex flex-row align-items-center">
                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPaymentsPagination('sortByName')">
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

                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Fees Type</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Tax</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payable</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsPaymentsPagination('sortByDate')">
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
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Receipt Number</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Method</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                </tr>
                            </thead>
                            <tbody id="user-table-data">
                                <?php

                                if ($payments && $payments->num_rows > 0):
                                    while ($data = $payments->fetch_assoc()):

                                        /* ── Exchange rate ───────────────────────────────────
                                                                                   rate: 1 course_cur = X paid_cur
                                                                                   null → same currency, no conversion needed           */
                                        $rate = resolveExchangeRate($data, (int)$data['course_currency_id']);

                                        /* ── Plan-level (CP) discount → always course currency ─
                                                                                   type 3 = percentage of cp.amount
                                                                                   type 2 = fixed amount (course currency)
                                                                                   other  = 0                                           */
                                        $cpDiscount = 0.0;
                                        if ($data['discount_type_discount_type_id'] == 3) {
                                            $cpDiscount = (float)$data['amount'] / 100 * (float)$data['discount'];
                                        } elseif ($data['discount_type_discount_type_id'] == 2) {
                                            $cpDiscount = (float)$data['discount'];
                                        }

                                        /* ── Receipt-level discount → stored in course currency ─
                                                                                   cpp.discount_amount is always course currency per schema */
                                        $receiptDiscount = (float)$data['discount_amount'];
                                        $totalDiscount   = $cpDiscount + $receiptDiscount;

                                        /* ── Receipt-level penalty → stored in PAID currency ────
                                                                                   Convert to course currency for display                  */
                                        $receiptPenaltyCc = toCourse((float)($data['receipt_penalty_paid_cur'] ?? 0), $rate);

                                        /* ── Cumulative penalty from panelty_payments → always LKR
                                                                                   If course currency is LKR: use as-is
                                                                                   If course currency is foreign: convert LKR → course
                                                                                     rate means 1 course = X paid (paid often = LKR here)
                                                                                     So: lkr_amount / rate = course_amount                 */
                                        $penaltyLkr = (float)($data['penalty_lkr'] ?? 0);
                                        $isCourseLkr = ((int)$data['course_currency_id'] === 1);
                                        if ($isCourseLkr || $rate === null) {
                                            /* course IS lkr, or same currency: penalty already in course cur */
                                            $mainPenaltyCc = $penaltyLkr;
                                        } else {
                                            /* course is foreign (e.g. GBP), penalty in LKR
                                                                                       rate = 1 GBP = X LKR → lkr / rate = gbp           */
                                            $mainPenaltyCc = ($rate > 0) ? $penaltyLkr / $rate : $penaltyLkr;
                                        }
                                        $totalPenalty = $receiptPenaltyCc + $mainPenaltyCc;

                                        /* ── Tax → stored in PAID currency, convert to course ── */
                                        $taxCc = toCourse((float)($data['receipt_tax_paid_cur'] ?? 0), $rate);

                                        /* ── Payable = amount - discounts + penalties + tax ──── */
                                        $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $taxCc;

                                        /* ── Paid amount → convert from paid currency to course ─ */
                                        $paidInCourse = toCourse((float)$data['paid_amount'], $rate);

                                        /* ── Display helpers ──────────────────────────────────── */
                                        $cc   = htmlspecialchars($data['course_currency_code']);
                                        $dp   = (int)$data['course_decimal_places'];
                                        $pcc  = htmlspecialchars($data['paid_currency_code']);
                                        $pdp  = (int)$data['paid_decimal_places'];
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
                                            <!-- Student Name -->
                                            <td class="px-4 py-3 pe-0 align-middle">
                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                    <?php if (!empty($data['profile_img']) && file_exists('./assets/imgs/students/' . $data['profile_img'])): ?>
                                                        <img src="./assets/imgs/students/<?= $data['profile_img'] ?>" class="hwr-40" alt="Student">
                                                    <?php else:
                                                        $fi = strtoupper(substr($data['first_name'], 0, 1));
                                                        $li = strtoupper(substr($data['last_name'],  0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $fi . $li ?></div>
                                                    <?php endif; ?>
                                                    <span class="text-14 main-text mx-2"><?= htmlspecialchars($data['title'] . '.' . $data['first_name'] . ' ' . $data['last_name']) ?></span>
                                                </div>
                                            </td>
                                            <!-- Fees Type -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= htmlspecialchars($data['payment_type']) ?></span>
                                            </td>

                                            <!-- Amount (course currency — plan instalment) -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= $cc . ' ' . number_format((float)$data['amount'], $dp) ?></span>
                                            </td>


                                            <!-- Tax (course currency) — NEW COLUMN -->
                                            <td class="py-2 align-middle">
                                                <?php if ($taxCc > 0): ?>
                                                    <span class="text-14 main-text"><?= $cc . ' ' . number_format($taxCc, $dp) ?></span>
                                                    <?php if ((float)$data['tax_rate'] > 0): ?>
                                                        <br><small class="sub-text" style="font-size:10px;"><?= number_format((float)$data['tax_rate'], 2) ?>%</small>
                                                    <?php endif; ?>
                                                    <?php if ($isForeign): ?>
                                                        <br><small class="sub-text" style="font-size:10px;">
                                                            <?= $pcc . ' ' . number_format((float)$data['receipt_tax_paid_cur'], $pdp) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-14 sub-text">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Payable Amount (course currency) -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text fw-medium"><?= $cc . ' ' . number_format($payableAmount, $dp) ?></span>
                                            </td>

                                            <!-- Paid Amount:
                                                                                         Primary = paid currency (what student gave)
                                                                                         Sub     = course currency equivalent (if foreign) -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?= $pcc . ' ' . number_format((float)$data['paid_amount'], $pdp) ?></span>
                                                <?php if ($isForeign): ?>
                                                    <br><small class="sub-text" style="font-size:10px;">
                                                        ≈ <?= $cc . ' ' . number_format($paidInCourse, $dp) ?>
                                                        <?php if ($rate): ?>
                                                            <span style="opacity:.65;">(@ <?= number_format($rate, 2) ?>)</span>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Paid Date -->
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text">
                                                    <?= $data['date_paid'] ? date('Y-m-d', strtotime($data['date_paid'])) : 'N/A' ?>
                                                </span>
                                            </td>

                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text">
                                                    <?= $data['receipt_number'] ? $data['receipt_number'] : 'N/A' ?>
                                                </span>
                                            </td>

                                            <!-- Payment Method -->
                                            <td class="py-2 align-middle">
                                                <button class="table-btn <?= $mclass ?> w-100"><?= htmlspecialchars($data['payment_method']) ?></button>
                                            </td>

                                            <!-- Status -->
                                            <td class="py-2 align-middle">
                                                <button class="table-btn <?= $sclass ?> w-100"><?= htmlspecialchars($data['payment_status']) ?></button>
                                            </td>

                                            <!-- Action -->
                                            <td class="py-3 align-middle">
                                                <div class="dropdown">
                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5">
                                                        <li>
                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center"
                                                                href="student-payment?payment-id=<?= (int)$data['course_payment_has_payment_id'] ?>">
                                                                <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                View
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                    endwhile;
                                else:
                                    ?>
                                    <tr class="justify-content-center align-middle">
                                        <td colspan="15" class="align-middle justify-content-center">
                                            <div class="d-flex flex-column align-items-center">
                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                            <nav class="col-12 px-2 pe-2 m-0 container-fluid">
                                <div class="row p-4 align-items-center">
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPaymentsPagination('previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                    </button>
                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;
                                            function renderPageButton($i, $isActive = false)
                                            {
                                                $ac  = $isActive ? 'active' : '';
                                                $ac2 = $isActive ? '' : "onclick=\"handleStudentsPaymentsPagination('goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$ac}' id='page-{$i}'><button class='hw-40' {$ac2}>{$i}</button></li>";
                                            }
                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                    renderPageButton($i, $pageno == $i);
                                                } elseif (!$shudderPages1 && $i < $pageno - 1 && $i > 1) {
                                                    $shudderPages1 = true;
                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                } elseif (!$shudderPages2 && $i > $pageno + 1 && $i < $number_of_pages) {
                                                    $shudderPages2 = true;
                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                }
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
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