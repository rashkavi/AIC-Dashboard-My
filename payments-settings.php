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
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 22) || UserPermissions::hasPermission('payments', 23))) {
            header("Location: ./payments");
        } else {

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Payments Settings - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
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
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0" id="accordionParent">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Payments Settings</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payments Settings</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <!-- ── Tax Rate Cards ── -->
                                        <?php
                                        $taxRates = Database::search("
                                                SELECT tr.*, u.user_fname, u.user_lname, t.title
                                                FROM `tax_rates` tr
                                                LEFT JOIN `users` u ON tr.`users_user_id` = u.`user_id`
                                                LEFT JOIN `title` t ON u.`title_title_id` = t.`title_id`
                                                ORDER BY tr.`date_added` DESC LIMIT 1
                                            ");

                                        // Get the most recently added tax rate id for highlighting
                                        $latestTaxRateId = null;
                                        $allTaxRates = [];
                                        if ($taxRates && $taxRates->num_rows > 0) {
                                            while ($tr = $taxRates->fetch_assoc()) {
                                                $allTaxRates[] = $tr;
                                            }
                                            $latestTaxRateId = $allTaxRates[0]['tax_rate_id']; // first = most recent (ORDER BY date_added DESC)
                                        }
                                        ?>

                                        <?php if (!empty($allTaxRates)): ?>
                                            <?php foreach ($allTaxRates as $tr):
                                                $isLatest = ($tr['tax_rate_id'] == $latestTaxRateId);
                                            ?>
                                                <div class="col-12 mb-3">

                                                    <!-- ── Main Card Row ── -->
                                                    <div class="px-4 py-3 component-theme <?= $isLatest ? '' : '' ?>"
                                                        style="<?= $isLatest ? 'border-left: 4px solid var(--dark-theme);' : 'border-left: 4px solid var(--line-color);' ?>">

                                                        <div class="row align-items-center g-0">

                                                            <!-- Left: Big Rate Display -->
                                                            <div class="col-12 col-md-3 d-flex align-items-center gap-3 border-end pe-4" style="border-color: var(--line-color) !important;">
                                                                <div class="d-flex flex-column align-items-center justify-content-center w-100 py-2">
                                                                    <span class="fw-bold main-text" style="font-size: 38px; line-height: 1;">
                                                                        <?= number_format($tr['tax_rate'], 2) ?>
                                                                        <span class="text-20" style="font-size: 20px;">%</span>
                                                                    </span>
                                                                    <span class="text-12 sub-text mt-1">Tax Rate</span>
                                                                </div>
                                                            </div>

                                                            <!-- Middle: Name + Badge + Meta -->
                                                            <div class="col-12 col-md-6 px-4 py-2">

                                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                                    <span class="text-16 main-text fw-semibold"><?= htmlspecialchars($tr['tax_rate_name']) ?></span>
                                                                    <?php if ($isLatest): ?>
                                                                        <span class="text-dark-green py-1 px-2 d-flex align-items-center gap-1">
                                                                            <iconify-icon icon="mdi:check-circle-outline" width="20" style="vertical-align:-2px;"></iconify-icon>
                                                                            Current
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div class="d-flex flex-wrap gap-3 mt-2">
                                                                    <span class="text-12 sub-text">
                                                                        <iconify-icon icon="mdi:clock-outline" width="12" style="vertical-align:-2px;margin-right:3px;"></iconify-icon>
                                                                        Last update: <span class="main-text fw-medium"><?= date('Y-m-d H:i', strtotime($tr['date_added'])) ?></span>
                                                                    </span>
                                                                    <span class="text-12 sub-text">
                                                                        <iconify-icon icon="mdi:account-outline" width="12" style="vertical-align:-2px;margin-right:3px;"></iconify-icon>
                                                                        By: <span class="main-text fw-medium"><?= htmlspecialchars($tr['title'] . '. ' . $tr['user_fname'] . ' ' . $tr['user_lname']) ?></span>
                                                                    </span>
                                                                </div>

                                                            </div>

                                                            <!-- Right: Actions -->
                                                            <div class="col-12 col-md-3 mt-2 mt-md-0 d-flex flex-column flex-md-row justify-content-md-end align-items-md-center gap-2">

                                                                <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 1)): ?>
                                                                    <button class="table-btn table-btn-dark-theme h-48 w-50 text-17"
                                                                        onclick="openUpdateTaxModal(<?= $tr['tax_rate_id'] ?>, '<?= htmlspecialchars($tr['tax_rate_name'], ENT_QUOTES) ?>', <?= $tr['tax_rate'] ?>)">
                                                                        <iconify-icon icon="mdi:pencil-outline" width="20" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>
                                                                        Update
                                                                    </button>
                                                                <?php endif; ?>

                                                                <button class="table-btn table-btn-purple active h-48 w-50 text-17"
                                                                    onclick="toggleTaxHistory(<?= $tr['tax_rate_id'] ?>, this)">
                                                                    <iconify-icon icon="mdi:history" width="20" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>
                                                                    View History
                                                                </button>

                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- ── History Collapse Area ── -->
                                                    <div class="tax-history-area d-none" id="tax-history-<?= $tr['tax_rate_id'] ?>">
                                                        <div class="component-theme px-4 py-3" style="border-top: 1px solid var(--line-color);">
                                                            <p class="section-overline mb-3">Update History</p>
                                                            <div id="tax-history-content-<?= $tr['tax_rate_id'] ?>">
                                                                <?php
                                                                $logs = Database::search("
                                                                        SELECT trl.*, 
                                                                            CONCAT(t.title, '. ', u.user_fname, ' ', u.user_lname) AS updated_by
                                                                        FROM `tax_rates` trl
                                                                        LEFT JOIN `users` u ON trl.`users_user_id` = u.`user_id`
                                                                        LEFT JOIN `title` t ON u.`title_title_id`  = t.`title_id`
                                                                        
                                                                        ORDER BY trl.`date_added` DESC
                                                                    ");

                                                                if ($logs && $logs->num_rows > 0): ?>
                                                                    <div class="table-responsive">
                                                                        <table class="table bl-solid m-0">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Updated By</th>
                                                                                    <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Name</th>
                                                                                    <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Rate</th>
                                                                                    <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Date</th>
                                                                                    <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php while ($log = $logs->fetch_assoc()): ?>
                                                                                    <tr>
                                                                                        <td class="px-3 py-3 text-14 main-text"><?= htmlspecialchars($log['updated_by']) ?></td>
                                                                                        <td class="px-3 py-3 text-14 main-text"><?= htmlspecialchars($log['tax_rate_name']) ?></td>
                                                                                        <td class="px-3 py-3 text-14 main-text"><?= number_format($log['tax_rate'], 2) ?>%</td>
                                                                                        <td class="px-3 py-3 text-14 main-text"><?= $log['date_added'] ?></td>
                                                                                        <td class="px-3 py-3 text-14 sub-text"><?= htmlspecialchars($log['note'] ?? '-') ?></td>
                                                                                    </tr>
                                                                                <?php endwhile; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>

                                                                <?php else: ?>
                                                                    <div class="text-center py-4 sub-text text-14">
                                                                        <iconify-icon icon="mdi:history" width="28" style="color:var(--line-color);display:block;margin:0 auto 8px;"></iconify-icon>
                                                                        No history available.
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            <?php endforeach; ?>

                                        <?php else: ?>
                                            <div class="col-12 component-theme px-4 py-5 text-center mb-4">
                                                <iconify-icon icon="mdi:percent-outline" width="40" style="color:var(--line-color);display:block;margin:0 auto 10px;"></iconify-icon>
                                                <span class="text-14 sub-text">No tax rates found.</span>
                                            </div>
                                        <?php endif; ?>



                                        <div class="col-12 p-4 component-theme mb-4 mt-4">
                                            <div class="row p-0 m-0 g-0">
                                                <!-- Payment Groups Section -->
                                                <div class="col-12">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10">
                                                            <div class="row">
                                                                <h3 class="sub-topic">Payment Plans</h3>
                                                                <span class="sub-text text-12">Oversee and manage payment plan configurations, including installment structures, discounts, and penalties.</span>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 1)) {
                                                        ?>
                                                            <div class="col-12 col-md-2 mt-3 mt-lg-0">
                                                                <div class="row h-100 justify-content-end align-items-center">
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" onclick="window.location.href='./add-system-payment-plan'">Add Payment Group</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>

                                                </div>

                                                <?php
                                                $plans = Database::search("SELECT * FROM `system_payment_plans`
                                                            INNER JOIN `payment_plan` ON `system_payment_plans`.`payment_plan_payment_plan_id` = `payment_plan_id`
                                                            INNER JOIN `payment_category` ON `system_payment_plans`.`payment_category_payment_category_id` = `payment_category_id` 
                                                            INNER JOIN `currencies` ON `payment_category`.`currencies_currency_id` = `currencies`.`currency_id`
                                                            ORDER BY `date_added` ASC");

                                                if ($plans->num_rows > 0) {
                                                ?>
                                                    <div class="col-12 mb-10">
                                                        <div class="row p-0 m-0 g-0 align-content-center" id="accordionParent">
                                                            <?php
                                                            while ($data = $plans->fetch_assoc()) {
                                                            ?>
                                                                <div class="col-12 bl-solid py-3 m-0 g-0 mt-3">
                                                                    <div class="row p-0 g-0 m-0">
                                                                        <div class="col-12 position-relative">

                                                                            <?php
                                                                            if ($data['is_default_plan'] == 1) {
                                                                            ?>
                                                                                <span class="default-ribbon">Default</span>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                            <!-- Default Badge -->
                                                                            <div class="row payment-plan-content pe-4">
                                                                                <div class="col-8 col-xl-8"
                                                                                    data-bs-toggle="collapse"
                                                                                    role="button"
                                                                                    data-bs-target="#collapse-<?= $data['system_payment_plan_id'] ?>"
                                                                                    aria-expanded="false">

                                                                                    <p class="text-16 dark-color fw-bold m-0">
                                                                                        <?= $data['payment_plan_name'] ?> - <?= $data['payment_category'] ?>
                                                                                    </p>

                                                                                    <span class="text-14 sub-text mt-1">Payment Plan : </span>
                                                                                    <span class="text-14 sub-text mt-1 fw-medium">
                                                                                        <?= $data['payment_plan'] ?> | <?= $data['code'] ?> <?= number_format($data['amount']) ?>
                                                                                    </span>
                                                                                </div>

                                                                                <?php
                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 13)) {

                                                                                ?>
                                                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                        <button class="custom-danger-btn w-100" onclick="window.location.href='./edit-system-payment-plan?plan-id=<?= $data['system_payment_plan_id'] ?>'">Edit Payment Plan</button>
                                                                                    </div>

                                                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                        <button class="custom-danger-btn w-100" onclick="confirmDeleteSystemPlan(<?= $data['system_payment_plan_id'] ?>)">Delete Payment Plan</button>
                                                                                    </div>
                                                                                <?php
                                                                                } ?>
                                                                            </div>
                                                                        </div>
                                                                        <div class="overflow-x-auto p-0 mt-3 g-0 collapse" id="collapse-<?= $data['system_payment_plan_id'] ?>" data-bs-target="#accordionParent" aria-expanded="false" aria-controls="accordionParent">
                                                                            <div class="p-4 g-0">
                                                                                <?php
                                                                                $planHasPayments = Database::search("SELECT * FROM `system_payment_plan_has_payments`
                                                                            INNER JOIN `payment_type` ON `system_payment_plan_has_payments`.`payment_type_payment_type_id` = `payment_type`.`payment_type_id`
                                                                            WHERE `system_payment_plan_id`='" . $data['system_payment_plan_id'] . "'");
                                                                                if ($planHasPayments->num_rows > 0) {
                                                                                    $planId = $data['system_payment_plan_id'];
                                                                                ?>
                                                                                    <div class="accordion" id="fees-type">
                                                                                        <?php
                                                                                        for ($x = 0; $x < $planHasPayments->num_rows; $x++) {
                                                                                            $data = $planHasPayments->fetch_assoc();
                                                                                            $itemId = "plan{$planId}_item{$data['system_payment_plan_has_payment_id']}";
                                                                                        ?>
                                                                                            <div class="accordion-item br-0" data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>">
                                                                                                <h2 class="accordion-header br-0" id="heading-<?= $itemId ?>">
                                                                                                    <button class="accordion-button br-0 <?= $x != 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $itemId ?>" aria-expanded="<?= $x == 0 ? 'true' : 'false' ?>" aria-controls="collapse-<?= $itemId ?>">
                                                                                                        <?= $data['payment_type'] ?>
                                                                                                    </button>
                                                                                                </h2>
                                                                                                <div id="collapse-<?= $itemId ?>" class="accordion-collapse collapse <?= $x == 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= $itemId ?>" data-bs-parent="#fees-type">
                                                                                                    <div class="accordion-body">
                                                                                                        <div class="col-12 component-theme">
                                                                                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                    <label for="" class="text-14 main-text required">Amount</label>
                                                                                                                    <input type="number" class="form-control custom-input mt-2" id="amount" value="<?= $data['amount'] ?>">
                                                                                                                    <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                                                                                </div>
                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                    <label class="text-14 main-text required" for="">Discount Type</label>
                                                                                                                    <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>">
                                                                                                                        <?php
                                                                                                                        $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                                                                        while ($discountType = $discountTypes->fetch_assoc()) {
                                                                                                                        ?>
                                                                                                                            <option value="<?php echo $discountType['discount_type_id'] ?>" <?= $data['discount_type_discount_type_id'] == $discountType['discount_type_id'] ? 'selected' : '' ?> data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>"><?php echo $discountType['discount_type'] ?></option>
                                                                                                                        <?php
                                                                                                                        }
                                                                                                                        ?>
                                                                                                                    </select>
                                                                                                                    <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                                                                </div>
                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-amount-area">
                                                                                                                    <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                                                                    <input type="number" class="form-control custom-input mt-2" id="discount" min="0" value="<?= $data['discount'] ?>">
                                                                                                                    <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                                                </div>
                                                                                                                <div class="col-12 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-note-area">
                                                                                                                    <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                                                                    <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                                                                    <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                                                                </div>
                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                    <label class="text-14 main-text not-required" for="">Due Date</label>
                                                                                                                    <input type="date" class="form-control custom-input mt-2" id="due-date" min="0" value="<?= $data['due_date'] ?>">
                                                                                                                    <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                                                                </div>
                                                                                                                <div class="col-12 d-flex align-items-center">
                                                                                                                    <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 ? 'checked' : '' ?> data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>" onchange="enablePanelty(this)">
                                                                                                                    <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                                                                </div>

                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] == 1 ? 'd-none' : '' ?>" data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>" id="panelty-option-area">
                                                                                                                    <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                                                    <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>" onchange="paneltyOptionChange(this)">
                                                                                                                        <option selected value="0">- Select -</option>
                                                                                                                        <?php
                                                                                                                        $options = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id` != 1");
                                                                                                                        while ($option = $options->fetch_assoc()) {
                                                                                                                        ?>
                                                                                                                            <option value="<?php echo $option['panelty_payment_option_id'] ?>" <?= $data['panelty_payment_option_panelty_payment_option_id'] == $option['panelty_payment_option_id'] ? 'selected' : '' ?>><?php echo $option['panelty_payment_option'] ?></option>
                                                                                                                        <?php
                                                                                                                        }
                                                                                                                        ?>
                                                                                                                    </select>
                                                                                                                    <span id="panelty-option-feedback" data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>" class="text-dark-red text-12"></span>
                                                                                                                </div>

                                                                                                                <div class="col-12 <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 && $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? '' : 'd-none' ?>" id="panelty-flat-area" data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>">
                                                                                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                                                        <?php
                                                                                                                        $amountType = 0;
                                                                                                                        $amount = 0;
                                                                                                                        $panelties = Database::search("SELECT * FROM `system_payment_plan_has_penalties` WHERE `system_payment_plan_has_payment_id` = '" . $data['system_payment_plan_has_payment_id'] . "'");
                                                                                                                        if ($panelties->num_rows > 0) {
                                                                                                                            $paneltiesData = $panelties->fetch_assoc();
                                                                                                                            $amountType = $paneltiesData['panelty_payment_type_panelty_payment_type_id'];
                                                                                                                            $amount = $paneltiesData['amount'];
                                                                                                                        }
                                                                                                                        ?>
                                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                            <label for="" class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                                            <select class="form-select custom-select mt-2" id="flat-type">
                                                                                                                                <option value="0">- Select -</option>
                                                                                                                                <?php
                                                                                                                                $types = Database::search("SELECT * FROM `panelty_payments_type`");

                                                                                                                                while ($type = $types->fetch_assoc()) {
                                                                                                                                ?>
                                                                                                                                    <option value="<?php echo $type['panelty_payments_type_id']; ?>" <?= $amountType == $type['panelty_payments_type_id'] ? 'selected' : '' ?>><?php echo $type['panelty_payments_type']; ?></option>
                                                                                                                                <?php
                                                                                                                                }
                                                                                                                                ?>
                                                                                                                            </select>
                                                                                                                            <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                                                                        </div>
                                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                            <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                                                            <input type="number" class="form-control custom-input mt-2" id="flat-amount" value="<?= $amount ?>">
                                                                                                                            <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                                                                        </div>
                                                                                                                    </div>
                                                                                                                </div>


                                                                                                                <div class="col-12 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? 'd-none' : '' ?>" id="panelty-custom-area" data-installment-id="<?= $data['system_payment_plan_has_payment_id'] ?>">
                                                                                                                    <div class="row p-0 m-0" id="custom-slots">

                                                                                                                        <?php
                                                                                                                        $panelties = Database::search("SELECT * FROM `system_payment_plan_has_penalties` WHERE `system_payment_plan_has_payment_id` = '" . $data['system_payment_plan_has_payment_id'] . "'");
                                                                                                                        if ($panelties->num_rows > 0) {
                                                                                                                            for ($y = 0; $y < $panelties->num_rows; $y++) {
                                                                                                                                $panelty = $panelties->fetch_assoc();
                                                                                                                        ?>
                                                                                                                                <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                                                    <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                                                        <div class="d-flex justify-content-between">
                                                                                                                                            <span class="text-14 sub-topic">Custom Date Slot - <?= $y + 1 ?></span>
                                                                                                                                        </div>

                                                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                            <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                                                            <select class="form-select custom-select mt-2 panelty-type">
                                                                                                                                                <option value="0">- Select -</option>
                                                                                                                                                <option value="1" <?= $panelty['panelty_payment_type_panelty_payment_type_id'] == 1 ? 'selected' : '' ?>>Fixed</option>
                                                                                                                                                <option value="2" <?= $panelty['panelty_payment_type_panelty_payment_type_id'] == 2 ? 'selected' : '' ?>>Percentage</option>
                                                                                                                                            </select>
                                                                                                                                        </div>

                                                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                            <label class="text-14 main-text required">Amount</label>
                                                                                                                                            <input type="number" class="form-control custom-input mt-2 panelty-amount" value="<?= $panelty['amount'] ?>">
                                                                                                                                        </div>

                                                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                            <label class="text-14 main-text required">Days</label>
                                                                                                                                            <input type="number" class="form-control custom-input mt-2 panelty-days" value="<?= $panelty['days'] ?>">
                                                                                                                                        </div>
                                                                                                                                    </div>
                                                                                                                                </div>
                                                                                                                            <?php
                                                                                                                            }
                                                                                                                        } else {
                                                                                                                            ?>
                                                                                                                            <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                                                <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                                                    <div class="d-flex justify-content-between">
                                                                                                                                        <span class="text-14 sub-topic">Custom Date Slot - 1</span>
                                                                                                                                    </div>

                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                        <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                                                        <select class="form-select custom-select mt-2 panelty-type">
                                                                                                                                            <option value="0">- Select -</option>
                                                                                                                                            <option value="1">Fixed</option>
                                                                                                                                            <option value="2">Percentage</option>
                                                                                                                                        </select>
                                                                                                                                    </div>

                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                        <label class="text-14 main-text required">Amount</label>
                                                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-amount">
                                                                                                                                    </div>

                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                        <label class="text-14 main-text required">Days</label>
                                                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-days">
                                                                                                                                    </div>
                                                                                                                                </div>
                                                                                                                            </div>
                                                                                                                        <?php
                                                                                                                        }
                                                                                                                        ?>
                                                                                                                    </div>

                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </div>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </div>

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php
                                                } else {
                                                ?>
                                                    <div class="col-12 px-4 py-3 component-theme">
                                                        <div class="row align-items-center justify-content-center">
                                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
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

                <div class="modal fade" id="updateTaxModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content component-theme">
                            <div class="modal-header" style="border-bottom: 1px solid var(--line-color);">
                                <span class="text-16 main-text fw-semibold">Update Tax Rate</span>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" id="update-tax-id">
                                <div class="row gy-3">

                                    <div class="col-12 d-flex flex-column">
                                        <label class="text-14 main-text required">Tax Rate Name</label>
                                        <input type="text" class="form-control custom-input mt-2" id="update-tax-name">
                                        <span class="text-12 text-dark-red" id="update-tax-name-feedback"></span>
                                    </div>

                                    <div class="col-12 d-flex flex-column">
                                        <label class="text-14 main-text required">Tax Rate (%)</label>
                                        <input type="number" step="0.01" min="0" class="form-control custom-input mt-2" id="update-tax-rate">
                                        <span class="text-12 text-dark-red" id="update-tax-rate-feedback"></span>
                                    </div>

                                    <div class="col-12 d-flex flex-column">
                                        <label class="text-14 main-text">Note</label>
                                        <textarea class="form-control custom-input mt-2" id="update-tax-note" rows="3"></textarea>
                                    </div>

                                </div>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid var(--line-color);">
                                <button class="custom-btn px-4" onclick="submitUpdateTaxRate(this)">
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="removeSystemPaymentPlanConfirmModal" tabindex="-1"
                    aria-labelledby="removeSystemPaymentPlanConfirmModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">

                            <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                                <button type="button"
                                    class="btn-close text-end"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>

                            <div class="modal-body text-center p-4">

                                <div class="mb-3">
                                    <iconify-icon
                                        icon="bx:error-circle"
                                        width="48"
                                        height="48"
                                        class="text-danger">
                                    </iconify-icon>
                                </div>

                                <h5 class="fw-semibold mb-2" id="removeSystemPaymentPlanConfirmModalTitle">
                                    Confirm Intake Payment Plan Removal
                                </h5>

                                <p class="text-muted mb-3">
                                    You are about to remove this <strong>Intake Payment Plan</strong>.
                                </p>

                                <div class="alert alert-warning text-start small mb-4">
                                    <div class="d-flex align-items-start gap-2">
                                        <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                        <div>
                                            <strong>This action will:</strong>
                                            <ul class="mb-0 ps-3">
                                                <li>Permanently remove the selected system payment plan</li>
                                                <li>Remove related payment plan details if configured in the delete process</li>
                                                <li>This action <strong>cannot be undone</strong></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-center gap-3">
                                    <button type="button"
                                        class="btn btn-outline-secondary px-4 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>

                                    <button type="button"
                                        id="confirmRemoveSystemPaymentPlan"
                                        class="btn btn-danger px-4 br-0">
                                        <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                        Remove Payment Plan
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

            </body>

            </html>
<?php
        }
    }
}
?>