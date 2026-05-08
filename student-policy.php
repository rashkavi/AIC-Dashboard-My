<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();

    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Student Policy - Admin Dashboard | <?= Project::getBaseName() ?> </title>

        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
        <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden" onload="initializeStudentPolicy();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Student Policy");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "./includes/header.php";
                            selectItem::setSelected("Student Policy");
                            ?>
                        </div>

                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php include "./includes/components/skeleton-header.php"; ?>

                            <div class="col-12 p-4 component-theme">
                                <div class="skeleton skeleton-title mb-4"></div>
                                <div class="skeleton skeleton-card"></div>
                            </div>
                        </div>
                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <span class="main-topic lh-lg" data-i18n="student-policy">Student Policy</span>
                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="student-policy-desc">Review the guidelines and rules that govern student conduct.</span>
                                    </div>
                                </div>
                                <div class="col-12 p-4 component-theme">
                                    <div class="row p-0 m-0 align-content-center">
                                        <div class="col-12 d-flex flex-column p-0 g-0">
                                            <div class="col-12 d-flex align-items-center p-0 g-0">
                                                <span class="sub-topic flex-grow-1" data-i18n="policy-content">Policy Content</span>

                                            </div>
                                            <div class="col-12 d-flex mt-4">
                                                <div id="div_editor1" class="br-0">
                                                    <?php
                                                    $policy = Database::search("SELECT  `student_policy` FROM `student_policy` ");
                                                    if ($policy->num_rows > 0) {
                                                        $row = $policy->fetch_assoc();
                                                        echo $row['student_policy'];
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="col-12 d-flex align-items-center justify-content-end p-0 g-0 mt-3">
                                                <button class="custom-btn px-5 pe-5 col-4" data-i18n="save" onclick="saveStudentPolicy(this);">Save</button>
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
        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>


<!DOCTYPE html>
<html lang="en">