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

                    $id = isset($decoded['id']) ? $decoded['id'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $date = isset($decoded['date']) ? $decoded['date'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $isValidCurrency = Database::search("SELECT * FROM `currency_exchange_rates` 
                    WHERE `currency_exchange_rate_id` = '" . $id . "'");

                    if ($isValidCurrency->num_rows == 0) {
                        $status->currency = 'Invalid currency.';
                    } else {
                        $query = "
                        SELECT 
        
                            cerl.currency_exchange_rate_log_id,
                            cerl.effective_date,
                            cerl.old_rate,
                            cerl.new_rate,
                            cerl.action_type,
                            cerl.note,
                            cerl.date_added,
        
                            cer.currency_exchange_rate_id,
        
                            bc.code AS base_currency_code,
                            bc.symbol AS base_currency_symbol,
        
                            tc.code AS target_currency_code,
                            tc.symbol AS target_currency_symbol,
        
                            users.user_fname,
                            users.user_lname
        
                        FROM currency_exchange_rate_logs cerl
        
                        INNER JOIN currency_exchange_rates cer
                            ON cerl.currency_exchange_rates_currency_exchange_rate_id = cer.currency_exchange_rate_id
        
                        INNER JOIN currencies bc
                            ON cer.base_currency_id = bc.currency_id
        
                        INNER JOIN currencies tc
                            ON cer.target_currency_id = tc.currency_id
        
                        LEFT JOIN users
                            ON cerl.updated_by = users.user_id
        
                        WHERE cer.currency_exchange_rate_id = '" . $id . "'
        
                        
                    ";

                        if (!empty($date)) {
                            $query .= " AND cerl.effective_date = '" . $date . "'";
                        }

                        $query .= " ORDER BY cerl.date_added DESC";

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
                        <table class="table bl-solid m-0 p-0 g-0" id="exchange-rate-history-table">

                            <thead>
                                <tr class="br-t-5 align-content-center">

                                    <th scope="col"
                                        class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        Currency Pair
                                    </th>

                                    <th scope="col"
                                        class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        Effective Date
                                    </th>

                                    <th scope="col"
                                        class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        Previous Rate
                                    </th>

                                    <th scope="col"
                                        class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        New Rate
                                    </th>

                                    <th scope="col"
                                        class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        Action
                                    </th>

                                    <th scope="col"
                                        class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        Updated By
                                    </th>

                                    <th scope="col"
                                        class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                        Updated At
                                    </th>

                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                if ($users->num_rows > 0) {
                                    while ($data = $users->fetch_assoc()) {

                                        $actionClass = "";

                                        if ($data["action_type"] == "ADD") {
                                            $actionClass = "table-btn-success";
                                        } else {
                                            $actionClass = "table-btn-warning";
                                        }

                                ?>
                                        <tr>
                                            <td class="px-4 py-3 align-middle">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                        <?php echo strtoupper(substr($data["base_currency_code"], 0, 1)); ?>
                                                    </div>

                                                    <div class="d-flex flex-column mx-2">
                                                        <span class="text-14 main-text fw-medium">
                                                            <?php echo $data["base_currency_code"]; ?>
                                                            →
                                                            <?php echo $data["target_currency_code"]; ?>
                                                        </span>
                                                        <span class="text-12 sub-text">
                                                            Exchange Rate History
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text">
                                                    <?php echo date("Y-m-d", strtotime($data["effective_date"])); ?>
                                                </span>
                                            </td>
                                            <td class="py-2 align-middle">
                                                <?php
                                                if (!empty($data["old_rate"])) {
                                                ?>
                                                    <span class="text-14 sub-text">
                                                        <?php echo $data["target_currency_symbol"]; ?>
                                                        <?php echo number_format($data["old_rate"], 2); ?>
                                                    </span>
                                                <?php
                                                } else {
                                                ?>
                                                    <span class="text-13 sub-text">
                                                        No Previous Rate
                                                    </span>
                                                <?php
                                                }
                                                ?>
                                            </td>

                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text fw-semibold">
                                                    <?php echo $data["target_currency_symbol"]; ?>
                                                    <?php echo number_format($data["new_rate"], 2); ?>
                                                </span>
                                            </td>

                                            <td class="py-2 align-middle">
                                                <button class="table-btn <?php echo $actionClass; ?> w-100">
                                                    <?php echo $data["action_type"]; ?>
                                                </button>
                                            </td>

                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text">
                                                    <?php
                                                    if (!empty($data["user_fname"])) {
                                                        echo $data["user_fname"] . " " . $data["user_lname"];
                                                    } else {
                                                        echo "System";
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="py-2 align-middle">
                                                <div class="d-flex flex-column">
                                                    <span class="text-14 main-text">
                                                        <?php echo date("Y-m-d", strtotime($data["date_added"])); ?>
                                                    </span>
                                                    <span class="text-12 sub-text">
                                                        <?php echo date("h:i A", strtotime($data["date_added"])); ?>
                                                    </span>
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
                                                <img src="./assets/imgs/common/no-data.png"
                                                    class="no-data-icon"
                                                    alt="No data" />
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleCurrencyExchangeHistorysPagination(<?= $id ?>,'previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton($i, $isActive = false, $currencyID)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handleCurrencyExchangeHistorysPagination({$currencyID},'goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                            }

                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                    renderPageButton($i, $pageno == $i, $id);
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCurrencyExchangeHistorysPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
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
