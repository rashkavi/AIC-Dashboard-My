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
        header("Location: ./index");
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
            <title>Students Reports - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden" onload="initializeStudentsReports()">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("Students Reports");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Students Reports");
                                ?>
                            </div>

                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-table.php"; ?>
                            </div>

                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-12 col-xxl-6">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="students-reports">Students Reports</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="students-desc">Effortlessly manage students, access profiles, and maintain oversight</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-12 col-xxl-6 mt-3 mt-xxl-0">
                                                <div class="row">
                                                    <div class="col-12 col-md-5 d-flex flex-column position-relative">
                                                        <input type="text" class="form-control custom-input" id="student-name" maxlength="45" placeholder="Student Name">
                                                        <div id="student-suggestions" class="list-group position-absolute w-100" style="top: 100%; z-index: 1000;"></div>
                                                        <span id="student-name-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-5 d-flex flex-column">
                                                        <input type="text" class="form-control custom-input" id="student-code" maxlength="45" placeholder="Student Code">
                                                        <span id="student-code-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-2 d-flex flex-column">
                                                        <button class="custom-btn w-100" onclick="searchStudent(this);"><iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-white"></iconify-icon></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <style>
                                        .student-action-card {
                                            padding: 12px;
                                            cursor: pointer;
                                            transition: all 0.25s ease;
                                            border: 1px solid var(--line-color);
                                            display: flex;
                                            flex-direction: row;
                                        }

                                        .student-action-card:hover {
                                            background: var(--light-theme);
                                            transform: translateY(-3px);
                                        }

                                        .student-action-card .iconify {
                                            font-size: 22px;
                                            margin-bottom: 5px;
                                            display: block;
                                        }

                                        .student-action-card.active {
                                            background: var(--light-theme);
                                            color: var(--dark-theme);
                                            border-color: var(--dark-theme);
                                            transform: translateY(-2px);
                                        }

                                        .report-contact small {
                                            font-size: 12px;
                                            line-height: 1.4;
                                        }

                                        .student-report-top {
                                            margin-bottom: 24px;
                                        }

                                        .student-report-profile {
                                            display: flex;
                                            gap: 24px;
                                            align-items: center;
                                            flex-wrap: wrap;
                                        }

                                        .student-report-image-wrap {
                                            width: 130px;
                                            height: 130px;
                                            overflow: hidden;
                                            flex-shrink: 0;
                                        }

                                        .student-report-image {
                                            width: 100%;
                                            height: 100%;
                                            object-fit: cover;
                                        }

                                        .student-report-basic {
                                            flex: 1;
                                            min-width: 260px;
                                        }


                                        .student-report-name {
                                            font-size: 28px;
                                            font-weight: 700;
                                            color: var(--main-text);
                                            margin-bottom: 16px;
                                        }

                                        .student-report-meta-wrap {
                                            display: flex;
                                            gap: 12px;
                                            flex-wrap: wrap;
                                        }

                                        .student-meta-card {
                                            min-width: 150px;
                                            background: var(--custom-white);
                                            border: 1px solid var(--line-color);
                                            padding: 12px 14px;
                                        }

                                        .student-meta-label {
                                            display: block;
                                            font-size: 11px;
                                            color: var(--sub-text);
                                            margin-bottom: 4px;
                                            text-transform: uppercase;
                                            letter-spacing: 0.5px;
                                        }

                                        .student-meta-value {
                                            display: block;
                                            font-size: 15px;
                                            font-weight: 700;
                                            color: var(--main-text);
                                        }

                                        .student-meta-value.active {
                                            color: #1f9d55;
                                        }

                                        .student-report-section {
                                            background: var(--custom-white);
                                            margin-bottom: 20px;

                                        }

                                        .student-report-section-title {
                                            font-size: 16px;
                                            font-weight: 700;
                                            color: var(--sub-topic);
                                            margin-bottom: 16px;
                                            padding-left: 12px;
                                            border-left: 4px solid var(--dark-theme);
                                        }

                                        .student-report-grid {
                                            display: grid;
                                            grid-template-columns: repeat(2, minmax(0, 1fr));
                                            gap: 14px;
                                        }

                                        .student-detail-box {
                                            background: var(--custom-white);
                                            border: 1px solid var(--line-color);
                                            padding: 14px 16px;
                                            transition: all 0.25s ease;
                                        }

                                        .student-detail-box:hover {
                                            transform: translateY(-2px);
                                            box-shadow: 0 8px 18px rgba(36, 49, 88, 0.08);
                                            border-color: var(--line-color);
                                        }

                                        .student-detail-box label {
                                            display: block;
                                            font-size: 12px;
                                            font-weight: 600;
                                            color: var(--sub-text);
                                            margin-bottom: 6px;
                                        }

                                        .student-detail-box div {
                                            font-size: 14px;
                                            font-weight: 600;
                                            color: var(--main-text);
                                            line-height: 1.6;
                                            word-break: break-word;
                                        }

                                        .student-detail-box.full-width {
                                            grid-column: 1 / -1;
                                        }

                                        @media (max-width: 768px) {
                                            .student-report-grid {
                                                grid-template-columns: 1fr;
                                            }

                                            .student-report-name {
                                                font-size: 22px;
                                            }

                                            .student-report-image-wrap {
                                                width: 110px;
                                                height: 110px;
                                            }
                                        }
                                    </style>
                                    <div class="col-12">
                                        <div class="row" id="student-details-area">

                                            <div class="col-12">
                                                <div class="row p-0 m-0 component-theme align-items-center justify-content-center">
                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
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
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
    }
}
?>