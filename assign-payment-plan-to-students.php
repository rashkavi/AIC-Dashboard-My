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
                $paymentPlan = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intakes_intake_id`='" . $id . "'");
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
                                                    if ($paymentPlan->num_rows > 0) {
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

                                            <?php
                                         
                                            if ($paymentPlan->num_rows == 0) {
                                            ?>
                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row align-items-center justify-content-center">
                                                        <img src="./assets/imgs/common/setup.png" class="no-data-icon" alt="No data" />
                                                        <span class="sub-topic text-center d-block">
                                                            Payment Group Not Configured
                                                        </span>
                                                        <span class="text-center d-block mt-1">
                                                            This intake does not have any payment group assigned yet.
                                                            Please create or assign a payment group to proceed with managing student payments.
                                                        </span>
                                                        <div class="text-center">
                                                            <button class="custom-btn mt-3 px-5" onclick="window.location.href='add-intake-payment-plan?intake-id=<?= $id ?>'">Add Payment Group</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php
                                            } else {
                                            ?>
                                                <div class="payment-card-container mb-4">
                                                    <div class="payment-card">
                                                        <div class="card-background"></div>

                                                        <div class="card-content">
                                                            <div class="payment-header p-0 m-0">
                                                                <div class="reference-section p-0 m-0">
                                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                                        <div>
                                                                            <h2 class="reference-number">Intake Payment Plan Management – Important Guidelines</h2>
                                                                            <span class="reference-label">
                                                                                Please review the following information carefully before proceeding.
                                                                            </span>
                                                                        </div>

                                                                        <button class="card-read-more-btn"
                                                                            type="button"
                                                                            onclick="toggleLeadCardDetails(this)">
                                                                            <span class="btn-text">View Details</span>
                                                                            <iconify-icon icon="mdi:chevron-down" width="18" height="18"></iconify-icon>
                                                                        </button>
                                                                    </div>
                                                                    <hr>
                                                                </div>
                                                            </div>

                                                            <!-- Collapsible Details Area -->
                                                            <div class="lead-extra-details" id="paymentGuidelineDetails">
                                                                <div class="details-row d-flex flex-column">

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Intake Payment Plan Configuration</span>
                                                                            <p class="detail-value">
                                                                                The new system allows payment plans to be created directly for each intake.
                                                                                Each payment plan can include payment amounts, installment structures, due dates,
                                                                                discounts, and penalty configurations.
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                                                <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12.5a.5.5 0 0 1-.777.416L8 11.101l-5.223 3.815A.5.5 0 0 1 2 14.5V2z" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Payment Plan Category Validation</span>
                                                                            <p class="detail-value">
                                                                                When a payment plan is selected, the system will identify its payment category,
                                                                                such as Local Institute Payment, and load only students who have not already
                                                                                been assigned a payment plan under that selected category.
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                                                <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Eligible Student Loading</span>
                                                                            <p class="detail-value">
                                                                                After selecting a payment plan, only eligible students will be displayed.
                                                                                Students who already have an assigned payment plan for the same payment category
                                                                                will not be shown, helping to prevent duplicate payment assignments.
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16">
                                                                                <path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-miterlimit="10" d="M6 10h2.5c.55 0 1-.45 1-1s-.45-1-1-1h-1c-.55 0-1-.45-1-1s.45-1 1-1H10M8 4.5v1.167M8 9.5v2M14.5 8a6.5 6.5 0 1 1-13 0a6.5 6.5 0 0 1 13 0Z" stroke-width="1" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Assign Payment Plan to Selected Students</span>
                                                                            <p class="detail-value">
                                                                                Once students are selected and confirmed, all details from the selected payment plan
                                                                                will be assigned to each student. Individual payment schedules will be generated
                                                                                automatically.
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                                                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.706c.89 0 1.438-.99.982-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Important Confirmation Note</span>
                                                                            <p class="detail-value">
                                                                                Please verify the selected payment plan and student list carefully before confirming.
                                                                                Once assigned, this may affect billing, reports, reminders, and cashier collections.
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                            <!-- End Collapsible Details Area -->

                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-4 component-theme mb-4">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Payment Plan</label>
                                                            <select id="payment-plan" class="form-select custom-select mt-2" onchange="handleSelectedPaymentCategoryStudents(<?= $id ?>)">
                                                                <option selected value="0">- Select -</option>
                                                                <?php

                                                                while ($paymentPlanData = $paymentPlan->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $paymentPlanData['intake_has_payment_plan_id'] ?>"><?= $paymentPlanData['payment_plan_name'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="payment-category-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-4 component-theme d-none" id="student-list-container">

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