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
                <title>Payments - Admin Dashboard | <?= Project::getBaseName() ?> </title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/translations.js?= Project::getVersion() ?>"></script>
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
                                                <div class="col-12 col-md-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="payments">Payments</span>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="payment-desc">Manage student payment records, fees, and transaction history efficiently.</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row gx-3 justify-content-end">
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                        ?><div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="bg-transparent border-0 px-3" onclick="location.href='payments-permissions.php'">
                                                                    <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 22) || UserPermissions::hasPermission('payments', 23)) {
                                                        ?>
                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <div class="dropdown w-100">
                                                                    <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                        Action
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                        <li><a class="dropdown-item" href="banks.php" data-i18n="manage-banks">Banks</a></li>
                                                                        <li><a class="dropdown-item" href="payments-settings" data-i18n="payments-settings">Settings</a></li>
                                                                    </ul>
                                                                </div>
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
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center">
                                                        <iconify-icon icon="icon-park-outline:currency" width="32" height="32" class="dark-theme"></iconify-icon>
                                                    </div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">Currency Exchange Rates</span>
                                                        <span class="text-14 main-text" data-i18n="">Monitor and manage real-time currency conversions with accuracy</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="currency-exchange-rates" class="text-decoration-none cursor-pointer col-12 align-content-center">
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
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center">
                                                        <iconify-icon icon="ix:inquiry" width="32" height="32" class="dark-theme"></iconify-icon>
                                                    </div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">Registration Payments (Inquiries)</span>
                                                        <span class="text-14 main-text" data-i18n="">Monitor and manage real-time currency conversions with accuracy</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="inquiries-registration-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
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
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="token:pay" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                            Students Payments (Receipts Approval)
                                                        </span>
                                                        <span class="text-14 main-text" data-i18n="">
                                                            View, manage, and track scheduled payments assigned to students efficiently
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">

                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="students-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100" data-i18n="">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="token:pay" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                            Students Due Payments
                                                        </span>
                                                        <span class="text-14 main-text" data-i18n="">
                                                            View, manage, and track scheduled payments assigned to students efficiently
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">

                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="students-due-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100" data-i18n="">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="cryptocurrency:pay" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                            Payment Plans
                                                        </span>
                                                        <span class="text-14 main-text" data-i18n="">
                                                            View, manage, and track scheduled payments assigned to students efficiently
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">

                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="payment-plans" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100" data-i18n="">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="mdi:report-bar" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                            Students Payments Reports
                                                        </span>
                                                        <span class="text-14 main-text" data-i18n="">
                                                            View, manage, and track scheduled payments assigned to students efficiently
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">

                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="students-payments-reports" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100" data-i18n="">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:student" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="student-payments">Students Payments</span>
                                                        <span class="text-14 main-text" data-i18n="student-payments-desc">Manage students payments with ease and flexibility</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 20)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="manage-students-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
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
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:teacher" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="lecturers-payments">Lecturers Payments</span>
                                                        <span class="text-14 main-text" data-i18n="lecturers-payments-desc">Manage lecturers payments with ease and flexibility</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 20)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
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