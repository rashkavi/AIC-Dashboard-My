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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            header("Location: ./intakes");
        } else {
            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes` INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intakes");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Intake Payment Plans - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    <style>
                        .accordion {
                            --bs-accordion-btn-color: var(--sub-topic);
                        }

                        .accordion-button::after {
                            order: -1;
                            margin-left: 0;
                            margin-right: 20px;
                            transform: rotate(0deg);
                        }

                        .accordion-button:not(.collapsed)::after {
                            transform: rotate(180deg);
                        }

                        .accordion-button {
                            justify-content: flex-start;
                        }

                        .accordion-icon {
                            transition: transform 0.3s ease, color 0.3s ease;
                        }
                    </style>
                </head>

                <body class="overflow-hidden">
                    <ul class="notifications"></ul>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "includes/side-menu.php";
                                load_side_menu::load("Course Overview");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("Course Overview");
                                        ?>
                                    </div>

                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-dashboards.php"; ?>
                                    </div>

                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0" id="accordionParent">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg">Intake Payment Plans</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programe-id=<?= $intakeData['programme_id'] ?>'><?= $intakeData["programme"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $intakeData["intake"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id)) {
                                                    ?>
                                                        <div class="col-12 col-md-2 mt-3 mt-lg-0">
                                                            <div class="row h-100 justify-content-end align-items-center">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="window.location.href='./add-intake-payment-plan?intake-id=<?= $id ?>'">Add Payment Plan</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>

                                            <?php
                                            $plans = Database::search("SELECT * FROM `intake_has_payment_plans`
                                            INNER JOIN `payment_plan` ON `intake_has_payment_plans`.`payment_plan_payment_plan_id` = `payment_plan_id`
                                            INNER JOIN `payment_category` ON `intake_has_payment_plans`.`payment_category_payment_category_id` = `payment_category_id` 
                                            INNER JOIN `currencies` ON `payment_category`.`currencies_currency_id` = `currencies`.`currency_id`
                                            WHERE `intakes_intake_id`='" . $id . "' ORDER BY `date_added` ASC");

                                            if ($plans->num_rows > 0) {
                                            ?>
                                                <div class="col-12 px-4 py-3 pe-4 g-0 component-theme mb-10">
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
                                                                                data-bs-target="#collapse-<?= $data['intake_has_payment_plan_id'] ?>"
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
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 13, $id)) {
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
                                                                                                                <div class="col-12 p-0 m-0 d-flex justify-content-end mt-2">
                                                                                                                    <button class="custom-btn col-6 col-lg-2" onclick="addNewCustomPaneltyArea(this, <?= $id ?>);">Add New</button>
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

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/fileChooser.js"></script>

                </body>

                </html>
<?php
            }
        }
    }
} ?>