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
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Manage Students Payment Types - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
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
                                include './includes/components/skeleton-header.php';
                                include './includes/components/skeleton-courses.php'; ?>
                            </div>
                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-lg-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="">Manage Payment Types and Panelty Payments</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments" data-i18n="payments">Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="student-payments">Student Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="">Manage Payment Types and Panelty Payments</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="row g-4">
                                            <div class="col-12 col-lg-6">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <span class="sub-topic">Payment Plans</span>
                                                    <div class="table-responsive mt-3">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Id</th>
                                                                    <th scope="col">Plan</th>
                                                                    <th scope="col"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $countOfPlans = 0;
                                                                $paymentTypes = Database::search("SELECT * FROM `payment_plan`");
                                                                while ($paymentType = $paymentTypes->fetch_assoc()) {
                                                                    $countOfPlans++;
                                                                ?>
                                                                    <tr>
                                                                        <td><?= $paymentType['payment_plan_id'] ?></td>
                                                                        <td><?= $paymentType['payment_plan'] ?></td>
                                                                        <td>

                                                                            <div class="d-flex align-items-center sub-text cursor-pointer" onclick="viewPaymentPlanHasPaymentTypes(<?= $paymentType['payment_plan_id'] ?>)">
                                                                                <iconify-icon icon="solar:eye-linear" width="20" height="20" class="sub-text me-1"></iconify-icon>View
                                                                            </div>

                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                                <tr>
                                                                    <td><?= $countOfPlans + 1 ?></td>
                                                                    <td>Non-Plan Payments</td>
                                                                    <td>
                                                                        <div class="d-flex align-items-center sub-text cursor-pointer" onclick="viewPaymentPlanHasPaymentTypes(6)">
                                                                            <iconify-icon icon="solar:eye-linear" width="20" height="20" class="sub-text me-1"></iconify-icon>View
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-6 d-flex d-none payment-types-display-area" data-plan="1">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <span class="sub-topic" id="payment-types-title">Full Payment Plan - Payment Types</span>
                                                    <div class="table-responsive mt-3">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Id</th>
                                                                    <th scope="col">Type</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $paymentTypes = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='1'");
                                                                if ($paymentTypes->num_rows > 0) {
                                                                    for ($m = 0; $m < $paymentTypes->num_rows; $m++) {
                                                                        $paymentType = $paymentTypes->fetch_assoc()
                                                                ?>
                                                                        <tr>
                                                                            <td><?= $m + 1 ?></td>
                                                                            <td><?= $paymentType['payment_type'] ?></td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="3" class="align-middle justify-content-center">
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
                                                    </div>
                                                    <div class="col-12 p-0 m-0">
                                                        <div class="row justify-content-end p-0 m-0">
                                                            <div class="col-12 col-lg-6 p-0 m-0">
                                                                <button class="custom-btn w-100" onclick="openAddPaymentTypeModal(1, 'Full Payments')">Add New</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-6 d-flex d-none payment-types-display-area" data-plan="2">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <span class="sub-topic" id="payment-types-title">Monthly Payment Plan - Payment Types</span>
                                                    <div class="table-responsive mt-3">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Id</th>
                                                                    <th scope="col">Type</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $paymentTypes = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='2'");

                                                                for ($m = 0; $m < $paymentTypes->num_rows; $m++) {
                                                                    $paymentType = $paymentTypes->fetch_assoc()

                                                                ?>
                                                                    <tr>
                                                                        <td><?= $m + 1 ?></td>
                                                                        <td><?= $paymentType['payment_type'] ?></td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-lg-6 d-flex d-none payment-types-display-area" data-plan="3">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <span class="sub-topic" id="payment-types-title">Sem Installments - Payment Types</span>
                                                    <div class="table-responsive mt-3">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Id</th>
                                                                    <th scope="col">Type</th>
                                                                    <th scope="col"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $paymentTypes = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='3'");

                                                                for ($s = 0; $s < $paymentTypes->num_rows; $s++) {
                                                                    $paymentType = $paymentTypes->fetch_assoc()
                                                                ?>
                                                                    <tr>
                                                                        <td><?= $s + 1 ?></td>
                                                                        <td><?= $paymentType['payment_type'] ?></td>
                                                                        <td>
                                                                            <div class="d-flex align-items-center sub-text cursor-pointer" onclick="openEditPaymentTypeModal(<?= $paymentType['payment_type_id'] ?>, '<?= $paymentType['payment_type'] ?>')">
                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-1"></iconify-icon>
                                                                                Edit
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-12 p-0 m-0">
                                                        <div class="row justify-content-end p-0 m-0">
                                                            <div class="col-12 col-lg-6 p-0 m-0">
                                                                <button class="custom-btn w-100" onclick="openAddPaymentTypeModal(3, 'Sem Installments')">Add New</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-lg-6 d-flex d-none payment-types-display-area" data-plan="4">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <span class="sub-topic" id="payment-types-title">Yearly Installments - Payment Types</span>
                                                    <div class="table-responsive mt-3">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Id</th>
                                                                    <th scope="col">Type</th>
                                                                    <th scope="col"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $paymentTypes = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='4'");
                                                                if ($paymentTypes->num_rows > 0) {
                                                                    for ($y = 0; $y < $paymentTypes->num_rows; $y++) {
                                                                        $paymentType = $paymentTypes->fetch_assoc()
                                                                ?>
                                                                        <tr>
                                                                            <td><?= $y + 1 ?></td>
                                                                            <td><?= $paymentType['payment_type'] ?></td>
                                                                            <td>
                                                                                <div class="d-flex align-items-center sub-text cursor-pointer" onclick="openEditPaymentTypeModal(<?= $paymentType['payment_type_id'] ?>, '<?= $paymentType['payment_type'] ?>')">
                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-1"></iconify-icon>
                                                                                    Edit
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="3" class="align-middle justify-content-center">
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
                                                    </div>
                                                    <div class="col-12 p-0 m-0">
                                                        <div class="row justify-content-end p-0 m-0">
                                                            <div class="col-12 col-lg-6 p-0 m-0">
                                                                <button class="custom-btn w-100" onclick="openAddPaymentTypeModal(4, 'Yearly Installments')">Add New</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-lg-6 d-flex d-none payment-types-display-area" data-plan="5">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <span class="sub-topic" id="payment-types-title">Installments - Payment Types</span>
                                                    <div class="table-responsive mt-3">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Id</th>
                                                                    <th scope="col">Type</th>
                                                                    <th scope="col"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $paymentTypes = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id`='5'");
                                                                if ($paymentTypes->num_rows > 0) {
                                                                    for ($i = 0; $i < $paymentTypes->num_rows; $i++) {
                                                                        $paymentType = $paymentTypes->fetch_assoc()
                                                                ?>
                                                                        <tr>
                                                                            <td><?= $i + 1 ?></td>
                                                                            <td><?= $paymentType['payment_type'] ?></td>
                                                                            <td>
                                                                                <div class="d-flex align-items-center sub-text cursor-pointer" onclick="openEditPaymentTypeModal(<?= $paymentType['payment_type_id'] ?>, '<?= $paymentType['payment_type'] ?>')">
                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-1"></iconify-icon>
                                                                                    Edit
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="3" class="align-middle justify-content-center">
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
                                                    </div>
                                                    <div class="col-12 p-0 m-0">
                                                        <div class="row justify-content-end p-0 m-0">
                                                            <div class="col-12 col-lg-6 p-0 m-0">
                                                                <button class="custom-btn w-100" onclick="openAddPaymentTypeModal(5, 'Installments')">Add New</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-lg-6 d-flex d-none payment-types-display-area" data-plan="6">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <span class="sub-topic" id="payment-types-title">Non-Plan Payments - Payment Types</span>
                                                    <div class="table-responsive mt-3">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Id</th>
                                                                    <th scope="col">Type</th>
                                                                    <th scope="col"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $paymentTypes = Database::search("SELECT * FROM `payment_type` WHERE `payment_plan_payment_plan_id` IS NULL");
                                                                if ($paymentTypes->num_rows > 0) {
                                                                    for ($n = 0; $n < $paymentTypes->num_rows; $n++) {
                                                                        $paymentType = $paymentTypes->fetch_assoc()
                                                                ?>
                                                                        <tr>
                                                                            <td><?= $n + 1 ?></td>
                                                                            <td><?= $paymentType['payment_type'] ?></td>
                                                                            <td>
                                                                                <div class="d-flex align-items-center sub-text cursor-pointer" onclick="openEditPaymentTypeModal(<?= $paymentType['payment_type_id'] ?>, '<?= $paymentType['payment_type'] ?>')">
                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-1"></iconify-icon>
                                                                                    Edit
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="3" class="align-middle justify-content-center">
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
                                                    </div>
                                                    <div class="col-12 p-0 m-0">
                                                        <div class="row justify-content-end p-0 m-0">
                                                            <div class="col-12 col-lg-6 p-0 m-0">
                                                                <button class="custom-btn w-100" onclick="openAddPaymentTypeModal('', 'Non-Plan Payments')">Add New</button>
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

            <?php
            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 2)) {
            ?>
                <div class="modal fade br-0" id="addPaymentType" aria-hidden="true" aria-labelledby="addPaymentTypeLabel" tabindex="-1">
                    <div class="modal-dialog modal modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="editfacultyLabel" data-i18n="">Add payment type</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-target="#addPaymentType" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <div class="col-12 p-0">
                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                        <div class="col-12 d-flex flex-column">
                                            <label for="" class="text-14 main-text required">Payment Plan</label>
                                            <input type="text" class="form-control custom-input mt-2" disabled id="plan-name">
                                        </div>
                                        <div class="col-12 d-flex flex-column">
                                            <label for="" class="text-14 main-text required">Type Name</label>
                                            <input type="text" class="form-control custom-input mt-2" id="type-name">
                                            <span class="text-12 text-dark-red" id="type-feedback"></span>
                                        </div>
                                        <div class="col-12 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                            <button class="col-12 col-lg-8 custom-btn mt-4" id="add-payment-type">Save Type</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            }

            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 10)) {
            ?>
                <div class="modal fade br-0" id="editPaymentType" aria-hidden="true" aria-labelledby="editPaymentTypeLabel" tabindex="-1">
                    <div class="modal-dialog modal modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="editfacultyLabel" data-i18n="">Edit payment plan</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-target="#editPaymentType" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <div class="col-12 p-0">
                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                        <div class="col-12 d-flex flex-column">
                                            <label for="" class="text-14 main-text required">Type Name</label>
                                            <input type="text" class="form-control custom-input mt-2" id="edit-type-name">
                                            <span class="text-12 text-dark-red" id="edit-type-feedback"></span>
                                        </div>
                                        <div class="col-12 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                            <button class="col-12 col-lg-8 custom-btn mt-4" id="save-edit-type">Save Changes</button>
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

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

        </body>

        </html>
<?php
    }
}
?>