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
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Add News - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
            <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />

            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
            <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden" onload="initializeNewsContent();">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("News");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("News");
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
                                            <span class="main-topic lh-lg" data-i18n="add-news">Add News</span>
                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                <ol class="breadcrumb m-0">
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="news" href="./news">News</a></li>
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="add-news">Add News</a></li>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 align-content-center">
                                            <div class="col-12 p-0 g-0">
                                                <div class="row gx-4 gy-3">

                                                    <div class="col-12 d-flex flex-column">
                                                        <label for="" class="text-14 main-text">News Content</label>
                                                        <div id="div_editor1" class="br-0 mt-2 bl-solid"></div>
                                                        <span class="text-14 sub-text" id="content-feedback"></span>
                                                    </div>
                                                    <div class="col-12 d-flex flex-column">
                                                        <label for="" class="text-14 main-text">News Image</label>
                                                        <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                            <div class="col-6 col-lg-3 d-flex flex-column justify-content-end">
                                                                <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-100" alt="News img" id="News-img">
                                                                <span class="text-14 text-dark-red" id="image-feedback"></span>
                                                            </div>
                                                            <div class="col-6 col-lg-3 d-flex">
                                                                <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                    <div class="col-12">
                                                                        <p class="text-14 sub-text g-0 p-0 m-0">Upload your News image here.</p>
                                                                        <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                        <p class="text-14 sub-text g-0 p-0 m-0">Supported format:</p>
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                    </div>
                                                                    <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                        <button class="custom-btn col-12 d-flex align-items-center justify-content-center" onclick="uploadCover('file-input','News-img');">
                                                                            <span class="text-14 dark-blue pe-3">Upload Image</span>
                                                                            <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="dark-blue"></iconify-icon>
                                                                        </button>
                                                                        <!-- Hidden file input -->
                                                                        <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-lg-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                                <button class="col-12 col-lg-8 custom-btn m-0" data-i18n="add-news" onclick="addNews(this)">Add News</button>
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
?>