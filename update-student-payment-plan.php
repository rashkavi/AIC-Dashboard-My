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
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-paln-id']) && !empty($_GET['payment-paln-id']))) {
            header("Location: ./users");
        } else {

            $id = $_GET['payment-paln-id'];

            $studentPaymentPlan = Database::search("SELECT
                            `pshi`.`payment_plan_has_students_has_intakes_id` AS `pphsiId`,
                            `shi`.`students_has_intakes_id` AS `shiId`,
                            `shi`.`intakes_intake_id`,
                            `faculties`.`faculty`,
                            `faculties`.`faculty_id`,
                            `intakes`.`intake`,
                            `intakes`.`intake_id`,
                            `login_details`.`first_name`,
                            `login_details`.`last_name`,
                            `title`.`title`,
                            `payment_category_payment_category_id`,
                            `discount_type_discount_type_id`,
                            `amount`,
                            `discount`,
                            `discount_note`,
                            `payment_plan_payment_plan_id`
                        FROM `payment_plan_has_students_has_intakes` AS `pshi`
                        INNER JOIN `students_has_intakes` AS `shi` 
                            ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                        INNER JOIN `students` 
                            ON `shi`.`students_student_id` = `students`.`student_id`
                        INNER JOIN `login_details`
                            ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                        INNER JOIN `title` 
                            ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `intakes` 
                            ON `shi`.`intakes_intake_id` = `intakes`.`intake_id`
                        INNER JOIN `programmes` 
                            ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                        INNER JOIN `faculties` 
                            ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`    
                        WHERE 
                            `pshi`.`payment_plan_has_students_has_intakes_id` = '" . $id . "'    
                        GROUP BY 
                            `pshi`.`payment_plan_has_students_has_intakes_id`
                        ORDER BY 
                            `pshi`.`payment_plan_has_students_has_intakes_id` ASC
                    ");

            if ($studentPaymentPlan->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $studentPaymentPlanData = $studentPaymentPlan->fetch_assoc();
                require_once "./includes/UserPermissionsClass.php";
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $studentPaymentPlanData['intake_id']))) {
                    header("Location: ./intake-details?intake-id=" . $studentPaymentPlanData['intake_id']);
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Update Payment Plan - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0  vh-100">
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
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Add Payment Plan</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $studentPaymentPlanData['intake_id'] ?>'><?= $studentPaymentPlanData['intake']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo ucwords(strtolower($studentPaymentPlanData['title'] . " " .  $studentPaymentPlanData['first_name'] . " " .  $studentPaymentPlanData['last_name'])); ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-student-payments?student-id=<?= $studentPaymentPlanData['shiId'] ?>">Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Update Payment Plan</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 g-0">
                                                            <div class="row gx-4 gy-3">
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Payment Category</label>
                                                                    <select id="payment-category" class="form-select custom-select mt-2">
                                                                        <option selected value="0">Select Payment Category</option>
                                                                        <?php
                                                                        $paymentCategory = Database::search("SELECT * FROM `payment_category`");
                                                                        while ($paymentCategoryData = $paymentCategory->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentCategoryData['payment_category_id'] ?>" <?= $paymentCategoryData['payment_category_id'] == $studentPaymentPlanData['payment_category_payment_category_id'] ? "selected" : "" ?>><?= $paymentCategoryData['payment_category'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="payment-category-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Payment Plan</label>
                                                                    <select id="payment-plan" class="form-select custom-select mt-2">
                                                                        <option selected value="0">Select Payment Plan</option>
                                                                        <?php
                                                                        $paymentsPlan = Database::search("SELECT * FROM `payment_plan`");
                                                                        while ($paymentPlan = $paymentsPlan->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentPlan['payment_plan_id'] ?>" <?= $paymentPlan['payment_plan_id'] == $studentPaymentPlanData['payment_plan_payment_plan_id'] ? "selected" : "" ?>><?= $paymentPlan['payment_plan'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="payment-plan-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Amount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0" value="<?= $studentPaymentPlanData['amount'] ?>">
                                                                    <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Discount Type</label>
                                                                    <select id="discount-type" class="form-select custom-select mt-2" onchange="changeDiscountType(this)">
                                                                        <option selected value="0">Select Discount Type</option>
                                                                        <?php
                                                                        $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                        while ($discountType = $discountTypes->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?php echo $discountType['discount_type_id'] ?>" <?php echo $discountType['discount_type_id'] == $studentPaymentPlanData['discount_type_discount_type_id'] ? "selected" : "" ?>><?php echo $discountType['discount_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column <?=$studentPaymentPlanData['discount_type_discount_type_id'] == 1 ? "d-none" : ""  ?>" id="discount-container">
                                                                    <label class="text-14 main-text required" for="">Discount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="discount" min="0" value="<?= $studentPaymentPlanData['discount'] ?>">
                                                                    <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 d-flex flex-column <?=$studentPaymentPlanData['discount_type_discount_type_id'] == 1 ? "d-none" : ""  ?>" id="discount-note-container">
                                                                    <label class="text-14 main-text" for="">Discount Note</label>
                                                                    <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"><?= $studentPaymentPlanData['discount_note'] ?></textarea>
                                                                    <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 d-flex flex-column mt-4">
                                                                    <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                        <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                            <button class="custom-btn w-100" onclick="editStudentPaymentPlan(<?= $id ?>);">Save Payment Plan</button>
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