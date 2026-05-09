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

        $query = "
        SELECT 
            cer.currency_exchange_rate_id,
            cer.base_currency_id,
            cer.target_currency_id,
            cer.effective_date,
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

        ORDER BY bc.code ASC
    ";

        $results = Database::search($query);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Users - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body onload="initializeCurrencyExchangeRates();">
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
                                                    <span class="main-topic lh-lg" data-i18n="">Currency Exchange Rates</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Currency Exchange Rate</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>

                                            <div class="col-12 col-lg-4 mt-3 mt-lg-0">
                                                <div class="row align-items-center justify-content-end gx-3 h-100">
                                                    <?php

                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 2)) {
                                                    ?>
                                                        <div class="col-6 mt-3 mt-lg-0 m-md-0">
                                                            <button class="custom-btn w-100" data-i18n="add-new" type="button" data-bs-target="#exchangeRateModal" data-bs-toggle="modal">
                                                                Add New
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card border-0 br-0">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                                <div>
                                                    <h5 class="main-text mb-1">Exchange Rate History</h5>
                                                    <span class="sub-text text-13">
                                                        Active currency rate trends against LKR
                                                    </span>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <input type="date" id="fromDate" class="form-control custom-input form-control-sm">
                                                    <input type="date" id="toDate" class="form-control custom-input form-control-sm">

                                                    <button class="custom-btn px-4" onclick="loadCurrencyChart()">
                                                        Filter
                                                    </button>
                                                </div>
                                            </div>

                                            <div style="height: 380px;">
                                                <canvas id="currencyRateChart"></canvas>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme mt-4">
                                        <div class="row p-0 m-0 g-0">

                                            <div class="col-12">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="table-responsive-wrapper">
                                                        <div class="table-responsive" id="users-table-container">
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
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Effective Date</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                    </tr>
                                                                </thead>

                                                                <tbody id="exchange-rate-table-data">
                                                                    <?php

                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = 10;

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;

                                                                    $rates = Database::search($query . " LIMIT " . $result_per_page . " OFFSET " . $page_results);

                                                                    if ($rates->num_rows > 0) {
                                                                        while ($data = $rates->fetch_assoc()) {
                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-rate<?php echo $data['currency_exchange_rate_id']; ?>">

                                                                                        <div class="flex-grow-1 d-flex align-items-center">
                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                                <?php echo strtoupper(substr($data["base_code"], 0, 1)); ?>
                                                                                            </div>

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
                                                                                        <?php echo $data["base_symbol"] . " " . $data["base_code"]; ?>
                                                                                    </span>
                                                                                </td>

                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text">
                                                                                        <?php echo $data["target_symbol"] . " " . $data["target_code"]; ?>
                                                                                    </span>
                                                                                </td>

                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text fw-semibold">
                                                                                        <?php echo $data["target_symbol"]; ?>
                                                                                        <?php echo number_format($data["rate"], 2); ?>
                                                                                    </span>
                                                                                </td>

                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text">
                                                                                        <?php echo date("Y-m-d", strtotime($data["effective_date"])); ?>
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
            <div class="modal fade" id="exchangeRateModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-md">
                    <div class="modal-content border-0 shadow br-10">
                        <div class="modal-header border-0 pb-0">
                            <div>
                                <h5 class="main-text fw-semibold mb-1">
                                    Add Exchange Rate
                                </h5>
                                <span class="sub-text text-13">
                                    Configure latest currency exchange rate
                                </span>
                            </div>
                            <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal">
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">

                                <!-- Base Currency -->
                                <div class="col-12 mb-3">
                                    <label class="text-13 sub-text mb-2">
                                        Base Currency
                                    </label>

                                    <select class="form-select custom-select" id="baseCurrency">
                                        <option value="">Select Currency</option>

                                        <?php
                                        $currencies = Database::search("
                                                                                SELECT * FROM currencies 
                                                                                WHERE is_active = 1
                                                                                ORDER BY code ASC
                                                                            ");

                                        while ($currency = $currencies->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $currency['currency_id']; ?>">
                                                <?= $currency['code']; ?> - <?= $currency['name']; ?>
                                            </option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="sub-text text-12 text-danger" id="base-currency-feedback"></span>
                                </div>

                                <!-- Target Currency -->
                                <div class="col-12 mb-3">
                                    <label class="text-13 sub-text mb-2">
                                        Target Currency
                                    </label>

                                    <select class="form-select custom-input" id="targetCurrency">

                                        <?php
                                        $targetCurrencies = Database::search("
                                                                        SELECT * FROM currencies 
                                                                        WHERE code = 'LKR'
                                                                        LIMIT 1
                                                                    ");

                                        $target = $targetCurrencies->fetch_assoc();
                                        ?>

                                        <option value="<?= $target['currency_id']; ?>">
                                            <?= $target['code']; ?> - <?= $target['name']; ?>
                                        </option>
                                    </select>
                                    <span class="sub-text text-12 text-danger" id="target-currency-feedback"></span>
                                </div>

                                <!-- Rate -->
                                <div class="col-12 mb-3">
                                    <label class="text-13 sub-text mb-2">
                                        Exchange Rate
                                    </label>

                                    <input type="number"
                                        class="form-control custom-input"
                                        id="exchangeRate"
                                        placeholder="Enter exchange rate"
                                        step="0.000001">
                                    <span class="sub-text text-12 text-danger" id="exchange-rate-feedback"></span>
                                </div>

                                <!-- Effective Date -->
                                <div class="col-12 mb-3">
                                    <label class="text-13 sub-text mb-2">
                                        Effective Date
                                    </label>

                                    <input type="date"
                                        class="form-control custom-input"
                                        id="effectiveDate"
                                        value="<?= date('Y-m-d'); ?>">
                                    <span class="sub-text text-12 text-danger" id="effective-date-feedback"></span>
                                </div>

                                <!-- Note -->
                                <div class="col-12 mb-2">
                                    <label class="text-13 sub-text mb-2">
                                        Note
                                    </label>

                                    <textarea class="form-control custom-input"
                                        id="exchangeNote"
                                        rows="3"
                                        placeholder="Optional note"></textarea>
                                    <span class="sub-text text-12 text-danger" id="exchange-note-feedback"></span>
                                </div>

                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button"
                                class="custom-btn px-4"
                                id="saveExchangeRateBtn" onclick="saveExchangeRate(this);">
                                Save Rate
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editExchangeRateModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-md">
                    <div class="modal-content border-0 shadow br-10">

                        <div class="modal-header border-0 pb-0">
                            <div>
                                <h5 class="main-text fw-semibold mb-1">Edit Exchange Rate</h5>
                                <span class="sub-text text-13">Update rate, effective date and note</span>
                            </div>

                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <input type="hidden" id="editExchangeRateId">

                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="text-13 sub-text mb-2">Exchange Rate</label>
                                    <input type="number" class="form-control custom-input" id="editExchangeRate" step="0.000001">
                                    <span class="sub-text text-12 text-danger" id="edit-exchange-rate-feedback"></span>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="text-13 sub-text mb-2">Effective Date</label>
                                    <input type="date" class="form-control custom-input" id="editEffectiveDate">
                                    <span class="sub-text text-12 text-danger" id="edit-effective-date-feedback"></span>
                                </div>

                                <div class="col-12 mb-2">
                                    <label class="text-13 sub-text mb-2">Note</label>
                                    <textarea class="form-control custom-input" id="editExchangeNote" rows="3"></textarea>
                                    <span class="sub-text text-12 text-danger" id="edit-exchange-note-feedback"></span>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer border-0 pt-0">

                            <button type="button" class="custom-btn px-4" onclick="updateExchangeRate();">
                                Update Rate
                            </button>
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
?>