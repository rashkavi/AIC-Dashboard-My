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
        $haveAnyPermission = false;
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))) {
            header("Location: ./dashboard");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Students Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializePayments();">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Payments");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Payments");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-courses.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="students-payments">Students Payments Categories</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments" data-i18n="payments">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="student-payments">Student Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="student-payments">Student Payments Categories</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 mt-3 mt-lg-0">
                                                    <div class="row gx-3 justify-content-end">
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 2)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn col-12" data-bs-target="#addPayment" data-bs-toggle="modal" data-i18n="add-new">Add New</button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                        $paymentsCategories = Database::search("
                                      SELECT 
                                          `pc`.`payment_category_id` AS `payment_category_id`,
                                          `pc`.`payment_category` AS `payment_category`,
                                          `pc`.`currencies_currency_id`,
                                          `c`.`code`,
                                          `c`.`decimal_places`,
                                          COALESCE(
                                              SUM(
                                                  CASE 
                                                      WHEN `cp`.`payment_status_payment_status_id` IN (2, 3, 5, 6, 8)
                                                      THEN `cp`.`amount`
                                                      ELSE 0
                                                  END
                                              ),
                                              0
                                          ) AS `total_payments`
                                      FROM 
                                          `payment_category` `pc`
                                      INNER JOIN 
                                            `currencies` `c`
                                          ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                      LEFT JOIN 
                                          `payment_plan_has_students_has_intakes` `pphsi`
                                          ON `pc`.`payment_category_id` = `pphsi`.`payment_category_payment_category_id`
                                      LEFT JOIN 
                                          `course_payments` `cp`
                                          ON `pphsi`.`payment_plan_has_students_has_intakes_id` = `cp`.`payment_plan_has_students_has_intakes_id`
                                      GROUP BY 
                                          `pc`.`payment_category_id`,
                                          `pc`.`payment_category`
                                  ");


                                        if ($paymentsCategories->num_rows > 0) {
                                            while ($paymentCategory = $paymentsCategories->fetch_assoc()) {
                                                $chartId = "paymentPieChart_" . $paymentCategory['payment_category_id']; // Unique ID for each chart
                                        ?>
                                                <div class="col-12 px-4 py-3 component-theme mb-10">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="col-8 col-md-9 col-lg-9 p-0 m-0 g-0 align-content-center">
                                                            <div class="row px-0 m-0 g-0 align-items-center">
                                                                <span class="text-15 text-dark-color fw-semibold lh-lg"><?php echo $paymentCategory['payment_category']; ?></span>
                                                            </div>
                                                            <div class="row px-0 m-0 g-0 align-items-center">
                                                                <span class="text-14 main-text lh-lg">Total Payments : <?= $paymentCategory['code'] ?> <?= number_format($paymentCategory['total_payments'], $paymentCategory['decimal_places'], '.', ',') ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                            <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 10)) {
                                                                ?>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-danger-btn w-100" data-i18n="edit" onclick="openPaymentCategoryEditModal(<?= $paymentCategory['payment_category_id'] ?>,'<?= $paymentCategory['payment_category'] ?>','<?= $paymentCategory['currencies_currency_id'] ?>')">Edit</button>
                                                                    </div>
                                                                <?php
                                                                }
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 20)) {
                                                                ?>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-i18n="view" type="button" onclick="window.location.href = 'payment-category?category-id=<?= $paymentCategory['payment_category_id'] ?>'">View</button>
                                                                        <ul class="dropdown-menu mt-2">
                                                                            <li><a class="dropdown-item" href="payment-category?category-id=<?= $paymentCategory['payment_category_id'] ?>" data-i18n="">Scheduled Payments</a></li>
                                                                            <li><a class="dropdown-item" href="payment-category-outstanding-payments?category-id=<?= $paymentCategory['payment_category_id'] ?>" data-i18n="outstanding-payments">Outstanding Payments</a></li>
                                                                            <li><a class="dropdown-item" href="panelty-payments?category-id=<?= $paymentCategory['payment_category_id'] ?>" data-i18n="">Panelty Payments</a></li>
                                                                        </ul>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php
                                            }
                                        } else {
                                            if ($sessionUserRole != "Developer") {
                                            ?>
                                                <div class="col-12 px-4 py-3 component-theme h-100">
                                                    <div class="row align-items-center justify-content-center h-100">
                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                    </div>
                                                </div>
                                            <?php
                                            }
                                        }
                                        if (1 == 2) {
                                            ?>
                                            <div class="col-12 px-4 py-3 component-theme mb-10">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-8 col-md-9 col-lg-9 p-0 m-0 g-0 align-content-center">
                                                        <div class="row px-0 m-0 g-0 align-items-center">
                                                            <span class="text-15 text-dark-color fw-semibold lh-lg">Portal Payments</span>
                                                        </div>
                                                        <div class="row px-0 m-0 g-0 align-items-center">
                                                            <?php
                                                            $totalPortalPayments = Database::search("SELECT 
                                                        SUM(`pp`.`amount`) AS total_payments
                                                        FROM `portal_payment` AS `pp` WHERE `pp`.`portal_payment_status_portal_payment_status_id`='2'")->fetch_assoc()['total_payments'];

                                                            $spanText = "Total Payments : LKR " . $totalPortalPayments . ".00";
                                                            if ($totalPortalPayments == '') {
                                                                $spanText = "Total Payments : LKR 0.00";
                                                            }
                                                            ?>
                                                            <span class="text-14 main-text lh-lg"><?= $spanText ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                            <?php
                                                            if ($sessionUserRole == "Developer") {
                                                            ?>
                                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <a href="portal-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                        <button class="custom-btn w-100" data-i18n="view">View</button>
                                                                    </a>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 px-4 py-3 component-theme mb-10">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-8 col-md-9 col-lg-9 p-0 m-0 g-0 align-content-center">
                                                        <div class="row px-0 m-0 g-0 align-items-center">
                                                            <span class="text-15 text-dark-color fw-semibold lh-lg">Portal Subscriptions</span>
                                                        </div>
                                                        <div class="row px-0 m-0 g-0 align-items-center">
                                                            <span class="text-14 main-text lh-lg">Track payments, monitor subscription periods, and ensure uninterrupted student access.</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="portal-subscriptions" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100" data-i18n="view">View</button>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
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

                <?php
                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 2)) {
                ?>
                    <!-- Payment Add Model => Start -->
                    <div class="modal fade br-0" id="addPayment" aria-hidden="true" aria-labelledby="addPaymentLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="editPaymentLabel">Add Payment Category</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-0">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 d-flex flex-column">
                                                <label for="" class="text-14 main-text required">Name</label>
                                                <input type="text" class="form-control custom-input mt-2" id="new-category-name">
                                                <span class="text-danger" id="new-category-name-feedback"></span>
                                            </div>
                                            <div class="col-12 col-lg-6 d-flex flex-column">
                                                <label for="" class="text-14 main-text required">Currency</label>
                                                <select class="form-select custom-select mt-2" id="currency">
                                                    <option value="0">- Select -</option>
                                                    <?php
                                                    $currencies = Database::search("SELECT * FROM `currencies` WHERE `is_active` = '1'");
                                                    while ($currency = $currencies->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?php echo $currency['currency_id']; ?>"><?php echo $currency['name']; ?> | <?php echo $currency['code']; ?></option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                                <span class="text-danger" id="currency-feedback"></span>
                                            </div>
                                            <div class="col-12 d-flex justify-content-end">
                                                <button class="custom-btn col-12 col-lg-4" onclick="addPaymentCategory(this);">Add New</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Payment Add Model => End -->
                <?php
                }

                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 10)) {
                ?>
                    <!-- Payment Edit Modal => Start -->
                    <div class="modal fade br-0" id="editPayment" aria-hidden="true" aria-labelledby="editPaymentLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="editPaymentLabel">Edit Payment Category</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-0 component-theme">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 d-flex flex-column">
                                                <label for="" class="text-14 main-text required">Name</label>
                                                <input type="text" class="form-control custom-input mt-2" id="edit-category-name">
                                                <span class="text-danger" id="edit-category-name-feedback"></span>
                                            </div>
                                            <div class="col-12 col-lg-6 d-flex flex-column">
                                                <label for="" class="text-14 main-text required">Currency</label>
                                                <select class="form-select custom-select mt-2" id="edit-category-currency">
                                                    <option value="0">- Select -</option>
                                                    <?php
                                                    $currencies = Database::search("SELECT * FROM `currencies` WHERE `is_active` = '1'");
                                                    while ($currency = $currencies->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?php echo $currency['currency_id']; ?>"><?php echo $currency['name']; ?> | <?php echo $currency['code']; ?></option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                                <span class="text-danger" id="edit-category-currency-feedback"></span>
                                            </div>
                                            <div class="col-12 d-flex justify-content-end">
                                                <button class="custom-btn col-12 col-lg-4" id="save-edit-category-btn">Save Changes</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Payment Edit Modal => End -->
                <?php
                }
                ?>

                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}

?>