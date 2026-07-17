<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        $haveAnyPermission = false;
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))) {
            header("Location: ./dashboard");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Payment Plans - Admin Dashboard | <?= Project::getBaseName() ?> </title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/translations.js?= Project::getVersion() ?>"></script>
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
                                    include "./includes/components/skeleton-courses.php"; ?>
                                </div>

                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-lg-6">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="payment-plans">Payment Plans</span>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="payment-plan-desc">Create and manage flexible payment plans for students.</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-lg-6 mt-3 mt-lg-0">
                                                    <div class="row gx-3 justify-content-end">
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 22) || UserPermissions::hasPermission('payments', 23)) {
                                                        ?>
                                                            <div class="col-12 col-md-4 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn col-12" onclick="location.href='add-new-payment-plan.php'">Add Payment Plan</button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        $query = "SELECT * FROM `intake_has_payment_plans`
                                            INNER JOIN `payment_plan` ON `intake_has_payment_plans`.`payment_plan_payment_plan_id` = `payment_plan_id`
                                            INNER JOIN `payment_category` ON `intake_has_payment_plans`.`payment_category_payment_category_id` = `payment_category_id` 
                                            INNER JOIN `currencies` ON `payment_category`.`currencies_currency_id` = `currency_id`
                                            INNER JOIN `intakes` ON `intake_has_payment_plans`.`intakes_intake_id` = `intakes`.`intake_id`
                                            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                            INNER JOIN `branches` ON `faculties`.`branches_branch_id` = `branches`.`branch_id`
                                             ORDER BY  `intake_has_payment_plans`.`date_added` DESC";
                                        $results = Database::search($query);

                                        if ($results->num_rows > 0) {
                                        ?>
                                            <div class="col-12 px-4 py-3 pe-4 g-0 component-theme mb-10">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-12 mb-4">
                                                        <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                <div class="d-flex custom-div align-items-center justify-content-center">

                                                                    <span class="text-14 dark-theme fw-medium p-0 m-0"><?php echo $results->num_rows; ?> <?php echo $results->num_rows > 1 ? "Payment Plans" : "Payment Plan"; ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                    <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                    <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handlePaymentPlansPagination('filter')">
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
                                                                            <div class="col-12 col-md-2 d-flex flex-column">
                                                                                <select name="" id="faculty-selector" class="form-select custom-select mt-2" onchange="loadProgrammes(),handlePaymentPlansPagination('filter');">
                                                                                    <option value="0" selected>- Select Faculty -</option>
                                                                                    <?php

                                                                                    $faculties = Database::search("SELECT * FROM `faculties`");
                                                                                    while ($data = $faculties->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-2 d-flex flex-column">
                                                                                <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes(),handlePaymentPlansPagination('filter');">
                                                                                    <option value="0" selected>- Select Programme -</option>
                                                                                </select>
                                                                                <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-2 d-flex flex-column">
                                                                                <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="handlePaymentPlansPagination('filter');">
                                                                                    <option value="0" selected>- Select Intake -</option>
                                                                                </select>
                                                                                <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                <label for="show-result-selector" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handlePaymentPlansPagination('filter')">
                                                                    <option selected value="10">10</option>
                                                                    <option value="25">25</option>
                                                                    <option value="50">50</option>
                                                                    <option value="100">100</option>
                                                                    <option value="250">250</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row p-0 m-0 g-0 align-content-center" id="accordionParent">

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
                                                    $plans = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

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
                                                                    <div class="row payment-plan-content align-items-center m-0 py-0 pe-4">
                                                                        <div class="col-8 col-xl-8"
                                                                            data-bs-toggle="collapse"
                                                                            role="button"
                                                                            data-bs-target="#collapse-<?= $data['intake_has_payment_plan_id'] ?>"
                                                                            aria-expanded="false">
                                                                           
                                                                            <p class="text-16 sub-text fw-bold m-0">
                                                                                Branch : <?= $data['branch'] ?>
                                                                            </p>
                                                                            <p class="text-16 sub-text fw-bold m-0">
                                                                                Faculty : <?= $data['faculty'] ?>
                                                                            </p>
                                                                            <p class="text-16 sub-text fw-bold m-0">
                                                                                Programme : <?= $data['programme'] ?>
                                                                            </p>
                                                                            <p class="text-16 sub-text fw-bold m-0">
                                                                                Intake : <?= $data['intake'] ?>
                                                                            </p>

                                                                            <p class="text-16 sub-text fw-bold m-0">
                                                                                <?= $data['payment_plan_name'] ?> - <?= $data['payment_category'] ?>
                                                                            </p>

                                                                            <span class="text-14 sub-text mt-1">Payment Plan : </span>
                                                                            <span class="text-14 sub-text mt-1 fw-medium">
                                                                                <?= $data['payment_plan'] ?> | <?= $data['code'] ?> <?= number_format($data['amount']) ?>
                                                                            </span>
                                                                        </div>

                                                                        <?php
                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments')) {
                                                                            $isValidPlan = Database::search("SELECT * FROM `payment_plan_has_students_has_intakes` WHERE `intake_has_payment_plan_id` = '" . $data['intake_has_payment_plan_id'] . "'");
                                                                            if ($isValidPlan->num_rows == 0) {
                                                                        ?>
                                                                                <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                    <button class="custom-danger-btn w-100" onclick="window.location.href='./edit-intake-payment-plan?plan-id=<?= $data['intake_has_payment_plan_id'] ?>'">Edit Payment Plan</button>
                                                                                </div>

                                                                                <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                    <button class="custom-danger-btn w-100" onclick="confirmDeletePlan(<?= $data['intake_has_payment_plan_id'] ?>)">Delete Payment Plan</button>
                                                                                </div>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                    <button class="custom-danger-btn w-100" data-bs-target="#warningModal" data-bs-toggle="modal">Edit Payment Plan</button>
                                                                                </div>

                                                                                <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                                                    <button class="custom-danger-btn w-100" data-bs-target="#warningModal" data-bs-toggle="modal">Delete Payment Plan</button>
                                                                                </div>
                                                                        <?php
                                                                            }
                                                                        } ?>
                                                                    </div>
                                                                </div>
                                                                <div class="overflow-x-auto p-0 mt-3 g-0 collapse" id="collapse-<?= $data['intake_has_payment_plan_id'] ?>" data-bs-target="#accordionParent" aria-expanded="false" aria-controls="accordionParent">
                                                                    <div class="p-4 g-0">
                                                                        <?php
                                                                        $planHasPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments`
                                                                            INNER JOIN `payment_type` ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id` = `payment_type`.`payment_type_id`
                                                                            WHERE `intake_has_payment_plan_id`='" . $data['intake_has_payment_plan_id'] . "'");
                                                                        if ($planHasPayments->num_rows > 0) {
                                                                            $planId = $data['intake_has_payment_plan_id'];
                                                                        ?>
                                                                            <div class="accordion" id="fees-type">
                                                                                <?php
                                                                                for ($x = 0; $x < $planHasPayments->num_rows; $x++) {
                                                                                    $data = $planHasPayments->fetch_assoc();
                                                                                    $itemId = "plan{$planId}_item{$data['intake_payments_plan_has_payment_id']}";
                                                                                ?>
                                                                                    <div class="accordion-item br-0" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
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
                                                                                                            <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="changenstallmentDiscountType(this)">
                                                                                                                <?php
                                                                                                                $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                                                                while ($discountType = $discountTypes->fetch_assoc()) {
                                                                                                                ?>
                                                                                                                    <option value="<?php echo $discountType['discount_type_id'] ?>" <?= $data['discount_type_discount_type_id'] == $discountType['discount_type_id'] ? 'selected' : '' ?> data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>"><?php echo $discountType['discount_type'] ?></option>
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
                                                                                                            <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 ? 'checked' : '' ?> data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="enablePanelty(this)">
                                                                                                            <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                                                        </div>

                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] == 1 ? 'd-none' : '' ?>" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" id="panelty-option-area">
                                                                                                            <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                                            <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="paneltyOptionChange(this)">
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
                                                                                                            <span id="panelty-option-feedback" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" class="text-dark-red text-12"></span>
                                                                                                        </div>

                                                                                                        <div class="col-12 <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 && $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? '' : 'd-none' ?>" id="panelty-flat-area" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                                                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                                                <?php
                                                                                                                $amountType = 0;
                                                                                                                $amount = 0;
                                                                                                                $panelties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` = '" . $data['intake_payments_plan_has_payment_id'] . "'");
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


                                                                                                        <div class="col-12 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? 'd-none' : '' ?>" id="panelty-custom-area" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                                                                            <div class="row p-0 m-0" id="custom-slots">

                                                                                                                <?php
                                                                                                                $panelties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` = '" . $data['intake_payments_plan_has_payment_id'] . "'");
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
                                                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                            <div class="row p-4 align-items-center">
                                                                <!-- Previous Button (col-1) -->
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handlePaymentPlansPagination('previousPage')">
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
                                                                            $action = $isActive ? '' : "onclick=\"handlePaymentPlansPagination('goToPage', '{$i}')\"";
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handlePaymentPlansPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                                                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                </button>
                                                            </div>
                                                        </nav>
                                                    </div>
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
                                    <div class="flex-grow-1"></div>
                                    <footer>
                                        <?php include './includes/footer.php' ?>
                                    </footer>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="removeIntakePaymentPlanConfirmModal" tabindex="-1"
                    aria-labelledby="removeIntakePaymentPlanConfirmModalTitle" aria-hidden="true">
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

                                <h5 class="fw-semibold mb-2" id="removeIntakePaymentPlanConfirmModalTitle">
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
                                                <li>Permanently remove the selected intake payment plan</li>
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
                                        id="confirmRemoveIntakePaymentPlan"
                                        class="btn btn-danger px-4 br-0">
                                        <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                        Remove Payment Plan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="warningModal" tabindex="-1"
                    aria-labelledby="warningModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">

                            <!-- Close -->
                            <div class="d-flex justify-content-end pe-3 pt-3">
                                <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>

                            <div class="modal-body text-center p-4">

                                <!-- Danger Icon -->
                                <div class="mb-3">
                                    <iconify-icon
                                        icon="mdi:alert-octagon-outline"
                                        width="50"
                                        height="50"
                                        class="text-danger">
                                    </iconify-icon>
                                </div>

                                <!-- Title -->
                                <h5 class="fw-semibold mb-2 text-danger" id="scheduleConfirmModalTitle">
                                    Restricted Action: Payment Plan In Use
                                </h5>

                                <!-- Main Message -->
                                <p class="text-muted mb-3">
                                    This <strong>Intake Payment Plan</strong> is currently assigned to one or more students.
                                </p>

                                <!-- Critical Warning Box -->
                                <div class="alert alert-danger text-start small mb-4">

                                    <div class="d-flex align-items-start gap-2">
                                        <iconify-icon icon="mdi:alert-circle" width="22"></iconify-icon>

                                        <div>
                                            <strong>Action Blocked for Data Integrity</strong>

                                            <ul class="mb-0 ps-3 mt-2">
                                                <li>This payment plan is actively linked to existing student records</li>
                                                <li>Editing or deleting this plan may cause <strong>payment inconsistencies</strong></li>
                                                <li>Existing invoices, payment histories, and balances may become <strong>invalid or corrupted</strong></li>
                                                <li>This could directly impact <strong>financial reporting and student account accuracy</strong></li>
                                            </ul>

                                            <div class="mt-3">
                                                ⚠️ <strong>This operation has been restricted to protect system data integrity.</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Actions -->
                                <div class="d-flex justify-content-center">
                                    <button type="button"
                                        class="btn btn-outline-secondary px-4 br-0"
                                        data-bs-dismiss="modal">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}

?>