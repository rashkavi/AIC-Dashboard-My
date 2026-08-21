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

                    $search_text   = isset($decoded['text']) ? $decoded['text'] : '';
                    $branch        = isset($decoded['branch']) ? (int)$decoded['branch'] : 0;
                    $faculty       = isset($decoded['faculty']) ? (int)$decoded['faculty'] : 0;
                    $programme     = isset($decoded['programme']) ? (int)$decoded['programme'] : 0;
                    $intake        = isset($decoded['intake']) ? (int)$decoded['intake'] : 0;
                    $counsellor    = isset($decoded['counsellor']) ? (int)$decoded['counsellor'] : 0;
                    $currency      = isset($decoded['currency']) ? (int)$decoded['currency'] : 0;
                    $dateFilter    = isset($decoded['dateFilter']) ? (int)$decoded['dateFilter'] : 0; // 1=This Month, 2=Last Month, 3=Range
                    $fromDate      = isset($decoded['fromDate']) ? $decoded['fromDate'] : '';
                    $toDate        = isset($decoded['toDate']) ? $decoded['toDate'] : '';
                    $sortByName    = isset($decoded['sortByName']) ? (int)$decoded['sortByName'] : 0;
                    $sortByDate    = isset($decoded['sortByDate']) ? (int)$decoded['sortByDate'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? (int)$decoded['resultPerPage'] : 10;
                    $pageno        = isset($decoded['page']) ? (int)$decoded['page'] : 1;

                    $LKR_CURRENCY_ID = 1;

                    /* ── Same conversion helpers as the view page ─────────────── */
                    function resolveLkrConversion(int $foreignCurrencyId, ?int $exchangeRateId, ?float $inlineRate): array
                    {
                        if ($foreignCurrencyId === 1 || $foreignCurrencyId === 0) {
                            return ['rate' => null, 'mode' => 'none'];
                        }
                        if ($exchangeRateId) {
                            $rr = Database::search(
                                "SELECT `base_currency_id`, `target_currency_id`, `rate`
                                 FROM `currency_exchange_rates`
                                 WHERE `currency_exchange_rate_id` = '" . (int)$exchangeRateId . "'
                                 LIMIT 1"
                            );
                            if ($rr && $rr->num_rows > 0) {
                                $r      = $rr->fetch_assoc();
                                $base   = (int)$r['base_currency_id'];
                                $target = (int)$r['target_currency_id'];
                                $rate   = (float)$r['rate'];
                                if ($rate > 0) {
                                    if ($base === $foreignCurrencyId && $target === 1) {
                                        return ['rate' => $rate, 'mode' => 'multiply'];
                                    }
                                    if ($base === 1 && $target === $foreignCurrencyId) {
                                        return ['rate' => $rate, 'mode' => 'divide'];
                                    }
                                    return ['rate' => $rate, 'mode' => 'multiply'];
                                }
                            }
                        }
                        if ($inlineRate !== null && $inlineRate > 0) {
                            return ['rate' => $inlineRate, 'mode' => 'multiply'];
                        }
                        return ['rate' => null, 'mode' => 'none'];
                    }

                    function foreignToLkr(float $foreignAmount, array $conv): float
                    {
                        if ($conv['mode'] === 'multiply' && $conv['rate'] !== null) {
                            return $foreignAmount * $conv['rate'];
                        }
                        if ($conv['mode'] === 'divide' && $conv['rate'] !== null && $conv['rate'] > 0) {
                            return $foreignAmount / $conv['rate'];
                        }
                        return $foreignAmount;
                    }

                    function lkrToForeign(float $lkrAmount, array $conv): float
                    {
                        if ($conv['mode'] === 'multiply' && $conv['rate'] !== null && $conv['rate'] > 0) {
                            return $lkrAmount / $conv['rate'];
                        }
                        if ($conv['mode'] === 'divide' && $conv['rate'] !== null) {
                            return $lkrAmount * $conv['rate'];
                        }
                        return $lkrAmount;
                    }

                    /* ── Base query — identical shape to the view page ────────── */
                    $query = "
                        SELECT
                            r.course_payment_receipt_id,
                            r.receipt_number,

                            br.branch_id,
                            br.branch,
                            fac.faculty_id,
                            prog.programme_id,
                            intk.intake_id,

                            shi.students_has_intakes_id,
                            s.student_id AS aId,
                            ld.first_name,
                            ld.last_name,
                            t.title,

                            cu.user_id      AS counsellor_id,
                            cu.user_fname   AS counsellor_first_name,
                            cu.user_lname   AS counsellor_last_name,

                            GROUP_CONCAT(DISTINCT pgp.payment_type ORDER BY pgp.payment_type SEPARATOR ', ') AS payment_types,

                            MIN(cpp.date_paid)                    AS paid_date,
                            SUM(cpp.paid_amount)                  AS total_paid_amount,

                            MAX(cpp.currencies_currency_id)       AS raw_paid_currency_id,

                            MAX(course_cur.currency_id)           AS base_currency_id,
                            MAX(course_cur.code)                  AS base_currency_code,
                            MAX(course_cur.decimal_places)        AS base_decimal_places,

                            MAX(cpp.currency_exchange_rate_id)    AS exchange_rate_id,
                            MAX(cpp.currency_exchange_rate)       AS inline_exchange_rate,

                            COALESCE(paid_cur.code, course_cur.code) AS paid_currency_code,
                            COALESCE(paid_cur.decimal_places, course_cur.decimal_places) AS paid_decimal_places

                        FROM course_payment_receipts r

                        INNER JOIN course_payment_has_payments cpp
                            ON cpp.course_payment_receipts_course_payment_receipt_id = r.course_payment_receipt_id

                        INNER JOIN course_payments cp
                            ON cp.course_payments_id = cpp.course_payments_course_payments_id

                        INNER JOIN payment_type pgp
                            ON cp.payment_type_payment_type_id = pgp.payment_type_id

                        INNER JOIN payment_plan_has_students_has_intakes phsi
                            ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id

                        INNER JOIN payment_category pc
                            ON phsi.payment_category_payment_category_id = pc.payment_category_id

                        INNER JOIN currencies course_cur
                            ON pc.currencies_currency_id = course_cur.currency_id

                        LEFT JOIN currencies paid_cur
                            ON cpp.currencies_currency_id = paid_cur.currency_id

                        INNER JOIN students_has_intakes shi
                            ON r.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

                        INNER JOIN students s
                            ON shi.students_student_id = s.student_id

                        INNER JOIN login_details ld
                            ON s.login_details_login_details_id = ld.login_details_id

                        INNER JOIN title t
                            ON s.title_title_id = t.title_id

                        LEFT JOIN users cu
                            ON s.users_user_id = cu.user_id

                        INNER JOIN intakes intk
                            ON shi.intakes_intake_id = intk.intake_id

                        INNER JOIN programmes prog
                            ON intk.programmes_programme_id = prog.programme_id

                        INNER JOIN faculties fac
                            ON prog.faculties_faculty_id = fac.faculty_id

                        INNER JOIN branches br
                            ON fac.branches_branch_id = br.branch_id

                        WHERE 1=1
                    ";

                    /* ── Search: student name or receipt number ───────────────── */
                    if ($search_text != "") {
                        $search_text_escaped = Database::escape($search_text);
                        $query .= " AND (
                            `ld`.`first_name` LIKE '%" . $search_text_escaped . "%'
                            OR `ld`.`last_name` LIKE '%" . $search_text_escaped . "%'
                            OR `r`.`receipt_number` LIKE '%" . $search_text_escaped . "%'
                        )";
                    }

                    /* ── Location filters — most specific wins, same pattern as
                           the existing students-payment filter cascade ───────── */
                    if ($intake != 0) {
                        $query .= " AND `intk`.`intake_id` = '" . $intake . "'";
                    } elseif ($programme != 0) {
                        $query .= " AND `prog`.`programme_id` = '" . $programme . "'";
                    } elseif ($faculty != 0) {
                        $query .= " AND `fac`.`faculty_id` = '" . $faculty . "'";
                    } elseif ($branch != 0) {
                        $query .= " AND `br`.`branch_id` = '" . $branch . "'";
                    }

                    /* ── Counsellor ────────────────────────────────────────────── */
                    if ($counsellor != 0) {
                        $query .= " AND `cu`.`user_id` = '" . $counsellor . "'";
                    }

                    /* ── Date filter ───────────────────────────────────────────── */
                    if ($dateFilter == 1) {
                        $query .= " AND YEAR(`cpp`.`date_paid`) = YEAR(CURDATE()) AND MONTH(`cpp`.`date_paid`) = MONTH(CURDATE())";
                    } elseif ($dateFilter == 2) {
                        $query .= " AND YEAR(`cpp`.`date_paid`) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(`cpp`.`date_paid`) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
                    } elseif ($dateFilter == 3 && $fromDate != '' && $toDate != '') {
                        $fromDateEscaped = Database::escape($fromDate);
                        $toDateEscaped   = Database::escape($toDate);
                        $query .= " AND `cpp`.`date_paid` BETWEEN '" . $fromDateEscaped . "' AND '" . $toDateEscaped . "'";
                    }

                    if ($currency != 0) {
                        $query .= " AND `paid_cur`.`currency_id` = '" . $currency . "'";
                    }

                    $query .= " GROUP BY r.course_payment_receipt_id";

                    /* Applied as HAVING (not a post-fetch PHP filter) so it
                       runs BEFORE LIMIT/OFFSET — otherwise pagination counts
                       would be wrong. Matches either the course's base
                       currency or the actually-paid currency. */


                    /* ── Sorting ───────────────────────────────────────────────── */
                    if ($sortByName != 0) {
                        $dir = $sortByName == 1 ? 'ASC' : 'DESC';
                        $query .= " ORDER BY `ld`.`first_name` " . $dir;
                    } elseif ($sortByDate != 0) {
                        $dir = $sortByDate == 1 ? 'ASC' : 'DESC';
                        $query .= " ORDER BY `paid_date` " . $dir;
                    } else {
                        $query .= " ORDER BY r.course_payment_receipt_id DESC";
                    }

                    $results       = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = $resultPerPage > 0 ? $resultPerPage : 10;

                    $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                    if ($pageno < 1) $pageno = 1;
                    if ($pageno > $number_of_pages) $pageno = $number_of_pages;

                    $page_results = ($pageno - 1) * $result_per_page;
                    $receipts = Database::search($query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
?>
                    <table class="table bl-solid m-0 p-0 g-0" id="collection-table">
                        <thead>
                            <tr class="br-t-5">
                                <th class="px-4 py-3 bg-table-header main-text fw-medium align-content-center">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Branch</span>
                                    </div>
                                </th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleCollectionReportPagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                        <?php if ($sortByDate == 0): ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php else: ?>
                                            <iconify-icon icon="<?= $sortByDate == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Receipt No</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center cursor-pointer" onclick="handleCollectionReportPagination('sortByName')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-14 main-text fw-medium me-2">Student Name</span>
                                        <?php if ($sortByName == 0): ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php else: ?>
                                            <iconify-icon icon="<?= $sortByName == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Foreign Amount</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">LKR Amount</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Counsellor</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment Type / Program</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Exchange Rate</th>
                            </tr>
                        </thead>
                        <tbody id="collection-table-data">
                            <?php
                            if ($receipts && $receipts->num_rows > 0):
                                while ($data = $receipts->fetch_assoc()):

                                    $baseCurrencyId = (int)($data['base_currency_id'] ?? 0);

                                    $rawPaidCurrencyId = $data['raw_paid_currency_id'] !== null ? (int)$data['raw_paid_currency_id'] : null;
                                    $paidCurrencyId    = $rawPaidCurrencyId ?? $baseCurrencyId;

                                    $totalPaidAmount = (float)$data['total_paid_amount'];

                                    $exchangeRateId = !empty($data['exchange_rate_id']) ? (int)$data['exchange_rate_id'] : null;
                                    $inlineRate     = isset($data['inline_exchange_rate']) && $data['inline_exchange_rate'] !== null ? (float)$data['inline_exchange_rate'] : null;

                                    $paidIsLkr    = ($paidCurrencyId === $LKR_CURRENCY_ID);
                                    $baseIsLkr    = ($baseCurrencyId === $LKR_CURRENCY_ID);
                                    $isAnyForeign = !$paidIsLkr || !$baseIsLkr;

                                    $foreignAmount = null;
                                    $lkrAmount     = null;
                                    $foreignCode   = null;
                                    $foreignDp     = 2;
                                    $conv          = ['rate' => null, 'mode' => 'none'];

                                    if (!$isAnyForeign) {
                                        $lkrAmount = $totalPaidAmount;
                                    } elseif (!$baseIsLkr && $paidCurrencyId === $baseCurrencyId) {
                                        /* Paid directly in the course's own foreign currency
                                           (e.g. base EUR, paid EUR) — no conversion happened,
                                           no genuine LKR figure to show. */
                                        $foreignAmount = $totalPaidAmount;
                                        $foreignCode   = htmlspecialchars($data['base_currency_code']);
                                        $foreignDp     = (int)$data['base_decimal_places'];
                                        $lkrAmount     = null;
                                    } elseif ($paidIsLkr) {
                                        $lkrAmount     = $totalPaidAmount;
                                        $conv          = resolveLkrConversion($baseCurrencyId, $exchangeRateId, $inlineRate);
                                        $foreignAmount = lkrToForeign($lkrAmount, $conv);
                                        $foreignCode   = htmlspecialchars($data['base_currency_code']);
                                        $foreignDp     = (int)$data['base_decimal_places'];
                                    } else {
                                        $foreignAmount = $totalPaidAmount;
                                        $conv          = resolveLkrConversion($paidCurrencyId, $exchangeRateId, $inlineRate);
                                        $lkrAmount     = foreignToLkr($foreignAmount, $conv);
                                        $foreignCode   = htmlspecialchars($data['paid_currency_code']);
                                        $foreignDp     = (int)$data['paid_decimal_places'];
                                    }

                                    $counsellorName = trim(($data['counsellor_first_name'] ?? '') . ' ' . ($data['counsellor_last_name'] ?? ''));
                                    if ($counsellorName === '') {
                                        $counsellorName = '—';
                                    }
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <span class="text-14 main-text mx-2"><?= htmlspecialchars($data['branch']) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text">
                                                <?= $data['paid_date'] ? date('Y-m-d', strtotime($data['paid_date'])) : 'N/A' ?>
                                            </span>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text fw-medium"><?= htmlspecialchars($data['receipt_number']) ?></span>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text"><?= htmlspecialchars($data['title'] . '.' . $data['first_name'] . ' ' . $data['last_name']) ?></span>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <?php if ($isAnyForeign && !$paidIsLkr && $foreignAmount !== null): ?>
                                                <span class="text-14 main-text"><?= $foreignCode . ' ' . number_format($foreignAmount, $foreignDp) ?></span>
                                            <?php else: ?>
                                                <span class="text-14 sub-text">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <?php if ($lkrAmount !== null): ?>
                                                <span class="text-14 main-text fw-medium">LKR <?= number_format($lkrAmount, 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-14 sub-text">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text"><?= htmlspecialchars($counsellorName) ?></span>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text"><?= htmlspecialchars($data['payment_types']) ?></span>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <?php if ($isAnyForeign && $conv['rate'] !== null): ?>
                                                <span class="text-14 main-text"><?= number_format($conv['rate'], 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-14 sub-text">—</span>
                                            <?php endif; ?>
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

                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                        <nav class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleCollectionReportPagination('previousPage')">
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
                                            $ac2 = $isActive ? '' : "onclick=\"handleCollectionReportPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCollectionReportPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
