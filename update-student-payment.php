<?php
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
            header("Location: ./faculties");
        } else {

            $id = $_GET['payment-id'];

            $paymentPlan = Database::search("SELECT
                    `cp`.`course_payments_id`,
                     `cp`.`discount_note`, 
                    `ps`.`payment_status`,
                    `ps`.`payment_status_id`,
                    `cp`.`amount`,
                    `cp`.`discount`,
                    `dt`.`discount_type`,
                    `dt`.`discount_type_id`, 
                  
                    `pt`.`payment_type`,
                    `pt`.`payment_type_id`,
                  
                    `cp`.`payment_plan_has_students_has_intakes_id`,
                    `cp`.`panelty_payment_option_panelty_payment_option_id`,
                    `cp`.`refference`,
                   `cp`.`due_date`,                   
                    `i`.`intake_id`,
                    `p`.`programme_id`,
                    `f`.`faculty_id`,
                    `i`.`intake`,
                    `p`.`programme`,
                    `f`.`faculty`,
                    `t`.`title`,
                    `ld`.`first_name`,
                    `ld`.`last_name`,
                    `shi`.`students_has_intakes_id` AS `shiId`

                FROM `course_payments` AS `cp`
                INNER JOIN `payment_status` AS `ps` 
                    ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                INNER JOIN `payment_type` AS `pt` 
                    ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
               
                INNER JOIN `discount_type` AS `dt` 
                    ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
                INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi` 
                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                INNER JOIN `students_has_intakes` AS `shi` 
                    ON `pphsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                INNER JOIN `students` AS `s` 
                    ON `shi`.`students_student_id` = `s`.`student_id`
                INNER JOIN `login_details` AS `ld`
                    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                INNER JOIN `title` AS `t` 
                    ON `s`.`title_title_id` = `t`.`title_id`
                INNER JOIN `intakes` AS `i` 
                    ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                INNER JOIN `programmes` AS `p` 
                    ON `i`.`programmes_programme_id` = `p`.`programme_id`
                INNER JOIN `faculties` AS `f` 
                    ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
                WHERE `cp`.`course_payments_id` = '" . $id . "';
                ");

            if ($paymentPlan->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $paymentPlanData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $paymentPlanData['intake_id']))) {
                    header("Location: ./faculties");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Update Student Payment- Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0" id="accordionParent">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Payment : # <?= $paymentPlanData['course_payments_id'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./faculties">Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="programmes?faculty-id=<?= $paymentPlanData['faculty_id'] ?>"><?php echo $paymentPlanData["faculty"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="intakes?programme-id=<?= $paymentPlanData['programme_id'] ?>">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="intake-details?intake-id=<?= $paymentPlanData['intake_id'] ?>"><?php echo $paymentPlanData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="intake-student-payments?student-id=<?= $paymentPlanData['shiId'] ?>"><?php echo ucwords(strtolower($paymentPlanData['title'] . " " . $paymentPlanData['first_name'] . " " . $paymentPlanData['last_name'])); ?> - Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payment</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" onclick="updateStudentPayment(this,<?= $id ?>)">Save Changes</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme mb-4">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 g-0">
                                                            <div class="row gx-4 gy-3">
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Payment Type</label>
                                                                    <select id="payment-type" class="form-select custom-select mt-2">
                                                                        <option selected value="0">Payment Type</option>
                                                                        <?php
                                                                        $paymentTypes = Database::search("SELECT * FROM `payment_type`");
                                                                        while ($paymentType = $paymentTypes->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?php echo $paymentType['payment_type_id'] ?>" <?= $paymentPlanData['payment_type_id'] == $paymentType['payment_type_id'] ? 'selected' : '' ?>><?php echo $paymentType['payment_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="pyment-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Amount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0" value="<?= $paymentPlanData['amount'] ?>">
                                                                    <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Discount Type</label>
                                                                    <select id="discount-type" class="form-select custom-select mt-2" onchange="changeDiscountType(this)">
                                                                        <option selected value="0">Select Discount Type</option>
                                                                        <?php
                                                                        $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                        while ($discountType = $discountTypes->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?php echo $discountType['discount_type_id'] ?>" <?= $paymentPlanData['discount_type_id'] == $discountType['discount_type_id'] ? 'selected' : '' ?>><?php echo $discountType['discount_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column <?= $paymentPlanData['discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-area">
                                                                    <label class="text-14 main-text required" for="">Discount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="discount" min="0" value="<?= $paymentPlanData['discount'] ?>">
                                                                    <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-lg-10 d-flex flex-column <?= $paymentPlanData['discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-note-area">
                                                                    <label class="text-14 main-text" for="">Discount Note</label>
                                                                    <textarea id="discount-note" class="form-control custom-input mt-2" rows="5" value="<?= $paymentPlanData['discount_note'] ?>"></textarea>
                                                                    <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Due Date</label>
                                                                    <input type="date" class="form-control custom-input mt-2" id="due-date" min="0" value="<?= $paymentPlanData['due_date'] ?>">
                                                                    <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 d-flex align-items-center">
                                                                    <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty"
                                                                        <?= (!empty($paymentPlanData['due_date']) && strtotime($paymentPlanData['due_date']) < time()) ? 'disabled' : '' ?>
                                                                        <?= $paymentPlanData['panelty_payment_option_panelty_payment_option_id'] != 1 ? 'checked' : '' ?>
                                                                        data-installment-id="<?= $paymentPlanData['course_payments_id'] ?>"
                                                                        onchange="enablePanelty(this)">
                                                                    <label class="text-14 main-text px-3" for="">Enable Penalty Payment</label>
                                                                </div>

                                                                <div class="col-12 col-lg-4 d-flex flex-column <?= $paymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 1 ? 'd-none' : '' ?>" data-installment-id="<?= $paymentPlanData['course_payments_id'] ?>" id="panelty-option-area">
                                                                    <label class="text-14 main-text required" for="">Penalty Options</label>
                                                                    <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="<?= $paymentPlanData['course_payments_id'] ?>" onchange="paneltyOptionChange(this)">
                                                                        <option selected value="0">- Select -</option>
                                                                        <?php
                                                                        $options = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id` != 1");
                                                                        while ($option = $options->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?php echo $option['panelty_payment_option_id'] ?>" <?= $paymentPlanData['panelty_payment_option_panelty_payment_option_id'] == $option['panelty_payment_option_id'] ? 'selected' : '' ?>><?php echo $option['panelty_payment_option'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="panelty-option-feedback" data-installment-id="<?= $paymentPlanData['course_payments_id'] ?>" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 
                                                                <?= $paymentPlanData['panelty_payment_option_panelty_payment_option_id'] != 1 && $paymentPlanData['panelty_payment_option_panelty_payment_option_id'] != 4 ? '' : 'd-none' ?>"
                                                                    data-installment-id="<?= $paymentPlanData['course_payments_id'] ?>"
                                                                    id="panelty-flat-area">
                                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                        <?php
                                                                        $amountType = 0;
                                                                        $amount = 0;
                                                                        if ($paymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 2 || $paymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 3) {
                                                                            $panelties = Database::search("SELECT * FROM `course_payments_penalties` WHERE `course_payments_course_payments_id` = '" . $paymentPlanData['course_payments_id'] . "'");
                                                                            if ($panelties->num_rows > 0) {
                                                                                $paneltiesData = $panelties->fetch_assoc();
                                                                                $amountType = $paneltiesData['panelty_payment_type_panelty_payment_type_id'];
                                                                                $amount = $paneltiesData['amount'];
                                                                            }
                                                                        }

                                                                        ?>
                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                            <label for="" class="text-14 main-text required">Penalty Amount Type</label>
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


                                                                <div class="col-12 d-flex flex-column 
                                                                <?= $paymentPlanData['panelty_payment_option_panelty_payment_option_id'] != 4 ? 'd-none' : '' ?>"
                                                                    data-installment-id="<?= $paymentPlanData['course_payments_id'] ?>"
                                                                    id="panelty-custom-area">
                                                                    <div class="row p-0 m-0" id="custom-slots">

                                                                        <?php
                                                                        if ($paymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 4) {
                                                                            $panelties = Database::search("SELECT * FROM `course_payments_penalties` WHERE `course_payments_course_payments_id` = '" . $paymentPlanData['course_payments_id'] . "'");

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
                                                                                                <label class="text-14 main-text required">Penalty Amount Type</label>
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
                                                                            }
                                                                        } else {
                                                                            ?>
                                                                            <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span class="text-14 sub-topic">Custom Date Slot - 1</span>
                                                                                    </div>

                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text required">Penalty Amount Type</label>
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

                                                                <div class="col-12 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Payment Note</label>
                                                                    <textarea id="payment-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                    <span id="payment-note-feedback" class="text-dark-red text-12"></span>
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
    }
}
?>