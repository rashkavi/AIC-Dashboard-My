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
                    $date = isset($decoded['date']) ? $decoded['date'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                    $query = "
                    SELECT 
                        cer.currency_exchange_rate_id,
                        cer.base_currency_id,
                        cer.target_currency_id,
                        cer.effective_date,
                        cer.effective_time,
                        cer.rate,
                        cer.note,
                        cer.date_added,
            
                        bc.code AS base_code,
                        bc.name AS base_name,
                        bc.symbol AS base_symbol,
            
                        tc.code AS target_code,
                        tc.name AS target_name,
                        tc.symbol AS target_symbol
            
                    FROM currency_exchange_rates cer
            
                    INNER JOIN currencies bc 
                        ON cer.base_currency_id = bc.currency_id
            
                    INNER JOIN currencies tc 
                        ON cer.target_currency_id = tc.currency_id
            
                    INNER JOIN (
                        SELECT 
                            base_currency_id,
                            target_currency_id,
                            MAX(effective_date) AS latest_effective_date
                        FROM currency_exchange_rates
                        WHERE effective_date IS NOT NULL
                        GROUP BY base_currency_id, target_currency_id
                    ) latest_rate 
                        ON cer.base_currency_id = latest_rate.base_currency_id
                        AND cer.target_currency_id = latest_rate.target_currency_id
                        AND cer.effective_date = latest_rate.latest_effective_date
            
                    WHERE bc.is_active = 1
                    AND tc.is_active = 1
                                               ";

                    if (!empty($date)) {
                        $query .= " AND cer.effective_date = '" . $date . "'";
                    }

                    $query .= "  ORDER BY cer.effective_date DESC, cer.effective_time DESC";

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $rates = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                    <table class="table bl-solid m-0 p-0 g-0" id="exchange-rates-table">
                        <thead>
                            <tr class="br-t-5 align-content-center">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <div class="d-flex justify-content-between align-items-center w-100">
                                            <span class="text-14 main-text fw-medium mx-2">Currency</span>
                                        </div>
                                    </div>
                                </th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Base Currency</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Target Currency</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Latest Rate</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Effective Date & Time</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                            </tr>
                        </thead>

                        <tbody id="exchange-rate-table-data">
                            <?php


                            if ($rates->num_rows > 0) {
                                while ($data = $rates->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-rate<?php echo $data['currency_exchange_rate_id']; ?>">

                                                <div class="flex-grow-1 d-flex align-items-center">
                                                    <div class="d-flex flex-column mx-2">
                                                        <span class="text-14 main-text">
                                                            <?php echo $data["base_code"]; ?> to <?php echo $data["target_code"]; ?>
                                                        </span>
                                                        <span class="text-12 sub-text">
                                                            <?php echo $data["base_name"]; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text">
                                                <?php echo  $data["base_code"]; ?>
                                            </span>
                                        </td>

                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text">
                                                <?php echo $data["target_code"]; ?>
                                            </span>
                                        </td>

                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text fw-semibold">
                                                <?php echo $data["target_code"]; ?>
                                                <?php echo number_format($data["rate"], 2); ?>
                                            </span>
                                        </td>

                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text">
                                                <?php echo date("Y-m-d", strtotime($data["effective_date"])) . " " . date("h:i A", strtotime($data["effective_time"])); ?>
                                            </span>
                                        </td>

                                        <td class="py-2 align-middle">
                                            <button class="table-btn table-btn-success w-100">
                                                Latest
                                            </button>
                                        </td>

                                        <td class="py-3 align-middle">
                                            <div class="dropdown">
                                                <iconify-icon
                                                    icon="charm:menu-kebab"
                                                    width="20"
                                                    height="20"
                                                    class="justify-content-center sub-text cursor-pointer"
                                                    data-bs-toggle="dropdown"
                                                    aria-expanded="false">
                                                </iconify-icon>

                                                <ul class="dropdown-menu border-0 shadow br-5">
                                                    <li>
                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center"
                                                            href="#"
                                                            onclick="openEditExchangeRateModal(
                                                                                                        '<?php echo $data['currency_exchange_rate_id']; ?>',
                                                                                                        '<?php echo $data['rate']; ?>',
                                                                                                        '<?php echo $data['effective_date']; ?>',
                                                                                                        '<?php echo htmlspecialchars($data['note'] ?? '', ENT_QUOTES); ?>'
                                                                                                    )">
                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                            Edit
                                                        </a>
                                                    </li>
                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                    <li>
                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center"
                                                            href="currency-exchange-rate-history?currency-id=<?php echo $data['currency_exchange_rate_id']; ?>">
                                                            <iconify-icon icon="material-symbols:history" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                            History
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                }
                            } else {
                                ?>
                                <tr class="justify-content-center align-middle">
                                    <td colspan="7" class="align-middle justify-content-center">
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleCurrencyExchangePagination('previousPage')">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
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
                                            $action = $isActive ? '' : "onclick=\"handleCurrencyExchangePagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCurrencyExchangePagination('nextPage',null,'<?= $number_of_pages ?>')">
                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
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
