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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) ||  UserPermissions::hasPermission('payments', 22))) {
            header("Location: ./banks");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Add Bank - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    include "./includes/components/skeleton-add-edit.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <span class="main-topic lh-lg">Add Bank</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./payments">Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./banks">Banks</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="index">Add Bank</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Bank Name</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="bank-name">
                                                            <span id="bnnk-name-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Account Number</label>
                                                            <input type="number" class="form-control custom-input mt-2" id="acc-number">
                                                            <span id="acc-number-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Account Name</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="acc-name">
                                                            <span id="acc-name-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Branch</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="branch">
                                                            <span id="branch-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Bank Logo</label>
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" alt="empty-recording-thumbnail" id="bank-logo" class="col-3 h-auto mt-2">

                                                            <p class="col-12 col-lg-3 text-14 sub-text g-0 p-0 mt-3">Upload Bank Logo here.
                                                                <span class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</span>
                                                                1200x800 pixels or 12:8 Ratio. Supported format: <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                            </p>
                                                            <button class="col-12 col-md-6 col-lg-3 custom-btn d-flex align-items-center justify-content-center" onclick="uploadCover('file-input','bank-logo');">
                                                                <span class="text-14 pe-3">Upload Image</span>
                                                                <iconify-icon icon="ic:sharp-upload" width="20" height="20"></iconify-icon>
                                                            </button>

                                                            <!-- Hiddedn file input -->
                                                            <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 g-0 mt-4">
                                                    <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                        <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                            <button class="custom-btn w-100" onclick="saveBank()">Save Bank</button>
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

                <!-- cover crop modal => Start -->
                <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Crop Image</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center d-flex  align-items-center justify-content-center">
                                <img id="image" class="img-fluid bg-black" style="width: 75%;height: auto;" />
                            </div>
                            <div class="modal-footer">
                                <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- cover crop modal => End -->

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
?>