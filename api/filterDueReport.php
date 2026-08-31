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

                    $filters = [
                        'text'       => isset($decoded['text']) ? $decoded['text'] : '',
                        'branch'     => isset($decoded['branch']) ? $decoded['branch'] : 0,
                        'faculty'    => isset($decoded['faculty']) ? $decoded['faculty'] : 0,
                        'programme'  => isset($decoded['programme']) ? $decoded['programme'] : 0,
                        'intake'     => isset($decoded['intake']) ? $decoded['intake'] : 0,
                        'counsellor' => isset($decoded['counsellor']) ? $decoded['counsellor'] : 0,
                        'currency'   => isset($decoded['currency']) ? $decoded['currency'] : 0,
                        'status'     => isset($decoded['status']) ? $decoded['status'] : 0,
                        'dateFilter' => isset($decoded['dateFilter']) ? $decoded['dateFilter'] : 0,
                        'fromDate'   => isset($decoded['fromDate']) ? $decoded['fromDate'] : '',
                        'toDate'     => isset($decoded['toDate']) ? $decoded['toDate'] : '',
                        'sortByName' => isset($decoded['sortByName']) ? (int)$decoded['sortByName'] : 0,
                        'sortByDue'  => isset($decoded['sortByDue']) ? (int)$decoded['sortByDue'] : 0,
                    ];

                    $resultPerPage = isset($decoded['resultPerPage']) ? (int)$decoded['resultPerPage'] : 10;
                    $pageno        = isset($decoded['page']) ? (int)$decoded['page'] : 1;

                    /* ── Same due/penalty calculation as the outstanding-
                           balances export — duplicated here on purpose so
                           this report never disagrees with it ──────────── */
                    function resolveExchangeRate(array $row, int $courseCurrencyId): ?float
                    {
                        $paidCurrId = (int)($row['currencies_currency_id'] ?? 0);
                        if (!$paidCurrId || $paidCurrId === $courseCurrencyId) {
                            return null;
                        }
                        if (!empty($row['currency_exchange_rate']) && (float)$row['currency_exchange_rate'] > 0) {
                            return (float)$row['currency_exchange_rate'];
                        }
                        if (!empty($row['currency_exchange_rate_id'])) {
                            $rr = Database::search(
                                "SELECT `rate` FROM `currency_exchange_rates`
                                 WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "'
                                 LIMIT 1"
                            );
                            if ($rr && $rr->num_rows > 0) {
                                $r = $rr->fetch_assoc();
                                if ((float)$r['rate'] > 0) return (float)$r['rate'];
                            }
                        }
                        return null;
                    }

                    function toCourse(float $amount, ?float $rate): float
                    {
                        if ($rate === null || $rate <= 0) return $amount;
                        return $amount / $rate;
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

                    function getInstallmentSummary(int $cpId, int $courseCurrencyId): array
                    {
                        $discounts = 0;
                        $penalties = 0;
                        $tax = 0;
                        $settled = 0;

                        $res = Database::search("
                            SELECT paid_amount, discount_amount, panelty_amount, tax_amount,
                                   currencies_currency_id, currency_exchange_rate,
                                   currency_exchange_rate_id, payment_status_payment_status_id
                            FROM course_payment_has_payments
                            WHERE course_payments_course_payments_id = '" . $cpId . "'
                            AND payment_status_payment_status_id IN (2, 3)
                        ");
                        if ($res && $res->num_rows > 0) {
                            while ($r = $res->fetch_assoc()) {
                                $rate = resolveExchangeRate($r, $courseCurrencyId);
                                $discounts += toCourse((float)$r['discount_amount'], $rate);
                                /* Due Amount's penalty component: ONLY the
                                   receipt-level panelty_amount (course currency).
                                   panelty_payments LOG table does NOT feed into
                                   this — see penalty_lkr below. */
                                $penalties += toCourse((float)$r['panelty_amount'], $rate);
                                $tax += toCourse((float)$r['tax_amount'], $rate);

                                if ((int)$r['payment_status_payment_status_id'] === 3) {
                                    $settled += toCourse((float)$r['paid_amount'], $rate);
                                }
                            }
                        }

                        /* penalty_lkr: raw SUM from panelty_payments, shown
                           as-is in the Penalty column — always LKR, no
                           conversion, NOT part of the Due Amount above. */
                        $penaltyLkr = 0;
                        $pr = Database::search("
                            SELECT SUM(amount) AS total_lkr_penalty
                            FROM panelty_payments
                            WHERE course_payments_course_payments_id = '" . $cpId . "'
                        ");
                        if ($pr && $pr->num_rows > 0) {
                            $prRow = $pr->fetch_assoc();
                            if (!empty($prRow['total_lkr_penalty'])) {
                                $penaltyLkr = (float)$prRow['total_lkr_penalty'];
                            }
                        }

                        return [
                            'discounts'   => $discounts,
                            'penalties'   => $penalties,
                            'tax'         => $tax,
                            'settled'     => $settled,
                            'penalty_lkr' => $penaltyLkr,
                        ];
                    }

                    function fetchDueInstallments(array $f): array
                    {
                        $query = "
                            SELECT
                                cp.course_payments_id,
                                cp.amount,
                                cp.discount,
                                cp.discount_type_discount_type_id,
                                cp.due_date,

                                c.code AS currency_code,
                                c.decimal_places,
                                c.currency_id AS course_currency_id,

                                shi.status_status_id,

                                s.student_id AS aId,
                                ld.first_name,
                                ld.last_name,
                                t.title,

                                intk.intake,
                                intk.intake_id,
                                prog.programme,
                                prog.programme_id,
                                fac.faculty_id,
                                br.branch,
                                br.branch_id,

                                cu.user_id      AS counsellor_id,
                                cu.user_fname   AS counsellor_first_name,
                                cu.user_lname   AS counsellor_last_name

                            FROM course_payments AS cp

                            INNER JOIN payment_plan_has_students_has_intakes AS phsi
                                ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

                            INNER JOIN payment_category AS pc
                                ON phsi.payment_category_payment_category_id = pc.payment_category_id

                            INNER JOIN currencies AS c
                                ON pc.currencies_currency_id = c.currency_id

                            INNER JOIN students_has_intakes AS shi
                                ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                            INNER JOIN students AS s
                                ON shi.students_student_id = s.student_id

                            INNER JOIN login_details AS ld
                                ON s.login_details_login_details_id = ld.login_details_id

                            INNER JOIN title AS t
                                ON s.title_title_id = t.title_id

                            INNER JOIN intakes AS intk
                                ON shi.intakes_intake_id = intk.intake_id

                            INNER JOIN programmes AS prog
                                ON intk.programmes_programme_id = prog.programme_id

                            INNER JOIN faculties AS fac
                                ON prog.faculties_faculty_id = fac.faculty_id

                            INNER JOIN branches AS br
                                ON fac.branches_branch_id = br.branch_id

                            LEFT JOIN users cu
                                ON s.users_user_id = cu.user_id

                            WHERE cp.payment_status_payment_status_id != 5
                            AND cp.due_date < CURDATE()
                        ";

                        if (!empty($f['text'])) {
                            $t = Database::escape($f['text']);
                            $query .= " AND (
                                s.student_id LIKE '%$t%'
                                OR ld.first_name LIKE '%$t%'
                                OR ld.last_name LIKE '%$t%'
                            ) ";
                        }
                        if (!empty($f['intake']) && $f['intake'] != '0') {
                            $query .= " AND intk.intake_id = '" . (int)$f['intake'] . "' ";
                        } elseif (!empty($f['programme']) && $f['programme'] != '0') {
                            $query .= " AND prog.programme_id = '" . (int)$f['programme'] . "' ";
                        } elseif (!empty($f['faculty']) && $f['faculty'] != '0') {
                            $query .= " AND fac.faculty_id = '" . (int)$f['faculty'] . "' ";
                        } elseif (!empty($f['branch']) && $f['branch'] != '0') {
                            $query .= " AND br.branch_id = '" . (int)$f['branch'] . "' ";
                        }
                        if (!empty($f['counsellor']) && $f['counsellor'] != '0') {
                            $query .= " AND cu.user_id = '" . (int)$f['counsellor'] . "' ";
                        }
                        if (!empty($f['currency']) && $f['currency'] != '0') {
                            $query .= " AND c.currency_id = '" . (int)$f['currency'] . "' ";
                        }
                        if (!empty($f['status']) && $f['status'] != '0') {
                            $query .= " AND shi.status_status_id = '" . (int)$f['status'] . "' ";
                        }
                        if (!empty($f['dateFilter']) && $f['dateFilter'] == '1') {
                            $query .= " AND MONTH(cp.due_date) = MONTH(CURDATE()) AND YEAR(cp.due_date) = YEAR(CURDATE()) ";
                        } elseif (!empty($f['dateFilter']) && $f['dateFilter'] == '2') {
                            $query .= " AND MONTH(cp.due_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(cp.due_date) = YEAR(CURDATE() - INTERVAL 1 MONTH) ";
                        } elseif (!empty($f['dateFilter']) && $f['dateFilter'] == '3') {
                            if (!empty($f['fromDate'])) $query .= " AND cp.due_date >= '" . Database::escape($f['fromDate']) . "' ";
                            if (!empty($f['toDate']))   $query .= " AND cp.due_date <= '" . Database::escape($f['toDate']) . "' ";
                        }

                        $query .= " ORDER BY cp.course_payments_id DESC ";

                        $latestTaxRate = 2.5;
                        $rate = Database::search("SELECT * FROM tax_rates ORDER BY tax_rate_id DESC LIMIT 1");
                        if ($rate && $rate->num_rows > 0) {
                            $latestTaxRate = (float)$rate->fetch_assoc()['tax_rate'];
                        }


                        $rows = [];
                        $results = Database::search($query);
                        if ($results && $results->num_rows > 0) {
                            while ($data = $results->fetch_assoc()) {
                                $cpDiscount = 0.0;
                                if ($data['discount_type_discount_type_id'] == 3) {
                                    $cpDiscount = (float)$data['amount'] / 100 * (float)$data['discount'];
                                } elseif ($data['discount_type_discount_type_id'] == 2) {
                                    $cpDiscount = (float)$data['discount'];
                                }

                                $summary = getInstallmentSummary((int)$data['course_payments_id'], (int)$data['course_currency_id']);

                                $totalDiscount = $cpDiscount + $summary['discounts'];
                                $totalPenalty  = $summary['penalties'];
                                $totalTax      = $summary['tax'];
                                $settled       = $summary['settled'];

                                $payableAmount = (float)$data['amount'] - $totalDiscount + $totalPenalty + $totalTax;
                                $dueAmount = $payableAmount - $settled;

                                $dueAmountWithTax = calculateTaxInclusiveAmount($dueAmount, $latestTaxRate);
                                $dueAmountSchouldPaidTax = $dueAmountWithTax - $dueAmount;

                                if ($dueAmount <= 0.009) {
                                    continue;
                                }

                                $data['due_amount']    = $dueAmount;
                                $data['due_amount_with_tax'] = $dueAmountWithTax;
                                $data['due_amount_should_paid_tax'] = $dueAmountSchouldPaidTax;
                                /* Penalty column: panelty_payments LOG total,
                                   raw LKR — unrelated to the penalty already
                                   folded into $dueAmount above. */
                                $data['penalty_amount'] = $summary['penalty_lkr'];
                                $data['student_name']   = trim($data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name']);
                                $data['counsellor_name'] = trim(($data['counsellor_first_name'] ?? '') . ' ' . ($data['counsellor_last_name'] ?? ''));
                                if ($data['counsellor_name'] === '') {
                                    $data['counsellor_name'] = 'N/A';
                                }

                                $rows[] = $data;
                            }
                        }

                        if (!empty($f['sortByName'])) {
                            $dir = $f['sortByName'] == 1 ? 1 : -1;
                            usort($rows, fn($a, $b) => $dir * strcasecmp($a['first_name'], $b['first_name']));
                        } elseif (!empty($f['sortByDue'])) {
                            $dir = $f['sortByDue'] == 1 ? 1 : -1;
                            usort($rows, fn($a, $b) => $dir * ($a['due_amount'] <=> $b['due_amount']));
                        }

                        return $rows;
                    }

                    $allRows = fetchDueInstallments($filters);
                    $results_count = count($allRows);
                    $result_per_page = $resultPerPage > 0 ? $resultPerPage : 10;

                    $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                    if ($pageno < 1) $pageno = 1;
                    if ($pageno > $number_of_pages) $pageno = $number_of_pages;

                    $page_results = ($pageno - 1) * $result_per_page;
                    $pageRows = array_slice($allRows, $page_results, $result_per_page);
?>
                    <table class="table bl-solid m-0 p-0 g-0" id="due-table">
                        <thead>
                            <tr class="br-t-5">
                                <th class="px-4 py-3 bg-table-header main-text fw-medium align-content-center">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Program</span>
                                    </div>
                                </th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Intake</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Branch</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleDueReportPagination('sortByName')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-14 main-text fw-medium me-2">Student Name</span>
                                        <?php if ($filters['sortByName'] == 0): ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php else: ?>
                                            <iconify-icon icon="<?= $filters['sortByName'] == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleDueReportPagination('sortByDue')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-14 main-text fw-medium me-2">Due Amount (Foreign Currency)</span>
                                        <?php if ($filters['sortByDue'] == 0): ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php else: ?>
                                            <iconify-icon icon="<?= $filters['sortByDue'] == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Tax Amount (Foreign Currency)</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Total Amount (Foreign Currency)</th>

                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Counsellor</th>
                            </tr>
                        </thead>
                        <tbody id="due-table-data">
                            <?php if (count($pageRows) > 0): ?>
                                <?php foreach ($pageRows as $data): ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <span class="text-14 main-text mx-2"><?= htmlspecialchars($data['programme']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['intake']) ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['branch']) ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['student_name']) ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['currency_code']) . ' ' . number_format($data['due_amount'], (int)$data['decimal_places']) ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['currency_code']) . ' ' . number_format($data['due_amount_should_paid_tax'], $data['decimal_places']) ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['currency_code']) . ' ' . number_format($data['due_amount_with_tax'], $data['decimal_places']) ?></span></td>
                                        <td class="py-2 align-middle">
                                            <?php if ($data['penalty_amount'] > 0): ?>
                                                <span class="text-14 main-text">LKR <?= number_format($data['penalty_amount'], 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-14 sub-text">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= htmlspecialchars($data['counsellor_name']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
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

                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                        <nav class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleDueReportPagination('previousPage')">
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
                                            $ac2 = $isActive ? '' : "onclick=\"handleDueReportPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleDueReportPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
