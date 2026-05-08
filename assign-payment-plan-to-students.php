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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            // header("Location: ./intakes");
        } else {
            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                // header("Location: ./course-overview");
            } else {

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                $intakeData = $intake->fetch_assoc();
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Add Payment Plan To Intake Students - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                        include './includes/components/skeleton-header.php';
                                        include './includes/components/skeleton-add-edit.php'; ?>
                                    </div>

                                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0" id="accordionParent">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-8 col-lg-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg">Add Payments Plan To Intake Students</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties?id=<?php echo $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $id ?>"><?= $intakeData["intake"] ?></a></li>

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
                                                                    <button class="custom-btn w-100" onclick="addStudentsToPaymentPlan(this,<?= $id ?>)">Save Payment Plan</button>
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
                                                                <label class="text-14 main-text required" for="">Payment Category</label>
                                                                <select id="payment-category" class="form-select custom-select mt-2" onchange="handleSelectedPaymentCategoryStudents(<?= $id ?>)">
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
                                                                <label class="text-14 main-text required" for="">Amount</label>
                                                                <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0">
                                                                <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                <label class="text-14 main-text required" for="">Discount Type</label>
                                                                <select id="discount-type" class="form-select custom-select mt-2" onclick="changeDiscountType(this)">
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
                                                                <label class="text-14 main-text required" for="" id="discount-label">Discount</label>
                                                                <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                                <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 d-flex flex-column d-none" id="discount-note-area">
                                                                <label class="text-14 main-text" for="">Discount Note</label>
                                                                <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 p-4 component-theme d-none" id="student-list-container">
                                                <div class="row p-0 gy-3  gx-3 align-content-center justify-content-end">
                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <?php
                                                            $studentCount = Database::search("
                                                                SELECT COUNT(DISTINCT `student_id`) AS unique_student_count 
                                                                FROM `students_has_intakes`
                                                                WHERE `intakes_intake_id` = '" . $id . "' 
                                                                AND `students_has_intakes_id` NOT IN (
                                                                    SELECT `students_has_intakes_students_has_intakes_id` 
                                                                    FROM `payment_plan_has_students_has_intakes`)
                                                                                                                                    
                                                            ");

                                                            $data = $studentCount->fetch_assoc();
                                                            ?>
                                                            <span class="text-14 dark-theme fw-medium"><?php echo $data["unique_student_count"]; ?> Students</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleStudentsPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button">Search</button>
                                                    </div>

                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentsPagination('filter')">
                                                            <option selected value="10">10</option>
                                                            <option value="25">25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                            <option value="250">250</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 mt-4">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="students-table-container">
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