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
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole))) {
            header("Location: ./dashboard");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Academic Reports - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
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
                                    include './includes/components/skeleton-header.php';
                                    include './includes/components/skeleton-courses.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="reports">Finance Reports</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="payments">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Student Payments Reports</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="row gy-4 gx-4">
                                                <div class="col-12 col-lg-6 mt-4">
                                                    <div class="row m-0 component-theme p-4 h-100">
                                                        <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                            <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-mdl2:analytics-report" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                            <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                                    Student Payment Summary Report
                                                                </span>
                                                                <span class="text-14 main-text" data-i18n="">
                                                                    View overdue payments grouped by aging periods and track total outstanding amounts.
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                            <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="window.location.href='student-payment-summary-report'">Genarate</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-lg-6 mt-4">
                                                    <div class="row m-0 component-theme p-4 h-100">
                                                        <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                            <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-mdl2:analytics-report" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                            <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                                    Collection Report
                                                                </span>
                                                                <span class="text-14 main-text" data-i18n="">
                                                                    View and track payment collections, outstanding balances, and overdue amounts.
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                            <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="window.location.href='./collection-report'">Genarate</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-lg-6 mt-4">
                                                    <div class="row m-0 component-theme p-4 h-100">
                                                        <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                            <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-mdl2:analytics-report" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                            <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                                    Due Payments Report
                                                                </span>
                                                                <span class="text-14 main-text" data-i18n="">
                                                                    View and track all due payments, including outstanding balances and overdue amounts.
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                            <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="window.location.href='due-report'">Genarate</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- <div class="col-12 col-lg-6 mt-4">
                                                    <div class="row m-0 component-theme p-4 h-100">
                                                        <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                            <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-mdl2:analytics-report" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                            <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                                    Outstanding balances
                                                                </span>
                                                                <span class="text-14 main-text" data-i18n="">
                                                                    View overdue payments grouped by aging periods and track total outstanding amounts.
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                            <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="window.location.href='student-outstanding-balance-report'">Genarate</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-lg-6 mt-4">
                                                    <div class="row m-0 component-theme p-4 h-100">
                                                        <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                            <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-mdl2:analytics-report" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                            <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                                <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">
                                                                    Late Payments
                                                                </span>
                                                                <span class="text-14 main-text" data-i18n="">
                                                                    View overdue payments grouped by aging periods and track total outstanding amounts.
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                            <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="window.location.href='late-payments'">Genarate</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div> -->

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

                <div class="modal fade" id="genarateDueReport" tabindex="-1" role="dialog" aria-labelledby="genarateDueReportTitle" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="genarateDueReportTitle">Balance due report with aged due payments</h5>
                                <button type="button" class="btn-close" data-bs-target="#genarateDueReport" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="col-12 p-0 g-0">
                                    <div class="row gx-4 gy-3">
                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                            <label for="" class="text-14 main-text required">Over Due Date Range</label>
                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="date-range-selector">
                                                <option selected value="0">- Select -</option>
                                                <option value="1">5 to 30 Days</option>
                                                <option value="2">30 to 60 Days</option>
                                                <option value="3">60 to 90 Days</option>
                                                <option value="4">Over 90 Days</option>
                                            </select>
                                            <div class="text-12 text-danger" id="date-range-feedback"></div>
                                        </div>
                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                            <label for="" class="text-14 main-text not-required">Status</label>
                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="status-selector">
                                                <option selected value="0">- Select -</option>
                                                <option value="1">Active Students</option>
                                                <option value="2">Inactive Students</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="col-12 col-md-4 custom-btn" onclick="genaratePaymentDueReport(this);">Genarate Report</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="genaratePaymentReport" tabindex="-1" role="dialog" aria-labelledby="genaratePaymentReportTitle" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="genaratePaymentReportTitle">Genarate Payment Report</h5>
                                <button type="button" class="btn-close" data-bs-target="#genaratePaymentReport" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="col-12 p-0 g-0">
                                    <div class="row gx-4 gy-3">
                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                            <label for="" class="text-14 main-text">From Date</label>
                                            <input type="date" class="form-control custom-input mt-2" id="from-date">
                                            <div class="text-12 text-danger" id="from-date-feedback"></div>
                                        </div>
                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                            <label for="" class="text-14 main-text">To Date</label>
                                            <input type="date" class="form-control custom-input mt-2" id="to-date">
                                            <div class="text-12 text-danger" id="to-date-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="col-12 col-md-4 custom-btn" onclick="genaratePaymentReport(this);">Genarate Report</button>
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
?>