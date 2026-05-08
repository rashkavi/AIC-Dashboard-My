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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id']) && !empty($_GET['id']))) {
            header("Location: ./frequently-asked-questions");
        } else {
            $id = $_GET['id'];
            $faq = Database::search("SELECT * FROM `faq` WHERE `faq_id` = '" . $id . "'");

            if ($faq->num_rows == 0) {
                header("Location: ./frequently-asked-questions");
            } else {
                $faqData = $faq->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Frequently Asked Questions - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />

                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
                    <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

                </head>

                <body class="overflow-hidden" onload="initializeFAQ()">
                    <div class="notifications"></div>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "includes/side-menu.php";
                                load_side_menu::load("FAQ");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("FAQ");
                                        ?>
                                    </div>
                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-add-edit.php"; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row align-items-center p-0 m-0 g-0">
                                                    <div class="col-12 col-md-12 col-lg-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg"><span data-i18n="faq"></span> - <?= $faqData['faq_id'] ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="faq" href="./frequently-asked-questions">Frequently Asked Questions</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer"><span data-i18n="faq"></span> - <?= $faqData['faq_id'] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faq', 11)) {
                                                    ?>
                                                        <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-danger-btn w-100" data-i18n="remove" onclick="deleteFAQ(<?= $faqData['faq_id'] ?>,2)">Remove</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>

                                                </div>
                                            </div>
                                            <div class="col-12 p-4 component-theme">
                                                <div class="row p-0 m-0 align-content-center">
                                                    <div class="col-12 d-flex flex-column p-0">
                                                        <label for="" class="text-14 main-text" data-i18n="question">Question</label>
                                                        <input type="text" class="form-control custom-input mt-2" id="question" value="<?= $faqData['question'] ?>">
                                                        <span id="question-feedback" class="text-12 text-dark-red"></span>
                                                    </div>
                                                    <div class="col-12 d-flex flex-column mt-3 p-0">
                                                        <label for="" class="text-14 main-text" data-i18n="answer">Answer</label>
                                                        <div id="div_editor1" class="br-0 mt-2">
                                                            <?= $faqData['answer'] ?>
                                                        </div>
                                                        <span id="answer-feedback" class="text-12 text-dark-red"></span>
                                                    </div>
                                                    <div class="col-12 d-flex flex-column mt-3 p-0">
                                                        <label for="" class="text-14 main-text" data-i18n="video-link">Video Link</label>
                                                        <input type="text" class="form-control custom-input mt-2" id="video_link" value="<?= htmlspecialchars($faqData['video_link'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span id="video-link-feedback" class="text-12 text-dark-red"></span>
                                                    </div>

                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faq', 10)) {
                                                    ?>
                                                        <div class="col-12 p-0 g-0 mt-3">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="custom-btn w-100" data-i18n="save" onclick="editFAQ('<?= $faqData['faq_id'] ?>');">Save Changes</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
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