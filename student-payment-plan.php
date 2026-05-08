<?php
require_once './includes/UserClass.php';
require_once "./includes/Project.php";
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['plan-id']) && !empty($_GET['plan-id']))) {
            header("Location: ./faculties");
        } else {

            $id = $_GET['plan-id'];

            $paymentPlan = Database::search("SELECT `payment_plan_has_students_has_intakes_id`,`payment_category`,`payment_plan`,`discount_type`,`amount`,`discount`, `discount_note`,`students_has_intakes_students_has_intakes_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`title`,`intake`,`programme`,`faculty`,`students_has_intakes`.`status_status_id` FROM `payment_plan_has_students_has_intakes`
            INNER JOIN `payment_plan` ON `payment_plan_has_students_has_intakes`.`payment_plan_payment_plan_id`=`payment_plan`.`payment_plan_id`
            INNER JOIN `payment_category` ON `payment_plan_has_students_has_intakes`.`payment_category_payment_category_id`=`payment_category`.`payment_category_id`
            INNER JOIN `discount_type` ON `payment_plan_has_students_has_intakes`.`discount_type_discount_type_id`=`discount_type`.`discount_type_id`
            INNER JOIN `students_has_intakes` ON `payment_plan_has_students_has_intakes`.`students_has_intakes_students_has_intakes_id`=`students_has_intakes`.`students_has_intakes_id`
            INNER JOIN `students` ON `students_has_intakes`.`students_student_id`=`students`.`student_id`
            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
            INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id`=`intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id`=`faculties`.`faculty_id`
            WHERE `payment_plan_has_students_has_intakes_id`='" . $id . "'");

            if ($paymentPlan->num_rows == 0) {
                header("Location: ./students");
            } else {
                $paymentPlanData = $paymentPlan->fetch_assoc();

                $settledPrice = 0;
                $duePrice = 0;

                $payments = Database::search("SELECT SUM(`amount`) AS `amount`,SUM(`discount`) AS `discount` FROM `course_payments` WHERE `payment_plan_has_students_has_intakes_id`='" . $id . "'");
                $paymentsData = $payments->fetch_assoc();

                $settledPrice = $paymentsData['amount'] + $paymentsData['discount'];
                $duePrice = $paymentPlanData['amount'] - $paymentPlanData['discount'] - $paymentsData['amount'] - $paymentsData['discount'];
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Student Payment Plan - Admin Dashboard | <?= Project::getBaseName(); ?></title>

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
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg"><?= $paymentPlanData['payment_category'] . " - " . $paymentPlanData['payment_plan']; ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $paymentPlanData['faculty_id'] ?>'><?= $paymentPlanData['faculty']; ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $paymentPlanData['programme_id'] ?>'><?= $paymentPlanData['programme']; ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $paymentPlanData['intake_id'] ?>'><?= $paymentPlanData['intake']; ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo ucwords(strtolower($paymentPlanData['title'] . " " . $paymentPlanData['first_name'] . " " . $paymentPlanData['last_name'])); ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payments</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 mb-4">
                                                <div class="row g-4 d-flex">
                                                    <div class="col-12 col-lg-6 d-flex">
                                                        <div class="row p-0 m-0 g-0 w-100"> <!-- Added w-100 to ensure full width -->
                                                            <div class="alert alert-dismissible component-theme br-0 custom-alert w-100"> <!-- Added w-100 -->
                                                                <div class="inner">
                                                                    <div class="app-card-body p-2">
                                                                        <h5>Payment Plan Details</h5>
                                                                        <div class="col-12 mt-4">
                                                                            <div class="table-responsive">
                                                                                <table class="table table-bordered">
                                                                                    <tbody>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Payment Category</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['payment_category']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Payment Plan</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['payment_plan']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Amount</td>
                                                                                            <td class="main-text text-18">LKR <?= $paymentPlanData['amount']; ?> .00</td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Discount Type</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['discount_type']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Discount</td>
                                                                                            <td class="main-text text-18">LKR <?= $paymentPlanData['discount']; ?> .00</td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="sub-text text-18 fw-semibold">Settled Amount</td>
                                                                                            <td class="sub-text text-18 fw-semibold">LKR <?= $settledPrice ?> .00</td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="text-danger text-18 fw-bold">Due Amount</td>
                                                                                            <td class="text-danger text-18 fw-bold">LKR <?= $duePrice ?> .00</td>
                                                                                        </tr>
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-lg-6 d-flex">
                                                        <div class="row p-0 m-0 g-0 w-100">
                                                            <div class="alert alert-dismissible component-theme br-0 custom-alert w-100">
                                                                <div class="inner">
                                                                    <div class="app-card-body p-2">
                                                                        <h5>Student Details</h5>
                                                                        <div class="col-12 mt-4">
                                                                            <div class="table-responsive">
                                                                                <table class="table table-bordered">
                                                                                    <tbody>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Student Intake Id</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['payment_category']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Student Name</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['title'] . " " . $paymentPlanData['first_name'] . " " . $paymentPlanData['last_name']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Faculty</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['faculty']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Programme</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['programme']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="main-text text-18">Intake</td>
                                                                                            <td class="main-text text-18"><?= $paymentPlanData['intake']; ?></td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td class="sub-text text-18 fw-semibold">Status</td>
                                                                                            <td class="sub-text text-18 fw-semibold">
                                                                                                <?php if ($paymentPlanData['status_status_id'] == 1) { ?>
                                                                                                    <button class="table-btn table-btn-success">Active</button>
                                                                                                <?php } else { ?>
                                                                                                    <button class="table-btn table-btn-danger">Inactive</button>
                                                                                                <?php } ?>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>



                                            <?php

                                            $payments = Database::search("SELECT * FROM `course_payments` WHERE `payment_plan_has_students_has_intakes_id` = '" . $paymentPlanData['payment_plan_has_students_has_intakes_id'] . "'");
                                            if ($payments->num_rows > 0) {
                                            ?>
                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="overflow-x-auto p-0 m-0 g-0" id="users-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text text-18 fw-medium">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                                <span class="text-14 main-text fw-medium mx-2">Payment Slip</span>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment Amount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment Date</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Discount Type</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Discount amount</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Discount Note</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment Status</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php
                                                                    $query = "SELECT * FROM `course_payments`
                                                                    INNER JOIN `payment_status` ON `course_payments`.`payment_status_payment_status_id` = `payment_status`.`payment_status_id`
                                                                    INNER JOIN `payment_type` ON `course_payments`.`payment_type_payment_type_id` = `payment_type`.`payment_type_id`
                                                                    INNER JOIN `payment_method` ON `course_payments`.`payment_method_payment_method_id` = `payment_method`.`payment_method_id`
                                                                    INNER JOIN `discount_type` ON `course_payments`.`discount_type_discount_type_id` = `discount_type`.`discount_type_id`
                                                                    INNER JOIN `bank` ON `course_payments`.`bank_bank_id` = `bank`.`bank_id`
                                                                    WHERE `payment_plan_has_students_has_intakes_id` = '" . $paymentPlanData['payment_plan_has_students_has_intakes_id'] . "'";

                                                                    $results = Database::search($query);
                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = $results_count;  // Consistent variable name

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $payments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");


                                                                    if ($payments->num_rows > 0) {
                                                                        while ($data = $payments->fetch_assoc()) {
                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">

                                                                                        <img src="assets/imgs/payment-slip/<?= $data["payment_receipt"]; ?>" style="max-width: 150px; max-height: 150px;" alt="">
                                                                                    </div>
                                                                                </td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR<?php echo $data["amount"]; ?>.00</span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["date_paid"]; ?> </span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["discount_type"]; ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?php echo $data["discount"]; ?> .00</span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["discount_note"]; ?></span></td>
                                                                                <td class="py-2 align-middle">
                                                                                    <?php
                                                                                    $status_classes = [
                                                                                        "Pending"     => "table-btn-primary",
                                                                                        "Approve"  => "table-btn-success",
                                                                                        "Rejected"  => "table-btn-danger",
                                                                                    ];
                                                                                    $sbutton_class = $status_classes[$data["payment_status"]];
                                                                                    ?>
                                                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["payment_status"]; ?></button>
                                                                                </td>

                                                                                <td class="py-3 align-middle">
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-payment-plan-single-payment?payment-id=<?php echo $data['course_payments_id']; ?>">
                                                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    View</a></li>
                                                                                            <hr class="mt-1 mb-0 me-2 mx-2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-payment-plan-single-payment?payment-id=<?php echo $data['course_payments_id']; ?>">
                                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Edit</a></li>
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleUsersPagination('previousPage')">
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
                                                                                    $action = $isActive ? '' : "onclick=\"handleUsersPagination('goToPage', '{$i}')\"";
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleUsersPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        </button>
                                                                    </div>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                            <?php
                                            } else {
                                            ?>
                                                <div class="col-12 px-4 py-3 component-theme h-100">
                                                    <div class="row align-items-center justify-content-center h-100">
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

                    <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Crop Image</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <img id="image" class="img-fluid" />
                                </div>
                                <div class="modal-footer">
                                    <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
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
?>