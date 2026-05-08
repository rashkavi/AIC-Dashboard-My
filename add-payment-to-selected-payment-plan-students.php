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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']) && isset($_GET['category-id']) && !empty($_GET['category-id']) && isset($_GET['plan-id']) && !empty($_GET['plan-id']))) {
            header("Location: ./intakes");
        } else {

            $id = $_GET['intake-id'];
            $categoryId = $_GET['category-id'];
            $planId = $_GET['plan-id'];
            $intake = Database::search("SELECT * FROM `intakes` INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./course-overview");
            } else {
                $intakeData = $intake->fetch_assoc();
                $paymentCategory = Database::search("SELECT * FROM `payment_category` WHERE `payment_category_id` = '" . $categoryId . "'");
                $paymentPlan = Database::search("SELECT * FROM `payment_plan` WHERE `payment_plan_id` = '" . $planId . "'");

                if ($paymentCategory->num_rows == 0 || $paymentPlan->num_rows == 0) {
                    header("Location: ./add-payment-to-intake-students?intake-id=" . $id);
                } else {
                    $paymentCategory = $paymentCategory->fetch_assoc();
                    $paymentPlan = $paymentPlan->fetch_assoc();
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
                                                                        <button class="custom-btn col-12" onclick="addStudentsToPayment(<?= $id ?>,<?= $categoryId ?>,<?= $planId ?>)">Add</button>
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
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Payment Type</label>
                                                                    <select id="payment-type" class="form-select custom-select mt-2">
                                                                        <option selected value="0">- Select -</option>
                                                                        <?php
                                                                        $paymentTypes = Database::search("SELECT * FROM `payment_type`");
                                                                        while ($paymentType = $paymentTypes->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentType['payment_type_id'] ?>"><?= $paymentType['payment_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="payment-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Amount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0">
                                                                    <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Discount Type</label>
                                                                    <select id="discount-type" class="form-select custom-select mt-2">
                                                                        <option selected value="0">- Select -</option>
                                                                        <?php
                                                                        $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                        while ($discountType = $discountTypes->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $discountType['discount_type_id'] ?>"><?= $discountType['discount_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text not-required" for="">Discount</label>
                                                                    <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                                    <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-lg-10 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Discount Note</label>
                                                                    <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                    <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-lg-2 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Enable Card Payment</label>
                                                                    <div class="form-check form-switch mt-3">
                                                                        <input class="form-check-input" type="checkbox" role="switch" id="card-payament-enable">
                                                                    </div>
                                                                    <span class="text-12 sub-text">Card payment is currently disabled by default. To enable card payments, please turn on the switch.</span>
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

                                                <div class="col-12 px-4 py-4 component-theme">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $studentCount = Database::search("
                                                                SELECT COUNT(DISTINCT `student_id`) AS unique_student_count 
                                                                FROM `students_has_intakes`
                                                                WHERE `intakes_intake_id` = '" . $id . "' 
                                                                AND `students_has_intakes_id` IN (
                                                                    SELECT `students_has_intakes_students_has_intakes_id` 
                                                                    FROM `payment_plan_has_students_has_intakes` 
                                                                    WHERE `payment_category_payment_category_id` = '" . $categoryId . "'
                                                                    AND `payment_plan_payment_plan_id` = '" . $planId . "'
                                                                )
                                                            ");

                                                                $data = $studentCount->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?= $data["unique_student_count"]; ?> Students</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button">Search</button>
                                                        </div>

                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 px-4 pb-4 component-theme">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="overflow-x-auto bg-table-header p-0 m-0 g-0" id="students-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Discount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Setteled Amount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Pending Amount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Due Amount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Last Payment Month</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Last Payment Status</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php
                                                                    $query = "
                                                                    SELECT 
                                                                `pphsi`.`payment_plan_has_students_has_intakes_id` AS `pphshiId`, 
                                                                `shi`.`students_has_intakes_id` AS `shiId`, 
                                                                `shi`.`student_id`,
                                                                `ld`.`first_name`, 
                                                                `ld`.`last_name`, 
                                                                `s`.`profile_img`, 
                                                                `t`.`title`,
                                                                `pphsi`.`amount`, 
                                                                `pphsi`.`discount`,
                                                                IFNULL(SUM(`cp`.`discount`), 0) AS `total_paid_discount`,
                                                                IFNULL(SUM(CASE WHEN `cp`.`payment_status_payment_status_id` BETWEEN 1 AND 7 THEN `cp`.`amount` ELSE 0 END), 0) AS `pending_payments`,
                                                                IFNULL(SUM(CASE WHEN `cp`.`payment_status_payment_status_id` = 8 THEN `cp`.`amount` ELSE 0 END), 0) AS `approve_payments`,
                                                                                                                                
                                                                IFNULL((
                                                                    SELECT MONTHNAME(`cp2`.`date_paid`) 
                                                                    FROM `course_payments` AS `cp2`
                                                                    WHERE `cp2`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                                                    ORDER BY `cp2`.`date_paid` DESC LIMIT 1
                                                                ), '-') AS `last_payment_month`,

                                                                IFNULL((
                                                                    SELECT `ps`.`payment_status_id` 
                                                                    FROM `course_payments` AS `cp2`
                                                                    LEFT JOIN `payment_status` AS `ps` 
                                                                    ON `cp2`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                    WHERE `cp2`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                                                    ORDER BY `cp2`.`course_payments_id` DESC LIMIT 1
                                                                ), '0') AS `last_payment_status_id`,
                                                                
                                                                IFNULL((
                                                                    SELECT `ps`.`payment_status` 
                                                                    FROM `course_payments` AS `cp2`
                                                                    LEFT JOIN `payment_status` AS `ps` 
                                                                    ON `cp2`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                    WHERE `cp2`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                                                    ORDER BY `cp2`.`course_payments_id` DESC LIMIT 1
                                                                ), 'Not Added Any Payment') AS `last_payment_status`

                                                            FROM 
                                                                `payment_plan_has_students_has_intakes` AS `pphsi`
                                                            INNER JOIN 
                                                                `students_has_intakes` AS `shi`
                                                                ON `pphsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                            INNER JOIN 
                                                                `students` AS `s`
                                                                ON `shi`.`students_student_id` = `s`.`student_id`
                                                            INNER JOIN 
                                                                        `login_details` AS `ld`
                                                                        ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                            INNER JOIN 
                                                                `intakes` AS `i`
                                                                ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                                            INNER JOIN 
                                                                `title` AS `t`
                                                                ON `s`.`title_title_id` = `t`.`title_id`
                                                            LEFT JOIN 
                                                                `course_payments` AS `cp` 
                                                                ON `pphsi`.`payment_plan_has_students_has_intakes_id` = `cp`.`payment_plan_has_students_has_intakes_id`

                                                            WHERE 
                                                                `shi`.`intakes_intake_id` = '" . $id . "'
                                                                AND `pphsi`.`payment_category_payment_category_id` = '" . $categoryId . "'
                                                                AND `pphsi`.`payment_plan_payment_plan_id` = '" . $planId . "'
                                                            GROUP BY 
                                                                `pphsi`.`payment_plan_has_students_has_intakes_id`, 
                                                                `shi`.`student_id` 
                                                                ORDER BY 
                                                                `pphsi`.`payment_plan_has_students_has_intakes_id` DESC
                                                                ";

                                                                    $results = Database::search($query);
                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = 10;  // Consistent variable name

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $students = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                    if ($students->num_rows > 0) {
                                                                        while ($data = $students->fetch_assoc()) {
                                                                            $duePayment = $data["amount"] - $data["discount"] - $data["total_paid_discount"] - $data["approve_payments"];

                                                                    ?>
                                                                            <tr>
                                                                                <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["pphshiId"]; ?>" onchange="checkRowEvents(<?= $data['pphshiId']; ?>);">
                                                                                        <?php
                                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                        ?>
                                                                                            <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                        <?php
                                                                                        } else {
                                                                                            $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                            $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                        ?>
                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["amount"] ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["discount"] + $data["total_paid_discount"]; ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["approve_payments"]; ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["pending_payments"]; ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $duePayment; ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["last_payment_month"]; ?></span></td>

                                                                                <td class="pt-3 pb-3 align-middle">
                                                                                    <?php
                                                                                    $status_classes = [
                                                                                        "0"     => "table-btn-purple",
                                                                                        "1"  => "table-btn-yellow",
                                                                                        "2"  => "table-btn-orange",
                                                                                        "3"  => "table-btn-success",
                                                                                        "4"  => "table-btn-danger",
                                                                                        "5"  => "table-btn-orange",
                                                                                        "6"  => "table-btn-success",
                                                                                        "7"  => "table-btn-danger",
                                                                                        "8"  => "table-btn-success",
                                                                                    ];
                                                                                    $sbutton_class = $status_classes[$data["last_payment_status_id"]];
                                                                                    ?>
                                                                                    <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["last_payment_status"]; ?></button>
                                                                                </td>
                                                                                <td class="py-3 align-middle">
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=<?= $data['shiId']; ?>">
                                                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    View</a></li>

                                                                                        </ul>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <tr class="justify-content-center align-middle">
                                                                            <td colspan="13" class="align-middle justify-content-center">
                                                                                <div class="d-flex flex-column align-items-center">
                                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </tbody>
                                                            </table>
                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                    <div class="row p-4 align-items-center">
                                                                        <!-- Previous Button (col-1) -->
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPagination('previousPage');">
                                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            <span class="text-14 main-text fw-semibold">Previous</span>
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
                                                                                    $action = $isActive ? '' : "onclick=\"handleStudentsPagination('goToPage', '{$i}')\"";
                                                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                                }

                                                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                        renderPageButton($i, $pageno == $i);
                                                                                    } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                                                        $shudderPages1 = true;
                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                    } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                                                        $shudderPages2 = true;
                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                    }
                                                                                }
                                                                                ?>

                                                                            </ul>
                                                                        </div>
                                                                        <!-- Next Button (col-1) -->
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPagination('nextPage',<?= $number_of_pages ?>);">
                                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        </button>
                                                                    </div>
                                                                </nav>
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
} ?>