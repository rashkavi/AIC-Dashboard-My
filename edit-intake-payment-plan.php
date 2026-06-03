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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['plan-id']) && !empty($_GET['plan-id']))) {
            header("Location: ./intakes");
        } else {
            $id = $_GET['plan-id'];
            $intake = Database::search("SELECT * FROM `intake_has_payment_plans` 
            INNER JOIN `intakes`  ON `intake_has_payment_plans`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` 
            WHERE `intake_has_payment_plan_id` = '" . $id . "' 
            AND `intake_has_payment_plan_id` NOT IN (SELECT `intake_has_payment_plan_id` FROM `payment_plan_has_students_has_intakes` WHERE `intake_has_payment_plan_id` = 3)");

            if ($intake->num_rows == 0) {
                // header("Location: ./intakes");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $intakeData['intake_id']))) {
                    header("Location: ./intakes");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Edit Intake Payment Plans - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
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

                    <body class="overflow-hidden" onload="initializeEditIntakePlan(<?= $intakeData['payment_plan_payment_plan_id'] ?>);">
                        <div class="notifications"></div>
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
                                                                <span class="main-topic lh-lg">Edit Intake Payment Plan</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programe-id=<?= $intakeData['programme_id'] ?>'><?= $intakeData["programme"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $intakeData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Intake Payment Plans - <?= $intakeData["payment_plan_name"] ?></a></li>
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
                                                                        <button class="custom-btn w-100" onclick="editIntakePaymentPlan(this, <?= $id ?>)">Save Payment Plan</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme mb-4">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 g-0">
                                                            <div class="row gx-4 gy-3">
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Plan Name</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="plan-name" maxlength="50" value="<?= $intakeData['payment_plan_name'] ?>">
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
                                                                            <option value="<?= $paymentCategoryData['payment_category_id'] ?>" <?php if ($intakeData['payment_category_payment_category_id'] == $paymentCategoryData['payment_category_id']) echo "selected" ?>><?= $paymentCategoryData['payment_category'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="payment-category-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Payment Plan</label>
                                                                    <select id="payment-plan" class="form-select custom-select mt-2" disabled onchange="loadPaymentType()">
                                                                        <option selected value="0">- Select -</option>
                                                                        <?php
                                                                        $paymentsPlan = Database::search("SELECT * FROM `payment_plan`");
                                                                        while ($paymentPlan = $paymentsPlan->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentPlan['payment_plan_id'] ?>" <?php if ($intakeData['payment_plan_payment_plan_id'] == $paymentPlan['payment_plan_id']) echo "selected" ?>><?= $paymentPlan['payment_plan'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="payment-plan-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Total Amount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0" value="<?= $intakeData['amount'] ?>">
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
                                                                            <option value="<?php echo $discountType['discount_type_id'] ?>" <?php if ($intakeData['discount_type_discount_type_id'] == $discountType['discount_type_id']) echo "selected" ?>><?php echo $discountType['discount_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="plan-discount-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column <?= $intakeData['discount_type_discount_type_id'] == 1 ? "d-none" : '' ?>" id="discount-container">
                                                                    <label class="text-14 main-text required" for="" id="discount-label">Discount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="plan-discount" min="0" value="<?= $intakeData['discount'] ?>">
                                                                    <span id="plan-discount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 d-flex flex-column <?= $intakeData['discount_type_discount_type_id'] == 1 ? "d-none" : '' ?>" id="discount-note-container">
                                                                    <label class="text-14 main-text required" for="">Discount Note</label>
                                                                    <textarea id="plan-discount-note" class="form-control custom-input mt-2" rows="5"><?= $intakeData['discount_note'] ?></textarea>
                                                                    <span id="plan-discount-note-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 d-flex align-items-center mt-2">
                                                                    <div class="form-check d-flex align-items-center m-0 p-0">
                                                                        <input
                                                                            type="checkbox"
                                                                            id="is-default"
                                                                            class="form-check-input hw-20 m-0 me-2" <?= $intakeData['is_default_plan'] == 1 ? "checked" : '' ?>>
                                                                        <label
                                                                            class="form-check-label text-14 main-text fw-medium cursor-pointer"
                                                                            for="is-default">
                                                                            Set as Default Payment Plan
                                                                        </label>
                                                                    </div>
                                                                </div>

                                                                <div class="accordion" id="fees-type">
                                                                    <?php
                                                                    $planHasPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments`
                                                                    INNER JOIN `payment_type` ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id` = `payment_type`.`payment_type_id`
                                                                    WHERE `intake_has_payment_plan_id`='" . $intakeData['intake_has_payment_plan_id'] . "'");

                                                                    for ($i = 0; $i < $planHasPayments->num_rows; $i++) {
                                                                        $data = $planHasPayments->fetch_assoc();
                                                                    ?>
                                                                        <div class="accordion-item br-0" data-installment-id="<?= $i + 1 ?>">
                                                                            <h2 class="accordion-header br-0" id="headingOne1">
                                                                                <button class="accordion-button br-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne<?= $i + 1 ?>" aria-expanded="<?= $i == 0 ? 'true' : 'false' ?>" aria-controls="collapseOne<?= $i + 1 ?>">
                                                                                    <span class="flex-grow-1 payment-btn-text">Payment - <?= $i + 1 ?></span>
                                                                                </button>
                                                                            </h2>
                                                                            <div id="collapseOne<?= $i + 1 ?>" class="accordion-collapse collapse <?= $i == 0 ? 'show' : '' ?>" aria-labelledby="headingOne<?= $i + 1 ?>" data-bs-parent="#fees-type">
                                                                                <div class="accordion-body">
                                                                                    <div class="col-12 component-theme">
                                                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label class="text-14 main-text required" for="">Payment Type</label>
                                                                                                <select id="payment-type" class="form-select custom-select mt-2">
                                                                                                    <?php
                                                                                                    $paymentType = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='" . $intakeData['payment_plan_payment_plan_id'] . "' OR `payment_plan_payment_plan_id` IS NULL");

                                                                                                    while ($paymentTypeData = $paymentType->fetch_assoc()) {

                                                                                                        echo $data['payment_type_id'] == $paymentTypeData['payment_type_id'] ? "selected" : '';
                                                                                                    ?>
                                                                                                        <option value="<?= $paymentTypeData['payment_type_id'] ?>" <?= $data['payment_type_id'] == $paymentTypeData['payment_type_id'] ? "selected" : '' ?>><?= $paymentTypeData['payment_type'] ?></option>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </select>
                                                                                                <span id="payment-type-feedback" class="text-dark-red text-12"></span>
                                                                                            </div>
                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label for="" class="text-14 main-text required">Amount</label>
                                                                                                <input type="number" class="form-control custom-input mt-2" id="amount" value="<?= $data['amount'] ?>">
                                                                                                <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                                                            </div>
                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label class="text-14 main-text not-required" for="">Discount Type</label>
                                                                                                <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="1" onchange="changenstallmentDiscountType(this)">
                                                                                                    <?php
                                                                                                    $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                                                    while ($discountType = $discountTypes->fetch_assoc()) {
                                                                                                    ?>
                                                                                                        <option value="<?php echo $discountType['discount_type_id'] ?>" <?php echo $data['discount_type_discount_type_id'] == $discountType['discount_type_id'] ? 'selected' : '' ?>><?php echo $discountType['discount_type'] ?></option>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </select>
                                                                                                <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                                            </div>
                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? "d-none" : '' ?>" id="discount-amount-area">
                                                                                                <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                                                <input type="number" class="form-control custom-input mt-2" id="discount" min="0" value="<?= $data['discount'] ?>">
                                                                                                <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                            </div>
                                                                                            <div class="col-12 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? "d-none" : '' ?>" id="discount-note-area">
                                                                                                <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                                                <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"><?= $data['discount_note'] ?></textarea>
                                                                                                <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                                            </div>
                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label class="text-14 main-text not-required" for="">Due Date</label>
                                                                                                <input type="date" class="form-control custom-input mt-2" id="due-date" min="0" value="<?= $data['due_date'] ?>">
                                                                                                <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                                            </div>
                                                                                            <div class="col-12 d-flex align-items-center">
                                                                                                <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 ? 'checked' : '' ?> data-installment-id="<?= $i + 1 ?>" onchange="enablePanelty(this)">
                                                                                                <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                                            </div>

                                                                                            <div class="col-12 col-lg-4 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] == 1 ? 'd-none' : '' ?>" data-installment-id="<?= $i + 1 ?>" id="panelty-option-area">
                                                                                                <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                                <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="<?= $i + 1 ?>" onchange="paneltyOptionChange(this)">
                                                                                                    <option selected value="0">- Select -</option>
                                                                                                    <?php
                                                                                                    $options = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id` != 1");
                                                                                                    while ($option = $options->fetch_assoc()) {
                                                                                                    ?>
                                                                                                        <option value="<?php echo $option['panelty_payment_option_id'] ?>" <?php echo $data['panelty_payment_option_panelty_payment_option_id'] == $option['panelty_payment_option_id'] ? 'selected' : '' ?>><?php echo $option['panelty_payment_option'] ?></option>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </select>
                                                                                                <span id="panelty-option-feedback" data-installment-id="<?= $i + 1 ?>" class="text-dark-red text-12"></span>
                                                                                            </div>
                                                                                            <?php
                                                                                            if ($data['panelty_payment_option_panelty_payment_option_id'] != 4 && $data['panelty_payment_option_panelty_payment_option_id'] != 1) {
                                                                                                $penalities = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id`='" . $data['intake_payments_plan_has_payment_id'] . "'");
                                                                                                $penalty = $penalities->fetch_assoc();
                                                                                            ?>

                                                                                                <div class="col-12" id="panelty-flat-area" data-installment-id="<?= $i + 1 ?>">
                                                                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                            <label for="" class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                            <select class="form-select custom-select mt-2" id="flat-type">
                                                                                                                <option value="0">- Select -</option>
                                                                                                                <?php
                                                                                                                $types = Database::search("SELECT * FROM `panelty_payments_type`");
                                                                                                                while ($type = $types->fetch_assoc()) {
                                                                                                                ?>
                                                                                                                    <option value="<?php echo $type['panelty_payments_type_id']; ?>" <?= $penalty['panelty_payment_type_panelty_payment_type_id'] == $type['panelty_payments_type_id'] ? 'selected' : '' ?>><?php echo $type['panelty_payments_type']; ?></option>
                                                                                                                <?php
                                                                                                                }
                                                                                                                ?>
                                                                                                            </select>
                                                                                                            <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                                                        </div>
                                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                            <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                                            <input type="number" class="form-control custom-input mt-2" id="flat-amount" value="<?= $penalty['amount'] ?>">
                                                                                                            <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <div class="col-12 d-none" id="panelty-flat-area" data-installment-id="<?= $i + 1 ?>">
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
                                                                                                <?php
                                                                                            }

                                                                                            if ($data['panelty_payment_option_panelty_payment_option_id'] == 4) {
                                                                                                $penalities = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id`='" . $data['intake_payments_plan_has_payment_id'] . "'");
                                                                                                for ($x = 0; $x < $penalities->num_rows; $x++) {
                                                                                                    $penalty = $penalities->fetch_assoc();

                                                                                                ?>
                                                                                                    <div class="col-12 d-flex flex-column d-none" id="panelty-custom-area" data-installment-id="<?= $i + 1 ?>">
                                                                                                        <div class="row p-0 m-0" id="custom-slots">

                                                                                                            <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                                <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                                    <div class="d-flex justify-content-between">
                                                                                                                        <span class="text-14 sub-topic">Custom Date Slot - <?= $x + 1 ?></span>

                                                                                                                    </div>

                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                        <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                                        <select class="form-select custom-select mt-2 panelty-type">
                                                                                                                            <option value="0">- Select -</option>
                                                                                                                            <option value="1" <?php echo $penalty['panelty_payment_type_panelty_payment_type_id'] == 1 ? 'selected' : '' ?>>Fixed</option>
                                                                                                                            <option value="2" <?php echo $penalty['panelty_payment_type_panelty_payment_type_id'] == 2 ? 'selected' : '' ?>>Percentage</option>
                                                                                                                        </select>
                                                                                                                    </div>

                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                        <label class="text-14 main-text required">Amount</label>
                                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-amount" value="<?= $penalty['amount'] ?>">
                                                                                                                    </div>

                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                        <label class="text-14 main-text required">Days</label>
                                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-days" value="<?= $penalty['days'] ?>">
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                        <div class="col-12 p-0 m-0 d-flex justify-content-end mt-3">
                                                                                                            <button class="custom-btn col-6 col-lg-2" onclick="addNewCustomPaneltyArea(this, 1);">Add New</button>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                <?php
                                                                                                }
                                                                                            } else {
                                                                                                ?>
                                                                                                <div class="col-12 d-flex flex-column d-none" id="panelty-custom-area" data-installment-id="<?= $i + 1 ?>">
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
                                                                $planHasIntallmnts = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='" . $intakeData['payment_plan_payment_plan_id'] . "' OR `payment_plan_payment_plan_id` IS NULL");

                                                                if ($planHasPayments->num_rows < $planHasIntallmnts->num_rows) {
                                                                ?>
                                                                    <div class="col-12 p-0 m-0 d-flex justify-content-end mt-3" id="add-new-payment-area">
                                                                        <button class="custom-btn col-6 col-lg-2" onclick="addNewPaymentArea();">Add New Payment</button>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>


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

                        <div class="modal fade" id="scheduleConfirmModal" tabindex="-1"
                            aria-labelledby="scheduleConfirmModalTitle" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">

                                    <div class="d-flex justify-content-end pe-3 pt-3">
                                        <button type="button"
                                            class="btn-close"
                                            data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body text-center p-4">

                                        <div class="mb-3">
                                            <iconify-icon
                                                icon="mdi:alert-circle-outline"
                                                width="48"
                                                height="48"
                                                class="text-warning">
                                            </iconify-icon>
                                        </div>

                                        <h5 class="fw-semibold mb-2" id="scheduleConfirmModalTitle">
                                            Apply Default Payment Plan
                                        </h5>

                                        <p class="text-muted mb-3" id="scheduleConfirmModalMessage">
                                            This intake already has students assigned.
                                        </p>

                                        <div class="alert alert-warning text-start small mb-4" id="scheduleConfirmDetails">
                                            Please confirm whether you want to continue applying the default payment plan has base payments to existing intake students.
                                        </div>

                                        <div class="d-flex justify-content-center gap-3">
                                            <button type="button" id="scheduleConfirmModalCancel"
                                                class="btn btn-outline-secondary px-4 br-0"
                                                data-bs-dismiss="modal">
                                                Cancel
                                            </button>

                                            <button type="button"
                                                id="confirmScheduleSave"
                                                class="btn btn-danger px-4 br-0">
                                                <iconify-icon icon="mdi:check-circle-outline" class="me-1"></iconify-icon>
                                                Continue Anyway
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
    }
} ?>