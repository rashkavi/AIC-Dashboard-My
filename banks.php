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
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 22) || UserPermissions::hasPermission('payments', 23))) {
            header("Location: ./payments");
        } else {

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Banks - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

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
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0" id="accordionParent">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Banks</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.history.back();">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Banks</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('payments', 22)) {
                                                ?>
                                                    <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                        <div class="row gx-3">
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn col-12" onclick="window.location.href='add-bank'">Add Bank</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="students-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="lecturers-table">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                            <span class="text-14 main-text fw-medium">Bank Logo</span>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Bankk Name</th>
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Account Number</th>
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Account Name</th>
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Branch</th>
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Linked Branch</th>
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Added By</th>
                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Added At</th>

                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php
                                                                $banks = Database::search("SELECT *,`branches`.`branch` AS 'linked_branch',`bank`.`branch` AS 'bank_branch',`bank`.`date_added` AS 'bank_added' FROM `bank`
                                                                    INNER JOIN `users` ON `bank`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
                                                                    INNER JOIN `branches` ON `bank`.`branches_branch_id` = `branches`.`branch_id`
                                                                    ORDER BY `bank`.`bank_id` DESC");

                                                                if ($banks->num_rows > 0) {
                                                                    while ($data = $banks->fetch_assoc()) {
                                                                ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                    <?php
                                                                                    if (!empty($data["logo"]) && file_exists('./assets/imgs/banks-logo/' . $data["logo"])) {
                                                                                    ?>
                                                                                        <img src="./assets/imgs/banks-logo/<?= $data["logo"]; ?>" class="w-100 h-auto" style="max-width: 100px;" alt="bank logo">
                                                                                    <?php
                                                                                    } else {
                                                                                    ?>
                                                                                        <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" style="max-width: 150px;" alt="bank logo">
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </div>
                                                                            </td>
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["bank_name"]; ?></span></td>
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["acc_no"]; ?></span></td>
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["acc_name"]; ?></span></td>
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["bank_branch"]; ?></span></td>
                                                                             <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["linked_branch"]; ?></span></td>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
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
                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["bank_added"]; ?></span></td>
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
    }
}
?>