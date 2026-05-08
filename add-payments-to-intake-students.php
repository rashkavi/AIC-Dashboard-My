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

            $intake = Database::search("SELECT * FROM `intakes` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./course-overview");
            } else {
                $intakeData = $intake->fetch_assoc();

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Add Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                    <link rel="stylesheet" href="./assets/css/custom-accordian.css?= Project::getVersion() ?>">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
                </head>

                <body class="overflow-hidden">
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
                                        include './includes/components/skeleton-header.php';
                                        include './includes/components/skeleton-add-edit.php'; ?>
                                    </div>
                                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0" id="accordionParent">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-8 col-lg-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg">Add Payments</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programmes?faculty-id=<?= $intakeData['faculties_faculty_id'] ?>'><?= $intakeData["faculty"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $intakeData["intake_id"] ?>"><?= $intakeData["intake"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./add-payment-to-intake-student?intake-id=<?= $intakeData["intake_id"] ?>">Payments</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Payment</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if ($sessionUserRole != null) {
                                                    ?>
                                                        <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3 h-100 align-items-center">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" onclick="addIntakeStudentsToPayments(this,<?= $id ?>)">Save Payments</button>
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
                                                            <span class="sub-topic">Select Paymnent Category and Plan</span>
                                                            <div class="col-12 col-md-3 d-flex flex-column">
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
                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <label class="text-14 main-text required" for="">Payment Plan</label>
                                                                <select id="payment-plan" class="form-select custom-select mt-2" onchange="loadPaymentType(),loadPaymentPlanHasStudents(<?= $id ?>)">
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
                                                                <button class="custom-btn col-6 col-lg-2" onclick="addNewPaymentArea();">Add New</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="container component-theme p-0 m-0" id="student-list-container">

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
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php

            }
        }
    }
} ?>