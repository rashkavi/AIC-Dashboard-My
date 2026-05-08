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
                                include "./includes/components/skeleton-table.php"; ?>
                            </div>
                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-lg-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="faq">Frequently Asked Questions</span>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="faq-desc">Find quick answers to common questions and concerns.</span>
                                                    </div>
                                                </div>
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faq', 10)) {
                                                ?>
                                                    <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                        <div class="row gx-3">
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn col-12" data-i18n="add-new" data-bs-target="#addFAQ" data-bs-toggle="modal">Add FAQ</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <?php
                                                            $users_count = Database::search("SELECT COUNT(*) as 'count' FROM `faq`");
                                                            $data = $users_count->fetch_assoc();
                                                            ?>
                                                            <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> <span data-i18n="faq"></span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-10 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handleFAQPagination('filter')">
                                                        </div>
                                                    </div>

                                                    <div class="d-none d-lg-block col-6"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleFAQPagination('filter')">
                                                            <option selected value="10">10</option>
                                                            <option value="25">25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                            <option value="250">250</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="table-responsive-wrapper">
                                                        <div class="table-responsive" id="faq-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="faq-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                                <span class="text-14 main-text fw-medium mx-2">Qusetion</span>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Added By</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Added At</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php
                                                                    $query = "SELECT * FROM `faq`
                                                                    INNER JOIN  `users` ON `faq`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    ORDER BY `faq_id` DESC";

                                                                    $results = Database::search($query);
                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = 10;  // Consistent variable name

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $faqs = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                    if ($faqs->num_rows > 0) {
                                                                        while ($data = $faqs->fetch_assoc()) {
                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                        <div class="flex-grow-1 d-flex align-content-center cursor-pointer">
                                                                                            <p class="text-14 main-text m-0"><?= $data["question"]; ?></p>
                                                                                        </div>

                                                                                    </div>
                                                                                </td>
                                                                                <td class="py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?= $data['user_id']; ?>)">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                        </div>

                                                                                    </div>
                                                                                </td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date_added"]; ?></span></td>

                                                                                <?php
                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faq', 11) || UserPermissions::hasPermission('faq', 10)) {
                                                                                ?>
                                                                                    <td class="py-3 align-middle">
                                                                                        <div class="dropdown">
                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                <?php
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faq', 10)) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href='./frequently-asked-question?id=<?= $data['faq_id']; ?>'>
                                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Edit</a></li>
                                                                                                <?php
                                                                                                }

                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faq', 11)) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="deleteFAQ(<?= $data['faq_id']; ?>)">
                                                                                                            <iconify-icon icon="material-symbols:delete-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Remove</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                ?>

                                                                                            </ul>
                                                                                        </div>
                                                                                    </td>
                                                                                <?php
                                                                                }
                                                                                ?>

                                                                            </tr>
                                                                        <?php
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <tr class="justify-content-center align-middle">
                                                                            <td colspan="13" class="align-middle justify-content-center">
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
                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                    <div class="row p-4 align-items-center">
                                                                        <!-- Previous Button (col-1) -->
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleFAQPagination('previousPage')">
                                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                                                        </button>
                                                                        <!-- Page Numbers (col-10, centered) -->
                                                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                            <ul class="pagination-list d-flex mb-0">
                                                                                <?php
                                                                                $shudderPages1 = false;
                                                                                $shudderPages2 = false;

                                                                                function renderPageButton($i, $isActive = false)
                                                                                {
                                                                                    $activeClass = $isActive ? 'active' : '';
                                                                                    $action = $isActive ? '' : "onclick=\"handleFAQPagination('goToPage', '{$i}')\"";
                                                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                }

                                                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                        renderPageButton($i, $pageno == $i);
                                                                                    } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                        $shudderPages1 = true;
                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                    } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                        $shudderPages2 = true;
                                                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                    }
                                                                                }
                                                                                ?>
                                                                            </ul>
                                                                        </div>
                                                                        <!-- Next Button (col-1) -->
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleFAQPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        </button>
                                                                    </div>
                                                                </nav>
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

            <!-- FAQ Add Model => Start -->
            <div class="modal fade br-0" id="addFAQ" aria-hidden="true" aria-labelledby="addFAQLabel" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                    <div class="modal-content p-4">
                        <div class="modal-header p-0 border-0">
                            <h1 class="modal-title text-16 text-dark-color" data-i18n="add-new" id="editSubjectLabel">Add FAQ</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0 mt-3">
                            <div class="col-12">
                                <div class="row gy-3 align-content-center">
                                    <div class="col-12 d-flex flex-column">
                                        <label for="" class="text-14 main-text" data-i18n="question">Question</label>
                                        <input type="text" class="form-control custom-input mt-2" id="question">
                                        <span id="question-feedback" class="text-12 text-dark-red"></span>
                                    </div>
                                    <div class="col-12 d-flex flex-column">
                                        <label for="" class="text-14 main-text" data-i18n="answer">Answer</label>
                                        <div id="div_editor1" class="br-0 mt-"></div>
                                        <span id="answer-feedback" class="text-12 text-dark-red"></span>
                                    </div>
                                    <div class="col-12 d-flex flex-column">
                                        <label for="" class="text-14 main-text" data-i18n="video-link">Video Link</label>
                                        <input type="text" class="form-control custom-input mt-2" id="video_link">
                                        <span id="video-link-feedback" class="text-12 text-dark-red"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer p-0 mt-4 border-0">
                            <button class="col-8 col-md-4 custom-btn m-0" data-i18n="add-faq" onclick="addFAQ()">Save FAQ</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- FAQ Add Model => End -->



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