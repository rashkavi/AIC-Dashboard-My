<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))) {
                        $status->error = 'You do not have permission to perform this action';
                    } else {
                        $text = isset($decoded['text']) ? $decoded['text'] : "";
                        $selectedPermissions = isset($decoded['selectedPermissions']) ? $decoded['selectedPermissions'] : [];
                        $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                        $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 0;
                        $page = isset($decoded['page']) ? $decoded['page'] : 0;

                        $query = "
                                    SELECT 
                                        `users`.`user_fname`,
                                        `users`.`user_lname`,
                                        `users`.`profile_img`,
                                        `users`.`user_id`, 
                                        `title`.`title`,
                                        `status`.`status`,
                                                        GROUP_CONCAT(DISTINCT CASE 
                                            WHEN `users_has_permission_to_other`.`status_status_id` = 1 
                                            THEN `permissions`.`permission`
                                        END ORDER BY `permission_to`.`permissions_permission_id`) AS activePermissions,
                                                        GROUP_CONCAT(DISTINCT CASE 
                                            WHEN `users_has_permission_to_other`.`status_status_id` = 2 
                                            THEN `permissions`.`permission`
                                        END ORDER BY `permission_to`.`permissions_permission_id`) AS inactivePermissions
                                        
                                    FROM `users_has_permission_to_other`
                                    
                                        INNER JOIN `permission_to` 
                                        ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                    INNER JOIN `permissions` 
                                        ON `permission_to`.`permissions_permission_id` = `permissions`.`permission_id`
                                        
                                        INNER JOIN `users` 
                                        ON `users_has_permission_to_other`.`users_user_id` = `users`.`user_id`
                                    INNER JOIN `title` 
                                        ON `users`.`title_title_id` = `title`.`title_id`
                                    INNER JOIN `status` 
                                        ON `users`.`status_status_id` = `status`.`status_id`
                                    WHERE `permission_to`.`permission_to_id` IN (SELECT `permission_to_id` FROM `permission_to` WHERE `permission_type_permission_type_id` = 3)
                                ";

                        if ($text != "") {
                            $query .= " AND (`users`.`user_id` LIKE '%" . $text . "%' 
                                    OR `users`.`user_fname` LIKE '%" . $text . "%' 
                                    OR `users`.`user_lname` LIKE '%" . $text . "%')";
                        }

                        if (count($selectedPermissions) > 0) {
                            $query .= " AND `permission_to`.`permission_to_id` IN (" . implode(",", $selectedPermissions) . ")";
                        }

                        if ($sort != 0) {
                            $query .= " AND `users_has_permission_to_other`.`status_status_id` = '" . $sort . "'";
                        }

                        $query .= "
                                GROUP BY `users`.`user_id`
                                ORDER BY `users_has_permission_to_other`.`added_at` DESC
                            ";

?>
                        <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                        <div class="d-flex flex-row align-items-center">
                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                            <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Active Permissions</th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Inactive Permissions</th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                    <th scope="col" class="px-4 py-3 bg-table-header">
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

                                $results = Database::search($query);
                                $pageno = $page;
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
                                            <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/users/' . $data["profile_img"])) {
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
                                            <td colspan="3" class="px-4 py-2 align-middle">
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

                                            <td colspan="3" class="px-4 py-2 align-middle">
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

                                            <td class="px-4 py-2 align-middle">
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
                                                    <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='user-faculties-permissions?user-id=<?= $data['user_id']; ?>'">View</button>
                                                </td>
                                                <td class="py-3 align-middle">
                                                    <div class="dropdown">
                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="user-faculties-permissions?user-id=<?= $data['user_id']; ?>">
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
                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data"/>
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleFacultyPermissionPagination('previousPage')">
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
                                                $action = $isActive ? '' : "onclick=\"handleFacultiesPermissionPagination('goToPage', '{$i}')\"";
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleFacultiesPermissionPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                        <span class="text-14 main-text fw-semibold">Next</span>
                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    </button>
                                </div>
                            </nav>
                        </div>
<?php
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
