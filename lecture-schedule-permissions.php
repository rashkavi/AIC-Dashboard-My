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

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))) {
            header("Location: ./users");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Lecture Schedule Permissions - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            load_side_menu::load("Lecture Schedule");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Lecture Schedule");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include './includes/components/skeleton-header.php';
                                    include './includes/components/skeleton-table.php'; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-8 col-lg-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="lecture-schedule-permissions">Lecture Schedule Permissions</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="lecture-schedule" href="./lecture-schedule">Lecture Schedule</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="lecture-schedule-permissions">Lecture Schedule Permissions</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5)) {
                                                ?>
                                                    <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0">
                                                        <div class="row justify-content-end gx-3">
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn col-12" onclick="window.location.href='assign-permissions-to-lecture-schedule'" data-i18n="assign-permissions">Assign Permissions</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $users_count = Database::search("SELECT COUNT(DISTINCT `users_user_id`) AS user_count FROM `users_has_permission_to_other` 
                                                            WHERE `permission_to_permission_to_id` IN (SELECT `permission_to_id` FROM `permission_to` WHERE `permission_type_permission_type_id` = 11)
                                                            AND `status_status_id` = 1");
                                                                $data = $users_count->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?php echo $data["user_count"]; ?> <span data-i18n="users"></span></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleLectureSchedulePermissionPagination('filter')">
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 collapse" id="filterCollapse">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3 justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2">
                                                                            <div class="dropdown w-100">
                                                                                <button class="custom-select dropdown-toggle w-100 component-theme sub-text bl-solid" type="button" id="permissionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                    Select Permissions
                                                                                </button>
                                                                                <ul class="dropdown-menu p-2 w-100 br-0" aria-labelledby="permissionDropdown">
                                                                                    <li>
                                                                                        <div class="form-check">
                                                                                            <input type="checkbox" class="form-check-input permission-checkbox" value="0" id="permission_all">
                                                                                            <label class="form-check-label sub-text" for="permission_all">All</label>
                                                                                        </div>
                                                                                    </li>
                                                                                    <?php
                                                                                    $userPermissions = Database::search("SELECT `permission_to_id`,`permission` FROM `permission_to`
                                                                    INNER JOIN `permissions` ON `permission_to`.`permissions_permission_id`=`permissions`.`permission_id`
                                                                    WHERE `permission_to`.`permission_type_permission_type_id`='11'");

                                                                                    if ($userPermissions->num_rows > 0) {
                                                                                        while ($userPermission = $userPermissions->fetch_assoc()) {
                                                                                    ?>
                                                                                            <li>
                                                                                                <div class="form-check">
                                                                                                    <input type="checkbox" class="form-check-input permission-checkbox" value="<?= $userPermission['permission_to_id'] ?>" id="permission_<?= $userPermission['permission_to_id'] ?>">
                                                                                                    <label class="form-check-label sub-text" for="permission_<?= $userPermission['permission_to_id'] ?>"><?= $userPermission['permission'] ?></label>
                                                                                                </div>
                                                                                            </li>
                                                                                    <?php
                                                                                        }
                                                                                    }
                                                                                    ?>
                                                                                </ul>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleLectureSchedulePermissionPagination('filter')">
                                                                                <option selected value="0">- Filter Status -</option>
                                                                                <option value="1">Active</option>
                                                                                <option value="2">Inactive</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleLectureSchedulePermissionPagination('filter')">
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
                                                            <div class="table-responsive" id="users-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium w-25">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                </div>
                                                                            </th>

                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium w-25">Active Permissions</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium w-25">Inactive Permissions</th>

                                                                            <th scope="col" class="py-3 bg-table-header">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-14 main-text fw-medium">User Status</span>
                                                                                    <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <?php

                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                                            ?>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <?php
                                                                            }

                                                                            ?>

                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        $query = "
                                                                     SELECT 
                                                                         `u`.`user_fname`,
                                                                         `u`.`user_lname`,
                                                                         `u`.`profile_img`,
                                                                         `u`.`user_id`,
                                                                         `t`.`title`,
                                                                         `s`.`status`,
                                                                 
                                                                         GROUP_CONCAT(
                                                                             DISTINCT CASE 
                                                                                 WHEN `upo`.`status_status_id` = 1 
                                                                                 THEN `p`.`permission`
                                                                             END 
                                                                             ORDER BY `pt`.`permissions_permission_id`
                                                                         ) AS `activePermissions`,
                                                                 
                                                                         GROUP_CONCAT(
                                                                             DISTINCT CASE 
                                                                                 WHEN `upo`.`status_status_id` = 2 
                                                                                 THEN `p`.`permission`
                                                                             END 
                                                                             ORDER BY `pt`.`permissions_permission_id`
                                                                         ) AS `inactivePermissions`
                                                                 
                                                                     FROM `users_has_permission_to_other` AS `upo`
                                                                     INNER JOIN `permission_to` AS `pt` 
                                                                         ON `upo`.`permission_to_permission_to_id` = `pt`.`permission_to_id`
                                                                     INNER JOIN `permissions` AS `p` 
                                                                         ON `pt`.`permissions_permission_id` = `p`.`permission_id`
                                                                     INNER JOIN `users` AS `u` 
                                                                         ON `upo`.`users_user_id` = `u`.`user_id`
                                                                     INNER JOIN `title` AS `t` 
                                                                         ON `u`.`title_title_id` = `t`.`title_id`
                                                                     INNER JOIN `status` AS `s` 
                                                                         ON `u`.`status_status_id` = `s`.`status_id`
                                                                     
                                                                     WHERE `pt`.`permission_to_id` IN (
                                                                         SELECT `permission_to_id`
                                                                         FROM `permission_to`
                                                                         WHERE `permission_type_permission_type_id` = 11
                                                                     )
                                                                 
                                                                     GROUP BY `u`.`user_id`
                                                                     ORDER BY `upo`.`added_at` DESC
                                                                 ";

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
                                                                        $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                        if ($users->num_rows > 0) {
                                                                            while ($data = $users->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle">
                                                                                        <div class="col-12 d-flex flex-wrap gap-3">
                                                                                            <?php

                                                                                            if (!empty($data['activePermissions'])) {
                                                                                                $activePermissions = explode(',', $data['activePermissions']);
                                                                                                foreach ($activePermissions as $permission) {
                                                                                            ?>
                                                                                                    <span class="py-1 px-2 bg-light-green text-dark-green d-flex align-items-center">
                                                                                                        <?= $permission ?>
                                                                                                        <iconify-icon icon="tabler:check" width="20" height="20" class="mx-2 me-0"></iconify-icon>
                                                                                                    </span>
                                                                                            <?php
                                                                                                }
                                                                                            }
                                                                                            ?>
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="py-2 align-middle">
                                                                                        <div class="col-12 d-flex flex-wrap gap-3">
                                                                                            <?php

                                                                                            if (!empty($data['inactivePermissions'])) {
                                                                                                $inactivePermissions = explode(',', $data['inactivePermissions']);
                                                                                                foreach ($inactivePermissions as $permission) {
                                                                                            ?>
                                                                                                    <span class="py-1 px-2 bg-light-red text-dark-red d-flex align-items-center">
                                                                                                        <?= $permission ?>
                                                                                                        <iconify-icon icon="material-symbols:close-rounded" width="20" height="20" class="mx-2 me-0"></iconify-icon>
                                                                                                    </span>
                                                                                            <?php
                                                                                                }
                                                                                            }
                                                                                            ?>
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "Active"     => "table-btn-success",
                                                                                            "Inactive"  => "table-btn-danger",
                                                                                        ];
                                                                                        $sbutton_class = $status_classes[$data["status"]];
                                                                                        ?>
                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["status"]; ?></button>
                                                                                    </td>
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                                                    ?>
                                                                                        <td class="py-3 align-middle">
                                                                                            <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='user-lecture-schedule-permissions?user-id=<?= $data['user_id']; ?>'">View</button>
                                                                                        </td>
                                                                                        <td class="py-3 align-middle">
                                                                                            <div class="dropdown">
                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="user-lecture-schedule-permissions?user-id=<?= $data['user_id']; ?>">
                                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Edit</a></li>
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLectureSchedulePermissionPagination('previousPage')">
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
                                                                                        $action = $isActive ? '' : "onclick=\"handleLectureSchedulePermissionPagination('goToPage', '{$i}')\"";
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLectureSchedulePermissionPagination('nextPage',null,'<?= $number_of_pages ?>')">
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

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

                <script>
                    const allCheckbox = document.getElementById('permission_all');
                    const permissionCheckboxes = document.querySelectorAll('.permission-checkbox:not(#permission_all)');

                    allCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            permissionCheckboxes.forEach(checkbox => {
                                checkbox.checked = false;
                                checkbox.disabled = true;
                            });
                            selectedPermissions = [];
                        } else {
                            permissionCheckboxes.forEach(checkbox => {
                                checkbox.disabled = false;
                            });
                        }
                        //console.log("Selected Permissions:", selectedPermissions);
                        handleLectureSchedulePermissionPagination('filter');
                    });

                    permissionCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            const permissionId = this.value;

                            if (this.checked) {
                                if (!selectedPermissions.includes(permissionId)) {
                                    selectedPermissions.push(permissionId);
                                }
                            } else {
                                selectedPermissions = selectedPermissions.filter(id => id !== permissionId);
                            }
                            if (selectedPermissions.length > 0) {
                                allCheckbox.checked = false;
                            }
                            //console.log("Selected Permissions:", selectedPermissions);
                            handleLectureSchedulePermissionPagination('filter');
                        });
                    });
                </script>
            </body>

            </html>
<?php

        }
    }
}
?>