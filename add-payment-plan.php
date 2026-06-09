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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./users");
        } else {
            $id = $_GET['student-id'];
            $studentHasIntake = Database::search("SELECT
                            `faculty`,
                            `faculty_id`,
                            `programme`,
                            `programme_id`,
                            `intake`,
                            `intake_id`,
                            `students_has_intakes`.`student_id`,
                            `first_name`,
                            `last_name`,
                            `title`,
                            `students_has_intakes_id`
                        FROM `students_has_intakes`
                        INNER JOIN `students` 
                            ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                        INNER JOIN `login_details`
                            ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                        INNER JOIN `title` 
                            ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `intakes` 
                            ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                        INNER JOIN `programmes` 
                            ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                        INNER JOIN `faculties` 
                            ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                        WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'
                    ");

            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $studentHasIntakeData['intake_id']))) {
                    header("Location: ./intake-details?intake-id=" . $studentHasIntakeData['intake_id']);
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Payment Plan - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Add Payment Plan</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $studentHasIntakeData['faculty_id'] ?>'><?= $studentHasIntakeData['faculty']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $studentHasIntakeData['programme_id'] ?>'><?= $studentHasIntakeData['programme']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $studentHasIntakeData['intake_id'] ?>'><?= $studentHasIntakeData['intake']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="intake-student-payments?student-id=<?= $id ?>"><?php echo ucwords(strtolower($studentHasIntakeData['title'] . " " . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name'])); ?> - Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Payment Plan</a></li>
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
                                                                    <select id="payment-plan" class="form-select custom-select mt-2">
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
                                                                    <label class="text-14 main-text required" for="">Course Amount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0">
                                                                    <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Discount Type</label>
                                                                    <select id="discount-type" class="form-select custom-select mt-2" onchange="changeDiscountType(this)">
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
                                                                <div class="col-12 col-md-4 d-flex flex-column d-none" id="discount-area">
                                                                    <label class="text-14 main-text not-required" for="discount" id="discount-label">Discount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                                    <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 d-flex flex-column d-none" id="discount-note-area">
                                                                    <label class="text-14 main-text" for="discount-note" id="discount-note-label">Discount Note</label>
                                                                    <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                    <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 d-flex flex-column mt-4">
                                                                    <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                        <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                            <button class="custom-btn w-100" onclick="addStudentToPaymentPlan(this,<?= $studentHasIntakeData['students_has_intakes_id'] ?>);">Save Payment Plan</button>
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