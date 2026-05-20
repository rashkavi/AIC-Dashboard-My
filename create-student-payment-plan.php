<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/SlipVerifier.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['inquiry-id']) && !empty($_GET['inquiry-id']))) {
            header("Location: ./students");
        } else {
            $leadId = (int) $_GET["inquiry-id"];

            $lead = Database::search("
    SELECT 
                                                             `students`.`student_id` AS 'sId', 
                                                         
                                                             `login_details`.`first_name`, 
                                                             `login_details`.`last_name`, 
                                                             `login_details`.`email`, 
                                                         
                                                             `students`.`mobile`, 
                                                         
                                                             `login_details`.`whatsapp`, 
                                                             `students`.`profile_img`, 
                                                             `login_details`.`date_registered`, 
                                                         
                                                             `title`.`title`, 
                                                         
                                                             `lead_student_status`.`lead_student_status`,
                                                             `lead_student_status`.`lead_student_status_id`,
                                                         
                                                             `login_details`.`verification`,
                                                             `login_details`.`reset_time`,
                                                             `intakes_intake_id`
                                                                                                                                                                           

                                                         FROM `students`
                                                         
                                                         INNER JOIN `login_details`
                                                             ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                         
                                                         INNER JOIN `title`
                                                             ON `students`.`title_title_id` = `title`.`title_id`
                                                         
                                                         INNER JOIN `lead_student_status`
                                                             ON `students`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`
                                                         INNER JOIN `students_has_intakes`
                                                             ON `students`.`student_id` = `students_has_intakes`.`students_student_id`                                                      
                                                         WHERE `students`.`student_id` = '" . $leadId . "'
                                                         AND `lead_student_status_lead_student_status_id`='1'
                                                            GROUP BY `students`.`student_id`
                                                         ORDER BY `students`.`student_id` DESC
");

            if ($lead->num_rows == 0) {
                header("Location: ./inquiries");
                exit;
            }

            $lead = $lead->fetch_assoc();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Inquiries Registration Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Inquiries");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Inquiries");
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
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-xxl-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="">Create Student Payment Plan</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./inquiries-registration-payments">Inquiry Registration Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Create Student Payment Plan</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row align-items-center justify-content-end gx-3 h-100">

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 mt-3">
                                                            <label class="text-16 fw-semibold main-text mb-2 d-block required">
                                                                Payment Pan Type
                                                            </label>
                                                            <div class="d-flex gap-4 px-2 mt-4">
                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2"
                                                                        type="radio"
                                                                        name="paymentPlanType"
                                                                        id="intakePaymentPlan"
                                                                        value="intake"
                                                                        checked>
                                                                    <label class="form-check-label" for="intakePaymentPlan">
                                                                        Intake Payment Plan
                                                                    </label>
                                                                </div>

                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2"
                                                                        type="radio"
                                                                        name="paymentPlanType"
                                                                        id="customPaymentPlan"
                                                                        value="custom">
                                                                    <label class="form-check-label" for="customPaymentPlan">
                                                                        Custom Payment Plan
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 mt-3" id="intakes-payment-plan-arae">
                                                    <div class="row">
                                                        <?php
                                                        $plans = Database::search("SELECT * FROM `intake_has_payment_plans`
                                            INNER JOIN `payment_plan` ON `intake_has_payment_plans`.`payment_plan_payment_plan_id` = `payment_plan_id`
                                            INNER JOIN `payment_category` ON `intake_has_payment_plans`.`payment_category_payment_category_id` = `payment_category_id` 
                                            INNER JOIN `currencies` ON `payment_category`.`currencies_currency_id` = `currencies`.`currency_id`
                                            WHERE `intakes_intake_id`='" . $lead['intakes_intake_id'] . "' ORDER BY `date_added` ASC");

                                                        if ($plans->num_rows > 0) {
                                                        ?>
                                                            <div class="col-12 py-3 g-0 component-theme mb-10">
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
                                                                                    <div class="row payment-plan-content d-flex pe-4 py-0 align-items-center">

                                                                                        <div class="col-auto">
                                                                                            <input type="checkbox"
                                                                                                class="form-check-input hw-20 intake-plan-checkbox"
                                                                                                name="selected_intake_plan"
                                                                                                value="<?= $data['intake_has_payment_plan_id'] ?>"
                                                                                                data-plan-id="<?= $data['intake_has_payment_plan_id'] ?>"
                                                                                                onchange="selectIntakePaymentPlan(this)">
                                                                                        </div>

                                                                                        <div class="col"
                                                                                            data-bs-toggle="collapse"
                                                                                            role="button"
                                                                                            data-bs-target="#intake-plan-collapse-<?= $data['intake_has_payment_plan_id'] ?>">

                                                                                            <p class="text-16 dark-color fw-bold m-0">
                                                                                                <?= $data['payment_plan_name'] ?> - <?= $data['payment_category'] ?>
                                                                                            </p>

                                                                                            <span class="text-14 sub-text mt-1">Payment Plan : </span>
                                                                                            <span class="text-14 sub-text mt-1 fw-medium">
                                                                                                <?= $data['payment_plan'] ?> | <?= $data['code'] ?> <?= number_format($data['amount']) ?>
                                                                                            </span>
                                                                                        </div>

                                                                                    </div>
                                                                                </div>
                                                                                <div class="overflow-x-auto p-0 mt-3 g-0 collapse" id="intake-plan-collapse-<?= $data['intake_has_payment_plan_id'] ?>" data-bs-target="#accordionParent" aria-expanded="false" aria-controls="accordionParent">
                                                                                    <div class="p-4 g-0">
                                                                                        <?php
                                                                                        $planHasPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments`
                                                                                        INNER JOIN `payment_type` ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id` = `payment_type`.`payment_type_id`
                                                                                        WHERE `intake_has_payment_plan_id`='" . $data['intake_has_payment_plan_id'] . "'");
                                                                                        if ($planHasPayments->num_rows > 0) {
                                                                                            $planId = $data['intake_has_payment_plan_id'];
                                                                                        ?>
                                                                                            <div class="accordion" id="fees-type-intake-plan">
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
                                                                                                        <div id="collapse-<?= $itemId ?>" class="accordion-collapse collapse <?= $x == 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= $itemId ?>" data-bs-parent="#fees-type-intake-plan">
                                                                                                            <div class="accordion-body">

                                                                                                                <div class="col-12 component-theme">
                                                                                                                    <div class="row p-0 gy-3 gx-3 align-content-center <?= $data['payment_type_payment_type_id'] == 18 ? 'mark-paid-box' : '' ?>">

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
                                                                                                                            <input type="checkbox" disabled class="form-check-input hw-20 m-0 p-0" id="enable-panelty" <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 ? 'checked' : '' ?> data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="enablePanelty(this)">
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

                                                                                                                        <?php
                                                                                                                        if ((int)$data['payment_type_payment_type_id'] === 18) {
                                                                                                                        ?>
                                                                                                                            <div class="col-12 d-flex align-items-center">
                                                                                                                                <div class="form-check d-flex align-items-center">
                                                                                                                                    <input type="checkbox"
                                                                                                                                        class="form-check-input hw-20 me-3 intake-mark-paid-checkbox"
                                                                                                                                        data-payment-type-id="<?= $data['payment_type_payment_type_id'] ?>"
                                                                                                                                        data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>"
                                                                                                                                        onchange="toggleMarkPaidFields(this)">
                                                                                                                                    <label class="form-check-label fw-semibold main-text">
                                                                                                                                        Registration fee already paid
                                                                                                                                    </label>
                                                                                                                                </div>
                                                                                                                            </div>
                                                                                                                        <?php
                                                                                                                        }
                                                                                                                        ?>
                                                                                                                        <?php
                                                                                                                        if ($data['payment_type_payment_type_id'] == 18) {
                                                                                                                        ?>
                                                                                                                            <div class="row mt-3 d-none mark-paid-fields">
                                                                                                                                <div class="col-12 col-md-4">
                                                                                                                                    <label class="text-14 main-text required">Paid Amount</label>
                                                                                                                                    <input type="number" class="form-control custom-input mt-2 paid-amount"
                                                                                                                                        value="<?= $data['amount'] ?>">
                                                                                                                                        <span id="paid-amount-feedback" class="text-dark-red text-12"></span>
                                                                                                                                </div>
                                                                                                                                <div class="col-12 col-md-4">
                                                                                                                                    <label class="text-14 main-text required">Discount Amount</label>
                                                                                                                                    <input type="number" class="form-control custom-input mt-2 discount"
                                                                                                                                        value="0">
                                                                                                                                        <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                                                                </div>

                                                                                                                                <div class="col-12 col-md-4">
                                                                                                                                    <label class="text-14 main-text required">Paid Date</label>
                                                                                                                                    <input type="date" class="form-control custom-input mt-2 paid-date"
                                                                                                                                        value="<?= date('Y-m-d') ?>">
                                                                                                                                        <span id="paid-date-feedback" class="text-dark-red text-12"></span>
                                                                                                                                </div>

                                                                                                                                <div class="col-12 col-md-4">
                                                                                                                                    <label class="text-14 main-text required">Payment Method</label>
                                                                                                                                    <select class="form-select custom-select mt-2 paid-method">
                                                                                                                                        <?php
                                                                                                                                        $methods = Database::search("SELECT * FROM `payment_method`");
                                                                                                                                        while ($method = $methods->fetch_assoc()) {
                                                                                                                                        ?>
                                                                                                                                            <option value="<?= $method['payment_method_id'] ?>" <?= $method['payment_method_id'] == 1 ? 'selected' : '' ?>>
                                                                                                                                                <?= $method['payment_method'] ?>
                                                                                                                                            </option>
                                                                                                                                        <?php } ?>
                                                                                                                                    </select>
                                                                                                                                    <span id="method-feedback" class="text-dark-red text-12"></span>
                                                                                                                                </div>

                                                                                                                                <div class="col-12 mt-3">
                                                                                                                                    <label class="text-14 main-text">Payment Note</label>
                                                                                                                                    <textarea class="form-control custom-input mt-2 paid-note"
                                                                                                                                        rows="3"></textarea>
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

                                                <div class="col-12 mt-3 p-0 component-theme mb-4 d-none" id="custom-plan-area">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 g-0">
                                                            <div class="row gx-4 gy-3">
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Plan Name</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="plan-name" maxlength="50">
                                                                    <span id="plan-name-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Payment Category</label>
                                                                    <select id="payment-category" class="form-select custom-select mt-2">
                                                                        <option selected value="0">- Select -</option>
                                                                        <?php
                                                                        $paymentCategory = Database::search("SELECT * FROM `payment_category`");
                                                                        while ($paymentCategoryData = $paymentCategory->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentCategoryData['payment_category_id'] ?>"><?= $paymentCategoryData['payment_category'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="payment-category-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Payment Plan</label>
                                                                    <select id="payment-plan" class="form-select custom-select mt-2" onchange="loadPaymentType()">
                                                                        <option selected value="0">- Select -</option>
                                                                        <?php
                                                                        $paymentsPlan = Database::search("SELECT * FROM `payment_plan`");
                                                                        while ($paymentPlan = $paymentsPlan->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentPlan['payment_plan_id'] ?>"><?= $paymentPlan['payment_plan'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="payment-plan-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Total Amount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0">
                                                                    <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Discount Type</label>
                                                                    <select id="plan-discount-type" class="form-select custom-select mt-2" onclick="changeDiscountType(this)">
                                                                        <option selected value="0">Select Discount Type</option>
                                                                        <?php
                                                                        $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                        while ($discountType = $discountTypes->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?php echo $discountType['discount_type_id'] ?>"><?php echo $discountType['discount_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="plan-discount-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column d-none" id="discount-container">
                                                                    <label class="text-14 main-text" for="" id="discount-label">Discount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="plan-discount" min="0" value="0">
                                                                    <span id="plan-discount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 d-flex flex-column d-none" id="discount-note-container">
                                                                    <label class="text-14 main-text" for="">Discount Note</label>
                                                                    <textarea id="plan-discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                    <span id="plan-discount-note-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 d-flex align-items-center mt-2">
                                                                    <div class="form-check d-flex align-items-center m-0 p-0">
                                                                        <input
                                                                            type="checkbox"
                                                                            id="is-default"
                                                                            class="form-check-input hw-20 m-0 me-2">

                                                                        <label
                                                                            class="form-check-label text-14 main-text fw-medium cursor-pointer"
                                                                            for="is-default">
                                                                            Set as Default Payment Plan
                                                                        </label>
                                                                    </div>
                                                                </div>

                                                                <div class="accordion d-none" id="fees-type">
                                                                    <div class="accordion-item br-0" data-installment-id="1">
                                                                        <h2 class="accordion-header br-0" id="headingOne1">
                                                                            <button class="accordion-button br-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne1" aria-expanded="true" aria-controls="collapseOne1">
                                                                                <span class="flex-grow-1 payment-btn-text">Payment - 1</span>
                                                                            </button>
                                                                        </h2>
                                                                        <div id="collapseOne1" class="accordion-collapse collapse show" aria-labelledby="headingOne1" data-bs-parent="#fees-type">
                                                                            <div class="accordion-body">
                                                                                <div class="col-12 component-theme">
                                                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                            <label class="text-14 main-text required" for="">Payment Type</label>
                                                                                            <select id="payment-type" class="form-select custom-select mt-2" disabled>
                                                                                                <option selected value="0">- Select -</option>
                                                                                            </select>
                                                                                            <span id="payment-type-feedback" class="text-dark-red text-12"></span>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                            <label for="" class="text-14 main-text required">Amount</label>
                                                                                            <input type="number" class="form-control custom-input mt-2" id="amount">
                                                                                            <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                            <label class="text-14 main-text not-required" for="">Discount Type</label>
                                                                                            <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="1" onchange="changenstallmentDiscountType(this)">
                                                                                                <?php
                                                                                                $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                                                while ($discountType = $discountTypes->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?php echo $discountType['discount_type_id'] ?>"><?php echo $discountType['discount_type'] ?></option>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </select>
                                                                                            <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column d-none" id="discount-amount-area">
                                                                                            <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                                            <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                                                            <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                        </div>
                                                                                        <div class="col-12 d-flex flex-column d-none" id="discount-note-area">
                                                                                            <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                                            <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                                            <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                            <label class="text-14 main-text not-required" for="">Due Date</label>
                                                                                            <input type="date" class="form-control custom-input mt-2" id="due-date" min="0">
                                                                                            <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                                        </div>
                                                                                        <div class="col-12 d-flex align-items-center">
                                                                                            <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" data-installment-id="1" onchange="enablePanelty(this)">
                                                                                            <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                                        </div>

                                                                                        <div class="col-12 col-lg-4 d-flex flex-column d-none" data-installment-id="1" id="panelty-option-area">
                                                                                            <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                            <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="1" onchange="paneltyOptionChange(this)">
                                                                                                <option selected value="0">- Select -</option>
                                                                                                <?php
                                                                                                $options = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id` != 1");
                                                                                                while ($option = $options->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?php echo $option['panelty_payment_option_id'] ?>"><?php echo $option['panelty_payment_option'] ?></option>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </select>
                                                                                            <span id="panelty-option-feedback" data-installment-id="1" class="text-dark-red text-12"></span>
                                                                                        </div>

                                                                                        <div class="col-12 d-none" id="panelty-flat-area" data-installment-id="1">
                                                                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                    <label for="" class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                    <select class="form-select custom-select mt-2" id="flat-type">
                                                                                                        <option value="0">- Select -</option>
                                                                                                        <?php
                                                                                                        $types = Database::search("SELECT * FROM `panelty_payments_type`");
                                                                                                        while ($type = $types->fetch_assoc()) {
                                                                                                        ?>
                                                                                                            <option value="<?php echo $type['panelty_payments_type_id']; ?>"><?php echo $type['panelty_payments_type']; ?></option>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </select>
                                                                                                    <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                                                </div>
                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                    <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                                    <input type="number" class="form-control custom-input mt-2" id="flat-amount">
                                                                                                    <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <div class="col-12 d-flex flex-column d-none" id="panelty-custom-area" data-installment-id="1">
                                                                                            <div class="row p-0 m-0" id="custom-slots">

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
                                                                                            </div>
                                                                                            <div class="col-12 p-0 m-0 d-flex justify-content-end mt-3">
                                                                                                <button class="custom-btn col-6 col-lg-2" onclick="addNewCustomPaneltyArea(this, 1);">Add New</button>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 p-0 m-0 d-flex justify-content-end mt-3 d-none" id="add-new-payment-area">
                                                                    <button class="custom-btn col-6 col-lg-2" onclick="addNewPaymentArea();">Add New Payment</button>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g- d-none" id="save-plan-btn">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" onclick="handleStudentPaymentPlan(this,<?= $leadId ?>);">Save Payment Plan</button>
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
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>
            </body>

            </html>
<?php
        }
    }
}
?>