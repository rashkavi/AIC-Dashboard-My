<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['currency-id']) && !empty($_GET['currency-id']))) {
            header("Location: ./currency-exchange-rates");
        } else {
            $currencyID = $_GET['currency-id'];

            $isValidCurrency = Database::search("SELECT * FROM `currency_exchange_rates` WHERE `currency_exchange_rate_id` = '" . $currencyID . "'");

            if ($isValidCurrency->num_rows == 0) {
                header("Location: ./currency-exchange-rates");
            } else {
                $currencyData = $isValidCurrency->fetch_assoc();

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
                    bc.symbol,

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

                WHERE bc.currency_id = '" . $currencyID . "'

                ORDER BY cerl.date_added DESC
            ";

                $results = Database::search($query);

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Currency Exchange Rate History - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body>
                    <div class="notifications"></div>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "./includes/side-menu.php";
                                load_side_menu::load("Payments");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("Payments");
                                        ?>
                                    </div>

                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-table.php"; ?>
                                    </div>

                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-8">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg" data-i18n="">Currency Exchange Rate History</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./currency-exchange-rates">Currency Exchange Rates</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Currency Exchange Rate History</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>

                                            <div class="col-12 p-4 component-theme">
                                                <div class="row p-0 m-0 g-0">

                                                    <div class="col-12 mb-4">
                                                        <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                                    <span class="text-14 dark-theme fw-medium p-0 m-0"><?php echo $results->num_rows ?> Logs</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                    <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                    <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handleCurrencyExchangeHistorysPagination(<?= $currencyID ?>,'filter')">
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                                                                    <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                </button>
                                                            </div>
                                                            <div class="col-12 collapse" id="filterCollapse">
                                                                <div class="card card-body border-0 p-0">
                                                                    <div class="col-12">
                                                                        <div class="row gy-3 justify-content-end">
                                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column" id="date-container">
                                                                                <input
                                                                                    type="text"
                                                                                    class="form-control custom-input mt-2"
                                                                                    id="date"
                                                                                    placeholder="Select Date"
                                                                                    onfocus="this.type='date'; this.showPicker();"
                                                                                    onblur="if(!this.value){this.type='text';}"
                                                                                    onchange="handleCurrencyExchangeHistorysPagination(<?= $currencyID ?>,'filter');">
                                                                                <span id="date-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                <label for="show-result-selector" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleCurrencyExchangeHistorysPagination(<?= $currencyID ?>,'filter')">
                                                                    <option selected value="10">10</option>
                                                                    <option value="25">25</option>
                                                                    <option value="50">50</option>
                                                                    <option value="100">100</option>
                                                                    <option value="250">250</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="users-table-container">
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

                                                                            $pageno = 1;
                                                                            $results_count = $results->num_rows;
                                                                            $result_per_page = 10;  // Consistent variable name

                                                                            if ($results_count > $result_per_page) {
                                                                                $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                            } else {
                                                                                $number_of_pages = 1;
                                                                            }

                                                                            $page_results = ($pageno - 1) * $result_per_page;
                                                                            $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

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
                                                                                                    <?php echo $data["base_currency_code"]; ?>
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
                                                                                                    <?php echo $data["target_currency_code"]; ?>
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
                                                                                                <?php echo $data["target_currency_code"]; ?>
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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleCurrencyExchangeHistorysPagination(<?= $currencyID ?>,'previousPage')">
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
                                                                                                renderPageButton($i, $pageno == $i, $currencyID);
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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCurrencyExchangeHistorysPagination(<?= $currencyID ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                                                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                                                                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </button>
                                                                            </div>
                                                                        </nav>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex-grow-1"></div>
                                        <footer>
                                            <?php include './includes/footer.php' ?>
                                        </footer>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php
            }
        }
    }
}
?>